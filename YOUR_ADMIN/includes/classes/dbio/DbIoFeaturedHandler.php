<?php
// -----
// Part of the Database I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.1.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit ('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart "Featured Products" import/export.
//
class DbIoFeaturedHandler extends DbIoHandler
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Featured'); 
        return [
            'version' => '2.1.0',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_FEATURED_DESCRIPTION,
        ];
    }

    public function exportInitialize($language = 'all')
    {
        $initialized = parent::exportInitialize($language);
        if ($initialized === true) {
            $this->order_by_clause .= 'f.products_id ASC';
        }
        return $initialized;
    }

    // -----
    // For each 'featured' row's export, append the product's name, model-number and 'base price' to the output,
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
        unset($fields['featured_id']);

        $products_id = $fields['products_id'];
        
        $fields['products_price'] = zen_get_products_base_price($products_id);
        $fields['products_model'] = zen_get_products_model($products_id);
        $fields['products_name'] = zen_get_products_name($products_id);
        $fields['v_dbio_command'] = '';
        return $fields;
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'Featured';
        $this->config = self::getHandlerInformation();
        $this->config['supports_dbio_commands'] = true;
        $this->config['keys'] = [
            TABLE_FEATURED => [
                'alias' => 'f',
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_FEATURED => [
                'alias' => 'f',
                'io_field_overrides' => [
                    'featured_id' => 'no-header',
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
        if ($field_name !== 'featured_id' && in_array($field_name, array_keys($this->tables['featured']['fields']))) {
            return self::DBIO_IMPORT_OK;
        }
        return self::DBIO_NO_IMPORT;
    }

    protected function importProcessField($table_name, $field_name, $language_id, $field_value)
    {
        global $db;

        $this->debugMessage("Featured::importProcessField($table_name, $field_name, $language_id, $field_value)");

        // -----
        // The 'products_id' supplied must be associated with a defined product.
        //
        if ($field_name === 'products_id') {
            $check = $db->Execute(
                "SELECT products_id
                   FROM " . TABLE_PRODUCTS . "
                  WHERE products_id = " . (int)$field_value . "
                  LIMIT 1"
            );
            if ($check->EOF) {
                $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is a not valid 'products_id'.", self::DBIO_ERROR);
                $this->record_status = false;
                return;
            }
        }

        parent::importProcessField($table_name, $field_name, $language_id, $field_value);
    }

    protected function importAddField($table_name, $field_name, $field_value)
    {
        $this->debugMessage("Featured::importAddField($table_name, $field_name, $field_value)");
        switch ($table_name) {
            case TABLE_FEATURED:
                if ($this->import_is_insert === true) {
                    if ($field_name === 'featured_date_added') {
                        $field_value = 'now()';
                    } elseif ($field_name === 'featured_last_modified') {
                        $field_value = self::DBIO_NO_IMPORT;
                    }
                } elseif ($field_name === 'featured_last_modified') {
                    $field_value = 'now()';
                }
                parent::importAddField($table_name, $field_name, $field_value);
                break;

            default:
                break;
        }
    }

    // -----
    // This function, called by the base DbIoHandler class when a non-blank v_dbio_command field is found in the
    // current import-record, gives this handler a chance to REMOVE a featured product's record from the database.
    //
    protected function importHandleDbIoCommand($command, $data)
    {
        global $db;

        $command = dbio_strtoupper($command);
        if ($command !== self::DBIO_COMMAND_REMOVE) {
            $this->debugMessage("Unrecognized command ($command) found at line #" . $this->stats['record_count'] . "; the operation was not performed.", self::DBIO_ERROR);
        } else {
            $products_id = $this->importGetFieldValue('products_id', $data);
            if ($products_id === false) {
                $this->debugMessage("Missing 'v_products_id' column at line #" . $this->stats['record_count'] . "; featured product not removed.", self::DBIO_ERROR);
            } else {
                $this->debugMessage("Removing featured-product entry for product_id #" . $products_id, self::DBIO_STATUS);
                if ($this->operation !== 'check') {
                    $db->Execute(
                        "DELETE FROM " . TABLE_FEATURED . "
                          WHERE products_id = " . (int)$products_id . "
                          LIMIT 1"
                    );
                }
            }
        }
        return false;
    }
}
