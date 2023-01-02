<?php
// -----
// Part of the Database I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2022, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.0.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit ('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart "Featured Products" import/export.
//
class DbIoFeaturedHandler extends DbIoHandler
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Featured'); 
        return [
            'version' => '2.0.0',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_FEATURED_DESCRIPTION,
        ];
    }

    public function exportInitialize ($language = 'all')
    {
        $initialized = parent::exportInitialize ($language);
        if ($initialized === true) {
            if ($this->where_clause != '') {
                $this->where_clause .= ' AND ';
            }
            $this->where_clause .= "p.products_id = f.products_id";
            $this->order_by_clause .= 'f.products_id ASC';
        }
        return $initialized;
    }

    public function exportPrepareFields (array $fields)
    {
        $fields = parent::exportPrepareFields ($fields);
        unset($fields['products_id'], $fields['featured_id']);

        return $fields;
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
        $this->stats['report_name'] = 'Featured';
        $this->config = self::getHandlerInformation();
        $this->config['keys'] = [
            TABLE_PRODUCTS => [
                'alias' => 'p',
                'products_model' => [
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ],
            ],
            TABLE_FEATURED => [
                'alias' => 'f',
                'products_id' => [
                    'type' => self::DBIO_KEY_IS_FIXED,
                    'match_fixed_key' => 'p.products_id',
                ],
            ],
        ];
        $this->config['tables'] = [
            TABLE_PRODUCTS => [
                'alias' => 'p',
                'key_fields_only' => true,
            ],
            TABLE_FEATURED => [
                'alias' => 'f',
                'io_field_overrides' => [
                    'products_id' => false,
                    'featured_id' => 'no-header',
                ],
            ],
        ];
    }

    protected function importAddField ($table_name, $field_name, $field_value) {
        $this->debugMessage ("Featured::importAddField ($table_name, $field_name, $field_value)");
        switch ($table_name) {
            case TABLE_FEATURED:
                if ($this->import_is_insert === true) {
                    if ($field_name === 'featured_date_added') {
                        $field_value = 'now()';
                    } elseif ($field_name === 'featured_last_modified') {
                        $field_value = self::DBIO_NO_IMPORT;
                    }
                } else {
                    if ($field_name === 'featured_last_modified') {
                        $field_value = 'now()';
                    }
                }
                parent::importAddField ($table_name, $field_name, $field_value);
                break;

            default:
                break;
        }  //-END switch interrogating $table_name
    }  //-END function importAddField
}  //-END class DbIoFeaturedHandler
