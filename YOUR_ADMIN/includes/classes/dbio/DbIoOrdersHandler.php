<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2017, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the customizations required for a Zen Cart "Orders" table export.  The export
// doesn't include any product- or product-attributes information.
//
class DbIoOrdersHandler extends DbIoOrdersBase
{
    public static function getHandlerInformation ()
    {
        DbIoHandler::loadHandlerMessageFile ('Orders'); 
        return array (
            'version' => '1.3.0',
            'handler_version' => '1.3.0',
            'include_header' => true,
            'export_only' => true,
            'allow_export_customizations' => true,
            'description' => DBIO_ORDERS_DESCRIPTION,
        );
    }
    
    public function exportPrepareFields (array $fields) 
    {
        $fields = parent::exportPrepareFields ($fields);
        
        if (!($this->config['additional_headers']['v_orders_status_name'] & self::DBIO_FLAG_NO_EXPORT)) {
            $orders_status_id = $fields['orders_status'];
            unset ($fields['orders_status']);
            $fields = $this->insertAtCustomizedPosition($fields, 'orders_status_name', $this->getOrdersStatusName($orders_status_id));            
        }
        return $fields;
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->stats['report_name'] = 'Orders';
        $this->config = self::getHandlerInformation ();
        $this->config['tables'] = array (
            TABLE_ORDERS => array (
                'alias' => 'o',
                'io_field_overrides' => array (
                    'orders_status' => 'no-header',
                )                    
            ),
        );
        $this->config['additional_headers'] = array (
            'v_orders_status_name' => self::DBIO_FLAG_FIELD_SELECT,
        );
        $this->config['additional_header_select'] = array (
            'v_orders_status_name' => 'o.orders_status'
        );
    }
    
    private function getOrdersStatusName ($orders_status_id) {
        global $db;
        $check = $db->Execute ("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = " . (int)$orders_status_id . " AND language_id = " . $_SESSION['languages_id'] . " LIMIT 1");
        return ($check->EOF) ? DbIoHandler::DBIO_UNKNOWN_VALUE : $check->fields['orders_status_name'];
    }

}  //-END class DbIoOrdersHandler
