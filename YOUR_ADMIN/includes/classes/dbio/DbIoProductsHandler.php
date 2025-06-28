<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.1.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart product import/export.
//
class DbIoProductsHandler extends DbIoHandler
{
    const DBIO_COMMAND_ADD    = 'ADD';      //-Forces the current product to be added, even if the model already exists.
    const DBIO_COMMAND_LINK   = 'LINK';     //-Links the product to the category specified by v_categories_name
    const DBIO_COMMAND_MOVE   = 'MOVE';     //-Moves the product to the category specified by v_categories_name
    const DBIO_COMMAND_UNLINK = 'UNLINK';   //-Unlinks the product from the specified v_categories_name, so long as it's not the current master_categories_id.

    protected bool $categories_name_found;
    protected bool $import_meta_tags_post_process;
    protected bool $import_can_insert;

    public static function getHandlerInformation()
    {
        global $db;
        DbIoHandler::loadHandlerMessageFile('Products'); 
        return [
            'version' => '2.1.0',
            'handler_version' => '1.6.0',
            'include_header' => true,
            'export_only' => false,
            'allow_export_customizations' => true,
            'description' => DBIO_PRODUCTS_DESCRIPTION,
        ];
    }

    public static function getHandlerExportFilters()
    {
        global $db;

        $manufacturers_options = [];
        $manufacturers_info = $db->Execute(
            "SELECT manufacturers_id as `id`, manufacturers_name as `text`
               FROM " . TABLE_MANUFACTURERS . "
              ORDER BY manufacturers_name ASC"
        );
        foreach ($manufacturers_info as $next_manufacturer) {
            $manufacturers_options[] = $next_manufacturer;
        }
        unset($manufacturers_info);

        $status_options = [
            [
                'id' => 'all',
                'text' => DBIO_PRODUCTS_TEXT_STATUS_ALL
            ],
            [
                'id' => '1',
                'text' => DBIO_PRODUCTS_TEXT_STATUS_ENABLED
            ],
            [
                'id' => '0',
                'text' => DBIO_PRODUCTS_TEXT_STATUS_DISABLED
            ],
        ];

        $categories_options = zen_get_category_tree();
        unset($categories_options[0]);

        $export_filters['products_filters'] = [
            'type' => 'array',
            'label' => DBIO_PRODUCTS_FILTERS_LABEL,
            'fields' => [
                'products_status' => [
                    'type' => 'dropdown',
                    'dropdown_options' => $status_options,
                    'label' => DBIO_PRODUCTS_STATUS_LABEL,
                ],
            ],
        ];
        if (count($manufacturers_options) > 0) {
            $export_filters['products_filters']['fields']['products_manufacturers'] = [
                'type' => 'dropdown_multiple',
                'dropdown_options' => $manufacturers_options,
                'label' => DBIO_PRODUCTS_MANUFACTURERS_LABEL,
            ];
        }
        $export_filters['products_filters']['fields']['products_categories'] = [
            'type' => 'dropdown_multiple',
            'dropdown_options' => array_values($categories_options),
            'label' => DBIO_PRODUCTS_CATEGORIES_LABEL,
        ];
        return $export_filters;
    }

    // -----
    // This function, called at the beginning of an export operation, gives the handler an opportunity to perform
    // some special checks.  For this handler, that's the gathering of the language-specific elements in the 
    // 'products_description' and 'meta_tags_products_description' tables.
    // 
    public function exportInitialize($language = 'all') 
    {
        $initialized = parent::exportInitialize($language);
        if ($initialized) {
            if ($this->where_clause !== '') {
                $this->where_clause .= ' AND ';
            }
            $export_language = ($this->export_language == 'all') ? $this->languages[$this->first_language_code] : $this->languages[$this->export_language];
            $this->where_clause .= "p.products_id = pd.products_id AND pd.language_id = $export_language";
            $this->order_by_clause .= 'p.products_id ASC';

            if (isset($this->customized_fields) && is_array($this->customized_fields)) {
                $customized_description_fields = [];
                $customized_metatags_fields = [];
                $key_fields = [
                    'products_id',
                    'products_model'
                ];
                foreach ($this->customized_fields as $current_field) {
                    if (in_array($current_field, $key_fields)) {
                        continue;
                    }
                    if (isset($this->tables[TABLE_PRODUCTS_DESCRIPTION]['fields'][$current_field])) {
                        $customized_description_fields[] = $current_field;
                    }
                    if (isset($this->tables[TABLE_META_TAGS_PRODUCTS_DESCRIPTION]['fields'][$current_field])) {
                        $customized_metatags_fields[] = $current_field;
                    }
                }
                if (count($customized_description_fields) === 0) {
                    $this->saved_data['products_description_sql'] = '';
                } else {
                    $description_fields = implode(', ', $customized_description_fields);
                    $this->saved_data['products_description_sql'] =
                        "SELECT $description_fields
                           FROM " . TABLE_PRODUCTS_DESCRIPTION . "
                          WHERE products_id = %u
                            AND language_id = %u
                          LIMIT 1";
                }
                if (count($customized_metatags_fields) === 0) {
                    $this->saved_data['products_metatags_sql'] = '';
                } else {
                    $metatags_fields = implode(', ', $customized_metatags_fields);
                    $this->saved_data['products_metatags_fields'] = $customized_metatags_fields;
                    $this->saved_data['products_metatags_sql'] =
                        "SELECT $metatags_fields
                           FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                          WHERE products_id = %u
                            AND language_id = %u
                          LIMIT 1";
                }
            } else {
                $this->saved_data['products_description_sql'] =
                    "SELECT *
                       FROM " . TABLE_PRODUCTS_DESCRIPTION . "
                      WHERE products_id = %u
                        AND language_id = %u
                      LIMIT 1";
                $this->saved_data['products_description_last_field'] = $this->getTableLastFieldName(TABLE_PRODUCTS_DESCRIPTION);
                
                $this->saved_data['products_metatags_sql'] =
                    "SELECT *
                       FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                      WHERE products_id = %u
                        AND language_id = %u
                      LIMIT 1";
                $this->saved_data['products_metatags_last_field'] = $this->getTableLastFieldName(TABLE_META_TAGS_PRODUCTS_DESCRIPTION);
            }
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
    public function exportFinalizeInitialization()
    {
        $this->debugMessage('Products::exportFinalizeInitialization. POST variables:' . print_r($_POST, true));

        // -----
        // Check to see if any of this handler's filter variables have been set.  If set, check the values and then
        // update the where_clause for the to-be-issued SQL query for the export.
        //
        if (isset($_POST['products_status']) && $_POST['products_status'] !== 'all') {
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . 'p.products_status = ' . (int)$_POST['products_status'];
        }
        if (isset($_POST['products_manufacturers']) && is_array($_POST['products_manufacturers'])) {
            $manufacturers_list = implode(',', $_POST['products_manufacturers']);
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . "p.manufacturers_id IN ($manufacturers_list)";
        }

        // -----
        // If the admin has requested a category-filter on the export, we'll return all products in the selected
        // category-list and any of those categories' sub-categories.
        //
        if (isset($_POST['products_categories']) && is_array($_POST['products_categories'])) {
            $products_categories = [];
            foreach ($_POST['products_categories'] as $categories_id) {
                $products_categories = $this->getSubCategories($categories_id, $products_categories);
            }
            $categories_list = implode(',', array_unique($products_categories));
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . "p.master_categories_id IN ($categories_list)";
        }
        return true;
    }

    protected function getSubCategories($categories_id, $products_categories)
    {
        global $db;

        $subcats = $db->Execute(
            "SELECT categories_id
               FROM " . TABLE_CATEGORIES . "
              WHERE parent_id = $categories_id"
        );
        $products_categories[] = $categories_id;
        foreach ($subcats as $next_subcat) {
            $products_categories = $this->getSubCategories($next_subcat['categories_id'], $products_categories);
        }
        return $products_categories;
    }

    public function exportPrepareFields(array $fields)
    {
        $fields = parent::exportPrepareFields($fields);
        $products_id = $fields['products_id'];

        // -----
        // Starting with v1.5.0, the base DbIoHandler class' processing has added an empty element to the
        // end of the $fields array to hold any to-be-imported DbIo command.  The command, if present
        // is always the very last column of an export.
        //
        // We'll pop (i.e. remove) that last element and re-add it once the "special" products' export
        // fields are inserted.
        //
        if (version_compare(parent::getHandlerVersion(), '1.5.0', '>=')) {
            array_pop($fields);
        }

        $tax_class_id = 0;
        if (isset ($fields['products_tax_class_id'])) {
            $tax_class_id = $fields['products_tax_class_id'];
            unset ($fields['products_tax_class_id']);
        }

        $first_language_code = $this->first_language_code;
        global $db;

        if ($this->export_language === 'all') {
            $this->debugMessage('Products::exportPrepareFields, language = ' . $this->export_language . ', default language = ' . $first_language_code . ', saved data: ' . print_r($this->saved_data, true) . ', languages: ' . print_r($this->languages, true));

            // -----
            // Check for, and insert, any additional language fields for the 'products_description' table.  The
            // processing (and result) is a bit different if we're exporting **all** product-related fields
            // than if we're using a customized template (i.e. a subset of all possible fields).
            //
            // - For an export that gathers **all** fields, each additional language's fields are be placed
            //   (as a block) after the previous language's 'block'.
            // - For a customized export, each additional language's field is placed directly after the previous language's
            //   field.
            //
            if ($this->saved_data['products_description_sql'] !== '') {
                $previous_language_code = '';
                $language_fields = [];
                foreach ($this->languages as $language_code => $language_id) {
                    if ($language_code != $first_language_code) {
                        $description_info = $db->Execute(sprintf($this->saved_data['products_description_sql'], $products_id, $language_id));
                        if (!$description_info->EOF) {
                            $encoded_fields = $this->exportEncodeData($description_info->fields);
                            foreach ($encoded_fields as $field_name => $field_value) {
                                if ($field_name != 'products_id' && $field_name != 'language_id') {
                                    if (!isset($this->customized_fields)) {
                                        $language_fields[$field_name . '_' . $language_code] = $field_value;
                                    } else {
                                        $fields = $this->insertCustomizedLanguageField($fields, $field_name, $field_value, "_$language_code", $previous_language_code);
                                    }
                                }
                            }
                        }
                        $previous_language_code = "_$language_code";
                    }
                }
                if (count($language_fields) !== 0) {
                    $fields = $this->insertLanguageFields($fields, $language_fields, $this->saved_data['products_description_last_field']);
                }
            }

            // -----
            // Product-related metatags 'language' fields are to be included.  The 'exportInitialize' method
            // has set some information into the 'saved_data' variable:
            //
            // 1) products_metatags_sql.
            //      If this is an empty string, no metatags output is requested.
            // 2) products_metatags_fields.
            //      This value, **if set** is an array of customized metatags fields to be included.  If the value
            //      is not set, then *all* metatag fields are to be included.
            //
            // If no metatags have been configured for a product, the default value for the requested fields is
            // included for the export.
            //
            if ($this->saved_data['products_metatags_sql'] !== '') {
                $previous_language_code = '';
                $language_fields = [];
                $include_all_fields = !isset($this->saved_data['products_metatags_fields']);

                // -----
                // The meta-tags language-related fields are a bit different, since they might not be present!
                //
                foreach ($this->languages as $language_code => $language_id) {
                    if ($language_code !== $first_language_code) {
                        $metatags_info = $db->Execute(sprintf($this->saved_data['products_metatags_sql'], $products_id, $language_id));
                        if (!$metatags_info->EOF) {
                            $metatags_fields = $metatags_info->fields;
                        } else {
                            $metatags_fields = [];
                            foreach ($this->tables[TABLE_META_TAGS_PRODUCTS_DESCRIPTION]['fields'] as $key => $values) {
                                if ($include_all_fields === true || in_array($key, $this->saved_data['products_metatags_fields'])) {
                                    $default_value = $values['default'];
                                    $metatags_fields[$key] = ($default_value === null) ? null : trim($default_value, "'");
                                }
                            }
                        }
                        $encoded_fields = $this->exportEncodeData($metatags_fields);
                        foreach ($encoded_fields as $field_name => $field_value) {
                            if ($field_name !== 'products_id' && $field_name !== 'language_id') {
                                if (!isset($this->customized_fields)) {
                                    $language_fields[$field_name . '_' . $language_code] = $field_value;
                                } else {
                                    $fields = $this->insertCustomizedLanguageField($fields, $field_name, $field_value, "_$language_code", $previous_language_code);
                                }
                            }
                        }
                        $previous_language_code = "_$language_code";
                    }
                }
                if (count($language_fields) !== 0) {
                    $fields = $this->insertLanguageFields($fields, $language_fields, $this->saved_data['products_metatags_last_field']);
                }
            }
        }

        // -----
        // Add the programmatically-generated fields, if present for the export (they might be either not
        // required or in a different order if the export's using a customized template).  These need to
        // be added to the export **in the order specified by the headers** to ensure that the data-value
        // columns line up properly to their associated headings, e.g. if the manufacturer's name is to be
        // included after the categories' name.
        //
        foreach ($this->headers as $this_column) {
            switch ($this_column) {
                case 'v_manufacturers_name':
                    // -----
                    // Add the manufacturer's name to the export, if enabled.
                    //
                    if (!($this->config['additional_headers']['v_manufacturers_name'] & self::DBIO_FLAG_NO_EXPORT)) {
                        $fields = $this->insertAtCustomizedPosition($fields, 'manufacturers_name', zen_get_products_manufacturers_name($products_id));
                    }
                    break;

                case 'v_tax_class_title':
                    // -----
                    // Add the tax-class title to the export, if enabled.
                    //
                    if (!($this->config['additional_headers']['v_tax_class_title'] & self::DBIO_FLAG_NO_EXPORT)) {
                        $tax_class_info = $db->Execute(
                            "SELECT tax_class_title 
                               FROM " . TABLE_TAX_CLASS . "
                              WHERE tax_class_id = $tax_class_id 
                              LIMIT 1"
                        );
                        $fields = $this->insertAtCustomizedPosition($fields, 'tax_class_title', ($tax_class_info->EOF) ? '' : $tax_class_info->fields['tax_class_title']);
                    }
                    break;

                case 'v_categories_name':
                    // -----
                    // Add the product's category-path to the export, if enabled.
                    //
                    if (!($this->config['additional_headers']['v_categories_name'] & self::DBIO_FLAG_NO_EXPORT)) {
                        $cPath_array = explode('_', zen_get_product_path($products_id));
                        $default_language_id = $this->languages[DEFAULT_LANGUAGE];
                        $categories_name = '';
                        foreach ($cPath_array as $next_category_id) {
                            $category_info = $db->Execute(
                                "SELECT categories_name 
                                   FROM " . TABLE_CATEGORIES_DESCRIPTION . "
                                  WHERE categories_id = $next_category_id
                                    AND language_id = $default_language_id
                                  LIMIT 1"
                            );
                            $categories_name .= (($category_info->EOF) ? self::DBIO_UNKNOWN_VALUE : $category_info->fields['categories_name']) . '^';
                        }
                        $fields = $this->insertAtCustomizedPosition($fields, 'categories_name', $this->exportEncodeData(dbio_substr($categories_name, 0, -1)));
                    }
                    break;

                case 'v_products_link':
                    // -----
                    // Add the product's storefront link to the export, if enabled.
                    //
                    if (!($this->config['additional_headers']['v_products_link'] & self::DBIO_FLAG_NO_EXPORT)) {
                        $products_type_handler = zen_get_handler_from_type(zen_get_products_type($products_id)) . '_info';
                        $fields = $this->insertAtCustomizedPosition($fields, 'products_link', zen_catalog_href_link($products_type_handler, 'products_id=' . $products_id));
                    }
                    break;

                default:
                    break;
            }
        }

        // -----
        // Now, add an empty column at the very end to hold the 'v_dbio_command' when the CSV is imported.
        //
        $fields[] = '';

        return $fields;
    }

    // -----
    // Since this handler joins to the 'meta_tags_products_description' language-based table, we'll need
    // to override the 'FROM' clause for the to-be-generated SQL to gather *only* the records for the
    // first/only selected language.  Otherwise, duplicated (and incorrect) records will be exported.
    //
    public function exportGetSql($sql_limit = '')
    {
        if (!isset($this->export_language) || !isset($this->select_clause)) {
            dbioLogError('Export aborted: DbIo export sequence error; not previously initialized.');
        }

        $language_code = ($this->export_language === 'all') ? $this->first_language_code : $this->export_language;
        $language_id = $this->languages[$language_code];
        $this->from_clause =
            TABLE_PRODUCTS . " AS p
                INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " AS pd
                    ON pd.products_id = p.products_id
                LEFT JOIN " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " AS mtpd
                    ON mtpd.products_id = p.products_id
                   AND mtpd.language_id = $language_id";

        return parent::exportGetSql($sql_limit);
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

    // -----
    // Called as the very last step of an import's header processing (so long as no previous error), giving this handler
    // the opportunity to inspect the input header information to make sure that any to-be-imported records contain
    // sufficient fields to perform a product's insertion, if needed.
    //
    // For an import to 'create' a product, we'll need:
    // - A v_categories_name.
    // - At least one of:
    //   - v_products_name_xx (in at least one of the store's languages).
    //   - v_products_model
    //   - v_products_url_xx (in at least one of the store's languages).
    //   - v_products_description_xx (in at least one of the store's languages).
    //
    // On entry, the base handler's importGetHeader processing has initialized the class' $headers array.  We'll set
    // a processing flag (used in importCheckKeyValue) to indicate whether/not sufficient fields are present for
    // a valid product insert.
    //
    // Take this opportunity to determine whether the to-be-imported record includes any elements
    // in the `meta_tags_products_description` table.  If no such fields are supplied, there won't be a need
    // to perform the 'clean-up' to keep that table's records 'in sync' with the main product.
    //
    // The class variable categories_name_found, on exit, indicates whether/not the v_categories_name column
    // is present.  This value is used by importHandleDbIoCommand's processing of 'UNLINK', 'LINK' or 'MOVE' commands.
    //
    protected function importFinalizeHeader()
    {
        $this->categories_name_found = false;
        $required_fields_found = false;
        foreach ($this->headers as $current_header) {
            switch ($current_header) {
                case 'categories_name':
                    $this->categories_name_found = true;
                    break;
                case 'products_model':
                case 'products_name':
                case 'products_url':
                case 'products_description':
                    $required_fields_found = true;
                    break;
            }
        }
        $this->import_can_insert = ($this->categories_name_found === true && $required_fields_found === true);

        // -----
        // If the import includes product-specific meta-tags, set an indication for use by
        // importRecordPostProcess.  The meta-tags' recording is 'tricky' for stores with
        // multiple languages, since an empty record needs to be created for any language that
        // doesn't have those records, so long as one language in the store has a non-empty
        // record.
        //
        $metatags_fields_included = false;
        foreach ($this->table_names as $table_name) {
            if ($table_name == TABLE_META_TAGS_PRODUCTS_DESCRIPTION) {
                $metatags_fields_included = true;
                break;
            }
        }
        $this->import_meta_tags_post_process = (count($this->languages) > 1 && $metatags_fields_included);
        return true;
    }

    // -----
    // For a 'full' export of the products' information, the store's non-default language fields are
    // inserted into the export after the specified field.
    //
    protected function insertLanguageFields($fields, $language_fields, $insert_after_field)
    {
        $keys = array_keys($fields);
        $fields_updated = [];
        for ($i = 0, $n = count($keys); $i < $n; $i++) {
            $key = $keys[$i];
            $fields_updated[$key] = $fields[$key];
            if ($key === $insert_after_field) {
                break;
            }
        }
        foreach ($language_fields as $key => $value) {
            $fields_updated[$key] = $value;
        }
        for ($i++; $i < $n; $i++) {
            $key = $keys[$i];
            $fields_updated[$key] = $fields[$key];
        }
        return $fields_updated;
    }

    protected function insertCustomizedLanguageField($fields, $field_name, $field_value, $language_code, $previous_language_code)
    {
        $field_keys = array_keys($fields);
        $field_position = array_search($field_name . $previous_language_code, $field_keys);
        if ($field_position !== false) {
            $fields_updated = [];
            for ($i = 0; $i <= $field_position; $i++) {
                $fields_updated[$field_keys[$i]] = $fields[$field_keys[$i]];
            }
            $fields_updated[$field_name . $language_code] = $field_value;
            for ($n = count($fields); $i < $n; $i++) {
                $fields_updated[$field_keys[$i]] = $fields[$field_keys[$i]];
            }
            $fields = $fields_updated;
        }
        return $fields;
    }

    // -----
    // This function, called during exportInitialize, determines the last field name in the
    // supplied table.  This establishes the 'anchor' position for the insertion of any additional
    // languages that might be used in the store.
    //
    protected function getTableLastFieldName($table_name)
    {
        $keys = array_keys($this->tables[$table_name]['fields']);
        return end($keys);
    }

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'Products';
        $this->config = self::getHandlerInformation ();
        $this->config['supports_dbio_commands'] = true;
        $this->config['keys'] = [
            TABLE_PRODUCTS => [
                'alias' => 'p',
                'capture_key_value' => true,
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
                'products_model' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED | self::DBIO_KEY_IS_ALTERNATE,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS => [
                'alias' => 'p',
                'io_field_overrides' => [
                    'manufacturers_id' => false,
                    'products_tax_class_id' => 'no-header',
                    'master_categories_id' => false,
                ],
                'join_clause' =>
                    "LEFT JOIN " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " AS mtpd
                        ON mtpd.products_id = p.products_id"
            ], 
            TABLE_PRODUCTS_DESCRIPTION => [
                'alias' => 'pd',
                'language_field' => 'language_id',
                'io_field_overrides' => [
                    'products_id' => false,
                    'language_id' => false,
                ],
            ],
            TABLE_META_TAGS_PRODUCTS_DESCRIPTION => [
                'alias' => 'mtpd',
                'language_field' => 'language_id',
                'no_from_clause' => true,
                'io_field_overrides' => [
                    'products_id' => false,
                    'language_id' => false,
                ],
            ],
        ];
        $this->config['additional_headers'] = [
            'v_manufacturers_name' => self::DBIO_FLAG_NONE,
            'v_tax_class_title' => self::DBIO_FLAG_FIELD_SELECT,
            'v_categories_name' => self::DBIO_FLAG_NONE,
            'v_products_link' => self::DBIO_FLAG_NONE,
        ];
        $this->config['additional_header_select'] = [
            'v_tax_class_title' => 'p.products_tax_class_id'
        ];
    }

    // -----
    // This function, called for header-element being imported, can return one of three values:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importHeaderFieldCheck($field_name)
    {
        $field_status = self::DBIO_IMPORT_OK;
        switch ($field_name) {
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
    // This function, called by the base DbIoHandler class when a non-blank v_dbio_command field is found in the
    // current import-record:
    //
    // - ADD:    Forces the current product-record to be inserted.
    // - REMOVE: Removes a product from the database.
    // - UNLINK: 'Unlinks' a product from a category **other than** the product's current master-categories-id.
    //
    protected function importHandleDbIoCommand($command, $data)
    {
        global $db;

        $continue_line_import = false;
        $command = dbio_strtoupper($command);

        // -----
        // Operation performed based on the command requested.
        //
        switch ($command) {
            // -----
            // ADD: The current CSV record's import can continue, forced as an insert operation ... so long as
            // a valid 'products_model' value is included in the record.
            //
            case self::DBIO_COMMAND_ADD:
                $continue_line_import = true;
                $this->import_is_insert = true;
                $this->debugMessage("Forcing ADD of product at line #" . $this->stats['record_count'] . ', via DbIo command', self::DBIO_STATUS);

                // -----
                // Retrieve the products-model for the current record; it must be supplied and non-blank.
                //
                $products_model = $this->importGetFieldValue('products_model', $data);
                if ($products_model === false || $products_model === '') {
                    $continue_line_import = false;
                    $this->debugMessage("Product ADD disallowed at line #" . $this->stats['record_count'] . "; products_model either not supplied or empty.", self::DBIO_ERROR);
                // -----
                // If the products-model **is** supplied and, by configuration, models cannot be duplicated, check to see that the current
                // model doesn't already exist.
                //
                } elseif (!defined('DBIO_PRODUCTS_ALLOW_DUPLICATE_MODELS') || DBIO_PRODUCTS_ALLOW_DUPLICATE_MODELS === 'No') {
                    $model_check = $db->Execute(
                        "SELECT products_id
                           FROM " . TABLE_PRODUCTS . "
                          WHERE products_model = '" . $db->prepareInput($products_model) . "'
                          LIMIT 1"
                    );
                    if (!$model_check->EOF) {
                        $continue_line_import = false;
                        $this->debugMessage("Product ADD disallowed at line #" . $this->stats['record_count'] . "; products_model ($products_model) already exists.", self::DBIO_ERROR);
                    }
                }
                break;

            // -----
            // REMOVE: Removes a product from the database.  The function zen_remove_product and the product itself must 
            // exist for the operation to complete. No additional CSV-record processing required.
            //
            case self::DBIO_COMMAND_REMOVE:
                if (!function_exists('zen_remove_product')) {
                    $this->debugMessage("Product not removed; missing zen_remove_product function.", self::DBIO_ERROR);
                } elseif ($this->import_is_insert === true) {
                    $this->debugMessage("Product not removed at line #" . $this->stats['record_count'] . "; it does not exist.", self::DBIO_WARNING);
                } else {
                    $this->debugMessage("Removing product ID #" . $this->key_fields['products_id'], self::DBIO_STATUS);
                    if ($this->operation !== 'check') {
                        zen_remove_product($this->key_fields['products_id']);
                    }
                }
                break;

            // -----
            // LINK: Links the product to the category associated with the v_category_name
            // so long as:
            //
            // 1) The category exists.
            // 2) The category doesn't have sub-categories.
            //
            case self::DBIO_COMMAND_LINK:
                if ($this->import_is_insert === true) {
                    $this->debugMessage("Product not linked at line #" . $this->stats['record_count'] . "; product does not exist.", self::DBIO_ERROR);
                    break;
                }
                if ($this->categories_name_found === false) {
                    $this->debugMessage("Product not linked at line #" . $this->stats['record_count'] . "; no 'v_categories_name' column present.", self::DBIO_ERROR);
                    break;
                }

                $category_id = $this->getCategoriesIdFromName($data, 'Product not linked');
                if ($category_id === false) {
                    break;
                }

                if (zen_has_category_subcategories($category_id) === true) {
                    $this->debugMessage("Product not linked at line #" . $this->stats['record_count'] . "; category_id #$category_id has sub-categories.", self::DBIO_ERROR);
                    break;
                }

                $continue_line_import = true;
                $this->debugMessage("Linking the product at line #" . $this->stats['record_count'] . " to category #$category_id.", self::DBIO_INFORMATIONAL);
                if ($this->operation !== 'check') {
                    $products_id = (int)$this->importGetFieldValue('products_id', $data);
                    zen_link_product_to_category($products_id, $category_id);
                }
                break;

            // -----
            // MOVE: Changes the product's master_categories_id to the category associated with the v_category_name
            // so long as:
            //
            // 1) The category exists.
            // 2) The category doesn't have sub-categories.
            //
            case self::DBIO_COMMAND_MOVE:
                if ($this->import_is_insert === true) {
                    $this->debugMessage("Product master-categories-id unchanged at line #" . $this->stats['record_count'] . "; product does not exist.", self::DBIO_ERROR);
                    break;
                }
                if ($this->categories_name_found === false) {
                    $this->debugMessage("Product master-categories-id unchanged at line #" . $this->stats['record_count'] . "; no 'v_categories_name' column present.", self::DBIO_ERROR);
                    break;
                }

                $category_id = $this->getCategoriesIdFromName($data, 'Product master-categories-id unchanged');
                if ($category_id === false) {
                    break;
                }

                if (zen_has_category_subcategories($category_id) === true) {
                    $this->debugMessage("Product master-categories-id unchanged at line #" . $this->stats['record_count'] . "; category_id #$category_id has sub-categories.", self::DBIO_ERROR);
                    break;
                }

                $continue_line_import = true;
                $this->debugMessage("Changing product's master_categories_id at line #" . $this->stats['record_count'] . " to category #$category_id.", self::DBIO_INFORMATIONAL);
                if ($this->operation !== 'check') {
                    $products_id = (int)$this->importGetFieldValue('products_id', $data);
                    $current_master_category = zen_get_products_category_id($products_id);
                    if ($current_master_category == $category_id) {
                        break;
                    }
                    zen_unlink_product_from_category($products_id, $current_master_category);

                    $move_sql =
                        "UPDATE " . TABLE_PRODUCTS . "
                            SET master_categories_id = $category_id
                          WHERE products_id = :key_value0:
                          LIMIT 1";
                    $move_sql = $this->importBindKeyValues($data, $move_sql);
                    $db->Execute($move_sql);
                    zen_link_product_to_category($products_id, $category_id);
                }
                break;

            // -----
            // UNLINK: Unlinks a product from the specified category-tree.  The product and the specified category must both
            // exist and the category must not be the product's master_category_id.  No additional CSV-record processing required.
            //
            case self::DBIO_COMMAND_UNLINK:
                if ($this->import_is_insert === true) {
                    $this->debugMessage("Product not unlinked at line #" . $this->stats['record_count'] . "; product does not exist.", self::DBIO_ERROR);
                    break;
                }
                if ($this->categories_name_found === false) {
                    $this->debugMessage("Product not unlinked at line #" . $this->stats['record_count'] . "; no 'v_categories_name' column present.", self::DBIO_ERROR);
                    break;
                }

                $parent_category = $this->getCategoriesIdFromName($data, 'Product not unlinked');
                if ($parent_category === false) {
                    break;
                }

                $products_sql = "SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = :key_value0: LIMIT 1";
                $products_sql = $this->importBindKeyValues($data, $products_sql);
                $master_cat_info = $db->ExecuteNoCache($products_sql);
                if ($master_cat_info->EOF) {
                    $this->debugMessage("Error retrieving product's master-categories-id ($products_sql).  The UNLINK operation is not performed.", self::DBIO_ERROR);
                    break;
                }
                if ($master_cat_info->fields['master_categories_id'] === $parent_category) {
                    $this->debugMessage("The product at line #" . $this->stats['record_count'] . " was not unlinked from its master_categories_id.", self::DBIO_ERROR);
                    break;
                }

                $unlink_sql = "DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = :key_value0: AND categories_id = $parent_category LIMIT 1";
                $unlink_sql = $this->importBindKeyValues($data, $unlink_sql);
                $log_status = ($this->operation === 'check') ? self::DBIO_WARNING : self::DBIO_INFORMATIONAL;
                $this->debugMessage("Unlinking product at line #" . $this->stats['record_count'] . " from category #$parent_category.", $log_status);
                if ($this->operation !== 'check') {
                    $db->Execute($unlink_sql);
                }
                break;

            default:
                $this->debugMessage("Unrecognized command ($command) found at line #" . $this->stats['record_count'] . "; the operation was not performed.", self::DBIO_ERROR);
                break;
        }
        return $continue_line_import;
    }

    // -----
    // This function returns the categories_id associated with the 'v_categories_name' specified, returning either (bool)false
    // if there is no such category tree or the categories_id otherwise.
    //
    protected function getCategoriesIdFromName(array $data, string $message_heading): string|false
    {
        global $db;

        $parent_category = 0;
        $categories_name_ok = true;
        $language_id = $this->languages[DEFAULT_LANGUAGE];
        $categories = explode('^', $this->importGetFieldValue('categories_name', $data));
        foreach ($categories as $current_category_name) {
            $category_info_sql =
                "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c
                        INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd
                            ON cd.categories_id = c.categories_id
                           AND cd.language_id = $language_id
                  WHERE c.parent_id = $parent_category 
                    AND cd.categories_name = :categories_name:
                  LIMIT 1";
            $category_info = $db->Execute(
                $db->bindVars($category_info_sql, ':categories_name:', $current_category_name, 'string'), false, false, 0, true
            );
            if ($category_info->EOF) {
                $this->debugMessage("$message_heading at line #" . $this->stats['record_count'] . "; category '$current_category_name' not present in the specified category-path.", self::DBIO_ERROR);
                $categories_name_ok = false;
                break;
            } else {
                $parent_category = $category_info->fields['categories_id'];
            }
        }
        return ($categories_name_ok === false) ? false : $parent_category;
    }

    // -----
    // This function, called at the start of each record's import, gives the handler the opportunity to provide a multi-key
    // method for the import.  The base DbIoHandler processing (based on this handler's configuration) has attempted to
    // locate a UNIQUE record based on either a products_id or products_model match.
    //
    // It's the responsibility of this handler to deal with the case where multiple database entries are found that match
    // the current record's specification.
    //
    protected function importCheckKeyValue($data)
    {
        global $db;

        // -----
        // If the current import is an update, then we need to see whether the record's keys (products_id, products_model) resulted
        // in a single or multiple set of matches.
        //
        // If multiple records were found with a non-null products_id is present in the current import record, the processing
        // for this record will depend on whether the handler's configuration is set to allow duplicate models to be imported.
        //
        // Note that if this case is detected by the base DbIoHandler class, that class has preset the record to indicate that
        // it's not importable.  It's the responsibility of this function to determine whether that duplication is OK.
        //
        if ($this->import_is_insert === false) {
            if (isset($this->data_key_check)) {
                $products_id = $this->importGetFieldValue('products_id', $data);
                $import_allowed = false;
                if (empty($products_id)) {
                    $this->debugMessage("Multiple records match the products_model, but no products_id specified; the record at line #" . $this->stats['record_count'] . " was not imported.", self::DBIO_WARNING);
                } else {
                    $current_products_model = zen_get_products_model($products_id);
                    $products_model = $this->importGetFieldValue('products_model', $data);
                    if ($products_model != $current_products_model && (!defined('DBIO_PRODUCTS_ALLOW_DUPLICATE_MODELS') || DBIO_PRODUCTS_ALLOW_DUPLICATE_MODELS == 'No')) {
                        $this->debugMessage("Record at line #" . $this->stats['record_count'] . " not imported; products_model ($products_model) exists and cannot, by configuration, be duplicated.", self::DBIO_WARNING);
                    } else {
                        foreach ($this->data_key_check as $next_key) {
                            if ($next_key['products_id'] === $products_id) {
                                $import_allowed = true;
                                $this->key_fields = $next_key;

                                $this->where_clause = $this->importBindKeyValues($data, $this->key_where_clause);
                                $where_clause_elements = explode(' OR ', $this->where_clause);
                                $this->where_clause = $where_clause_elements[0];
                                break;  //- Out of while-loop
                            }
                        }
                        if ($import_allowed === false) {
                            $this->debugMessage("No matching products_id, but multiple records match the products_model so no unique product could be determined for the update.  The record at line #" . $this->stats['record_count'] . " was not imported.", self::DBIO_WARNING);
                        }
                    }
                }
                $this->record_status = $import_allowed;
            }
        // -----
        // Otherwise, the record's been found to be a product-insert.
        //
        } else {
            // -----
            // If the processing by 'importFinalizeHeader' determined that there are insufficient header-fields to create
            // a product's record, set the indication that the associated record can't be imported, with a message
            // to the current admin.
            //
            if ($this->import_can_insert === false) {
                $this->record_status = false;
                $this->debugMessage("Record at line #" . $this->stats['record_count'] . " not imported; insufficient header-fields provided for a product addition.", self::DBIO_WARNING);
            // -----
            // Otherwise, sufficient 'fields' are available, as found in the import's header.  Still need to check that the
            // required fields' data itself is valid for a product insert.
            //
            } else {
                // -----
                // Check to see whether an import is allowed without an associated "ADD" command.  If not and either no
                // v_dbio_command column is present or that column doesn't contain that command, deny the record import.
                //
                if (defined('DBIO_PRODUCTS_INSERT_REQUIRES_COMMAND') && DBIO_PRODUCTS_INSERT_REQUIRES_COMMAND == 'Yes') {
                    if (!isset($this->dbio_command_index) || $data[$this->dbio_command_index] !== self::DBIO_COMMAND_ADD) {
                        $this->record_status = false;
                        $this->debugMessage("Record at line#" . $this->stats['record_count'] . " not imported; v_dbio_command must be set to 'ADD' for a product addition.", self::DBIO_WARNING);
                    }
                }

                // -----
                // To 'insert' (i.e. add) a product, the categories_name field must be non-blank and at least one
                // of the products_model or products_name, products_url or products description (in at least one of the
                // store's languages) must also be non-blank.
                //
                if ($this->record_status === true) {
                    $categories_name = $this->importGetFieldValue('categories_name', $data);
                    if (empty($categories_name)) {
                        $this->record_status = false;
                        $this->debugMessage("Record at line#" . $this->stats['record_count'] . " not imported; 'v_categories_name' must be supplied for a product addition.", self::DBIO_WARNING);
                    } else {
                        $record_ok = false;
                        if (!empty($this->importGetFieldValue('products_model', $data))) {
                            $record_ok = true;
                        } else {
                            foreach ($this->languages as $id => $language_id) {
                                if (!empty($this->importGetLanguageFieldValue('products_name', $language_id, $data))) {
                                    $record_ok = true;
                                    break;
                                }
                                if (!empty($this->importGetLanguageFieldValue('products_description', $language_id, $data))) {
                                    $record_ok = true;
                                    break;
                                }
                                if (!empty($this->importGetLanguageFieldValue('products_url', $language_id, $data))) {
                                    $record_ok = true;
                                    break;
                                }
                            }
                        }
                        $this->record_status = $record_ok;
                    }
                }
            }
        }
        return $this->record_status;
    }

    // -----
    // This function handles any overall record post-processing required for the Products import, specifically
    // making sure that the products' price sorter is run for the just inserted/updated product.
    //
    // If the import for a multi-lingual store includes product-related meta-tags, need some additional checking 
    // to make sure that everything's 'in sync'.
    //
    protected function importRecordPostProcess($products_id)
    {
        global $db;

        $this->debugMessage('Products::importRecordPostProcess (' . json_encode($products_id) . '): ' . $this->data_key_sql . "\n" . print_r($this->key_fields, true), self::DBIO_INFORMATIONAL);

        // -----
        // If processing as an "Import (Check only)", no database operations are performed.
        //
        if ($this->operation === 'check') {
            return;
        }

        // -----
        // Determine the product on which we're operating; for a product-insert, use the $products_id input;
        // otherwise, use the value determined by the product's 'key-field'.
        //
        $pID = ($products_id === false) ? $this->key_fields['products_id'] : $products_id;

        // -----
        // Update the product's price-sorter.
        //
        zen_update_products_price_sorter($pID);

        // -----
        // If the import includes products' metatags entries for a multi-lingual store, need to check that:
        //
        // 1) If a metatags-entry exists for at least one of the store's languages, need to create an empty
        //    entry for any language that doesn't currently have such a record.
        // 2) If **all** metatags table-entries are empty, need to remove all such records (no metatags defined).
        //
        if ($this->import_meta_tags_post_process === true) {
            $check = $db->ExecuteNoCache(
                "SELECT *
                   FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                  WHERE products_id = $pID"
            );
            $languages_found = [];
            $empty_records_found = 0;
            foreach ($check as $next_check) {
                $is_empty_record = (empty($next_check['metatags_title'] . $next_check['metatags_keywords'] . $next_check['metatags_description']));
                $languages_found[$check->fields['language_id']] = $is_empty_record;
                if ($is_empty_record === true) {
                    $empty_records_found++;
                }
            }
            $num_languages = count($this->languages);

            // -----
            // If all metatags table entries are empty for the current product, remove them all.
            //
            if ($num_languages === $empty_records_found) {
                $this->debugMessage("[*] Removing empty meta-tags records for products_id = $pID.", self::DBIO_INFORMATIONAL);
                if ($this->operation !== 'check') {
                    $db->Execute(
                        "DELETE FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . " WHERE products_id = $pID"
                    );
                }
            // -----
            // Otherwise, if the number of meta-tag table entries doesn't match the number of languages, insert
            // an empty table-record for any 'empty' languages.
            //
            } elseif ($num_languages !== count($languages_found)) {
                foreach ($this->languages as $code => $language_id) {
                    if (!isset($languages_found[$language_id])) {
                        $this->debugMessage("[*] Inserting empty meta-tag record for products_id = $pID, language_code = '$code'.", self::DBIO_INFORMATIONAL);
                        if ($this->operation !== 'check') {
                            $db->Execute(
                                "INSERT INTO " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                                    (products_id, language_id, metatags_title, metatags_keywords, metatags_description)
                                 VALUES
                                    ($pID, $language_id, '', '', '')"
                            );
                        }
                    }
                }
            }
        }
    }

    protected function importAddField($table_name, $field_name, $field_value)
    {
        $this->debugMessage("Products::importAddField ($table_name, $field_name, $field_value)");
        global $db;

        switch ($table_name) {
            case TABLE_PRODUCTS:
                if ($this->import_is_insert === true) {
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
                parent::importAddField($table_name, $field_name, $field_value);
                break;
            case self::DBIO_SPECIAL_IMPORT:
                switch ($field_name) {
                    case 'manufacturers_name':
                        $manufacturers_id = 0;
                        if (!empty ($field_value)) {
                            $manufacturer_check_sql = "SELECT manufacturers_id FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_name = :manufacturer_name: LIMIT 1";
                            $manufacturer_check = $db->ExecuteNoCache($db->bindVars($manufacturer_check_sql, ':manufacturer_name:', $field_value, 'string'));
                            if (!$manufacturer_check->EOF) {
                                $manufacturers_id = $manufacturer_check->fields['manufacturers_id'];
                            } else {
                                $this->debugMessage("[*] Import, creating database entry for manufacturer named \"$field_value\"", self::DBIO_ACTIVITY | self::DBIO_STATUS);
                                if ($this->operation !== 'check') {
                                    $sql_data_array = [
                                        [
                                            'fieldName' => 'manufacturers_name',
                                            'value' => $field_value,
                                            'type' => 'string',
                                        ],
                                        [
                                            'fieldName' => 'date_added',
                                            'value' => 'now()',
                                            'type' => 'noquotestring',
                                        ],
                                    ];
                                    $db->perform(TABLE_MANUFACTURERS, $sql_data_array);
                                    $manufacturers_id = $db->Insert_ID();

                                    foreach ($this->languages as $language_code => $language_id) {
                                        $sql_data_array = [
                                            [
                                                'fieldName' => 'manufacturers_id',
                                                'value' => $manufacturers_id,
                                                'type' => 'integer',
                                            ],
                                            [
                                                'fieldName' => 'languages_id',
                                                'value' => $language_id,
                                                'type' => 'integer',
                                            ],
                                        ];
                                        $db->perform (TABLE_MANUFACTURERS_INFO, $sql_data_array);
                                    }
                                }
                            }
                        }
                        $this->import_sql_data[TABLE_PRODUCTS]['manufacturers_id'] = [
                            'value' => $manufacturers_id,
                            'type' => 'integer',
                        ];
                        break;
                    case 'tax_class_title':
                        if (!empty($field_value)) {
                            $tax_class_check_sql = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . " WHERE tax_class_title = :tax_class_title: LIMIT 1";
                            $tax_class_check = $db->Execute($db->bindVars($tax_class_check_sql, ':tax_class_title:', $field_value, 'string'));
                            if ($tax_class_check->EOF) {
                                $this->debugMessage('[*] Import line #' . $this->stats['record_count'] . ", undefined tax_class_title ($field_value).  Defaulting product to untaxed.", self::DBIO_WARNING);
                            }
                            $tax_class_id = ($tax_class_check->EOF) ? 0 : $tax_class_check->fields['tax_class_id'];
                            $this->import_sql_data[TABLE_PRODUCTS]['products_tax_class_id'] = [
                                'value' => $tax_class_id,
                                'type' => 'integer',
                            ];
                        }
                        break;
                    case 'categories_name':
                        $parent_category = 0;
                        $categories_name_ok = true;
                        $language_id = $this->languages[DEFAULT_LANGUAGE];
                        $categories = explode('^', $field_value);
                        foreach ($categories as $current_category_name) {
                            if ($current_category_name === '') {
                                $categories_name_ok = false;
                                $this->debugMessage('[*] Product not inserted at line number ' . $this->stats['record_count'] . ", blank sub-category name found ($field_value).", self::DBIO_WARNING);
                                break;
                            }

                            $category_info_sql = 
                                "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c
                                        INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                            ON cd.categories_id = c.categories_id
                                           AND cd.language_id = $language_id
                                  WHERE c.parent_id = $parent_category 
                                    AND cd.categories_name = :categories_name: 
                                  LIMIT 1";
                            $category_info = $db->Execute($db->bindVars($category_info_sql, ':categories_name:', $current_category_name, 'string'), false, false, 0, true);
                            if (!$category_info->EOF) {
                                $parent_category = $category_info->fields['categories_id'];
                            } elseif ($this->import_is_insert === true) {
                                if (DBIO_PRODUCTS_AUTO_CREATE_CATEGORIES !== 'Yes') {
                                    $categories_name_ok = false;
                                    $this->debugMessage('[*] Product not inserted at line number ' . $this->stats['record_count'] . ", no match found for categories_name ($current_category_name).", self::DBIO_WARNING);
                                } else {
                                    $parent_category = $this->createCategory($current_category_name, $parent_category);
                                    if ($parent_category === false) {
                                        $categories_name_ok = false;
                                    }
                                }
                                if ($categories_name_ok === false) {
                                    break;
                                }
                            }
                        }
                        if ($categories_name_ok === true) {
                            $category_check = $db->ExecuteNoCache(
                                "SELECT categories_id
                                   FROM " . TABLE_CATEGORIES . "
                                  WHERE parent_id = $parent_category
                                  LIMIT 1"
                            );
                            if (!$category_check->EOF) {
                                $categories_name_ok = false;
                                $this->debugMessage("[*] Product not inserted at line number " . $this->stats['record_count'] . "; category id ($parent_category) has categories.", self::DBIO_WARNING);
                            } else {
                                $this->import_sql_data[TABLE_PRODUCTS]['master_categories_id'] = [
                                    'value' => $parent_category,
                                    'type' => 'integer',
                                ];

                                $this->config['tables'][TABLE_PRODUCTS_TO_CATEGORIES]['alias'] = 'p2c';
                                $this->import_sql_data[TABLE_PRODUCTS_TO_CATEGORIES]['categories_id'] = [
                                    'value' => $parent_category,
                                    'type' => 'integer',
                                ];
                            }
                        }
                        if ($categories_name_ok === false) {
                            $this->record_status = false;
                        }
                        break;
                    default:
                        break;
                }  //-END switch interrogating $field_name for self::DBIO_SPECIAL_IMPORT
                break;
            default:
                parent::importAddField($table_name, $field_name, $field_value);
                break;
        }  //-END switch interrogating $table_name
    }  //-END function importAddField

    protected function createCategory($categories_name, $parent_category_id)
    {
        global $db;

        $created_category_id = false;
        $parent_check = $db->Execute(
            "SELECT products_id
               FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
              WHERE categories_id = $parent_category_id
              LIMIT 1"
        );
        if (!$parent_check->EOF) {
            $this->debugMessage("[*] Cannot add the category named $categories_name to parent_category_id $parent_category_id; the parent category contains products.", self::DBIO_ERROR);
        } else {
            $this->debugMessage("[*] Creating a category named $categories_name, with parent_category_id $parent_category_id.", self::DBIO_WARNING | self::DBIO_ACTIVITY);
            if ($this->operation !== 'check') {
                $sql_data_array = [
                    [
                        'fieldName' => 'parent_id',
                        'value' => $parent_category_id,
                        'type' => 'integer'
                    ],
                    [
                        'fieldName' => 'date_added',
                        'value' => 'now()',
                        'type' => 'noquotestring'
                    ],
                    [
                        'fieldName' => 'sort_order',
                        'value' => 0,
                        'type' => 'integer'
                    ],
                ];
                $db->perform(TABLE_CATEGORIES, $sql_data_array);
                $created_category_id = zen_db_insert_id();
                
                $description_array = [
                    [
                        'fieldName' => 'categories_id',
                        'value' => $created_category_id,
                        'type' => 'integer'
                    ],
                    [
                        'fieldName' => 'categories_name',
                        'value' => $categories_name,
                        'type' => 'string'
                    ],
                    [
                        'fieldName' => 'categories_description',
                        'value' => '',
                        'type' => 'string'
                    ],
                ];
                foreach ($this->languages as $language_code => $language_id) {
                    $sql_data_array = $description_array;
                    $sql_data_array[] = [
                        'fieldName' => 'language_id',
                        'value' => $language_id,
                        'type' => 'integer'
                    ];
                    $db->perform(TABLE_CATEGORIES_DESCRIPTION, $sql_data_array);
                }
            }
        }
        return $created_category_id;
    }

    // -----
    // This function, issued just prior to the database action, allows the I/O handler to make any "last-minute" changes based
    // on the record's 'key' value -- for this report, it's the products_id value.
    //
    // If we're doing an insert (i.e. a new product), simply add the products_id field to the non-products tables' SQL
    // input array.
    //
    // If we're doing an update (i.e. existing product), the built-in handling has already taken care of the products_description
    // table, but there's some special handling required for the products-to-categories table.  That table's update
    // happens within this function and we set the return value to false to indicate to the parent processing that the
    // associated update has been already handled.
    //
    protected function importUpdateRecordKey($table_name, $table_fields, $products_id)
    {
        global $db;

        switch ($table_name) {
            case TABLE_PRODUCTS:
                if ($this->import_is_insert === true) {
                    $table_fields['products_id'] = [
                        'value' => $products_id,
                        'type' => 'integer'
                    ];
                } else {
                    $this->where_clause = 'products_id = ' . (int)$this->key_fields['products_id'];
                    $products_id = (int)$this->key_fields['products_id'];
                }
                break;

            case TABLE_PRODUCTS_DESCRIPTION:
                if ($this->import_is_insert === true) {
                    $table_fields['products_id'] = [
                        'value' => $products_id,
                        'type' => 'integer',
                    ];
                }
                break;

            case TABLE_PRODUCTS_TO_CATEGORIES:
                if ($this->operation !== 'check') {
                    $pID = ($this->import_is_insert === true) ? $products_id : $this->key_fields['products_id'];
                    $db->Execute(
                        "INSERT IGNORE INTO $table_name
                            (products_id, categories_id)
                         VALUES 
                            ($pID, " . (int)$table_fields['categories_id']['value'] . ")"
                    );
                }
                $table_fields = false;
                break;

            // -----
            // The 'meta_tags_products_description' language-specific entry for the product will be **REMOVED** if all
            // associated fields are empty, bypassing the normal import processing for this table's fields.
            //
            // Note that an empty record in this table will be created at the end of the csv-record's import if meta
            // tags were defined for some, but not all, languages supported by the current store!
            //
            case TABLE_META_TAGS_PRODUCTS_DESCRIPTION:
                if (empty($this->import_language_id)) {
                    dbioLogError("importUpdateRecordKey, missing 'import_language_id'.");
                }
                $this->debugMessage("importUpdateRecordKey: " . print_r($table_fields, true) . PHP_EOL . 'key_fields: ' . print_r($this->key_fields, true));
                $table_data = '';
                foreach ($table_fields as $key => $values) {
                    $table_data .= trim($values['value']);
                }
                if (empty($table_data)) {
                    // -----
                    // Indicate to the parent class that the table-record has been processed.
                    //
                    $table_fields = false;
                    
                    // -----
                    // If we're updating the product, remove this language-specific meta-tags' record.
                    //
                    if ($this->import_is_insert === false) {
                        // -----
                        // If the current DbIo operation is an import-check, simply output a debug message containing
                        // the record-deletion SQL.  Otherwise, actually run the SQL, removing that record.
                        //
                        $sql = 
                            "DELETE FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                              WHERE products_id = " . $this->key_fields['products_id'] . "
                                AND language_id = " . $this->import_language_id . "
                              LIMIT 1";
                        if ($this->operation === 'check') {
                            $this->debugMessage("importUpdateRecordKey, removing record: $sql", self::DBIO_STATUS);
                        } else {
                            $db->Execute($sql);
                        }
                    }
                } elseif ($this->import_is_insert === true) {
                    $table_fields['products_id'] = [
                        'value' => $products_id,
                        'type' => 'integer',
                    ];
                    $table_fields['language_id'] = [
                        'value' => $this->import_language_id,
                        'type' => 'integer',
                    ];
                }
                break;

            default:
                break;
        }
        return parent::importUpdateRecordKey($table_name, $table_fields, $products_id);
    }

    // -----
    // Keep the product's date_added and last_updated fields in sync.  When a product is inserted, set its
    // date_added, keeping its last_updated as the default; on an update, keep the existing date_added but
    // make sure the product's last_updated date is "now".
    //
    // For product inserts, ensure that the `products_id` field is not part of the to-be-inserted record.
    //
    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        global $db;

        if ($table_name === TABLE_PRODUCTS) {
            if (($is_override === true && $is_insert === true) || ($is_override === false && $this->import_is_insert === true)) {
                unset($table_fields['products_last_modified'], $table_fields['products_id']);
                $table_fields['products_date_added'] = [
                    'value' => 'now()',
                    'type' => 'datetime',
                ];
            } else {
                unset($table_fields['products_date_added']);
                $table_fields['products_last_modified'] = [
                    'value' => 'now()',
                    'type' => 'datetime',
                ];
            }
        } elseif ($table_name === TABLE_PRODUCTS_DESCRIPTION) {
            if (!$this->import_is_insert === true) {
                $this->where_clause = "pd.products_id = " . $this->key_fields['products_id'] . " AND pd.language_id = " . $this->import_language_id;
            }
        } elseif ($table_name === TABLE_META_TAGS_PRODUCTS_DESCRIPTION) {
            // -----
            // Since a product's meta-tags' table-entries are optional, it's possible on a product
            // update that those fields aren't defined.  If that's the case, the meta-tag information
            // needs to be inserted even though the product's being updated.
            //
            if ($this->import_is_insert === false) {
                $products_id = $this->key_fields['products_id'];

                // -----
                // Check to see if the existing product's language-specific meta-tags record exists. If not,
                // we'll do an database action 'override' so that the meta-tag information can be inserted
                // even though the base product's being updated.
                //
                $check = $db->Execute(
                    "SELECT *
                       FROM " . TABLE_META_TAGS_PRODUCTS_DESCRIPTION . "
                      WHERE products_id = $products_id
                        AND language_id = " . $this->import_language_id . "
                      LIMIT 1"
                );
                if ($check->EOF) {
                    $is_override = true;
                    $is_insert = true;
                    $table_fields['products_id'] = [
                        'value' => $products_id,
                        'type' => 'integer',
                    ];
                    $table_fields['language_id'] = [
                        'value' => $this->import_language_id,
                        'type' => 'integer',
                    ];
                } else {
                    $this->where_clause = "mtpd.products_id = $products_id AND mtpd.language_id = " . $this->import_language_id;
                }
            }
        }
        return parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, ' LIMIT 1', $is_override, $is_insert);
    }

}  //-END class DbIoProductsHandler
