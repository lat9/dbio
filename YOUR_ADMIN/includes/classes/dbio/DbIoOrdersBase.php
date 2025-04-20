<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.2
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class provides the common filtering for handlers that are exporting order-related information.
//
class DbIoOrdersBase extends DbIoHandler
{
    public static function getHandlerExportFilters()
    {
        return [
            'orders_status' => [
                'type' => 'select_orders_status',
                'label' => DBIO_ORDERS_ORDERS_STATUS_LABEL,
            ],
            'orders_id_range' => [
                'type' => 'array',
                'label' => DBIO_ORDERS_ORDERS_ID_RANGE_LABEL,
                'fields' => [
                    'orders_id_min' => [
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_ID_MIN_LABEL,
                    ],
                    'orders_id_max' => [
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_ID_MAX_LABEL,
                    ],
                ],
            ],
            'orders_date_range' => [
                'type' => 'array',
                'label' => DBIO_ORDERS_ORDERS_DATE_RANGE_LABEL,
                'fields' => [
                    'orders_date_start' => [
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_DATE_MIN_LABEL,
                    ],
                    'orders_date_end' => [
                        'type' => 'input',
                        'label' => DBIO_ORDERS_ORDERS_DATE_MAX_LABEL,
                    ],
                ],
            ],
        ];
    }

    // -----
    // This function gives the current handler the last opportunity to modify the SQL query clauses used for the current export.  It's
    // usually provided by handlers that use an "export_filter", allowing the handler to inspect any filter-variables provided by
    // the caller.
    //
    // Returns a boolean (true/false) indication of whether the export's initialization was successful.  If unsuccessful, the handler
    // is **assumed** to have set its reason into the class message variable.
    //
    public function exportFinalizeInitialization()
    {
        // -----
        // Check to see if any of this handler's filter variables have been set.  If set, check the values and then
        // update the where_clause for the to-be-issued SQL query for the export.
        //
        if (isset($_POST['orders_status']) && $_POST['orders_status'] !== '0') {
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . 'o.orders_status = ' . (int)$_POST['orders_status'];
        }
        if (!empty($_POST['orders_id_min']) && ctype_digit($_POST['orders_id_min'])) {
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . 'o.orders_id >= ' . (int)$_POST['orders_id_min'];
        }
        if (!empty($_POST['orders_id_max']) && ctype_digit($_POST['orders_id_max'])) {
            $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . 'o.orders_id <= ' . (int)$_POST['orders_id_max'];
        }
        if (!empty($_POST['orders_date_start'])) {
           $validated_date = $this->formatValidateDate($_POST['orders_date_start']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . "o.date_purchased >= '$validated_date 00:00:00'";
            }
        }
        if (!empty($_POST['orders_date_end'])) {
            $validated_date = $this->formatValidateDate($_POST['orders_date_end']);
            if ($validated_date !== false) {
                $this->where_clause .= (($this->where_clause === '') ? '' : ' AND ') . "o.date_purchased <= '$validated_date 23:59:59'";
            }
        }
        return true;
    }

    // -----
    // This abstract function, required to be supplied for the actual handler, is called during the class
    // construction to have the handler set its database-related configuration.  It needs to be defined
    // here for class-inheritance but the actual handlers need to supply it!
    //
    protected function setHandlerConfiguration()
    {
        dbioLogError('The real handler needs to supply this function!');
    }
}  //-END class DbIoOrdersBase
