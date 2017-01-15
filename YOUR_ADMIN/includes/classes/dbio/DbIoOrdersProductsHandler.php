<?php
// -----
// Part of the DataBase I/O Manager (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2017, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart "Orders Products" export-only.  The class
// provides its own header to limit the processing output.
//
class DbIoOrdersProductsHandler extends DbIoOrdersBase 
{
    public static function getHandlerInformation ()
    {
        DbIoHandler::loadHandlerMessageFile ('OrdersProducts'); 
        return array (
            'version' => '1.1.0',
            'handler_version' => '1.1.0',
            'include_header' => true,
            'export_only' => true,
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
        } elseif (isset ($this->config['ordersproducts_cutoff_field'])) {
            foreach ($fields as $field_name => &$field_value) {
                if ($field_name == $this->config['ordersproducts_cutoff_field']) {
                    break;
                }
                $field_value = '';
            }
        }
        return parent::exportPrepareFields ($fields);
    }
    
// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    // Since this is an export-only report, it doesn't include a 'key'.
    //
    protected function setHandlerConfiguration () 
    {
        $this->stats['report_name'] = 'OrdersProducts';
        $this->config = self::getHandlerInformation ();

        $this->config['tables'] = array (
            TABLE_ORDERS => array (
                'alias' => 'o',
            ),
            TABLE_ORDERS_PRODUCTS => array (
                'alias' => 'op',
            ),
            TABLE_ORDERS_STATUS => array (
                'alias' => 'os',
            ),
        );
        $this->config['fixed_headers'] = array (
            'date_purchased' => TABLE_ORDERS,
            'orders_status_name' => TABLE_ORDERS_STATUS,
            'orders_id' => TABLE_ORDERS,
            'customers_id' => TABLE_ORDERS,
            'customers_name' => TABLE_ORDERS,
            'customers_company' => TABLE_ORDERS,
            'customers_street_address' => TABLE_ORDERS,
            'customers_suburb' => TABLE_ORDERS,
            'customers_city' => TABLE_ORDERS,
            'customers_state' => TABLE_ORDERS,
            'customers_postcode' => TABLE_ORDERS,
            'customers_country' => TABLE_ORDERS,
            'customers_telephone' => TABLE_ORDERS,
            'customers_email_address' => TABLE_ORDERS,
            'products_quantity' => TABLE_ORDERS_PRODUCTS,
            'products_model' => TABLE_ORDERS_PRODUCTS,
            'products_name' => TABLE_ORDERS_PRODUCTS,
        );
        $this->config['export_where_clause'] = 'o.orders_id = op.orders_id AND os.orders_status_id = o.orders_status AND os.language_id = ' . $_SESSION['languages_id'];
        $this->config['export_order_by_clause'] = 'o.orders_id ASC';
        
        // -----
        // Report-specific configuration values.  Set here so that reports that extend this report can make modifications.
        //
        $this->config['ordersproducts_cutoff_field'] = 'products_quantity';
    }
    
}  //-END class DbIoOrdersProductsHandler
