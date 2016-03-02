<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart product-attribute import/export.
//
class DbIoProductsAttribsBasicHandler extends DbIoHandler 
{
    public static function getHandlerInformation ()
    {
        include_once (DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/DbIoProductsAttribsBasicHandler.php');      
        return array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSATTRIBSBASIC_DESCRIPTION,
        );
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
        return parent::exportFinalizeInitialization ();
    }
    
    // -----
    // Prepare the additional fields (the list of options' values' names) for the export.
    //
    // On entry:
    //      $fields ... An associative array of values, as retrieved by the report's export SQL.
    //
    // On exit:
    //      $fields ... Updated to remove the key-values and add the ^-separated list of values associated with the option.
    //
    public function exportPrepareFields (array $fields) 
    {
        global $db;
        $products_id = $fields['products_id'];
        $products_options_id = $fields['products_options_id'];
        unset ($fields['products_id'], $fields['products_options_id']);
        
        if (PRODUCTS_OPTIONS_SORT_BY_PRICE == '1') {
            $order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pov.products_options_values_name';
        } else {
            $order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pa.options_values_price';
        }
        $export_language = ($this->export_language == 'all') ? DEFAULT_LANGUAGE : $this->export_language;
        $attrib_info = $db->Execute ("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
                                       WHERE pa.products_id = $products_id
                                         AND pa.options_id = $products_options_id
                                         AND pa.options_values_id = pov.products_options_values_id
                                         AND pov.language_id = " . $this->languages[$export_language] . "
                                    ORDER BY $order_by");
        $products_options_values_names = array ();
        while (!$attrib_info->EOF) {
            $products_options_values_names[] = $attrib_info->fields['products_options_values_name'];
            $attrib_info->MoveNext ();
        }
        $fields['products_options_values_name_' . $export_language] = implode ('^', $products_options_values_names);
        
        return parent::exportPrepareFields ($fields);
      
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
        $this->stats['report_name'] = 'ProductsAttribsBasic';
        if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
            $options_order_by = 'LPAD(po.products_options_sort_order,11,"0")';
        } else {
            $options_order_by = 'po.products_options_name';
        }
        $this->config = self::getHandlerInformation ();
        $this->config['key'] = array (
            'table' => TABLE_PRODUCTS, 
            'match_field' => 'products_model', 
            'key_field' => 'products_id', 
            'key_field_type' => 'integer' 
        );
        $this->config['fixed_headers'] = array (
            'tables' => array (
                TABLE_PRODUCTS_ATTRIBUTES => 'pa',
                TABLE_PRODUCTS => 'p',
                TABLE_PRODUCTS_OPTIONS => 'po',
            ),
            'language_tables' => array (
                'po' => 'language_id',
            ),
            'fields' => array (
                'products_model' => 'p',
                'products_id' => 'p',
                'products_options_id' => 'po',
                'products_options_type' => 'po',
                'products_options_name' => 'po',
            ),
            'no_header_fields' => array (
                'products_id',
                'products_options_id',
            ),
            'where_clause' => 'pa.products_id = p.products_id AND pa.options_id = po.products_options_id AND po.language_id = %u GROUP BY p.products_id, po.products_options_id',
            'order_by_clause' => "p.products_model, $options_order_by",
        );
        $this->config['additional_headers'] = array (
            'v_products_options_values_name' => self::DBIO_FLAG_NONE,
        );
    } 
    
    protected function exportInitializeFixedHeaders ()
    {
        parent::exportInitializeFixedHeaders ();
        $export_language_code = $this->export_language;
        if ($export_language_code == 'all') {
            $export_language_code = DEFAULT_LANGUAGE;
        }
        $this->where_clause = sprintf ($this->where_clause, $this->languages[$export_language_code]);
        $this->headers[] = "v_products_options_values_name_$export_language_code";
    }
    
    
    
    protected function importAddField ($table_name, $field_name, $field_value, $field_type) {
        $this->debugMessage ("importAddField ($table_name, $field_name, $field_value, $field_type)");
        global $db;
        switch ($table_name) {
            case TABLE_PRODUCTS:
                $import_this_field = true;
                if ($this->import_is_insert) {
                    if ($field_name === 'products_date_added') {
                        $field_value = 'now()';
                        $field_type = 'noquotestring';
                    } elseif ($field_name === 'products_last_modified') {
                        $import_this_field = false;
                    }
                } else {
                    if ($field_name === 'products_last_modified') {
                        $field_value = 'now()';
                        $field_type = 'noquotestring';
                    } elseif ($field_name === 'products_date_added') {
                        $import_this_field = false;
                    }
                }
                if ($import_this_field) {
                    parent::importAddField ($table_name, $field_name, $field_value, $field_type);
                }
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
                        parent::importAddField (TABLE_PRODUCTS, 'manufacturers_id', $manufacturers_id, 'integer');
                        break;
                    case 'tax_class_title':
                        if (zen_not_null ($field_value)) {
                            $tax_class_check_sql = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . " WHERE tax_class_title = :tax_class_title: LIMIT 1";
                            $tax_class_check = $db->Execute ($db->bindVars ($tax_class_check_sql, ':tax_class_title:', $field_value, 'string'));
                            if ($tax_class_check->EOF) {
                                $this->debugMessage ('[*] Import line #' . $this->stats['record_count'] . ", undefined tax_class_title ($field_value).  Defaulting product to untaxed.", self::DBIO_WARNING);
                      
                            }
                            $tax_class_id = ($tax_class_check->EOF) ? 0 : $tax_class_check->fields['tax_class_id'];
                            parent::importAddField (TABLE_PRODUCTS, 'products_tax_class_id', $tax_class_id, 'integer');
                    
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
                                parent::importAddField (TABLE_PRODUCTS, 'master_categories_id', $parent_category, 'integer');
                                parent::importAddField (TABLE_PRODUCTS_TO_CATEGORIES, 'categories_id', $parent_category, 'integer');

                            }
                        }
                        if (!$categories_name_ok) {
                            $this->record_ok = false;
                        }
                        break;
                    default:
                        break;
                }  //-END switch interrogating $field_name for self::DBIO_SPECIAL_IMPORT
                break;
            default:
                parent::importAddField ($table_name, $field_name, $field_value, $field_type);
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
    protected function importUpdateRecordKey ($table_name, $sql_data_array, $products_id, $key_value_fields) 
    {
        if ($products_id !== false) {
            global $db;
            if ($this->import_is_insert) {
                if ($table_name != TABLE_PRODUCTS) {
                    $sql_data_array[] = array ( 'fieldName' => 'products_id', 'value' => $products_id, 'type' => 'integer' );
              
                }
            } elseif ($table_name == TABLE_PRODUCTS_TO_CATEGORIES && $this->operation != 'check') {
                if ($this->operation == 'check')
                foreach ($sql_data_array as $next_category) {
                    $db->Execute ("INSERT IGNORE INTO $table_name (products_id, categories_id) VALUES ( $products_id, " . $next_category['value'] . ")");
              
                }
                $sql_data_array = false;
            }
        }
        return parent::importUpdateRecordKey ($table_name, $sql_data_array, $products_id);
      
    }

}  //-END class DbIoProductsHandler
