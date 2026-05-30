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
// This DbIo class handles the export and import (existing records only) of information in the Zen Cart 'products_options_stock' table.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.
//
// For the import, the CSV **must** contain both the products_id and v_products_options_combination fields, since those are
// used as an entry's key-pair.
//
class DbIoOptionsStockUpdateHandler extends DbIoOptionsStockBase
{
    public static function getHandlerInformation(): array|false
    {
        global $sniffer;
        if (zen_config('DBIO_CURRENT_VERSION', '0.0.0') < '2.2.0' || !defined('TABLE_PRODUCTS_OPTIONS_STOCK') || !$sniffer->table_exists(TABLE_PRODUCTS_OPTIONS_STOCK)) {
            trigger_error("Incompatible DbIo version (" . zen_config('DBIO_CURRENT_VERSION', '0.0.0') . ") detected.  Either update the DbIo plugin to v2.2.0 or later or remove this file.", E_USER_WARNING);
            return false;
        }
        DbIoHandler::loadHandlerMessageFile('OptionsStockUpdate');
        return [
            'version' => '2.2.0',
            'handler_version' => '2.2.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_OPTIONSSTOCKUPDATE_DESCRIPTION,
        ];
    }

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration(): void
    {
        $this->stats['report_name'] = 'OptionsStockUpdate';
        $this->config = self::getHandlerInformation();
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

    // -----
    // This report's import handling is a little different.  The base class' handling is used to determine whether the record's associated
    // products_id exists as a POSM-managed entity.
    //
    protected function importCheckKeyValue($data): bool
    {
        global $db;

        // -----
        // Determine the current record's products_id value; it's the products_id (if valid) present in the record or the ID associated
        // with the products_model present in the record ... or it's not updateable!
        //
        if ($this->posmBaseDetermineProductsId($data) === true) {
            // -----
            // Otherwise, determine if the current record's options_combination value is associated with a defined set of option/option-value
            // pairs, continuing only if so.
            //
            $option_message = '';
            $option_error = false;
            $products_id = $this->products_id;
            $options_array = $this->posmBaseProcessCurrentOptionCombination($products_id, $data, $option_message);
            if ($options_array === false) {
                $option_error = true;
            } else {
                // -----
                // Now, see that the current product/option-combination is being POSM managed, setting an import error if not.
                //
                $this->debugMessage("importCheckKeyValue, generating hash for $products_id using options" . print_r($options_array, true));
                $hash_value = generate_pos_option_hash($products_id, $options_array);
                $pos_info = $db->Execute(
                    "SELECT pos_id FROM " . TABLE_PRODUCTS_OPTIONS_STOCK . "
                      WHERE products_id = $products_id
                        AND pos_hash = '$hash_value' LIMIT 1", false, false, 0, true
                );
                if ($pos_info->EOF) {
                    $option_error = true;
                    $option_message = 'no record found to update';
                } else {
                    $this->where_clause = 'pos.pos_id = ' . $pos_info->fields['pos_id'];
                    $this->import_is_insert = false;
                    $this->import_action = 'update';
                }
            }
            if ($option_error === true) {
                $this->record_status = false;
                $this->debugMessage("[*] Record not imported at line number " . $this->stats['record_count'] . "; $option_message.", self::DBIO_WARNING);
            }
        }
        return $this->record_status;
    }

}  //-END class DbIoOptionsStockUpdateHandler
