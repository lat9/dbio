<?php
// -----
// Part of the DataBase I/O Manager (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
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
    public static function getHandlerInformation ()
    {
        DbIoHandler::loadHandlerMessageFile ('OrdersProducts'); 
        return array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
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
        return $fields;
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
        // -----
        // Check to see if any of this handler's filter variables have been set.  If set, check the values and then
        // update the where_clause for the to-be-issued SQL query for the export.
        //
        if ($_POST['orders_status'] != '0') {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'orders_status = ' . (int)$_POST['orders_status'];
        }
        if (zen_not_null ($_POST['orders_id_min']) && ctype_digit ($_POST['orders_id_min'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'orders_id >= ' . (int)$_POST['orders_id_min'];
        }
        if (zen_not_null ($_POST['orders_id_max']) && ctype_digit ($_POST['orders_id_max'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'orders_id <= ' . (int)$_POST['orders_id_max'];
        }
        if (zen_not_null ($_POST['orders_date_start'])) {
           $validated_date = $this->formatValidateDate ($_POST['orders_date_start']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "date_purchased >= '$validated_date 00:00:00'";
            }
        }
        if (zen_not_null ($_POST['orders_date_end'])) {
            $validated_date = $this->formatValidateDate ($_POST['orders_date_end']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "date_purchased <= '$validated_date 23:59:59'";
            }
        }
        return true;
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
