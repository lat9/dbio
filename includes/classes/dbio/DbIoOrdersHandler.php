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
            'export_filters' => array (
                'orders_id_range' => array (
                    'type' => 'array',
                    'label' => DBIO_ORDERS_ORDERS_ID_RANGE_LABEL,
                    'fields' => array (
                        'orders_id_min' => array (
                            'type' => 'input',
                            'label' => DBIO_ORDERS_ORDERS_ID_MIN_LABEL,
                        ),
                        'orders_id_max' => array (
                            'type' => 'input',
                            'label' => DBIO_ORDERS_ORDERS_ID_MAX_LABEL,
                        ),
                    ),
                ),
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
        if (zen_not_null ($_POST['orders_id_min']) && ctype_digit ($_POST['orders_id_min'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'orders_id >= ' . (int)$_POST['orders_id_min'];
        }
        if (zen_not_null ($_POST['orders_id_max']) && ctype_digit ($_POST['orders_id_max'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'orders_id <= ' . (int)$_POST['orders_id_max'];
        }        
        return true;
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
