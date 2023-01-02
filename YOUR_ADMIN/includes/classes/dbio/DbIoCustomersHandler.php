<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2022, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.0.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This dbIO class handles the import and export of "raw" information in the Zen Cart 'customers' table.
//
class DbIoCustomersHandler extends DbIoHandler
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Customers');
        return [
            'version' => '2.0.0',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => true,
            'description' => DBIO_CUSTOMERS_DESCRIPTION,
        ];
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S
// ----------------------------------------------------------------------------------

    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the dbIO operations.
    //
    protected function setHandlerConfiguration()
    {
        $this->stats['report_name'] = 'Customers';
        $this->config = self::getHandlerInformation();
        $this->config['keys'] = [
            TABLE_CUSTOMERS => [
                'alias' => 'c',
                'capture_key_value' => true,
                'customers_id' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_CUSTOMERS => [
                'alias' => 'c',
                'join_clause' => 'INNER JOIN ' . TABLE_ADDRESS_BOOK . ' ab ON ab.address_book_id = c.customers_default_address_id',
            ],
            TABLE_ADDRESS_BOOK => [
                'alias' => 'ab',
                'no_from_clause' => true,
                'io_field_overrides' => [
                    'address_book_id' => false,
                    'customers_id' => false,
                ],
            ], 
        ];
    }
}  //-END class DbIoCustomersHandler
