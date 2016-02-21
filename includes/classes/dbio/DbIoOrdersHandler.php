<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class handles the customizations required for a Zen Cart "Orders" table export.  The export
// doesn't include any product- or product-attributes information.
//
class DbIoOrdersHandler extends DbIoHandler 
{
    public function __construct ($log_file_suffix)
    {
        include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/DbIoOrdersHandler.php');
        parent::__construct ($log_file_suffix);
    }
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration () 
    {
        $this->stats['report_name'] = 'Orders';
        $this->config = array (
            'version' => '0.0.0',
            'handler_version' => '0.0.0',
            'include_header' => true,
            'export_only' => true,
            'tables' => array (
                TABLE_ORDERS => array (
                    'short_name' => 'o',
                    'io_field_overrides' => array (
                        'orders_status' => 'no-header',
                    )                    
                ),
            ),
            'description' => DBIO_ORDERS_DESCRIPTION,
            'additional_headers' => array (
                'v_orders_status_name' => self::DBIO_FLAG_NONE,
            ),
        );
    }
    
    public function exportPrepareFields (array $fields) 
    {
        $fields = parent::exportPrepareFields ($fields);
        $orders_status_id = $fields['orders_status'];
        unset ($fields['orders_status']);
       
        $fields['orders_status_name'] = $this->getOrdersStatusName ($orders_status_id);

        return $fields;
      
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

    private function getOrdersStatusName ($orders_status_id) {
        global $db;
        $check = $db->Execute ("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = " . (int)$orders_status_id . " AND language_id = " . $_SESSION['languages_id'] . " LIMIT 1");
        return ($check->EOF) ? DbIoHandler::DBIO_UNKNOWN_VALUE : $check->fields['orders_status_name'];
    }

}  //-END class DbIoOrdersHandler
