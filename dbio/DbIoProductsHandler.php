<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

if (!class_exists ('DbIoHandler')) {
    require (DIR_FS_DBIO . 'DbIoHandler.php');

}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart product import/export.
//
class DbIoProductsHandler extends DbIoHandler 
{   
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->config = array (
            'version' => '0.0.0',
            'key' => array ( 
                'table' => TABLE_PRODUCTS, 
                'match_field' => 'products_model', 
                'key_field' => 'products_id', 
                'key_field_type' => 'integer' 
            ),
            'include_header' => true,
            'tables' => array ( 
                TABLE_PRODUCTS => array ( 
                    'short_name' => 'p',
                    'insert_now' => 'products_date_added',
                    'update_now' => 'products_last_modified',
                    'io_field_overrides' => array (
                        'products_id' => 'no-header',
                        'manufacturers_id' => false,
                        'products_tax_class_id' => 'no-header',
                        'master_categories_id' => false,
                    ),
                ), 
                TABLE_PRODUCTS_DESCRIPTION => array ( 
                    'short_name' => 'pd',
                    'language_field' => 'language_id',
                    'key_field' => 'products_id',
                    'io_field_overrides' => array (
                        'products_id' => false,
                        'language_id' => false,
                    ),
                ), 
            ),
            'additional_headers' => array (
                'v_manufacturers_name' => self::DBIO_FLAG_NONE,
                'v_tax_class_title' => self::DBIO_FLAG_NONE,
                'v_categories_name' => self::DBIO_FLAG_PER_LANGUAGE,
            ),
            'description' => array (
                'en' => "This report-format supports import/export of all fields within the <code>products</code> and <code>products_description</code> tables, the basic product information.",
            ),
        );
    } 

    public function exportInitialize ($language = 'all') 
    {
        $initialized = parent::exportInitialize ($language);
        if ($initialized) {
            if ($this->export['where'] != '') {
                $this->export['where'] .= ' AND ';
        
            }

            $export_language = ($this->export_language == 'all') ? 1 : $this->languages[$this->export_language];
            $this->export['where'] .= "p.products_id = pd.products_id AND pd.language_id = $export_language";
            $this->export['order_by'] .= 'p.products_id ASC';

            $this->export[TABLE_PRODUCTS_DESCRIPTION]['language_sql'] = 
                'SELECT ' . $this->export[TABLE_PRODUCTS_DESCRIPTION]['select'] . 
                ' FROM ' . TABLE_PRODUCTS_DESCRIPTION . 
                ' WHERE products_id = %u AND language_id = %u LIMIT 1';
        }
        return $initialized;
    }

    public function exportPrepareFields (array $fields) 
    {
        $products_id = $fields['products_id'];
        $tax_class_id = $fields['products_tax_class_id'];
        unset ($fields['products_id'], $fields['products_tax_class_id']);
      
        global $db;     
        if ($this->export_language == 'all') {
            foreach ($this->languages as $language_code => $language_id) {
                if ($language_id != 1) {
                    $description_info = $db->Execute (sprintf ($this->export[TABLE_PRODUCTS_DESCRIPTION]['language_sql'], $products_id, $language_id));
                    if (!$description_info->EOF) {
                        foreach ($description_info->fields as $field_name => $field_value) {
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
        foreach ($this->languages as $language_code => $language_id) {
            $categories_name = '';
            foreach ($cPath_array as $next_category_id) {
                if ($this->export_language !== 'all' && $this->export_language != $language_code) {
                    continue;
                }
                $category_info = $db->Execute ("SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = $next_category_id AND language_id = $language_id LIMIT 1");
                $categories_name .= (($category_info->EOF) ? '--unknown--' : $category_info->fields['categories_name']) . '^';
            
            }
            $fields['categories_name_' . $language_code] = substr ($categories_name, 0, -1);
        }
        return parent::exportPrepareFields ($fields);
      
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

    protected function importFieldCheck ($field_name) 
    {
        switch ($field_name) {
            case 'products_id':
            case 'language_id':
            case 'manufacturers_id':
            case 'products_tax_class_id':
            case 'master_categories_id':
                $field_name = DBIO_NO_IMPORT;
                break;
            default:
                break;
        }
        return $field_name;
    }

    protected function importFinalizeFields ($data) 
    {
        return parent::importFinalizeFields ($data);
  
    }

    protected function importAddField ($table_name, $field_name, $field_value, $field_type) {
        global $db;
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
                        $this->debugMessage ("[*] Import, creating database entry for manufacturer named \"$field_value\"");
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
                parent::importAddField (TABLE_PRODUCTS, 'manufacturers_id', $manufacturers_id, 'integer');
                break;
            case 'tax_class_title':
                if (zen_not_null ($field_value)) {
                    $tax_class_check_sql = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . " WHERE tax_class_title = :tax_class_title: LIMIT 1";
                    $tax_class_check = $db->Execute ($db->bindVars ($tax_class_check_sql, ':tax_class_title:', $field_value, 'string'));
                    if ($tax_class_check->EOF) {
                        $this->debugMessage ('[*] Import line #' . $this->import['record_count'] . ", undefined tax_class_title ($field_value).  Defaulting product to untaxed.", self::DBIO_WARNING);
              
                    }
                    $tax_class_id = ($tax_class_check->EOF) ? 0 : $tax_class_check->fields['tax_class_id'];
                    parent::importAddField (TABLE_PRODUCTS, 'products_tax_class_id', $tax_class_id, 'integer');
            
                }
                break;
            case 'categories_name':
                $parent_category = 0;
                $categories = explode ('^', $field_value);
                foreach ($categories as $current_category_name) {
                    $category_info_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                           WHERE c.parent_id = $parent_category 
                                             AND c.categories_id = cd.categories_id
                                             AND cd.categories_name = :categories_name: 
                                             AND cd.language_id = 1 LIMIT 1";
                    $category_info = $db->Execute ($db->bindVars ($category_info_sql, ':categories_name:', $current_category_name, 'string'), false, false, 0, true);
                    if (!$category_info->EOF) {
                        $parent_category = $category_info->fields['categories_id'];
                      
                    } else {
                        $this->debugMessage ("[*] Creating category named \"$current_category_name\", with parent category $parent_category.");
                      
                        $sql_data_array = array ();
                        $sql_data_array[] = array ( 'fieldName' => 'parent_id', 'value' => $parent_category, 'type' => 'integer' );
                        $sql_data_array[] = array ( 'fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring' );
                        $db->perform (TABLE_CATEGORIES, $sql_data_array);
                      
                        $categories_id = $db->insert_ID ();
                        foreach ($this->languages as $language_code => $language_id) {
                            $sql_data_array = array ();
                            $sql_data_array[] = array ( 'fieldName' => 'categories_id', 'value' => $categories_id, 'type' => 'integer' );
                            $sql_data_array[] = array ( 'fieldName' => 'language_id', 'value' => $language_id, 'type' => 'integer' );
                            $sql_data_array[] = array ( 'fieldName' => 'categories_name', 'value' => $current_category_name, 'type' => 'string' );
                            $db->perform (TABLE_CATEGORIES_DESCRIPTION, $sql_data_array);
                        
                        }
                        $parent_category = $categories_id;
                      
                    }
                }
                $category_check = $db->Execute ("SELECT categories_id FROM " . TABLE_CATEGORIES . " WHERE parent_id = $parent_category LIMIT 1", false, false, 0, true);
                if (!$category_check->EOF) {
                    $this->import['record_ok'] = false;
                    $this->debugMessage ("[*] Product not processed at line number " . $this->import['record_count'] . "; category ($field_name) has categories.", self::DBIO_WARNING);

                } else {
                    parent::importAddField (TABLE_PRODUCTS, 'master_categories_id', $parent_category, 'integer');
                    parent::importAddField (TABLE_PRODUCTS_TO_CATEGORIES, 'categories_id', $parent_category, 'integer');

                }
                break;
            default:
                parent::importAddField ($table_name, $field_name, $field_value, $field_type);
                break;
        }
    }

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
    protected function importUpdateRecordKey ($table_name, $sql_data_array, $products_id) 
    {
        global $db;
        if ($this->import['action'] == 'insert') {
            if ($table_name != TABLE_PRODUCTS) {
                $sql_data_array[] = array ( 'fieldName' => 'products_id', 'value' => $products_id, 'type' => 'integer' );
          
            }
        } elseif ($table_name == TABLE_PRODUCTS_TO_CATEGORIES) {
            foreach ($sql_data_array as $next_category) {
                $db->Execute ("INSERT IGNORE INTO $table_name (products_id, categories_id) VALUES ( $products_id, " . $next_category['value'] . ")");
          
            }
            $sql_data_array = false;

        }
        return parent::importUpdateRecordKey ($table_name, $sql_data_array, $products_id);
      
    }

}  //-END class DbIoProductsHandler
