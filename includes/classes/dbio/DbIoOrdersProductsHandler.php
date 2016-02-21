<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart "Orders Products" export-only.  The class
// provides its own header to limit the processing output.
//
class DbIoOrdersProductsHandler extends DbIoHandler 
{
    public function __construct ($log_file_suffix)
    {
        include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/DbIoOrdersProductsHandler.php');
        parent::__construct ($log_file_suffix);
    }
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->config = array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => true,
            'export_headers' => array (
                'tables' => array (
                    TABLE_ORDERS => 'o',
                    TABLE_ORDERS_PRODUCTS => 'op',
                    TABLE_ORDERS_STATUS => 'os',
                ),
                'fields' => array (
                    'date_purchased' => 'o',
                    'orders_status_name' => 'os',
                    'orders_id' => 'o',
                    'customers_id' => 'o',
                    'customers_name' => 'o',
                    'customers_company' => 'o',
                    'customers_street_address' => 'o',
                    'customers_suburb' => 'o',
                    'customers_city' => 'o',
                    'customers_state' => 'o',
                    'customers_postcode' => 'o',
                    'customers_country' => 'o',
                    'customers_telephone' => 'o',
                    'customers_email_address' => 'o',
                    'products_model' => 'op',
                    'products_name' => 'op',
                ),
                'where_clause' => 'o.orders_id = op.orders_id AND os.orders_status_id = o.orders_status AND os.language_id = ' . $_SESSION['languages_id'],
                'order_by_clause' => 'o.orders_id DESC',
            ),
            'description' => DBIO_ORDERSPRODUCTS_DESCRIPTION,
        );
    }

    // -----
    // For each to-be-exported record, check to see if the record is associated with the previous record's
    // order.  If not, capture this new order ID (all fields will be output to the CSV); otherwise, output
    // **only** the columns associated with the additional product in the current order.
    //
    public function exportPrepareFields (array $fields) 
    {
        if (!isset ($this->last_order_handled) || $this->last_order_handled !== $fields['orders_id']) {
            $this->last_order_handled = $fields['orders_id'];
        } else {
            foreach ($fields as $field_name => &$field_value) {
                if ($field_name == 'products_model') {
                    break;
                }
                $field_value = '';
            }
        }
        return $fields;
    }
    
// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

}  //-END class DbIoOrdersProductsHandler
