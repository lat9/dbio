<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.2
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the import and export of information in the Zen Cart 'products_options_values' table.
//
// Each table-record is exported as a single CSV record; all currently-defined fields are exported.
//
// For the import, the CSV **must** contain both the products_options_values_id and language_id fields, since those are
// used as the table's key-pair.  An entry is updated if a database record is found that matches both fields; otherwise,
// the record is inserted using the specified language_id and a products_options_values_id that is calculated as the 
// current table's maximum value (+1).
//
// Usage Notes:
//
// 1) When importing new records for a multi-language store, the import should be run once per language value.
//    Otherwise, the products_options_id will get "out-of-sync" between the multiple languages.
//
class DbIoProductsOptionsValuesHandler extends DbIoHandler
{
    protected int $optionValueNameLength;
    protected array $optionsByLanguage;
    protected bool $isLanguageOnlyInsert;

    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('ProductsOptionsValues'); 
        return [
            'version' => '2.0.2',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_PRODUCTSOPTIONSVALUES_DESCRIPTION,
        ];
    }

    // -----
    // Generate and return the SQL query used to export the products' options' values.
    //
    public function exportGetSql($sql_limit = '')
    {
        $export_sql =
            "SELECT pov.products_options_values_id, pov.language_id, pov.products_options_values_name, po.products_options_id, po.products_options_name, pov.products_options_values_sort_order
               FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
                    LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " pov2po
                        ON pov2po.products_options_values_id = pov.products_options_values_id
                    LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " po
                        ON po.products_options_id = pov2po.products_options_id
                       AND po.language_id = pov.language_id
              ORDER BY po.language_id, po.products_options_name, po.products_options_id, pov.products_options_values_name, pov.products_options_values_sort_order";
        $export_sql .= " $sql_limit";
        $this->debugMessage("exportGetSql:\n$export_sql");
        return $export_sql;
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIo operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'ProductsOptionsValues';
        $this->config = self::getHandlerInformation();
        $this->config['handler_does_import'] = true;  //-Indicate that **all** the import-based database manipulations are performed by this handler
        $this->config['keys'] = [
            TABLE_PRODUCTS_OPTIONS_VALUES => [
                'alias' => 'pov',
                'products_options_values_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
                'language_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS_OPTIONS_VALUES => [
                'alias' => 'pov',
                'language_override' => self::DBIO_OVERRIDE_ALL,
            ],
            TABLE_PRODUCTS_OPTIONS => [
                'alias' => 'po',
            ],
        ];

        $this->config['fixed_headers'] = [
            'products_options_values_id' => TABLE_PRODUCTS_OPTIONS_VALUES,
            'language_id' => TABLE_PRODUCTS_OPTIONS_VALUES,
            'products_options_values_name' => TABLE_PRODUCTS_OPTIONS_VALUES,
            'products_options_id' => TABLE_PRODUCTS_OPTIONS,
            'products_options_name' => TABLE_PRODUCTS_OPTIONS,
            'products_options_values_sort_order' => TABLE_PRODUCTS_OPTIONS_VALUES,
        ];
        $this->export_where_clause = '';
        $this->export_order_by_clause = '';

        $this->optionValueNameLength = (int)zen_field_length(TABLE_PRODUCTS_OPTIONS_VALUES, 'products_options_values_name');

        global $db;
        $option_names = $db->Execute(
            "SELECT products_options_id, language_id
               FROM " . TABLE_PRODUCTS_OPTIONS
        );
        $this->optionsByLanguage = [];
        foreach ($option_names as $next_option) {
            if (!isset($this->optionsByLanguage[$next_option['language_id']])) {
                $this->optionsByLanguage[$next_option['language_id']] = [];
            }
            $this->optionsByLanguage[$next_option['language_id']][] = $next_option['products_options_id'];
        }
    }

    // -----
    // Called by the DbIoHandler class, just prior to each record's import.  Gives us a
    // chance to:
    //
    // 1) See if the products_options_values_id is 0.  If so, force the import to be an insert.
    //    Otherwise, the value must be associated with an option's value; if not, the record's
    //    import will be denied.
    // 2) See if the associated language_id is, in fact, valid for the store.  If not, the
    //    record's import will be denied.
    // 3) If the products_options_values_id and language_id were both found to be valid and the
    //    record's not *specifically* an insert, check to see whether the values-id/language-id
    //    pair are currently recorded in the database.  If not, this is a language-only record insert.
    //
    protected function importCheckKeyValue($data)
    {
        $products_options_values_id = $this->importGetFieldValue('products_options_values_id', $data);
        $this->isLanguageOnlyInsert = false;
        if ($products_options_values_id === '0') {
            $this->import_is_insert = true;
        } else {
            global $db;
            $check = $db->Execute(
                "SELECT products_options_values_id, language_id
                   FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . "
                  WHERE products_options_values_id = " . (int)$products_options_values_id
            );
            if ($check->EOF) {
                $this->record_status = false;
                $this->debugMessage("[*] products_options_values_id, line #" . $this->stats['record_count'] . ": Value ($products_options_values_id) is not associated with an existing options-value.", self::DBIO_ERROR);
            }
        }

        $language_id = $this->importGetFieldValue('language_id', $data);
        if (!in_array($language_id, array_values($this->languages))) {
            $this->record_status = false;
            $this->debugMessage("[*] products_options_values.language_id, line #" . $this->stats['record_count'] . ": Value ($language_id) is not a valid language id.", self::DBIO_ERROR);
        }

        if ($this->record_status === true && $this->import_is_insert === false) {
            $this->isLanguageOnlyInsert = true;
            foreach ($check as $next_value) {
                if ($language_id == $next_value['language_id']) {
                    $this->isLanguageOnlyInsert = false;
                    break;
                }
            }
            if ($this->isLanguageOnlyInsert === true) {
                $this->import_is_insert = true;
            }
        }

        return parent::importCheckKeyValue($data);
    }

    protected function importProcessField($table_name, $field_name, $language_id, $field_value)
    {
        switch ($field_name) {
            case 'products_options_values_id':
            case 'products_options_values_sort_order':
            case 'language_id':
            case 'products_options_id':
                if (!ctype_digit($field_value)) {
                    $this->record_status = false;
                    $this->debugMessage("[*] $table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) must contain only digits.", self::DBIO_ERROR);
                }
                $this->saved_data[$field_name] = $field_value;
                break;

            case 'products_options_values_name':
                if (dbio_strlen($field_value) > $this->optionValueNameLength) {
                    $this->record_status = false;
                    $this->debugMessage("[*] products_options_values_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is too long; max length is {$this->optionValueNameLength}.", self::DBIO_ERROR);
                }
                $this->saved_data[$field_name] = $field_value;
                break;

            default:
                break;
        }
    }

    protected function importFinishProcessing()
    {
        if (count($this->saved_data) !== 5) {
            $this->record_status = false;
            $this->debugMessage("[*] importFinishProcessing, line #" . $this->stats['record_count'] . ": Missing one or more required data columns.", self::DBIO_ERROR);
            return;
        }

        $language_id = $this->saved_data['language_id'];
        if (!isset($this->optionsByLanguage[$language_id])) {
            $this->record_status = false;
            $this->debugMessage("[*] importFinishProcessing, line #" . $this->stats['record_count'] . ": No option names are available for language id $language_id.", self::DBIO_ERROR);
            return;
        }

        $option_id = $this->saved_data['products_options_id'];
        if (!isset($this->optionsByLanguage[$language_id][$option_id])) {
            $this->record_status = false;
            $this->debugMessage("[*] importFinishProcessing, line #" . $this->stats['record_count'] . ": Unknown option id ($option_id) for language_id $language_id.", self::DBIO_ERROR);
        }

        $sql_data_array = [];
        foreach ($this->saved_data as $field_name => $value) {
            if ($field_name === 'products_options_id' || ($this->import_is_insert && $field_name === 'products_options_values_id')) {
                continue;
            }
            $sql_data_array[] = ['fieldName' => $field_name, 'value' => $value, 'type' => ($field_name === 'products_options_values_name') ? 'stringIgnoreNull' : 'integer'];
        }

        $products_options_values_id = (int)$this->saved_data['products_options_values_id'];

        global $db;
        if ($this->import_is_insert) {
            if ($this->isLanguageOnlyInsert === false) {
                $next_id = $db->Execute(
                    "SELECT MAX(products_options_values_id) + 1 AS next_id
                       FROM " . TABLE_PRODUCTS_OPTIONS_VALUES
                );
                $products_options_values_id = $next_id->fields['next_id'];
            }
            $sql_data_array[] = ['fieldName' => 'products_options_values_id', 'value' => $products_options_values_id, 'type' => 'integer'];

            $this->debugMessage("Inserting record into products_options_values at line #" . $this->stats['record_count'] . ": " . json_encode($sql_data_array));
            if ($this->operation !== 'check') {
                $db->perform(TABLE_PRODUCTS_OPTIONS_VALUES, $sql_data_array);

                $sql_data_array = [
                    ['fieldName' => 'products_options_id', 'value' => $this->saved_data['products_options_id'], 'type' => 'integer'],
                    ['fieldName' => 'products_options_values_id', 'value' => $products_options_values_id, 'type' => 'integer'],
                ];
                $db->perform(TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS, $sql_data_array);
            }
        } else {
            $this->debugMessage("Updating record in products_options_values at line #" . $this->stats['record_count'] . ": " . json_encode($sql_data_array));
            if ($this->operation !== 'check') {
                $db->perform(TABLE_PRODUCTS_OPTIONS_VALUES, $sql_data_array, 'update', "products_options_values_id = $products_options_values_id LIMIT 1");

                $check = $db->Execute(
                    "SELECT products_options_values_to_products_options_id
                       FROM " . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . "
                      WHERE products_options_id = " . (int)$this->saved_data['products_options_id'] . "
                        AND products_options_values_id = $products_options_values_id
                      LIMIT 1"
                );
                if ($check->EOF) {
                    $sql_data_array = [
                        ['fieldName' => 'products_options_id', 'value' => $this->saved_data['products_options_id'], 'type' => 'integer'],
                        ['fieldName' => 'products_options_values_id', 'value' => $products_options_values_id, 'type' => 'integer'],
                    ];
                    $db->perform(TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS, $sql_data_array);
                }
            }
        }
    }
}  //-END class DbIoProductsOptionsValuesHandler
