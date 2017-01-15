<?php
// -----
// Part of the DataBase I/O Manager (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
}

// -----
// This dbIO class provides the common filtering for handlers that are exporting order-related information.
//
class DbIoOrdersBase extends DbIoHandler 
{
    public static function getHandlerExportFilters ()
    {
        return array (
            'orders_status' => array (
                'type' => 'select_orders_status',
                'label' => DBIO_ORDERS_ORDERS_STATUS_LABEL,
            ),
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
            'orders_date_range' => array (
                'type' => 'array',
                'label' => DBIO_ORDERS_ORDERS_DATE_RANGE_LABEL,
                'fields' => array (
                    'orders_date_start' => array (
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_DATE_MIN_LABEL,
                    ),
                    'orders_date_end' => array (
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_DATE_MAX_LABEL,
                    ),
                ),
            ),
        );
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
        if (isset ($_POST['orders_status']) && $_POST['orders_status'] != '0') {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'o.orders_status = ' . (int)$_POST['orders_status'];
        }
        if (isset ($_POST['orders_id_min']) && zen_not_null ($_POST['orders_id_min']) && ctype_digit ($_POST['orders_id_min'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'o.orders_id >= ' . (int)$_POST['orders_id_min'];
        }
        if (isset ($_POST['orders_id_max']) && zen_not_null ($_POST['orders_id_max']) && ctype_digit ($_POST['orders_id_max'])) {
            $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . 'o.orders_id <= ' . (int)$_POST['orders_id_max'];
        }
        if (isset ($_POST['orders_date_start']) && zen_not_null ($_POST['orders_date_start'])) {
           $validated_date = $this->formatValidateDate ($_POST['orders_date_start']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "o.date_purchased >= '$validated_date 00:00:00'";
            }
        }
        if (isset ($_POST['orders_date_end']) && zen_not_null ($_POST['orders_date_end'])) {
            $validated_date = $this->formatValidateDate ($_POST['orders_date_end']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause == '') ? '' : ' AND ') . "o.date_purchased <= '$validated_date 23:59:59'";
            }
        }
        return true;
    }
    
    // -----
    // This abstract function, required to be supplied for the actual handler, is called during the class
    // construction to have the handler set its database-related configuration.  It needs to be defined
    // here for class-inheritance but the actual handlers need to supply it!
    //
    protected function setHandlerConfiguration ()
    {
        trigger_error ('The real handler needs to supply this function!', E_USER_ERROR);
    }
    
}  //-END class DbIoOrdersBase
