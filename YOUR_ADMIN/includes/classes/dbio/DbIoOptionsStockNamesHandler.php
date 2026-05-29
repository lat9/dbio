<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2022, Vinos de Frutas Tropicales.
//
// Last Updated:  POSM v4.4.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the import and export of information in the Zen Cart 'products_options_stock_names' table.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.
//
// For the import, the CSV **must** contain both the pos_name_id and language_id fields, since those are
// used as the table's key-pair.  An entry is updated if a database record is found that matches both fields; otherwise,
// the record is inserted using the specified language_id and a pos_name_id that is calculated as the
// current table's maximum value (+1).
//
// Usage Notes:
//
// 1) When importing new records for a multi-language store, the import should be run once per language value.
//    Otherwise, the products_options_id will get "out-of-sync" between the multiple languages.
//
class DbIoOptionsStockNamesHandler extends DbIoHandler
{
    public static function getHandlerInformation()
    {
        global $sniffer;

        if (!defined('DBIO_CURRENT_VERSION') || DBIO_CURRENT_VERSION < '1.0.1' || !$sniffer->table_exists(TABLE_PRODUCTS_OPTIONS_STOCK_NAMES)) {
            trigger_error("Incompatible DbIo version (" . DBIO_CURRENT_VERSION . ") detected.  Either update the DbIo plugin to v1.0.1 or later or remove this file.", E_USER_WARNING);
            return false;
        }
        DbIoHandler::loadHandlerMessageFile('OptionsStockNames');
        return [
            'version' => '1.0.1',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_OPTIONSSTOCKNAMES_DESCRIPTION,
        ];
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
        $this->stats['report_name'] = 'OptionsStockNames';
        $this->config = self::getHandlerInformation();
        $this->config['keys'] = [
            TABLE_PRODUCTS_OPTIONS_STOCK_NAMES => [
                'alias' => 'posn',
                'pos_name_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
                'language_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS_OPTIONS_STOCK_NAMES => [
                'alias' => 'posn',
                'language_override' => self::DBIO_OVERRIDE_ALL,
            ],
        ];
    }
}  //-END class DbIoOptionsStockNamesHandler
