<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2019, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
    exit ('Illegal access');
}

// -----
// This DbIo class handles the import and export of information in the Zen Cart 'products_attributes' and 'products_attributes_download' tables.
//
// Each table-record is exported as a single CSV record; all currently-defined fields in each of those tables are exported.  
//
// For the import, the CSV **must** contain the 'products_id', 'options_id', and 'options_values_id' fields, since those are
// used to determine whether the import inserts or updates a database record.  
//
// An optional 'v_dbio_command' column can be supplied, where the command 'REMOVE' causes a matching attribute to
// be removed from the database.
//
class DbIoProductsAttribsRawHandler extends DbIoHandler 
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('ProductsAttribsRaw'); 
        return array(
            'version' => '1.1.0',
            'handler_version' => '1.4.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSATTRIBSRAW_DESCRIPTION,
        );
    }
   
    // -----
    // There are some "subtleties" about this report that require some force-feeding for the SQL necessary to properly
    // create the associated report, so override the export SQL generation.
    //
    public function exportFinalizeInitialization()
    {
        // -----
        // Grab the fields from the various product/attribute tables.
        //
        $this->from_clause = TABLE_PRODUCTS . " AS p, " . TABLE_PRODUCTS_OPTIONS . " AS po, " . TABLE_PRODUCTS_OPTIONS_VALUES . " AS pov, " . TABLE_PRODUCTS_ATTRIBUTES . " AS pa LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " AS pad ON (pa.products_attributes_id = pad.products_attributes_id)";
        
        // -----
        // Insert the products_model, products_options_name and products_options_values_name fields, to make the output a little more readable.
        //
        $this->select_clause = str_replace(
            array( 
                'pa.products_id,', 
                'pa.options_id,',
                'pa.options_values_id,',
            ),
            array( 
                'pa.products_id, p.products_model,', 
                'pa.options_id, po.products_options_name,',
                'pa.options_values_id, pov.products_options_values_name,',
            ),
            $this->select_clause);
            
        // -----
        // Need also to update the headers so that the column-offsets match properly, in light of the above additions.
        //
        $inserted_columns = array(
            'v_products_id' => 'v_products_model',
            'v_options_id' => 'v_products_options_name',
            'v_options_values_id' => 'v_products_options_values_name',
        );
        foreach ($inserted_columns as $insert_after => $inserted_column) {
            $index = array_search($insert_after, $this->headers);
            if ($index !== false) {
                array_splice($this->headers, $index+1, 0, $inserted_column);
            }
        }
        $this->headers = array_values($this->headers);

        // -----
        // Tie all the tables together via the WHERE/AND clauses.
        //
        $this->where_clause = "
                pa.products_id = p.products_id
            AND pa.options_id = po.products_options_id
            AND po.language_id = " . (int)$_SESSION['languages_id'] . "
            AND pa.options_values_id = pov.products_options_values_id
            AND pov.language_id = " . (int)$_SESSION['languages_id'];
            
        $this->order_by_clause = "pa.products_id ASC, pa.options_id ASC, pa.options_values_id ASC";
        
        return parent::exportFinalizeInitialization();
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIO operations.
    //
    protected function setHandlerConfiguration() 
    {
        $this->stats['report_name'] = 'ProductsAttribsRaw';
        $this->config = self::getHandlerInformation();
        $this->config['supports_dbio_commands'] = true;
        $this->config['keys'] = array(
            TABLE_PRODUCTS_ATTRIBUTES => array(
                'alias' => 'pa',
                'capture_key_value' => true,
                'products_attributes_id' => array(
                    'type' => self::DBIO_KEY_IS_MASTER,
                ),
                'products_id' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'options_id' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'options_values_id' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
            ),
        );
        $this->config['tables'] = array(
            TABLE_PRODUCTS_ATTRIBUTES => array(
                'alias' => 'pa',
            ),
            TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD => array(
                'alias' => 'pad',
                'io_field_overrides' => array(
                    'products_attributes_id' => false,
                ),
            ),
        );
    }
    
    // -----
    // While the products_attributes_id field might be specified by the imported CSV, it's not a value that's importable.
    //
    protected function importHeaderFieldCheck($field_name)
    {
        return ($field_name == 'products_attributes_id') ? self::DBIO_NO_IMPORT : self::DBIO_IMPORT_OK;
    }
    
    // -----
    // This function, called at the very beginning of the processing of each import-file record, gives this
    // handler the opportunity to check some prerequisites, like a valid products_id, options_id and options_values_id
    // record (i.e. they're each defined).  If any are not valid, the record's not importable.
    //
    // Note: The checking needs to be performed **only** for an insert, since a products_attributes record is located
    // by a match on those three fields.
    //
    protected function importCheckKeyValue($data)
    {
        global $db;
        if ($this->import_is_insert) {
            $products_id = $this->importGetFieldValue('products_id', $data);
            $check = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = '$products_id' LIMIT 1");
            if ($check->EOF) {
                $this->record_status = false;
                $this->debugMessage("ProductsAttribsRawHandler::importCheckKeyValue: Unknown products_id ($products_id) at line #" . $this->stats['record_count'] . ', record not inserted.', self::DBIO_WARNING);
            }
            unset ($check);
            
            $options_id = $this->importGetFieldValue('options_id', $data);
            $check = $db->Execute("SELECT products_options_id FROM " . TABLE_PRODUCTS_OPTIONS . " WHERE products_options_id = '$options_id' LIMIT 1");
            if ($check->EOF) {
                $this->record_status = false;
                $this->debugMessage("ProductsAttribsRawHandler::importCheckKeyValue: Unknown options_id ($options_id) at line #" . $this->stats['record_count'] . ', record not inserted.', self::DBIO_WARNING);
            }
            unset($check);
            
            $options_values_id = $this->importGetFieldValue('options_values_id', $data);
            $check = $db->Execute("SELECT products_options_values_id FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = '$options_values_id' LIMIT 1");
            if ($check->EOF) {
                $this->record_status = false;
                $this->debugMessage("ProductsAttribsRawHandler::importCheckKeyValue: Unknown options_values_id ($options_values_id) at line #" . $this->stats['record_count'] . ', record not inserted.', self::DBIO_WARNING);
            }
        }
        return $this->record_status;
    }
    
    // -----
    // This function, called by the base DbIoHandler class when a non-blank v_dbio_command field is found in the
    // current import-record, gives this handler a chance to REMOVE a product option-combination from the database.
    //
    protected function importHandleDbIoCommand($command, $data)
    {
        $command = dbio_strtoupper($command);
        if ($command == self::DBIO_COMMAND_REMOVE) {
            $check = $GLOBALS['db']->Execute(
                "SELECT products_attributes_id
                   FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                  WHERE products_id = " . (int)$this->importGetFieldValue('products_id', $data) . "
                    AND options_id = " . (int)$this->importGetFieldValue('options_id', $data) . "
                    AND options_values_id = " . (int)$this->importGetFieldValue('options_values_id', $data) . "
                  LIMIT 1"
            );
            if ($check->EOF) {
                $this->debugMessage("Product option-combination not found at line #" . $this->stats['record_count'] . "; the 'REMOVE' operation was not performed.", self::DBIO_ERROR);
            } elseif ($this->operation != 'check') {
                $attributes_id = $check->fields['products_attributes_id'];
                $GLOBALS['db']->Execute(
                    "DELETE FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                      WHERE products_attributes_id = $attributes_id
                      LIMIT 1"
                );
                $GLOBALS['db']->Execute(
                    "DELETE FROM " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . "
                      WHERE products_attributes_id = $attributes_id
                      LIMIT 1"
                );
                zen_update_products_price_sorter($this->importGetFieldValue('products_id', $data));
            }
        } else {
            $this->debugMessage("Unrecognized command ($command) found at line #" . $this->stats['record_count'] . "; the operation was not performed.", self::DBIO_ERROR);
        }
        return false;
    }
    
    // -----
    // Fix-up any blank values in the attribute's download maxdays/maxcount fields (they're output as blank if no associated downloads-table
    // record is found) to prevent unwanted warnings from being issued.
    //
    protected function importProcessField($table_name, $field_name, $language_id, $field_value) 
    {
        if ($table_name == TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD) {
            if (($field_name == 'products_attributes_maxdays' || $field_name == 'products_attributes_maxcount') && empty($field_value)) {
                $field_value = '0';
            }
        } else {
            switch ($field_name) {
                case 'products_id':             //-Fall through ...
                case 'options_id':              //-Fall through ...
                case 'options_values_id':
                    $this->saved_data[$field_name] = $field_value;
                    break;
                default:
                    break;
            }
        }
        parent::importProcessField($table_name, $field_name, $language_id, $field_value);
    }
    
    // -----
    // This function, called to create an import-record's associated SQL, checks to see if the current attribute is to have a download-file associated with it.
    // That's tested by the presence of a value (unchecked) in the 'products_attributes_filename' field.  If the value is present, the associated record in the
    // 'products_attributes_download' table is created/modified; if that field's value is empty (a null-string), then any existing record will be removed.
    //
    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        $proceed_with_query = true;
        if ($table_name == TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD) {
            if (empty($this->import_sql_data[TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD]['products_attributes_filename']['value'])) {
                $proceed_with_query = false;
            } elseif ($this->operation != 'check') {
                if ($this->import_is_insert) {
                    $this->import_sql_data[TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD]['products_attributes_id']['value'] = $this->key_fields['products_attributes_id'];
                } else {
                    $this->where_clause = "pad.products_attributes_id = " . $this->key_fields['products_attributes_id'];
                }
            }
        }
        return ($proceed_with_query) ? parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause, $is_override, $is_insert) : false;
    }
    
    // -----
    // At the end of a record's import, make sure that there's an entry in the po2pov table that ties the just-processed 
    // option/value pair together and update the associated product's price-sorter.
    //
    protected function importRecordPostProcess($record_key_value)
    {
        global $db;
        if ($this->operation != 'check') {
            if ($this->import_is_insert) {
                $record_inserted = 'no';
                $check = $db->Execute(
                    "SELECT products_options_id FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " 
                      WHERE products_options_id = " . $this->saved_data['options_id'] . "
                        AND products_options_values_id = " . $this->saved_data['options_values_id'] . "
                      LIMIT 1");
                if ($check->EOF) {
                    $record_inserted = 'yes';
                    $sql_query = 
                        "INSERT INTO " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " (products_options_id, products_options_values_id) VALUES ( " . $this->saved_data['options_id'] . ', ' . $this->saved_data['options_values_id'] . ')';
                    $this->debugMessage("ProductsAttribsRaw::importRecordPostProcess\n$sql_query", self::DBIO_STATUS);  //- Forces the generated SQL to be logged!!
                    $db->Execute($sql_query);
                }
                $this->debugMessage("ProductsAttribsRaw::importRecordPostProcess, record updated ($record_inserted), options: " . print_r($this->saved_data, true));
            }
            zen_update_products_price_sorter($this->saved_data['products_id']);
        }
    }

}  //-END class DbIoProductsAttribsRawHandler
