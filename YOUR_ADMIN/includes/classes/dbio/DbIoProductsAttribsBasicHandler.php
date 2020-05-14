<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2020, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart product-attribute import/export.
//
// The I/O fields supported by this fixed-field I/O handler are:  v_products_model, v_products_options_type, v_products_options_name, and v_products_options_values
//
// For imports, 
// - The v_products_model field identifies the (presumed) single product associated with the (possibly) multiple imports.
// - The combination of v_products_options_type and v_products_options_name are used to find existing option/option-value pairs in the
//   store's DEFAULT LANGUAGE.  All combinations must pre-exist in the database or the associated record's information is not imported.
//
class DbIoProductsAttribsBasicHandler extends DbIoHandler 
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('ProductsAttribsBasic');
        return array(
            'version' => '1.6.2',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSATTRIBSBASIC_DESCRIPTION,
        );
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
    public function exportPrepareFields(array $fields) 
    {
        global $db;
        $products_id = $fields['products_id'];
        $products_options_id = $fields['products_options_id'];
        unset($fields['products_id'], $fields['products_options_id']);
        
        if (PRODUCTS_OPTIONS_SORT_BY_PRICE == '1') {
            $order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pov.products_options_values_name';
        } else {
            $order_by = 'LPAD(pa.products_options_sort_order,11,"0"), pa.options_values_price';
        }
        $language_id = $this->languages[DEFAULT_LANGUAGE];
        $attrib_info = $db->Execute(
            "SELECT products_options_values_name FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
              WHERE pa.products_id = $products_id
                AND pa.options_id = $products_options_id
                AND pa.options_values_id = pov.products_options_values_id
                AND pov.language_id = $language_id
              ORDER BY $order_by");
        $products_options_values_names = array();
        while (!$attrib_info->EOF) {
            $products_options_values_names[] = $attrib_info->fields['products_options_values_name'];
            $attrib_info->MoveNext();
        }
        $fields['products_options_values_name'] = implode('^', $products_options_values_names);

        return parent::exportPrepareFields($fields);
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
        $this->stats['report_name'] = 'ProductsAttribsBasic';
        $this->config = self::getHandlerInformation();
        $this->config['handler_does_import'] = true;  //-Indicate that **all** the import-based database manipulations are performed by this handler
        $this->config['keys'] = array(
            TABLE_PRODUCTS => array(
                'alias' => 'p',
                'products_model' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
                'products_id' => array(
                    'type' => self::DBIO_KEY_IS_MASTER,
                ),
            ),
        );
        $this->config['tables'] = array(
            TABLE_PRODUCTS_ATTRIBUTES => array(
                'alias' => 'pa',
            ),
            TABLE_PRODUCTS => array(
                'alias' => 'p',
            ),
            TABLE_PRODUCTS_OPTIONS => array(
                'alias' => 'po',
            ),
            TABLE_PRODUCTS_OPTIONS_VALUES => array(
                'alias' => 'pov',
                'no_from_clause' => true,
            ),
        );
        $this->config['fixed_headers'] = array(
            'products_model' => TABLE_PRODUCTS,
            'products_id' => TABLE_PRODUCTS,
            'products_options_id' => TABLE_PRODUCTS_OPTIONS,
            'products_options_type' => TABLE_PRODUCTS_OPTIONS,
            'products_options_name' => TABLE_PRODUCTS_OPTIONS,
        );
        $this->config['fixed_fields_no_header'] = array(
            'products_id',
            'products_options_id',
        );
        $this->config['export_where_clause'] = 'pa.products_id = p.products_id AND pa.options_id = po.products_options_id AND po.language_id = ' . $this->languages[DEFAULT_LANGUAGE] . ' GROUP BY p.products_model, po.products_options_id, po.products_options_type, po.products_options_name, p.products_id';
        
        if (PRODUCTS_OPTIONS_SORT_ORDER == '0') {
            $options_order_by = 'LPAD(po.products_options_sort_order,11,"0")';
        } else {
            $options_order_by = 'po.products_options_name';
        }
        $this->config['export_order_by_clause'] = "p.products_model, $options_order_by";

        $this->config['additional_headers'] = array(
            'v_products_options_values_name' => self::DBIO_FLAG_NONE,
        );
    }
    
    // -----
    // This report's import handling is a little different.  The base class' handling is used to determine whether the record's associated
    // products_model exists; that value, in turn, provides the product's ID.  If the model_number doesn't exist, then the record's import
    // is denied.
    //
    // The products_id value is saved by the base class processing in the class array "key_fields".
    //
    protected function importCheckKeyValue($data)
    {
        if ($this->import_is_insert === true) {
            $this->record_status = false;
            $this->debugMessage("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; product's model ($data_value) does not exist.", self::DBIO_WARNING);
        }
        return $this->record_status;
    }
    
    protected function importProcessField($table_name, $field_name, $language_id, $field_value)
    {
        switch ($field_name) {
            case 'products_options_type':
            case 'products_options_name':
                if (!isset ($this->saved_data['option'])) {
                    $this->saved_data['option'] = array ();
                }
                $this->saved_data['option'][$field_name] = $field_value;
                break;
            case 'products_options_values_name':
                $this->saved_data['option_values'] = $field_value;
                break;
            default:
                break;
        }
    }
    
    protected function importFinishProcessing()
    {
        global $db;
        $message = '';
        if (!isset($this->saved_data['option']) || !isset($this->saved_data['option']['products_options_type']) || !isset($this->saved_data['option']['products_options_name'])) {
            $message = " product's option fields do not exist.";
        } elseif (!isset($this->saved_data['option_values'])) {
            $message = " product's option value field(s) do not exist.";
        } else {
            $products_options_name = $this->saved_data['option']['products_options_name'];
            $products_options_type = $this->saved_data['option']['products_options_type'];
            $language_id = $this->languages[DEFAULT_LANGUAGE];
            
            $option_check = $db->Execute(
                "SELECT products_options_id FROM " . TABLE_PRODUCTS_OPTIONS . "
                  WHERE products_options_name = '" . $db->prepare_input($products_options_name) . "'
                    AND language_id = $language_id
                    AND products_options_type = " . (int)$products_options_type . " 
                    LIMIT 1", 
                false, 
                false, 
                0, 
                true
            );
            if ($option_check->EOF) {
                $message = " no match for option name::option_type::default_language_id ($products_options_name::$products_options_type::$language_id).";
            } else {
                $products_options_id = $option_check->fields['products_options_id'];

                $options_values_names = explode('^', $this->saved_data['option_values']);
                $options_values = array();
                foreach ($options_values_names as $current_value_name) {
                    $options_values[] = $db->prepare_input($current_value_name);
                }
                $options_values_list = "'" . implode("', '", $options_values) . "'";
                $options_values_check = $db->Execute(
                    "SELECT pov.products_options_values_id, pov.products_options_values_name
                      FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov, " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " pov2po
                     WHERE pov.products_options_values_name IN ($options_values_list)
                       AND pov.language_id = $language_id
                       AND pov.products_options_values_id = pov2po.products_options_values_id
                       AND pov2po.products_options_id = $products_options_id", 
                    false, 
                    false, 
                    0, 
                    true
                );
                if (count($options_values_names) != $options_values_check->RecordCount()) {
                    $values_found = array();
                    while (!$options_values_check->EOF) {
                        $values_found = $options_values_check->fields['products_options_values_name'];
                        $options_values_check->MoveNext();
                    }
                    $values_not_found = implode(', ', array_diff ($options_values_names, $values_found));
                    $message = " one or more option-values ($values_not_found) were either not present in the default language or are not associated with the products_options_id of $products_options_id.";
                } else {
                    $products_id = $this->key_fields['products_id'];
                    $attributes_insert_sql = array(
                        'products_id' => array('value' => $products_id, 'type' => 'integer'),
                        'options_id' => array('value' => $products_options_id, 'type' => 'integer'),
                        'options_values_id' => array('value' => 0, 'type' => 'integer'),
                    );
                    while (!$options_values_check->EOF) {
                        $check = $db->Execute(
                            "SELECT products_attributes_id FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                              WHERE products_id = $products_id
                                AND options_id = $products_options_id
                                AND options_values_id = " . $options_values_check->fields['products_options_values_id'] . " 
                              LIMIT 1", 
                            false, 
                            false, 
                            0, 
                            true
                        );
                        if ($check->EOF) {
                            $attributes_insert_sql['options_values_id']['value'] = $options_values_check->fields['products_options_values_id'];
                            $attrib_insert_query = $this->importBuildSqlQuery(TABLE_PRODUCTS_ATTRIBUTES, '', $attributes_insert_sql, '', true, true);
                            if ($this->operation != 'check') {
                                $db->Execute($attrib_insert_query);
                            }
                        }
                        $options_values_check->MoveNext();
                    }
                    $this->record_status = 'processed';
                }
            }
        }
        if ($message != '') {
            $this->record_status = false;
            $this->debugMessage("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; $message", self::DBIO_WARNING);
        }
    }
    
    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        return ($table_name == TABLE_PRODUCTS) ? false : parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause, $is_override, $is_insert);
    }

}  //-END class DbIoProductsAttribsBasicHandler
