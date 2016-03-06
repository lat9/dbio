<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart "Featured Products" import/export.
//
class DbIoFeaturedHandler extends DbIoHandler 
{
    public static function getHandlerInformation ()
    {
        include_once (DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/DbIoFeaturedHandler.php');
        return array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_FEATURED_DESCRIPTION,
        );
    }

    public function exportPrepareFields (array $fields) 
    {
        $fields = parent::exportPrepareFields ($fields);
        unset ($fields['products_id'], $fields['featured_id']);

        return $fields;
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
        $this->stats['report_name'] = 'Featured';
        $this->config = self::getHandlerInformation ();
        $this->config['extra_keys'] = array (
            'products_id' => array (
                'table' => TABLE_PRODUCTS,
                'match_field' => 'products_model',
                'match_field_type' => 'string',
                'key_field' => 'products_id',
                'key_field_type' => 'integer',
            ),
        );
        $this->config['key'] = array (
            'table' => TABLE_FEATURED,
            'match_field' => 'products_id',
            'extra_key_name' => 'products_id',
            'key_field' => 'featured_id', 
            'key_field_type' => 'integer',
        );
        $this->config['tables'] = array (
            TABLE_PRODUCTS => array (
                'alias' => 'p',
                'import_extra_keys_only' => true,
                'export_key_field_only' => true,
                'key_field' => 'products_model',
            ),
            TABLE_FEATURED => array ( 
                'alias' => 'f',
                'key_field' => 'featured_id',
                'io_field_overrides' => array (
                    'products_id' => false,
                    'featured_id' => 'no-header',
                ),
            ), 
        );
    }

    public function exportInitialize ($language = 'all') 
    {
        $initialized = parent::exportInitialize ($language);
        if ($initialized) {
            if ($this->where_clause != '') {
                $this->where_clause .= ' AND ';
            }
            $this->where_clause .= "p.products_id = f.products_id";
            $this->order_by_clause .= 'f.products_id ASC';
        }
        return $initialized;
    }

 
    // -----
    // Part of the dbIO import record-processing, check to see what type of operation is being performed.  If the
    // current key value is false, then either the products_model doesn't exist or there's no current record
    // for the product in the "featured" database table.  Determine which and continue if the model's found.
    //
    protected function importCheckKeyValue ($data_value, $key_value, $key_value_fields) 
    {
        global $db;
        $this->debugMessage ("Featured::importCheckKeyValue ($data_value, $key_value, key_value_fields:" . str_replace ("\n", '', var_export ($key_value_fields, true)));
        $products_id = false;
        if ($key_value === false) {
            $model_check_sql = "SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_model = :products_model: LIMIT 1";
            $model_check_sql = $db->bindVars ($model_check_sql, ':products_model:', $data_value, 'string');
            $model_check = $db->Execute ($model_check_sql);
            if (!$model_check->EOF) {
                $products_id = $model_check->fields['products_id'];
            }
        } elseif (isset ($key_value_fields['products_id'])) {
            $products_id = $key_value_fields['products_id'];
        }
        
        if ($products_id === false) {
            $this->debugMessage ("[*] Featured product not inserted at line number " . $this->stats['record_count'] . "; product's model ($data_value) does not exist.", self::DBIO_WARNING);
            $this->record_ok = false;
        } elseif ($this->import_is_insert) {
            $this->import_sql_data[TABLE_FEATURED]['products_id'] = array ( 'value' => $products_id, 'type' => 'integer' );
        }
        
        unset ($this->import_sql_data[TABLE_PRODUCTS]);
        
        return $this->record_ok;
    }  

    protected function importAddField ($table_name, $field_name, $field_value) {
        $this->debugMessage ("Featured::importAddField ($table_name, $field_name, $field_value)");
        switch ($table_name) {
            case TABLE_FEATURED:
                if ($this->import_is_insert) {
                    if ($field_name === 'featured_date_added') {
                        $field_value = 'now()';
                    } elseif ($field_name === 'featured_last_modified') {
                        $field_value = self::DBIO_NO_IMPORT;
                    }
                } else {
                    if ($field_name === 'featured_last_modified') {
                        $field_value = 'now()';
                    }
                }
                parent::importAddField ($table_name, $field_name, $field_value);
                break;
             default:
                break;
        }  //-END switch interrogating $table_name
    }  //-END function importAddField
   
    protected function importProcessField ($table_name, $field_name, $language_id, $field_value) 
    {
        if ($table_name == FILENAME_FEATURED) {
             parent::importProcessField ($table_name, $field_name, $language_id, $field_value);
        }
    }

}  //-END class DbIoFeaturedHandler
