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
        $first_language_code = ($this->export_language == 'all') ? DEFAULT_LANGUAGE : $this->export_language;
        $this->where_clause = sprintf ($this->where_clause, $this->languages[$first_language_code]);
        
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
        $this->config = self::getHandlerInformation ();
        $this->config['key'] = array (
            'table' => TABLE_PRODUCTS, 
            'match_field' => 'products_model',
            'match_field_type' => 'string',
            'key_field' => 'products_id', 
            'key_field_type' => 'integer' 
        );
        $this->config['tables'] = array (
            TABLE_PRODUCTS_ATTRIBUTES => array (
                'alias' => 'pa',
            ),
            TABLE_PRODUCTS => array (
                'alias' => 'p',
            ),
            TABLE_PRODUCTS_OPTIONS => array (
                'alias' => 'po',
                'language_field' => 'language_id',
            ),
            TABLE_PRODUCTS_OPTIONS_VALUES => array (
                'alias' => 'pov',
                'language_field' => 'language_id',
                'no_from_clause' => true,
            ),
        );
        $this->config['fixed_headers'] = array (
            'products_model' => TABLE_PRODUCTS,
            'products_id' => TABLE_PRODUCTS,
            'products_options_id' => TABLE_PRODUCTS_OPTIONS,
            'products_options_type' => TABLE_PRODUCTS_OPTIONS,
            'products_options_name' => TABLE_PRODUCTS_OPTIONS,
        );
        $this->config['fixed_fields_no_header'] = array (
            'products_id',
            'products_options_id',
        );
        $this->config['export_where_clause'] = 'pa.products_id = p.products_id AND pa.options_id = po.products_options_id AND po.language_id = %u GROUP BY p.products_id, po.products_options_id';
        
        if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
            $options_order_by = 'LPAD(po.products_options_sort_order,11,"0")';
        } else {
            $options_order_by = 'po.products_options_name';
        }
        $this->config['export_order_by_clause'] = "p.products_model, $options_order_by";

        $this->config['additional_headers'] = array (
            'v_products_options_values_name' => self::DBIO_FLAG_PER_LANGUAGE,
        );
    }
    
    // -----
    // Check the "key" value (the products_model mapped to a products_id).  If that value is false, then the associated
    // product model wasn't found, so there's nothing to import for the current data record.
    //
    protected function importCheckKeyValue ($data_value, $key_value, $key_value_fields)
    {
        if ($key_value === false) {
            $this->record_status = false;
            $this->debugMessage ("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; product's model ($data_value) does not exist.", self::DBIO_WARNING);
        } else {
            $this->saved_data = array ( 'products_id' => $key_value, 'option' => array () );
        }
        return $this->record_status;
    }
    
    protected function importProcessField ($table_name, $field_name, $language_id, $field_value)
    {
        global $db;
        if ($table_name == TABLE_PRODUCTS_OPTIONS) {
            if (!isset ($this->saved_data['option'][$language_id])) {
                $this->saved_data['option'][$language_id] = array ();
            }
            $this->saved_data['option'][$language_id][$field_name] = $field_value;
        } elseif ($table_name == TABLE_PRODUCTS_OPTIONS_VALUES) {
            if (!isset ($this->saved_data['option'][$language_id]) || !isset ($this->saved_data['option'][$language_id]['products_options_type']) || !isset ($this->saved_data['option'][$language_id]['products_options_name'])) {    $this->record_status = false;
                $this->debugMessage ("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; product's option information does not exist.", self::DBIO_WARNING);
            } else {
                $products_options_name = $this->saved_data['option'][$language_id]['products_options_name'];
                $products_options_type = $this->saved_data['option'][$language_id]['products_options_type'];
                $option_check = $db->Execute ("SELECT products_options_id FROM " . TABLE_PRODUCTS_OPTIONS . "
                                                WHERE products_options_name = '" . $db->prepare_input ($products_options_name) . "'
                                                  AND language_id = $language_id
                                                  AND products_options_type = " . (int)$products_options_type . " LIMIT 1", false, false, 0, true);
                if ($option_check->EOF) {
                    $this->record_status = false;
                    $this->debugMessage ("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; product's option ($products_options_name::$products_options_type::$language_id) not found.", self::DBIO_WARNING);
                } else {
                    $products_options_id = $option_check->fields['products_options_id'];
                    $options_values_names = explode ('^', $field_value);
                    if (count ($options_values_names) == 0) {
                        $this->record_status = false;
                        $this->debugMessage ("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; option names\' list cannot be empty ($field_value).", self::DBIO_WARNING);
                    } else {
                        foreach ($options_values_names as $current_option_value_name) {
                            $value_check = $db->Execute ("SELECT products_options_values_id FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . "
                                                           WHERE products_options_values_name = '" . $db->prepare_input ($current_option_value_name) . "'
                                                             AND language_id = $language_id LIMIT 1", false, false, 0, true);
                            if ($value_check->EOF) {
                                $this->record_status = false;
                                $this->debugMessage ("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; option value name ($current_option_value_name::$language_id) does not exist.", self::DBIO_WARNING);
                            } else {
                                $attribute_check = $db->Execute ("SELECT products_attributes_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                                                                   WHERE products_id = " . (int)$this->saved_data['products_id'] . "
                                                                     AND options_id = $products_options_id
                                                                     AND options_values_id = " . $value_check->fields['products_options_values_id'] . " LIMIT 1", false, false, 0, true);
                                if ($attribute_check->EOF) {
                                    $table_fields = array (
                                        'products_id' => array ( 'value' => $this->saved_data['products_id'], 'type' => 'integer' ),
                                        'options_id' => array ( 'value' => $products_options_id, 'type' => 'integer' ),
                                        'options_values_id' => array ( 'value' => $value_check->fields['products_options_values_id'], 'type' => 'integer' ),
                                    );
                                    $sql_query = $this->importBuildSqlQuery (TABLE_PRODUCTS_ATTRIBUTES, $table_fields, true, true);
                                    if ($this->operation != 'check') {
                                        $db->Execute ($sql_query);
                                        $attrib_record_id = $db->Insert_ID ();
                                        $this->stats['inserts']++;
                                        $this->debugMessage ("ProductsAttribsBasic inserted products_attributes_id = $attrib_record_id.", self::DBIO_ACTIVITY);
                                    }
                                }
                            }
                        }
                        $this->record_status = 'processed';
                    }
                }
            }
        }
    }

}  //-END class DbIoProductsHandler
