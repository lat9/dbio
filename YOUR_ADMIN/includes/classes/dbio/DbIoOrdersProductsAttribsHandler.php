<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the customizations required for a basic Zen Cart "Orders Products/Attributes" export-only.  The class
// provides its own header to limit the processing output.
//
class DbIoOrdersProductsAttribsHandler extends DbIoOrdersProductsHandler 
{
    public static function getHandlerInformation ()
    {
        DbIoHandler::loadHandlerMessageFile ('OrdersProductsAttribs'); 
        $handler_info = parent::getHandlerInformation ();
        $handler_info['description'] = DBIO_ORDERSPRODUCTSATTRIBS_DESCRIPTION;
        
        return $handler_info;
    }
    
    // -----
    // Let the OrdersProducts handler do its thing, gathering the base order and product information, then check to
    // see if the current product has any attributes ... and add them.  Note that
    //
    public function exportPrepareFields (array $fields) 
    {
        $fields = parent::exportPrepareFields ($fields);
        if (!isset ($this->last_orders_products_id_handled) || $this->last_orders_products_id_handled !== $fields['orders_products_id']) {
            $this->last_orders_products_id_handled = $fields['orders_products_id'];
        } elseif (isset ($this->config['ordersproductsattribs_cutoff_field'])) {
            foreach ($fields as $field_name => &$field_value) {
                if ($field_name == $this->config['ordersproductsattribs_cutoff_field']) {
                    break;
                }
                $field_value = '';
            }
        }
        unset ($fields['orders_products_id']);
        return $fields;
    }
    
// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.  Since this handler "extends" the OrdersProducts handler, let
    // that handler provide the default configuration, then make extension-specific overrides.
    //
    protected function setHandlerConfiguration () 
    {
        parent::setHandlerConfiguration ();
        $this->stats['report_name'] = 'OrdersProductsAttribs';
        $this->config['tables'][TABLE_ORDERS_PRODUCTS]['join_clause'] = 'LEFT JOIN ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' opa ON op.orders_products_id = opa.orders_products_id';
        $this->config['tables'][TABLE_ORDERS_PRODUCTS_ATTRIBUTES] = array (
            'alias' => 'opa',
            'no_from_clause' => true,
        );
        $additional_headers = array (
            'products_options' => TABLE_ORDERS_PRODUCTS_ATTRIBUTES,
            'products_options_values' => TABLE_ORDERS_PRODUCTS_ATTRIBUTES,
            'orders_products_id' => TABLE_ORDERS_PRODUCTS,
        );
        $this->config['fixed_headers'] += $additional_headers;
        if (!isset ($this->config['fixed_fields_no_header'])) {
            $this->config['fixed_fields_no_headers'] = array ();
        }
        $this->config['fixed_fields_no_header'][] = 'orders_products_id';
        $this->config['export_order_by_clause'] .= ', op.orders_products_id ASC, opa.orders_products_attributes_id ASC';
        $this->config['description'] = DBIO_ORDERSPRODUCTSATTRIBS_DESCRIPTION;
        $this->config['ordersproductsattribs_cutoff_field'] = 'products_options';
    }

}  //-END class DbIoOrdersProductsAttribsHandler
