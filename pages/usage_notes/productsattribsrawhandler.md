# ProductsAttribsRawHandler &mdash; Usage Notes

The `ProductsAttribsRawHandler` supports the import and export of product-attribute related fields for your Zen Cart.

| Feature Name                             | Comments                                                     |
| ---------------------------------------- | ------------------------------------------------------------ |
| Export/Import                            | Both                                                         |
| Customized Fields for Export             | &cross;                                                      |
| Export Filters                           | None                                                         |
| Required Columns (aka Fields) for Import | `v_products_id`, `v_options_id`, `v_options_values_id`       |
| Database Tables Affected                 | `products_attributes`, `products_attributes_download`, `products_options_values_to_products_options`, and `products` (via `zen_update_products_price_sorter`). |
| DbIo Commands                            | `REMOVE`                                                     |

## Additional Fields on Export

This handler provides some additional fields on an 'export' action to make it easier to identify the attribute; all but the `v_dbio_command` field are ignored on an import:

| Field Name                     | Description                                                  |
| ------------------------------ | ------------------------------------------------------------ |
| v_products_model               | The 'products_model'  associated with the attribute's `products_id`. |
| v_manufacturers_name           | The 'name' associated with the attribute's product's `manufacturers_id`. |
| v_products_options_name        | The 'name' associated with the attribute's `options_id`.     |
| v_products_options_values_name | The 'name' associated with the attribute's `options_values_id`. |
| v_dbio_command                 | Can be either an empty string (no command) or `REMOVE` to cause the attribute to be removed from the database. |

## Controlling an Attribute's Download Options

An attribute's import can optionally include a download filename associated with the attribute, associated with the `products_attributes_download` table.  The import processing depends on

1.  Whether the base attribute information is being inserted or updated.
2. Whether an existing attribute has a pre-existing download filename.
3. Whether a download filename (`v_products_attributes_filename`) is supplied for the to-be-imported record.
   - The handler supports a sub-command (`REMOVE`) value for this field, enabling a previously-recorded filename to be removed from the given attribute.

Here's how the processing flows:

1. If an attribute matching the `products_id`+`options_id`+`options_values_id` does not exist or the attribute exists but does not currently have an associated download filename:
   1. If the `products_attributes_filename` field does not exist in the import or if the field exists and is an empty string:
      - No `products_attributes_download` record is created.
   2. Otherwise, if the filename field is not a [valid filename](#checking-a-download-filename-for-validity)
      - The addition of the `products_attributes_download` record is disallowed.  Note that a new `products_attributes` record might have been created!
   3. Otherwise, the `products_attributes_download` record is created.
2. Otherwise, an existing attribute with an existing `products_attributes_download` record:
   1. If the `products_attributes_filename` field does not exist in the import
      - No update is made to the `products_attributes_download` table.
   2. Otherwise, if that field is set to `REMOVE` (capitalization required)
      - The associated `products_attributes_download` table record is deleted.
   3. Otherwise, if that field is not a [valid filename](#checking-a-download-filename-for-validity) (including an empty string)
      - No change is made to the `products_attributes_download` table and the record is marked as an import error.
   4. Otherwise, the associated `products_attributes_download` record is updated.

### Checking a Download Filename for Validity

When an import includes a `v_products_attributes_filename` field, there are a couple of rudimentary checks on that filename that must pass to enable the associated `products_attributes_download` table-record to be created or updated:

1. The field's value must not be an empty string.
2. The field's value must not start with a `.` (period).
3. The field's value must not contain any characters in this list: `[<>:"|?*]`.

