# ProductsOptionsValuesHandler &mdash; Usage Notes

The `ProductsOptionsValuesHandler` supports the import and export of product's' options' values fields for your Zen Cart.

| Feature Name                             | Comments                                       |
| ---------------------------------------- | ---------------------------------------------- |
| Export/Import                            | Both                                           |
| Customized Fields for Export             | &cross;                                        |
| Export Filters                           | None                                           |
| Required Columns (aka Fields) for Import | `v_products_options_values_id`, `v_language_id` |
| Database Tables Affected                 | `products_options_values`                      |
| DbIo Commands                            | Not supported                                  |

## Importing Products' Options' Values

For the import, the CSV **must** contain both the  `v_products_options_values_id` and `v_language_id` fields, since those are used  as the table's key-pair.  An entry is updated if a database record is  found that matches both fields; otherwise,  the record is inserted using the specified language_id and a products_options_values_id that is  calculated as the table's current maximum value (+1).

1. When importing new records for a multi-language store, the import should be run once per language value.   Otherwise, the `products_options_values_id ` will get "out-of-sync" between the multiple languages.
2. On an import:
   a.  If the `products_options_values_id` value is 0, the import is forced to be an insert.
   b. If the associated `language_id` is not valid for the store, the record's import will be denied.

Thus, to create a new option-value, simply set the `v_products_options_values_id` to 0 with a valid `v_language_id`.  The option-values get 'bound' to their associated option via the `ProductsAttribsBasic` or `ProductsAttribsRaw` handlers' imports.