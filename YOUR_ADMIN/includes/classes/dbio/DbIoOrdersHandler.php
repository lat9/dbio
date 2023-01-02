<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2023, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.0.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a Zen Cart "Orders" table export.  The export
// doesn't include any product- or product-attributes information.
//
class DbIoOrdersHandler extends DbIoOrdersBase
{
    protected
        $ot_class_defaults = [];

    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Orders'); 
        return [
            'version' => '2.0.0',
            'handler_version' => '1.3.0',
            'include_header' => true,
            'export_only' => true,
            'allow_export_customizations' => true,
            'description' => DBIO_ORDERS_DESCRIPTION,
        ];
    }

    public function exportPrepareFields(array $fields)
    {
        global $db;

        if (!($this->config['additional_headers']['v_orders_status_name'] & self::DBIO_FLAG_NO_EXPORT)) {
            $orders_status_id = $fields['orders_status'];
            unset($fields['orders_status']);
            $fields = $this->insertAtCustomizedPosition($fields, 'orders_status_name', $this->getOrdersStatusName($orders_status_id));
        }

        $order_totals = $db->Execute(
            "SELECT `class`, `value`
               FROM " . TABLE_ORDERS_TOTAL . "
              WHERE orders_id = " . $fields['orders_id'] . "
                AND `class` NOT IN ('ot_total', 'ot_tax')
              ORDER BY `class` ASC"
        );
        $this_orders_totals = [];
        foreach ($order_totals as $next_total) {
            $this_orders_totals[$next_total['class']] = $next_total['value'];
        }
        $this_orders_totals = array_merge($this->ot_class_defaults, $this_orders_totals);
        
        $fields = array_merge($fields, $this_orders_totals);

        return parent::exportPrepareFields($fields);
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration()
    {
        global $db;

        $this->stats['report_name'] = 'Orders';
        $this->config = self::getHandlerInformation ();
        $this->config['tables'] = [
            TABLE_ORDERS => [
                'alias' => 'o',
                'io_field_overrides' => [
                    'orders_status' => 'no-header',
                ],
            ],
        ];
        $this->config['additional_headers'] = [
            'v_orders_status_name' => self::DBIO_FLAG_FIELD_SELECT,
        ];

        // -----
        // Added in v2.0.0, include the value for each of the order-total types
        // associated with the store's orders.
        //
        $ot_info = $db->Execute(
            "SELECT DISTINCT `class`
               FROM " . TABLE_ORDERS_TOTAL . "
              WHERE `class` NOT IN ('ot_total', 'ot_tax')
              ORDER BY `class` ASC"
        );
        foreach ($ot_info as $next_ot) {
            $this->config['additional_headers']['v_' . $next_ot['class']] = self::DBIO_FLAG_NONE;
            $this->ot_class_defaults[$next_ot['class']] = '0';
        }

        $this->config['additional_header_select'] = [
            'v_orders_status_name' => 'o.orders_status'
        ];
    }

    private function getOrdersStatusName($orders_status_id) {
        global $db;
 
        $check = $db->Execute(
            "SELECT orders_status_name
               FROM " . TABLE_ORDERS_STATUS . "
              WHERE orders_status_id = " . (int)$orders_status_id . "
                AND language_id = " . $_SESSION['languages_id'] . "
              LIMIT 1"
         );
        return ($check->EOF) ? DbIoHandler::DBIO_UNKNOWN_VALUE : $check->fields['orders_status_name'];
    }
}  //-END class DbIoOrdersHandler
