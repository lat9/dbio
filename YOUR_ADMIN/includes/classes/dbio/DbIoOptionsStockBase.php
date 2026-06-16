<?php

declare(strict_types=1);
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2014-2026 Vinos de Frutas Tropicales
//
// Last updated:  DbIo v2.2.1
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the common processing for imports and exports in the Zen Cart 'products_options_stock' table.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.
//
// For the import, the CSV **must** contain both the products_id and v_products_options_combination fields, since those are
// used as an entry's key-pair.  An entry is updated ONLY IF a database record is found that matches both fields.
//
class DbIoOptionsStockBase extends DbIoHandler
{
    // -----
    // Normally, the option/option-value pairs are specified as ^name1~value1[^namen~valuen ...] instead of the more standard
    // use of the colon (:) as the inner separator.  Unfortunately, there are stores (like the ZC demo products) that include
    // a colon in either an option's name or an option-value's name.
    //
    public const string OPTION_OUTER_SEPARATOR = '^';
    public const string OPTION_INNER_SEPARATOR = '~';

    // -----
    // Handler-specific variable declarations.
    //
    protected bool|string $products_id;

    // -----
    // Update the export header, inserting the product's name and model and the option-combinations' name list and removing
    // the pos_id and pos_hash columns.
    //
    public function exportFinalizeInitialization(): bool
    {
        $this->headerInsertColumns(
            'v_products_id',
            [
                'v_products_name',
                'v_products_model',
                'v_products_options_combination'
            ]
        );
        $this->headers = array_diff($this->headers, ['v_pos_id', 'v_pos_hash']);
        return parent::exportFinalizeInitialization();
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
        $pos_id = $fields['pos_id'];
        $products_id = $fields['products_id'];

        $attributes_info = $this->dbExecuteNoCache(
            "SELECT * FROM " . TABLE_PRODUCTS_OPTIONS_STOCK_ATTRIBUTES . "
              WHERE pos_id = $pos_id
                AND products_id = $products_id
           ORDER BY pos_attribute_id"
        );
        $option_combinations = [];
        foreach ($attributes_info as $attribute) {
            $current_combination = $this->getOptionValueNamePair($attribute['options_id'], $attribute['options_values_id']);
            if ($current_combination === false) {
                $option_combinations[] = 'Unknown[' . $attribute['options_id'] . "]:Unknown[" . $attribute['options_values_id'] . ']';
            } else {
                $option_combinations[] = $current_combination;
            }
        }

        unset($fields['pos_id'], $fields['pos_hash']);
        $insert_fields = [
            'products_name' => zen_get_products_name($products_id),
            'products_model' => zen_get_products_model($products_id),
            'products_option_combination' => implode(self::OPTION_OUTER_SEPARATOR, $option_combinations),
        ];
        return parent::exportPrepareFields($this->arrayInsertAfter($fields, 'products_id', $insert_fields));
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------
    // -----
    // This abstract function, required to be supplied for the actual handler, is called during the class
    // construction to have the handler set its database-related configuration.  It needs to be defined
    // here for class-inheritance but the actual handlers need to supply it!
    //
    // If not overridden, a PHP fatal error is logged and a `zen_exit` is made!
    //
    protected function setHandlerConfiguration(): void
    {
        dbioLogError('The real handler needs to supply this function!');
    }

    // -----
    // Since the $db->ExecuteNoCache method is not supported on all Zen Cart versions, define a
    // method here for various processes to use.  It'll make the code a bit more readable.
    //
    protected function dbExecuteNoCache(string $sql_statement): queryFactoryResult
    {
        global $db;

        return $db->Execute($sql_statement, false, false, 0, true);
    }

    // -----
    // Enable this handler to accept a products_id and/or products_model as the "record key" for imports.  The
    // basic handling will check to see if there is a products_options_stock record associated with an input
    // products_id; this handler's processing will, if no products_id match is found, attempt to locate a
    // record using the products_id associated with an input products_model, so long as that model number
    // is unique.
    //
    protected function posmBaseDetermineProductsId(array $data): bool
    {
        global $db;

        if (!$this->import_is_insert) {
            $this->products_id = $this->importGetFieldValue('products_id', $data);
        } else {
            $products_model = $this->importGetFieldValue('products_model', $data);
            if ($products_model === false) {
                $this->record_status = false;
                $this->debugMessage('Data record at line #' . $this->stats['record_count'] . ' not imported. No match for products_id; products_model not included in import.', self::DBIO_ERROR);
            } else {
                $record_check = $this->dbExecuteNoCache(
                    "SELECT products_id
                       FROM " . TABLE_PRODUCTS . "
                      WHERE products_model = '" . $db->prepare_input($products_model) . "'
                      LIMIT 2"
                );
                if ($record_check->EOF) {
                    $this->record_status = false;
                    $this->debugMessage('Data record at line #' . $this->stats['record_count'] . " not imported. No matching product found for model $products_model.", self::DBIO_ERROR);
                } elseif ($record_check->RecordCount() > 1) {
                    $this->record_status = false;
                    $this->debugMessage('Data record at line #' . $this->stats['record_count'] . " not imported.  Multiple product records found for model $products_model.", self::DBIO_ERROR);
                } else {
                    $this->products_id = $record_check->fields['products_id'];
                }
            }
        }
        return $this->record_status;
    }

    // -----
    // Since we've determined the current record's products_id either directly or via model-number lookup, make sure that the
    // current products_id value is being used for any record manipulations.
    //
    protected function importProcessField(string $table_name, string $field_name, string $language_id, ?string $field_value): void
    {
        if ($field_name === 'products_id' && isset($this->products_id)) {
            $field_value = $this->products_id;
        }
        parent::importProcessField($table_name, $field_name, $language_id, $field_value);
    }

    // -----
    // While the pos_id or pos_hash fields might be specified by the imported CSV, they're not values that are importable and
    // the products_options_combination field requires "special handling".
    //
    protected function importHeaderFieldCheck(string $field_name): string
    {
        switch ($field_name) {
            case 'pos_id':      //- Fall through ...
            case 'pos_hash':
                $status = self::DBIO_NO_IMPORT;
                break;
            case 'products_options_combination':
                $status = self::DBIO_SPECIAL_IMPORT;
                break;
            default:
                $status = self::DBIO_IMPORT_OK;
                break;
        }
        return $status;
    }

    // -----
    // Used in export processing, creates the textual option-name/option-value-name string associated with the specified
    // option_id/option_value_id pair, returning boolean false if invalid inputs are received or the string otherwise.
    //
    protected function getOptionValueNamePair(string $options_id, string $options_values_id): false|string
    {
        global $db;

        $option_info = $this->dbExecuteNoCache(
            "SELECT po.products_options_name, pov.products_options_values_name
               FROM " . TABLE_PRODUCTS_OPTIONS . " po, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
              WHERE po.products_options_id = " . (int)$options_id . "
                AND po.language_id = " . $this->languages[DEFAULT_LANGUAGE] . "
                AND pov.products_options_values_id = " . (int)$options_values_id . "
                AND pov.language_id = " . $this->languages[DEFAULT_LANGUAGE]
        );
        if ($option_info->EOF) {
            $return_value = false;
        } else {
            $return_value = $option_info->fields['products_options_name'] . self::OPTION_INNER_SEPARATOR . $option_info->fields['products_options_values_name'];
        }
        return $return_value;
    }

    // -----
    // This function converts the products_options_combination field for the current CSV record into an array of option_id/option_value_id pairs,
    // returning (boolean) false if one or more of the option-pairs aren't pre-defined in the database and setting the log-message in the specified value.
    //
    // If all option-pairs are found, the function returns an associative array containing the database record ids for the option combinations.
    //
    protected function posmBaseProcessCurrentOptionCombination(string $products_id, array $data, string &$option_message, bool $options_must_exist = true): false|array
    {
        global $db;

        $option_message = '';
        $option_error = false;
        $option_combination = $this->importGetFieldValue('products_options_combination', $data);
        if ($option_combination === false) {
            $option_error = true;
            $option_message = 'missing products_options_combination column';
        } else {
            $combinations = explode(self::OPTION_OUTER_SEPARATOR, $option_combination);
            $options_array = [];
            $default_language = $this->languages[DEFAULT_LANGUAGE];
            $products_id = (int)$products_id;
            if ($options_must_exist === true) {
                foreach ($combinations as $current_combination) {
                    $current_pair = explode(self::OPTION_INNER_SEPARATOR, $current_combination);
                    $current_info = $this->dbExecuteNoCache(
                        "SELECT po.products_options_id, pov.products_options_values_id
                           FROM " . TABLE_PRODUCTS_OPTIONS . " po, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov, " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " pov2po, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                          WHERE po.products_options_name = '" . $db->prepare_input($current_pair[0]) . "'
                            AND po.language_id = $default_language
                            AND pov.products_options_values_name = '" . $db->prepare_input($current_pair[1]) . "'
                            AND pov.language_id = $default_language
                            AND pov2po.products_options_id = po.products_options_id
                            AND pov2po.products_options_values_id = pov.products_options_values_id
                            AND pa.options_id = po.products_options_id
                            AND pa.options_values_id = pov.products_options_values_id
                            AND pa.products_id = $products_id LIMIT 1"
                    );
                    if ($current_info->EOF) {
                        $option_error = true;
                        $option_message .= self::OPTION_OUTER_SEPARATOR . $current_combination . self::OPTION_OUTER_SEPARATOR;
                    } else {
                        $options_array[$current_info->fields['products_options_id']] = $current_info->fields['products_options_values_id'];
                    }
                }
                if ($option_error === true) {
                    $option_message = 'unknown option-combinations specified (' . str_replace(self::OPTION_OUTER_SEPARATOR . self::OPTION_OUTER_SEPARATOR, self::OPTION_OUTER_SEPARATOR, $option_message) . ')';
                }
            } else {
                foreach ($combinations as $current_combination) {
                    $current_pair = explode(self::OPTION_INNER_SEPARATOR, $current_combination);
                    $option_id = $this->getOptionIdFromName($current_pair[0]);
                    if ($option_id === false) {
                        $option_error = true;
                    } else {
                        $option_value_return = $this->getOptionValueIdFromName($current_pair[1], $option_id);
                        if ($option_value_return === false) {
                            $option_error = true;
                        } else {
                            $option_id = $option_value_return['option_id'];
                            $option_value_id = $option_value_return['option_value_id'];
                            $attribute_check = $this->dbExecuteNoCache(
                                "SELECT products_attributes_id
                                 FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                                WHERE products_id = $products_id
                                  AND options_id = $option_id
                                  AND options_values_id = $option_value_id
                                LIMIT 1"
                            );
                            if ($attribute_check->EOF) {
                                $this->debugMessage("posmBaseProcessCurrentOptionCombination: creating attribute record for products_id $products_id ($option_id :: $option_value_id)", self::DBIO_ACTIVITY | self::DBIO_STATUS);
                                if ($this->operation !== 'check') {
                                    // -----
                                    // Inserting a new option-combination for this product.  The attribute's sort-order
                                    // will be added as the sort-order of the specified option-value.
                                    //
                                    $option_sort = $this->dbExecuteNoCache(
                                        "SELECT products_options_values_sort_order
                                           FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . "
                                          WHERE products_options_values_id = $option_value_id
                                          LIMIT 1"
                                    );
                                    $products_options_sort_order = ($option_sort->EOF) ? 0 : $option_sort->fields['products_options_values_sort_order'];
                                    $db->Execute(
                                        "INSERT INTO " . TABLE_PRODUCTS_ATTRIBUTES . "
                                            (products_id, options_id, options_values_id, products_options_sort_order)
                                        VALUES
                                            ($products_id, $option_id, $option_value_id, $products_options_sort_order)"
                                    );
                                }
                            }
                            $options_array[$option_id] = $option_value_id;
                        }
                    }
                }
            }
        }
        if ($option_error === true && $option_message !== '') {
            $this->debugMessage("posmBaseProcessCurrentOptionCombination: $option_message; the record at line #" . $this->stats['record_count'] . " not imported", self::DBIO_ERROR);
        }
        return ($option_error === true) ? false : $options_array;
    }

    // -----
    // This function, searches the products_options table for a match on the specified option-name.  One of
    // three possibilities exists:
    //
    // 1) The option-name isn't found; returns (bool)false.
    // 2) The option-name exists ONCE; returns (int)options_id
    // 3) Multiple instances of the option-name exist; returns an array of the matching option_id values.
    //
    private function getOptionIdFromName(string $option_name): false|int|array
    {
        global $db;

        $option_id = false;

        $option_info = $this->dbExecuteNoCache(
            "SELECT products_options_id
              FROM " . TABLE_PRODUCTS_OPTIONS . "
             WHERE products_options_name = '" . $db->prepare_input($option_name) . "'
               AND language_id = " . $this->languages[zen_config('DEFAULT_LANGUAGE')]
        );
        switch ($option_info->RecordCount()) {
            case 0:
                $this->debugMessage("getOptionIdFromName: No matching record found for the option named $option_name; the record at line #" . $this->stats['record_count'] . " not imported", self::DBIO_ERROR);
                break;
            case 1:
                $option_id = (int)$option_info->fields['products_options_id'];
                break;
            default:
                $option_id = [];
                foreach ($option_info as $option) {
                    $option_id[] = $option['products_options_id'];
                }
                break;
        }
        return $option_id;
    }

    // -----
    // This function locates, if present, or creates the specified "option-value name" in the database, returning its
    // options_values_id.  The option_id input is either the singular (int) ID associated with the previously-found
    // option-name or an array of matching option id values.
    //
    // Upon return:
    //
    // * If the return-value is (boolean)false, then multiple instances of that name were found in the database, but none
    //   are associated with a specified options_id ... the current record will not be imported and no database updates occur.
    //
    // * When the return-value is an array, then the matching 'option_id' and 'option_value_id' values are returned.
    //
    private function getOptionValueIdFromName(string $option_value_name, int|array $option_id): false|array
    {
        global $db;

        $option_value_return = false;

        // -----
        // First, check to see if the value's name exists in the database, gathering ALL instances in case multiple
        // values are present.  Further processing depends on the number of records located in the database.
        //
        $option_value_info = $this->dbExecuteNoCache(
            "SELECT products_options_values_id, products_options_values_sort_order
             FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . "
            WHERE products_options_values_name = '" . $db->prepare_input($option_value_name) . "'
              AND language_id = " . $this->languages[zen_config('DEFAULT_LANGUAGE')]
        );
        switch ($option_value_info->RecordCount()) {
            // -----
            // If no record is found (and we're not just performing a 'check' of the input CSV):
            //
            // 1) If multiple instances of the associated "option-name" were found, we don't know which
            //    instance of the option-name to use; return (boolean)false to indicate that we couldn't
            //    find/determine that value
            // 2) Otherwise ...
            //    a) Determine the next option value to be used.
            //    b) Create an instance of the named option value in **all** languages configured for the store.
            //    c) Associate this newly-created value with the option identified.
            //
            case 0:
                if (is_array($option_id)) {
                    $option_id_list = implode(',', $option_id);
                    $this->debugMessage("getOptionValueIdFromName: No option-value record found for \"$option_value_name\", but multiple options ($option_id_list) were; record not imported at line #" . $this->stats['record_count'] . '.', self::DBIO_ERROR);
                } else {
                    $next_id = $this->dbExecuteNoCache("SELECT MAX(products_options_values_id) + 1 AS next_id FROM " . TABLE_PRODUCTS_OPTIONS_VALUES);
                    $option_value_id = $next_id->fields['next_id'];

                    // -----
                    // Determine a unique sort order to apply to this option-value by finding the current maximum
                    // sort-order for the specified option.  The sort-order for this newly-added option-value will
                    // be the maximum of *all* option-values' sort-orders for the specified option ... plus 5.  That
                    // allows spacing between the sort orders so that it's somewhat easier make subsequent ordering
                    // changes.
                    //
                    $sort_order = $this->dbExecuteNoCache(
                        "SELECT pov.products_options_values_sort_order
                           FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " AS pov
                                INNER JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " AS pov2po
                                    ON pov2po.products_options_id = " . (int)$option_id . "
                          WHERE pov.products_options_values_id = pov2po.products_options_values_id
                          ORDER BY pov.products_options_values_sort_order DESC
                          LIMIT 1"
                    );
                    $options_sort_order = ($sort_order->EOF) ? 5 : ($sort_order->fields['products_options_values_sort_order'] + 5);
                    $option_value_return = true;
                    $this->debugMessage("getOptionValueIdFromName: creating value named $option_value_name for option ID $option_id", self::DBIO_ACTIVITY | self::DBIO_STATUS);
                    if ($this->operation !== 'check') {
                        foreach ($this->languages as $language_code => $language_id) {
                            $db->Execute(
                                "INSERT INTO " . TABLE_PRODUCTS_OPTIONS_VALUES . "
                                    (products_options_values_id, language_id, products_options_values_name, products_options_values_sort_order)
                                VALUES
                                    ($option_value_id, $language_id, '" . $db->prepare_input($option_value_name) . "', $options_sort_order)"
                            );
                        }
                        $db->Execute(
                            "INSERT INTO " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
                                (products_options_id, products_options_values_id)
                            VALUES
                                ($option_id, $option_value_id)"
                        );
                    }
                }
                break;

            // -----
            // If **exactly one** record is found, ensure that this option-value is associated with a specified
            // option, creating that association if not currently present and we're not just performing a 'check'.
            //
            case 1:
                $option_value_id = $option_value_info->fields['products_options_values_id'];
                $option_id_list = (is_array($option_id)) ? implode(',', $option_id) : $option_id;
                $value_check = $this->dbExecuteNoCache(
                    "SELECT products_options_id
                       FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
                      WHERE products_options_id IN ($option_id_list)
                        AND products_options_values_id = $option_value_id"
                );
                switch ($value_check->RecordCount()) {
                    case 0:
                        if (is_array($option_id)) {
                            $this->debugMessage("getOptionValueIdFromName: Multiple options found, no association for option-value-id ($option_value_id) to the options ($option_id_list); the record at line #" . $this->stats['record_count'] . ' not imported.', self::DBIO_ERROR);
                        } else {
                            $option_value_return = true;
                            $this->debugMessage("getOptionValueIdFromName: associating option value id $option_value_id to option id $option_id", self::DBIO_ACTIVITY | self::DBIO_STATUS);
                            if ($this->operation !== 'check') {
                                $db->Execute(
                                    "INSERT INTO " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
                                        (products_options_id, products_options_values_id)
                                    VALUES
                                        ($option_id, $option_value_id)"
                                );
                            }
                        }
                        break;
                    case 1:
                        $option_id = $value_check->fields['products_options_id'];
                        $option_value_return = true;
                        break;
                    default:
                        $this->debugMessage("getOptionValueIdFromName: Multiple option-associations found for option_value_id ($option_value_id) to option_ids in ($option_id_list); record at line #" . $this->stats['record_count'] . " not imported.", self::DBIO_ERROR);
                        break;
                }
                break;

            // -----
            // Otherwise, multiple instances of the requested option-value name have been found.  Search through the current
            // option-value to option records, trying to find a match on the specified option(s); if none or multiple is found,
            // note that the record can't be imported since we don't know which value entry was requested.
            //
            default:
                $option_value_list = [];
                foreach ($option_value_info as $next_value) {
                    $option_value_list[] = $next_value['products_options_values_id'];
                }
                $option_value_list = implode(',', $option_value_list);
                $option_list = (is_array($option_id)) ? implode(',', $option_id) : $option_id;

                $value_check = $this->dbExecuteNoCache(
                    "SELECT products_options_id, products_options_values_id
                     FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
                    WHERE products_options_id IN ($option_list)
                      AND products_options_values_id IN ($option_value_list)"
                );
                if ($value_check->RecordCount() !== 1) {
                    $this->debugMessage("getOptionValueIdFromName: Multiple associations found for the option value named $option_value_name for option_id values in the list ($option_list); record at line #" . $this->stats['record_count'] . " not imported.", self::DBIO_ERROR);
                } else {
                    $option_value_id = $value_check->fields['products_options_values_id'];
                    $option_id = $value_check->fields['products_options_id'];
                    $option_value_return = true;
                }
                break;
        }
        if ($option_value_return !== false) {
            $option_value_return = [
                'option_id' => $option_id,
                'option_value_id' => $option_value_id
            ];
        }
        return $option_value_return;
    }

    // -----
    // For any import, ensure that the last_modified field is set to the current date/time.
    //
    protected function importBuildSqlQuery(string $table_name, string $table_alias, array $table_fields, string $extra_where_clause = '', bool $is_override = false, bool $is_insert = true): string
    {
        $table_fields['last_modified'] = [
            'value' => 'now()',
            'type' => 'datetime',
        ];
        return parent::importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause, $is_override, $is_insert);
    }

    // -----
    // This common-use method enables the various POSM handlers to request that
    // the 'base' product's quantity be updated to be the sum of all POSM-managed
    // variants' quantities.
    //
    // Note: The class method posmBaseDetermineProductsId has previously set the
    // class variable 'products_id' to reflect that being currently processed.
    //
    protected function posmBaseUpdateBaseProductsQuantity(): void
    {
        if (!empty($this->products_id)) {
            posm_update_base_product_quantity($this->products_id);
        }
    }

    // -----
    // Override the DbIoHandler 'base' method so that the base product's quantity is
    // updated to be the sum of all its POSM-managed variants' quantities after a
    // successful import of an **updated** product.
    //
    // Note: The DbIoOptionsStockFullHandler, for a newly-added variant, performs this
    // operation itself!  The $key_value input is required, based on the method's
    // definition in the DbIoHandler class, but is unused -- see the method definition
    // above.
    //
    protected function importRecordPostProcess(string|false $key_value): void
    {
        $this->posmBaseUpdateBaseProductsQuantity();
    }

}  //-END class DbIoOptionsStockBase
