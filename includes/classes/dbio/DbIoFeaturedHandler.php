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
    public function __construct ($log_file_suffix)
    {
        include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/DbIoFeaturedHandler.php');
        parent::__construct ($log_file_suffix);
    }
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->config = array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'extra_keys' => array (
                'products_id' => array (
                    'table' => TABLE_PRODUCTS,
                    'match_field' => 'products_model',
                    'match_field_type' => 'string',
                    'key_field' => 'products_id',
                ),
            ),
            'key' => array ( 
                'table' => TABLE_FEATURED,
                'match_field' => 'products_id',
                'extra_key_name' => 'products_id',
                'key_field' => 'featured_id', 
                'key_field_type' => 'integer',
            ),
            'include_header' => true,
            'tables' => array (
                TABLE_PRODUCTS => array (
                    'short_name' => 'p',
                    'import_extra_keys_only' => true,
                    'export_key_field_only' => true,
                    'key_field' => 'products_model',
                ),
                TABLE_FEATURED => array ( 
                    'short_name' => 'f',
                    'key_field' => 'featured_id',
                    'io_field_overrides' => array (
                        'products_id' => false,
                        'featured_id' => 'no-header',
                    ),
                ), 
            ),
            'description' => DBIO_FEATURED_DESCRIPTION,
        );
    }

    public function exportInitialize ($language = 'all') 
    {
        $initialized = parent::exportInitialize ($language);
        if ($initialized) {
            if ($this->export['where'] != '') {
                $this->export['where'] .= ' AND ';
            }
            $this->export['where'] .= "p.products_id = f.products_id";
            $this->export['order_by'] .= 'f.products_id ASC';
        }
        return $initialized;
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
    // This function, called for each-and-every data-element being imported, can return one of three values:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importFieldCheck ($field_name) 
    {
        switch ($field_name) {
            case 'products_model':
            case 'status':
            case 'featured_date_added':
            case 'expires_date':
            case 'date_status_change':
            case 'featured_date_available':
                $field_status = self::DBIO_IMPORT_OK;
                break;
            default:
                $field_status = self::DBIO_NO_IMPORT;
                break;
        }
        return $field_status;
    }
 
    // -----
    // Part of the dbIO import record-processing, check to see what type of operation is being performed.  If the
    // current key value **is** false, then either the products_model doesn't exist or there's no current record
    // for the product in the "featured" database table.
    //
    protected function importCheckKeyValue ($data_value, $key_value, $key_value_fields) 
    {
        global $db;
        $this->debugMessage ("importCheckKeyValue ($data_value, $key_value, " . str_replace ("\n", '', var_export ($key_value_fields, true)));
        if ($key_value === false) {
            $model_check_sql = "SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_model = :products_model: LIMIT 1";
            $model_check_sql = $db->bindVars ($model_check_sql, ':products_model:', $data_value, 'string');
            $model_check = $db->Execute ($model_check_sql);
            if ($model_check->EOF) {
                $this->debugMessage ("[*] Featured product not inserted at line number " . $this->stats['record_count'] . "; product's model ($data_value) does not exist.", self::DBIO_WARNING);
                $this->record_ok = false;
                $this->stats['inserts']--;
            } else {
                parent::importAddField (TABLE_FEATURED, 'products_id', $model_check->fields['products_id'], 'integer');
            }
        }
        return parent::importCheckKeyValue ($data_value, $key_value, $key_value_fields);
    }  

    protected function importAddField ($table_name, $field_name, $field_value, $field_type) {
        $this->debugMessage ("importAddField ($table_name, $field_name, $field_value, $field_type)");
        global $db;
        switch ($table_name) {
            case TABLE_FEATURED:
                $import_this_field = true;
                if ($this->import_is_insert) {
                    if ($field_name === 'featured_date_added') {
                        $field_value = 'now()';
                        $field_type = 'noquotestring';
                    } elseif ($field_name === 'featured_last_modified') {
                        $import_this_field = false;
                    }
                } else {
                    if ($field_name === 'featured_last_modified') {
                        $field_value = 'now()';
                        $field_type = 'noquotestring';
                    } elseif ($field_name === 'featured_date_added') {
                        $import_this_field = false;
                    }
                }
                if ($import_this_field) {
                    parent::importAddField ($table_name, $field_name, $field_value, $field_type);
                }
                break;
             default:
                parent::importAddField ($table_name, $field_name, $field_value, $field_type);
                break;
        }  //-END switch interrogating $table_name
    }  //-END function importAddField
   
    protected function importProcessField ($table_name, $field_name, $language_id, $field_value, $field_type = false) 
    {
        if ($table_name == FILENAME_FEATURED) {
             parent::importProcessField ($table_name, $field_name, $language_id, $field_value, $field_type);
        }
    }

}  //-END class DbIoFeaturedHandler
