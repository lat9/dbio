<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2024, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.1
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
// - Only **new** option/option-value pairs are successfully imported!
//
class DbIoProductsAttribsBasicHandler extends DbIoHandler
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('ProductsAttribsBasic');
        return [
            'version' => '2.0.1',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSATTRIBSBASIC_DESCRIPTION,
        ];
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
        
        if (PRODUCTS_OPTIONS_SORT_BY_PRICE === '1') {
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
        $products_options_values_names = [];
        foreach ($attrib_info as $next_attrib) {
            $products_options_values_names[] = $next_attrib['products_options_values_name'];
        }
        $fields['products_options_values_name'] = implode('^', $products_options_values_names);

        return parent::exportPrepareFields($fields);
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'ProductsAttribsBasic';
        $this->config = self::getHandlerInformation();
        $this->config['handler_does_import'] = true;  //-Indicate that **all** the import-based database manipulations are performed by this handler
        $this->config['keys'] = [
            TABLE_PRODUCTS => [
                'alias' => 'p',
                'products_model' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_MASTER,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS_ATTRIBUTES => [
                'alias' => 'pa',
            ],
            TABLE_PRODUCTS => [
                'alias' => 'p',
            ],
            TABLE_PRODUCTS_OPTIONS => [
                'alias' => 'po',
            ],
            TABLE_PRODUCTS_OPTIONS_VALUES => [
                'alias' => 'pov',
                'no_from_clause' => true,
            ],
        ];
        $this->config['fixed_headers'] = [
            'products_model' => TABLE_PRODUCTS,
            'products_id' => TABLE_PRODUCTS,
            'products_options_id' => TABLE_PRODUCTS_OPTIONS,
            'products_options_type' => TABLE_PRODUCTS_OPTIONS,
            'products_options_name' => TABLE_PRODUCTS_OPTIONS,
        ];
        $this->config['fixed_fields_no_header'] = [
            'products_id',
            'products_options_id',
        ];
        $this->config['export_where_clause'] = 'pa.products_id = p.products_id AND pa.options_id = po.products_options_id AND po.language_id = ' . $this->languages[DEFAULT_LANGUAGE] . ' GROUP BY p.products_model, po.products_options_id, po.products_options_type, po.products_options_name, p.products_id';
        
        if (PRODUCTS_OPTIONS_SORT_ORDER === '0') {
            $options_order_by = 'LPAD(po.products_options_sort_order,11,"0")';
        } else {
            $options_order_by = 'po.products_options_name';
        }
        $this->config['export_order_by_clause'] = "p.products_model, $options_order_by";

        $this->config['additional_headers'] = [
            'v_products_options_values_name' => self::DBIO_FLAG_NONE,
        ];
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
                    $this->saved_data['option'] = [];
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
        if (!isset($this->saved_data['option'], $this->saved_data['option']['products_options_type'], $this->saved_data['option']['products_options_name'])) {
            $message = " product's option fields do not exist.";
        } elseif (!isset($this->saved_data['option_values'])) {
            $message = " product's option value field(s) do not exist.";
        } else {
            $products_options_name = $this->saved_data['option']['products_options_name'];
            $products_options_type = $this->saved_data['option']['products_options_type'];
            $language_id = $this->languages[DEFAULT_LANGUAGE];
            
            $option_check = $db->ExecuteNoCache(
                "SELECT products_options_id FROM " . TABLE_PRODUCTS_OPTIONS . "
                  WHERE products_options_name = '" . $db->prepare_input($products_options_name) . "'
                    AND language_id = $language_id
                    AND products_options_type = " . (int)$products_options_type . " 
                    LIMIT 1"
            );
            if ($option_check->EOF) {
                $message = " no match for option name::option_type::default_language_id ($products_options_name::$products_options_type::$language_id).";
            } else {
                $products_options_id = $option_check->fields['products_options_id'];

                // -----
                // Check to see that the submitted option values' names are present in
                // the database and associated with the option named.  If so, add the
                // option-value's options_values_id to the list of combinations to be
                // added for the product and remove that entry from the options' values'
                // names-array.
                //
                // If the array of option-value-names submitted still has entries when
                // this loop completes, that implies that at least one option-value named
                // is not valid for the supplied option name.
                //
                $options_values_check = $db->ExecuteNoCache(
                    "SELECT pov.products_options_values_id, pov.products_options_values_name
                       FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov, " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " pov2po
                      WHERE pov2po.products_options_id = $products_options_id
                        AND pov.language_id = $language_id
                        AND pov.products_options_values_id = pov2po.products_options_values_id"
                );
                $options_values_names = explode('^', $this->saved_data['option_values']);
                $options_values_ids = [];
                foreach ($options_values_check as $next_value) {
                    $value_index = array_search($next_value['products_options_values_name'], $options_values_names);
                    if ($value_index !== false) {
                        $options_values_ids[] = $next_value['products_options_values_id'];
                        unset($options_values_names[$value_index]);
                    }
                    if (count($options_values_names) === 0) {
                        break;
                    }
                }

                // -----
                // If there were options' values' names that are not currently present in the
                // database and associated with the selected option, the record cannot be imported.
                //
                if (count($options_values_names) !== 0) {
                    $values_not_found = implode(', ', $options_values_names);
                    $message = " one or more option-values ($values_not_found) were either not present in the default language or are not associated with the products_options_id of $products_options_id.";
                // -----
                // Otherwise, loop through each of the options_values_id's associated
                // with the current import record.  If any are not currently recorded
                // for the current product, add them.
                //
                } else {
                    $products_id = $this->key_fields['products_id'];
                    $attributes_insert_sql = [
                        'products_id' => [
                            'value' => $products_id,
                            'type' => 'integer'
                        ],
                        'options_id' => [
                            'value' => $products_options_id,
                            'type' => 'integer'
                        ],
                        'options_values_id' => [
                            'value' => 0,
                            'type' => 'integer'
                        ],
                    ];
                    foreach ($options_values_ids as $next_values_id) {
                        $check = $db->ExecuteNoCache(
                            "SELECT products_attributes_id
                               FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                              WHERE products_id = $products_id
                                AND options_id = $products_options_id
                                AND options_values_id = $next_values_id
                              LIMIT 1"
                        );
                        if ($check->EOF) {
                            $attributes_insert_sql['options_values_id']['value'] = $next_values_id;
                            $attrib_insert_query = $this->importBuildSqlQuery(TABLE_PRODUCTS_ATTRIBUTES, '', $attributes_insert_sql, '', true, true);
                            if ($this->operation !== 'check') {
                                $db->Execute($attrib_insert_query);
                            }
                        }
                    }
                    $this->record_status = 'processed';
                }
            }
        }
        if ($message !== '') {
            $this->record_status = false;
            $this->debugMessage("[*] Attributes not inserted at line number " . $this->stats['record_count'] . "; $message", self::DBIO_WARNING);
        }
    }

    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        return ($table_name === TABLE_PRODUCTS) ? false : parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause, $is_override, $is_insert);
    }
}  //-END class DbIoProductsAttribsBasicHandler
