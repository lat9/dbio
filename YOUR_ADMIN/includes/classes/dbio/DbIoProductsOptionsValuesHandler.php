<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2019, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the import and export of information in the Zen Cart 'products_options_values' table.
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
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('ProductsOptionsValues'); 
        return array(
            'version' => '1.0.1',
            'handler_version' => '1.0.0',
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
    protected function setHandlerConfiguration() 
    {
        $this->stats['report_name'] = 'ProductsOptionsValues';
        $this->config = self::getHandlerInformation();
        $this->config['keys'] = array (
            TABLE_PRODUCTS_OPTIONS_VALUES => array(
                'alias' => 'pov',
                'products_options_values_id' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'language_id' => array (
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
            ),
        );
        $this->config['tables'] = array(
            TABLE_PRODUCTS_OPTIONS_VALUES => array(
                'alias' => 'pov',
                'language_override' => self::DBIO_OVERRIDE_ALL,
            ),
        );
    }
    
    // -----
    // Called by the DbIoHandler class, just prior to each record's import.  Gives us a
    // chance to:
    //
    // 1) See if the products_options_values_id is 0.  If so, force the import to be an insert.
    // 2) See if the associated language_id is, in fact, valid for the store.  If not, the
    //    record's import will be denied.
    //
    protected function importCheckKeyValue($data)
    {
        if ($this->importGetFieldValue('products_options_values_id', $data) == 0) {
            $this->import_is_insert = true;
        }
        $language_id = $this->importGetFieldValue('language_id', $data);
        if (!in_array($language_id, array_values($this->languages))) {
            $this->record_status = false;
            $this->debugMessage("[*] products_options_values.language_id, line #" . $this->stats['record_count'] . ": Value ($language_id) is not valid language id.", self::DBIO_ERROR);
        }
        return parent::importCheckKeyValue($data);
    }
    
    // -----
    // Overriding the base import processing, to ensure that the v_products_options_id value is updated to
    // the next-available value if the import specifies the value as 0.
    //
    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        $record_is_insert = ($is_override) ? $is_insert : $this->import_is_insert;
        if ($record_is_insert && $table_fields['products_options_values_id']['value'] == 0) {
            $next_id = $GLOBALS['db']->Execute(
                "SELECT MAX(products_options_values_id) + 1 AS next_id
                   FROM " . TABLE_PRODUCTS_OPTIONS_VALUES
            );
            $table_fields['products_options_values_id']['value'] = $next_id->fields['next_id'];
        }
        return parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause, $is_override, $is_insert);
    }

}  //-END class DbIoProductsOptionsValuesHandler
