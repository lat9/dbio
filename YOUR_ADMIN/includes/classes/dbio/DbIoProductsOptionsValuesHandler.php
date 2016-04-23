<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the import and export of information in the Zen Cart 'products_options_values' table.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.  
//
// For the import, the CSV **must** contain both the products_options_values_id and language_id fields, since those are
// used as the table's key-pair.  An entry is updated if a database record is found that matches both fields; otherwise,
// the record is inserted using the specified language_id and a products_options_values_id that is calculated as the 
// current table's maximum value (+1).
//
// Usage Notes:
//
// 1) When importing new records for a multi-language store, the import should be run once per language value.
//    Otherwise, the products_options_id will get "out-of-sync" between the multiple languages.
//
class DbIoProductsOptionsValuesHandler extends DbIoHandler 
{
    public static function getHandlerInformation ()
    {
        DbIoHandler::loadHandlerMessageFile ('ProductsOptionsValues'); 
        return array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSOPTIONSVALUES_DESCRIPTION,
        );
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->stats['report_name'] = 'ProductsOptionsValues';
        $this->config = self::getHandlerInformation ();
        $this->config['keys'] = array (
            TABLE_PRODUCTS_OPTIONS => array (
                'alias' => 'pov',
                'products_options_values_id' => array (
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'language_id' => array (
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
            ),
        );
        $this->config['tables'] = array (
            TABLE_PRODUCTS_OPTIONS_VALUES => array (
                'alias' => 'pov',
                'language_override' => self::DBIO_OVERRIDE_ALL,
            ),
        );
    }

}  //-END class DbIoProductsOptionsValuesHandler
