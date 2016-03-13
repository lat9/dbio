<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart product import/export.
//
class DbIoProductsHandler extends DbIoHandler 
{
    public static function getHandlerInformation ()
    {
        global $db;
        DbIoHandler::loadHandlerMessageFile ('Products'); 
        
        $manufacturers_options = array ();
        $manufacturers_info = $db->Execute ("SELECT manufacturers_id as `id`, manufacturers_name as `text` FROM " . TABLE_MANUFACTURERS . " ORDER BY manufacturers_name ASC");
        while (!$manufacturers_info->EOF) {
            $manufacturers_options[] = $manufacturers_info->fields;
            $manufacturers_info->MoveNext ();
        }
        unset ($manufacturers_info);
        
        $status_options = array (
            array ( 'id' => 'all', 'text' => DBIO_PRODUCTS_TEXT_STATUS_ALL ),
            array ( 'id' => '1', 'text' => DBIO_PRODUCTS_TEXT_STATUS_ENABLED ),
            array ( 'id' => '0', 'text' => DBIO_PRODUCTS_TEXT_STATUS_DISABLED ),
        );
        
        $categories_options = zen_get_category_tree ();
        unset ($categories_options[0]);
        
        $my_config = array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTS_DESCRIPTION,
            'export_filters' => array (
                'products_filters' => array (
                    'type' => 'array',
                    'label' => DBIO_PRODUCTS_FILTERS_LABEL,
                    'fields' => array (
                        'products_status' => array (
                            'type' => 'dropdown',
                            'dropdown_options' => $status_options,
                            'label' => DBIO_PRODUCTS_STATUS_LABEL,
                        ),
                    ),
                ),
            ),
        );
        if (count ($manufacturers_options) > 0) {
            $my_config['export_filters']['products_filters']['fields']['products_manufacturers'] = array (
                'type' => 'dropdown_multiple',
                'dropdown_options' => $manufacturers_options,
                'label' => DBIO_PRODUCTS_MANUFACTURERS_LABEL,
            );
        }
        $my_config['export_filters']['products_filters']['fields']['products_categories'] = array (
            'type' => 'dropdown_multiple',
            'dropdown_options' => array_values ($categories_options),
            'label' => DBIO_PRODUCTS_CATEGORIES_LABEL,
        );
        return $my_config;
    }

    public function exportInitialize ($language = 'all') 
    {
        $initialized = parent::exportInitialize ($language);
        if ($initialized) {
            if ($this->where_clause != '') {
                $this->where_clause .= ' AND ';
        
            }
            $export_language = ($this->export_language == 'all') ? $this->languages[DEFAULT_LANGUAGE] : $this->languages[$this->export_language];
            $this->where_clause .= "p.products_id = pd.products_id AND pd.language_id = $export_language";
            $this->order_by_clause .= 'p.products_id ASC';
            
            $this->saved_data['products_description_sql'] = 
                'SELECT * FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE products_id = %u AND language_id = %u LIMIT 1';
        }
        return $initialized;
    }
    
    // -----
    // This function gives the current handler the last opportunity to modify the SQL query clauses used for the current export.  It's
    // usually provided by handlers that use an "export_filter", allowing the handler to inspect any filter-variables provided by
    // the caller.
    //
    // Returns a boolean (true/false) indication of whether the export's initialization was successful.  If unsuccessful, the handler
    // is **assumed** to have set its reason into the class message variable.
    //
    public function exportFinalizeInitialization ()
    {
        $this->debugMessage ('exportFinalizeInitialization for Products. POST variables:' . var_export ($_POST, true));
        
        // -----
        // Check to see if any of this handler's filter variables have been set.  If set, check the values and then
        // update the where_clause for the to-be-issued SQL query for the export.
        //
        if ($_POST['products_status'] != 'all') {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'p.products_status = ' . (int)$_POST['products_status'];
        }
        if (isset ($_POST['products_manufacturers']) && is_array ($_POST['products_manufacturers'])) {
            $manufacturers_list = implode (',', $_POST['products_manufacturers']);
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "p.manufacturers_id IN ($manufacturers_list)";
        }
        if (isset ($_POST['products_categories']) && is_array ($_POST['products_categories'])) {
            $categories_list = implode (',', $_POST['products_categories']);
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "p.master_categories_id IN ($categories_list)";
        }
        return true;
    }
    
    public function exportPrepareFields (array $fields) 
    {
        $fields = parent::exportPrepareFields ($fields);
        $products_id = $fields['products_id'];
        $tax_class_id = $fields['products_tax_class_id'];
        unset ($fields['products_id'], $fields['products_tax_class_id']);
      
        global $db;     
        if ($this->export_language == 'all') {
            foreach ($this->languages as $language_code => $language_id) {
                if ($language_id != 1) {
                    $description_info = $db->Execute (sprintf ($this->saved_data['products_description_sql'], $products_id, $language_id));
                    if (!$description_info->EOF) {
                        $encoded_fields = $this->exportEncodeData ($description_info->fields);
                        foreach ($encoded_fields as $field_name => $field_value) {
                            if ($field_name != 'products_id' && $field_name != 'language_id') {
                                $fields[$field_name . '_' . $language_code] = $field_value;
                            }
                        }
                    }
                }
            }
        }
      
        $fields['manufacturers_name'] = zen_get_products_manufacturers_name ($products_id);
      
        $tax_class_info = $db->Execute ("SELECT tax_class_title FROM " . TABLE_TAX_CLASS . " WHERE tax_class_id = $tax_class_id LIMIT 1");
        $fields['tax_class_title'] = ($tax_class_info->EOF) ? '' : $tax_class_info->fields['tax_class_title'];
      
        $cPath_array = explode ('_', zen_get_product_path ($products_id));
        $default_language_id = $this->languages[DEFAULT_LANGUAGE];
        $categories_name = '';
        foreach ($cPath_array as $next_category_id) {
            $category_info = $db->Execute ("SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = $next_category_id AND language_id = $default_language_id LIMIT 1");
            $categories_name .= (($category_info->EOF) ? self::DBIO_UNKNOWN_VALUE : $category_info->fields['categories_name']) . '^';
        
        }
        $fields['categories_name'] = substr ($categories_name, 0, -1);

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
        $this->stats['report_name'] = 'Products';
        $this->config = self::getHandlerInformation ();
        $this->config['keys'] = array (
            TABLE_PRODUCTS => array (
                'alias' => 'p',
                'capture_key_value' => true,
                'products_model' => array (
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'products_id' => array (
                    'type' => self::DBIO_KEY_IS_MASTER,
                ),
            ),
        );
        $this->config['tables'] = array (
            TABLE_PRODUCTS => array ( 
                'alias' => 'p',
                'io_field_overrides' => array (
                    'products_id' => 'no-header',
                    'manufacturers_id' => false,
                    'products_tax_class_id' => 'no-header',
                    'master_categories_id' => false,
                ),
            ), 
            TABLE_PRODUCTS_DESCRIPTION => array ( 
                'alias' => 'pd',
                'language_field' => 'language_id',
                'io_field_overrides' => array (
                    'products_id' => false,
                    'language_id' => false,
                ),
            ), 
        );
        $this->config['additional_headers'] = array (
            'v_manufacturers_name' => self::DBIO_FLAG_NONE,
            'v_tax_class_title' => self::DBIO_FLAG_NONE,
            'v_categories_name' => self::DBIO_FLAG_NONE,
        );
    } 
    
    // -----
    // This function, called for header-element being imported, can return one of three values:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importHeaderFieldCheck ($field_name) 
    {
        $field_status = self::DBIO_IMPORT_OK;
        switch ($field_name) {
            case 'products_id':
            case 'language_id':
            case 'manufacturers_id':
            case 'products_tax_class_id':
            case 'master_categories_id':
                $field_status = self::DBIO_NO_IMPORT;
                break;
            case 'manufacturers_name':
            case 'tax_class_title':
            case 'categories_name':
                $field_status = self::DBIO_SPECIAL_IMPORT;
                break;
            default:
                break;
        }
        return $field_status;
    }
     
    // -----
    // This function handles any overall record post-processing required for the Products import, specifically
    // making sure that the products' price sorter is run for the just inserted/updated product.
    //
    protected function importRecordPostProcess ($products_id)
    {
        $this->debugMessage ('Products::importRecordPostProcess: ' . $this->data_key_sql . "\n" . var_export ($this->key_fields, true), self::DBIO_WARNING);
        if ($products_id !== false && $this->operation != 'check') {
            zen_update_products_price_sorter ($products_id);
        }
    }    

    protected function importAddField ($table_name, $field_name, $field_value) {
        $this->debugMessage ("Products::importAddField ($table_name, $field_name, $field_value)");
        global $db;
        switch ($table_name) {
            case TABLE_PRODUCTS:
                if ($this->import_is_insert) {
                    if ($field_name === 'products_date_added') {
                        $field_value = 'now()';
                    } elseif ($field_name === 'products_last_modified') {
                        $field_value = self::DBIO_NO_IMPORT;
                    }
                } else {
                    if ($field_name === 'products_last_modified') {
                        $field_value = 'now()';
                    }
                }
                parent::importAddField ($table_name, $field_name, $field_value);
                break;
            case self::DBIO_SPECIAL_IMPORT:
                switch ($field_name) {
                    case 'manufacturers_name':
                        if (empty ($field_value)) {
                            $manufacturers_id = 0;

                        } else {
                            $manufacturer_check_sql = "SELECT manufacturers_id FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_name = :manufacturer_name: LIMIT 1";
                            $manufacturer_check = $db->Execute ($db->bindVars ($manufacturer_check_sql, ':manufacturer_name:', $field_value, 'string'), false, false, 0, true);
                            if (!$manufacturer_check->EOF) {
                                $manufacturers_id = $manufacturer_check->fields['manufacturers_id'];
                          
                            } else {
                                $this->debugMessage ("[*] Import, creating database entry for manufacturer named \"$field_value\"", self::DBIO_ACTIVITY);
                                $sql_data_array = array ();
                                $sql_data_array[] = array ( 'fieldName' => 'manufacturers_name', 'value' => $field_value, 'type' => 'string' );
                                $sql_data_array[] = array ( 'fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring' );
                                $db->perform (TABLE_MANUFACTURERS, $sql_data_array);
                                $manufacturers_id = $db->Insert_ID();
                          
                                foreach ($this->languages as $language_code => $language_id) {
                                    $sql_data_array = array ();
                                    $sql_data_array[] = array ( 'fieldName' => 'manufacturers_id', 'value' => $manufacturers_id, 'type' => 'integer' );
                                    $sql_data_array[] = array ( 'fieldName' => 'languages_id', 'value' => $language_id, 'type' => 'integer' );
                                    $db->perform (TABLE_MANUFACTURERS_INFO, $sql_data_array);
                            
                                }
                            }
                        }
                        $this->import_sql_data[TABLE_PRODUCTS]['manufacturers_id'] = array ( 'value' => $manufacturers_id, 'type' => 'integer' );
                        break;
                    case 'tax_class_title':
                        if (zen_not_null ($field_value)) {
                            $tax_class_check_sql = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . " WHERE tax_class_title = :tax_class_title: LIMIT 1";
                            $tax_class_check = $db->Execute ($db->bindVars ($tax_class_check_sql, ':tax_class_title:', $field_value, 'string'));
                            if ($tax_class_check->EOF) {
                                $this->debugMessage ('[*] Import line #' . $this->stats['record_count'] . ", undefined tax_class_title ($field_value).  Defaulting product to untaxed.", self::DBIO_WARNING);
                      
                            }
                            $tax_class_id = ($tax_class_check->EOF) ? 0 : $tax_class_check->fields['tax_class_id'];
                            $this->import_sql_data[TABLE_PRODUCTS]['products_tax_class_id'] = array ( 'value' => $tax_class_id, 'type' => 'integer' );
                        }
                        break;
                    case 'categories_name':
                        $parent_category = 0;
                        $categories_name_ok = true;
                        $language_id = $this->languages[DEFAULT_LANGUAGE];
                        $categories = explode ('^', $field_value);
                        foreach ($categories as $current_category_name) {
                            $category_info_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                                   WHERE c.parent_id = $parent_category 
                                                     AND c.categories_id = cd.categories_id
                                                     AND cd.categories_name = :categories_name: 
                                                     AND cd.language_id = $language_id LIMIT 1";
                            $category_info = $db->Execute ($db->bindVars ($category_info_sql, ':categories_name:', $current_category_name, 'string'), false, false, 0, true);
                            if (!$category_info->EOF) {
                                $parent_category = $category_info->fields['categories_id'];
                              
                            } elseif ($this->import_is_insert) {
                                $categories_name_ok = false;
                                $this->debugMessage ('[*] Product not inserted at line number ' . $this->stats['record_count'] . ", no match found for categories_name ($current_category_name).", self::DBIO_WARNING);
                                break;
                              
                            }
                        }
                        if ($categories_name_ok && $this->import_is_insert) {
                            $category_check = $db->Execute ("SELECT categories_id FROM " . TABLE_CATEGORIES . " WHERE parent_id = $parent_category LIMIT 1", false, false, 0, true);
                            if (!$category_check->EOF) {
                                $categories_name_ok = false;
                                $this->debugMessage ("[*] Product not inserted at line number " . $this->stats['record_count'] . "; category ($field_name) has categories.", self::DBIO_WARNING);

                            } else {
                                $this->import_sql_data[TABLE_PRODUCTS]['master_categories_id'] = array ( 'value' => $parent_category, 'type' => 'integer' );
                                $this->import_sql_data[TABLE_PRODUCTS_TO_CATEGORIES]['categories_id'] = array ( 'value' => $parent_category, 'type' => 'integer' );
                            }
                        }
                        if (!$categories_name_ok) {
                            $this->record_status = false;
                        }
                        break;
                    default:
                        break;
                }  //-END switch interrogating $field_name for self::DBIO_SPECIAL_IMPORT
                break;
            default:
                parent::importAddField ($table_name, $field_name, $field_value);
                break;
        }  //-END switch interrogating $table_name
    }  //-END function importAddField

    // -----
    // This function, issued just prior to the database action, allows the I/O handler to make any "last-minute" changes based
    // on the record's 'key' value -- for this report, it's the products_id value.
    //
    // If we're doing an insert (i.e. a new product), simply add the products_id field to the non-products tables' SQL
    // input array.
    //
    // If we're doing an update (i.e. existing product), the built-in handling has already taken care of the language
    // tables, but there's some special handling required for the products-to-categories table.  That table's update
    // happens within this function and we set the return value to false to indicate to the parent processing that the
    // associated update has been already handled.
    //
    protected function importUpdateRecordKey ($table_name, $table_fields, $new_products_id) 
    {
        if ($products_id !== false) {
            global $db;
            if ($table_name != TABLE_PRODUCTS) {
                if ($this->import_is_insert) {
                    $table_fields['products_id'] = array ( 'value' => $new_products_id, 'type' => 'integer' );
                } else {
                    $this->where_clause = 'products_id = ' . (int)$this->key_fields['products_id'];
                }
            }
            if ($table_name == TABLE_PRODUCTS_TO_CATEGORIES) {
                if ($this->operation != 'check') {
                    foreach ($table_fields as $field_name => $field_data) {
                        $db->Execute ("INSERT IGNORE INTO $table_name (products_id, categories_id) VALUES ( $new_products_id, " . (int)$field_data['value'] . ")");
                    }
                }
                $table_fields = false;
            }
        }
        return parent::importUpdateRecordKey ($table_name, $table_fields, $new_products_id);
      
    }

}  //-END class DbIoProductsHandler
