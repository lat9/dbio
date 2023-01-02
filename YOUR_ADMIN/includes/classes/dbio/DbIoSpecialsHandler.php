<?php
// -----
// Part of the Database I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2020-2023, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.0.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart "Specials Products" import/export.
//
class DbIoSpecialsHandler extends DbIoHandler
{
    protected
        $enable_specials_gv,
        $products_id;

    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Specials');
        return [
            'version' => '2.0.0',
            'handler_version' => '1.6.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_SPECIALS_DESCRIPTION,
        ];
    }

    public function exportInitialize($language = 'all')
    {
        $initialized = parent::exportInitialize($language);
        if ($initialized === true) {
            $this->order_by_clause .= 's.products_id ASC';
        }
        return $initialized;
    }

    // -----
    // For each 'specials' row's export, append the product's name, model-number and 'base price' to the output,
    // adding those fields for reference use.
    //
    // Note: Since this handler supports DbIo commands, the base class' handling has appended an empty
    // column as the last field to hold a potential command (this handler supports REMOVE).  Need to remove that
    // column's data from the fields prior to inserting the 'helper' columns and then add it back.
    //
    public function exportPrepareFields(array $fields)
    {
        $fields = parent::exportPrepareFields($fields);
        array_pop($fields);
        unset($fields['specials_id']);

        $products_id = $fields['products_id'];
        
        $fields['products_price'] = zen_get_products_base_price($products_id);
        $fields['products_model'] = zen_get_products_model($products_id);
        $fields['products_name'] = zen_get_products_name($products_id);
        $fields['v_dbio_command'] = '';
        return $fields;
    }

    // -----
    // When an import is started, set a processing flag to indicate whether or not the store
    // has enabled gift-certificates to be placed on special (saves processing time for each
    // imported-special's loop).
    //
    // For gift-certificates to be placed on special, the 'Gift Voucher' order-total must be installed
    // and 'permission granted' to allow those products to have special prices.
    //
    public function importInitialize($language = 'all', $operation = 'check') 
    {
        $this->enable_specials_gv = (defined('MODULE_ORDER_TOTAL_GV_SPECIAL') && MODULE_ORDER_TOTAL_GV_SPECIAL != 'false');
        return parent::importInitialize($language, $operation);
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'Specials';
        $this->config = self::getHandlerInformation();
        $this->config['supports_dbio_commands'] = true;
        $this->config['keys'] = [
            TABLE_SPECIALS => [
                'alias' => 's',
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_SPECIALS => [
                'alias' => 's',
                'io_field_overrides' => [
                    'specials_id' => 'no-header',
                ],
            ], 
        ];
        $this->config['additional_headers'] = [
            'v_products_price' => self::DBIO_FLAG_NONE,
            'v_products_model' => self::DBIO_FLAG_NONE,
            'v_products_name' => self::DBIO_FLAG_NONE,
        ];
    }

    // -----
    // This function, called for header-element being imported, can return one of three values:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importHeaderFieldCheck($field_name)
    {
        switch ($field_name) {
            case 'products_id':
            case 'specials_new_products_price':
            case 'specials_date_added':
            case 'specials_last_modified':
            case 'expires_date':
            case 'date_status_change':
            case 'status':
            case 'specials_date_available':
                $field_status = self::DBIO_IMPORT_OK;
                break;

            default:
                $field_status = self::DBIO_NO_IMPORT;
                break;
        }
        return $field_status;
    }

    // -----
    // This function, called at the start of each record's import, gives the handler the opportunity to validate the
    // fields for the import.  The base DbIoHandler processing (based on this handler's configuration) has attempted to
    // locate a UNIQUE record based on a products_id match.
    //
    // For this report's import, the 'specials_new_products_price' field is required and the import is disallowed if
    // that column isn't present.
    //
    // This method also saves (for use by the 'importProcessField' method) the current record's products_id value.
    //
    protected function importCheckKeyValue($data)
    {
        $specials_price = $this->importGetFieldValue('specials_new_products_price', $data);
        if ($specials_price === false) {
            $this->record_status = false;
            $this->debugMessage("Record at line#" . $this->stats['record_count'] . " not imported; 'v_specials_new_products_price' column must be present.", self::DBIO_WARNING);
        }
        $this->products_id = $this->importGetFieldValue('products_id', $data);
        return $this->record_status;
    }

    protected function importProcessField($table_name, $field_name, $language_id, $field_value)
    {
        global $db;

        $this->debugMessage("Specials::importProcessField($table_name, $field_name, $language_id, $field_value)");
        $valid_fieldname = true;
        $field_error = false;
        switch ($field_name) {
            // -----
            // The 'products_id' supplied must be associated with a defined product.
            //
            case 'products_id':
                $check = $db->Execute(
                    "SELECT products_id, products_model
                       FROM " . TABLE_PRODUCTS . "
                      WHERE products_id = " . (int)$field_value . "
                      LIMIT 1"
                );
                if ($check->EOF) {
                    $field_error = true;
                    $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is a not valid 'products_id'.", self::DBIO_ERROR);
                } elseif ($this->enable_specials_gv === false && strpos($check->fields['products_model'], 'GIFT') === 0) {
                    $field_error = true;
                    $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": Gift certificates (pID#$field_value) cannot be placed on special.", self::DBIO_ERROR);
                }
                break;

            // -----
            // If the 'specials_new_products_price' is specified as a percent-off (i.e. the last character
            // of the value is a '%'), check that the value preceding that character is numeric with 1-3 leading
            // digits, with an optional decimal separator ('.') followed by up to 4 decimal digits.  If that check 
            // passes, ensure that the value is not more than 100.
            //
            // If the %-off value is found to be valid, modify the to-be-updated product's price to be
            // a fraction of its current base price.
            //
            case 'specials_new_products_price':
                if (substr($field_value, -1) !== '%') {
                    break;
                }
                $percent_off = substr($field_value, 0, -1);
                if (!preg_match('/^\d{1,3}(?:\.\d{0,4})?$/', $percent_off) || $percent_off > 100) {
                    $field_error = true;
                    $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is a not valid percentage.", self::DBIO_ERROR);
                    break;
                }
                $products_base_price = zen_get_products_base_price($this->products_id);
                $field_value = $products_base_price * (100 - $percent_off) / 100;
                break;

            case 'specials_date_added':
                if ($this->import_is_insert) {
                    $field_value = date('Y-m-d H:i:s');
                }
                break;

            case 'specials_last_modified':
                if ($this->import_is_insert) {
                    $field_value = null;
                } else {
                    $field_value = date('Y-m-d H:i:s');
                }
                break;

            case 'expires_date':
            case 'date_status_change':
            case 'specials_date_available':
                break;

            case 'status':
                if ($field_value !== false && $field_value !== '0' && $field_value !== '1') {
                    $field_error = true;
                    $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": The value ($field_value), if supplied, must be either '0' or '1'.", self::DBIO_ERROR);
                }
                break;

             default:
                $valid_fieldname = false;
                break;
        }

        if ($valid_fieldname === true) {
            if ($field_error === true) {
                $this->record_status = false;
            } else {
                parent::importProcessField($table_name, $field_name, $language_id, $field_value);
            }
        }
    }

    // -----
    // This function handles any overall record post-processing required for the Specials import, specifically
    // making sure that the products' price sorter is run for the just inserted/updated product.
    //
    protected function importRecordPostProcess($products_id)
    {
        if ($this->operation !== 'check') {
            zen_update_products_price_sorter($this->products_id);
        }
    }

    // -----
    // This function, called by the base DbIoHandler class when a non-blank v_dbio_command field is found in the
    // current import-record, gives this handler a chance to REMOVE a special's information from the database.
    //
    protected function importHandleDbIoCommand($command, $data)
    {
        global $db;

        $command = dbio_strtoupper($command);
        if ($command === self::DBIO_COMMAND_REMOVE) {
            $this->debugMessage("Removing special for product_id (" . $this->products_id . ")", self::DBIO_STATUS);
            if ($this->operation !== 'check') {
                $db->Execute(
                    "DELETE FROM " . TABLE_SPECIALS . "
                      WHERE products_id = " . $this->products_id . "
                      LIMIT 1"
                );
                zen_update_products_price_sorter($this->products_id);
            }
        } else {
            $this->debugMessage("Unrecognized command ($command) found at line #" . $this->stats['record_count'] . "; the operation was not performed.", self::DBIO_ERROR);
        }
        return false;
    }
}
