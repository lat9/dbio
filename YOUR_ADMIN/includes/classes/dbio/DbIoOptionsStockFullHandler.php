<?php

declare(strict_types=1);
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2014-2026 Vinos de Frutas Tropicales
//
// Last Updated:  DbIo v2.2.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the export and import of information in the Zen Cart 'products_options_stock' table, including the creation of new entries.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.
//
// For the import, the CSV **must** contain both the products_id and v_products_options_combination fields, since those are
// used as an entry's key-pair.
//
class DbIoOptionsStockFullHandler extends DbIoOptionsStockBase
{
    // -----
    // Handler-specific variable declarations.
    //
    protected array $saved_data;

    public static function getHandlerInformation(): array|false
    {
        global $sniffer;
        if (zen_config('DBIO_CURRENT_VERSION', '0.0.0') < '2.2.0' || !defined('TABLE_PRODUCTS_OPTIONS_STOCK') || !$sniffer->table_exists(TABLE_PRODUCTS_OPTIONS_STOCK)) {
            trigger_error("Incompatible DbIo version (" . zen_config('DBIO_CURRENT_VERSION', '0.0.0') . ") detected.  Either update the DbIo plugin to v2.2.0 or later or remove this file.", E_USER_WARNING);
            return false;
        }
        DbIoHandler::loadHandlerMessageFile('OptionsStockFull');
        return [
            'version' => '2.2.0',
            'handler_version' => '2.2.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_OPTIONSSTOCKFULL_DESCRIPTION,
        ];
    }

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration(): void
    {
        $this->stats['report_name'] = 'OptionsStockFull';
        $this->config = self::getHandlerInformation();
        $this->config['handler_does_import'] = true;
        $this->config['supports_dbio_commands'] = true;
        $this->config['keys'] = [
            TABLE_PRODUCTS_OPTIONS_STOCK => [
                'alias' => 'pos',
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS_OPTIONS_STOCK => [
                'alias' => 'pos',
            ],
        ];
    }

    protected function importHandleDbIoCommand(string $command, array $data): bool
    {
        global $db;

        if (strtoupper($command) !== self::DBIO_COMMAND_REMOVE) {
            $this->debugMessage("Unrecognized command ($command) found at line #" . $this->stats['record_count'] . "; the operation was not performed.", self::DBIO_ERROR);
            return false;
        }

        if ($this->posmBaseDetermineProductsId($data)) {
            $option_message = '';
            $options_array = $this->posmBaseProcessCurrentOptionCombination($this->products_id, $data, $option_message, true);
            if ($options_array === false) {
                return false;
            }

            // -----
            // The option-combinations are valid; next check to see if this is going to be an insert or update operation by seeing
            // if there's currently a record tieing those option-combinations to the current product.
            //
            $hash_value = generate_pos_option_hash($this->products_id, $options_array);
            $pos_info = $db->Execute(
                "SELECT pos_id FROM " . TABLE_PRODUCTS_OPTIONS_STOCK . "
                  WHERE products_id = " . $this->products_id . "
                    AND pos_hash = '$hash_value' LIMIT 1", false, false, 0, true
            );
            if ($pos_info->EOF) {
                $this->debugMessage("Record not removed at line #" . $this->stats['record_count'] . "; invalid option-combinations for the product.", self::DBIO_ERROR);
            } else {
                $this->debugMessage("Removing options' stock records for products ID " . $this->products_id . ", option-combination " . $this->importGetFieldValue('products_options_combination', $data), self::DBIO_ACTIVITY | self::DBIO_STATUS);
                if ($this->operation !== 'check') {
                    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_OPTIONS_STOCK . " WHERE pos_id = " . $pos_info->fields['pos_id']);
                    $db->Execute("DELETE FROM " . TABLE_PRODUCTS_OPTIONS_STOCK_ATTRIBUTES . " WHERE pos_id = " . $pos_info->fields['pos_id']);
                }
            }
        }
        return false;
    }

    // -----
    // This report's import handling is a little different.  The base class' handling is used to determine whether an options' stock
    // record exists for the current CSV record's specified product's ID.
    //
    protected function importCheckKeyValue(array $data): bool
    {
        global $db;

        // -----
        // The base DbIoHandler processing, based on this handler's configuration, has determined whether there's at least one existing
        // 'products_options_stock' record associated with the current product's ID. Call the POSM base-class handler to determine
        // the products_id that's to be associated with the current record.
        //
        if ($this->posmBaseDetermineProductsId($data) === true) {
            // -----
            // In either case, the option-combinations must be associated with a valid name-pair, so let's check that first.  The called
            // function will set the option_message variable in the case of an error.
            //
            $option_message = '';
            $option_error = false;
            $products_id = $this->products_id;
            $options_array = $this->posmBaseProcessCurrentOptionCombination($products_id, $data, $option_message, false);
            if ($options_array === false) {
                $option_error = true;
            } else {
                // -----
                // Create the variables that are going to be needed whether we're inserting or updating a record.
                //
                $this->debugMessage("importCheckKeyValue, generating hash for $products_id using options" . print_r($options_array, true));
                $hash_value = generate_pos_option_hash($products_id, $options_array);

                // -----
                // Save the record's "key" values for use on an insert, if needed.
                //
                $this->saved_data = [
                    'products_id' => $products_id,
                    'pos_hash' => $hash_value,
                    'options_array' => $options_array,
                ];

                // -----
                // The option-combinations are valid; next check to see if this is going to be an insert or update operation by seeing
                // if there's currently a record tieing those option-combinations to the current product.
                //
                $pos_info = $db->Execute(
                    "SELECT pos_id FROM " . TABLE_PRODUCTS_OPTIONS_STOCK . "
                      WHERE products_id = $products_id
                        AND pos_hash = '$hash_value' LIMIT 1", false, false, 0, true
                );
                if ($pos_info->EOF) {
                    $this->import_is_insert = true;
                    $this->import_action = 'insert';
                    $this->where_clause = '';
                } else {
                    $this->import_is_insert = false;
                    $this->import_action = 'update';
                    $this->where_clause = 'pos.pos_id = ' . $pos_info->fields['pos_id'];
                }

                // -----
                // Getting a little tricky here.  For a record that's an update, we're going to let the base class handle the
                // processing but for an insert action, we're going to do it in this handler.
                //
                $this->handler_does_import = $this->import_is_insert;
            }

            if ($option_error === true) {
                $this->record_status = false;
                $this->debugMessage("[*] Record not imported at line number " . $this->stats['record_count'] . "; $option_message.", self::DBIO_WARNING);
            }
        }
        return $this->record_status;
    }

    // -----
    // This function, called on a record-insert action **ONLY**, creates the products_options_stock and associated products_options_stock_attributes
    // table records for an inserted value.
    //
    // Note: Unlike other base-class functions, this function is a total override; calling the parent function will result in a record-import error.
    //
    protected function importFinishProcessing(): void
    {
        global $db;

        // -----
        // This handler specifies a single database table as its configuration target.  As such, we'll just extract local copies of the current
        // record's information directly from the generated import_sql_data array.
        //
        $table_name = TABLE_PRODUCTS_OPTIONS_STOCK;

        $table_fields = $this->import_sql_data[$table_name];
        $table_fields['pos_hash'] = [
            'value' => $this->saved_data['pos_hash'],
            'type' => 'string'
        ];

        $sql_query = $this->importBuildSqlQuery($table_name, 'pos', $table_fields);
        if ($this->operation !== 'check') {
            $db->Execute($sql_query);
            $pos_id = $db->insert_ID();
            $products_id = $this->saved_data['products_id'];
            foreach ($this->saved_data['options_array'] as $options_id => $options_values_id) {
                $db->Execute(
                    "INSERT INTO " . TABLE_PRODUCTS_OPTIONS_STOCK_ATTRIBUTES . "
                        (pos_id, products_id, options_id, options_values_id)
                     VALUES
                        ($pos_id, $products_id, $options_id, $options_values_id)"
                );
            }
        }

        // -----
        // Use the method defined in the DbIoOptionsStockBase class to now update
        // the active product's 'base' quantity to be the sum of all POSM-managed
        // variants' quantities.
        //
        $this->posmBaseUpdateBaseProductsQuantity();
    }

}  //-END class DbIoOptionsStockFullHandler
