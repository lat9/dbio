<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2020, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

// -----
// This DbIo class handles the customizations required for a basic Zen Cart manufacturers import/export.
//
class DbIoManufacturersHandler extends DbIoHandler 
{
    public static function getHandlerInformation()
    {
        global $db;
        DbIoHandler::loadHandlerMessageFile('Manufacturers'); 
        return array(
            'version' => '1.6.0',
            'handler_version' => '1.2.0',
            'include_header' => true,
            'export_only' => false,
            'description' => DBIO_MANUFACTURERS_DESCRIPTION,
        );
    }

    // -----
    // This function, called at the beginning of an export operation, gives the handler an opportunity to perform
    // some special checks.
    // 
    public function exportInitialize($language = 'all') 
    {
        $initialized = parent::exportInitialize($language);
        if ($initialized) {
            if ($this->where_clause != '') {
                $this->where_clause .= ' AND ';
        
            }
            $export_language = ($this->export_language == 'all') ? $this->languages[$this->first_language_code] : $this->languages[$this->export_language];
            $this->where_clause .= "m.manufacturers_id = mi.manufacturers_id AND mi.languages_id = $export_language";
            $this->order_by_clause .= 'm.manufacturers_id ASC';
            
            $this->saved_data['manufacturers_info_sql'] = 
                'SELECT * FROM ' . TABLE_MANUFACTURERS_INFO . ' WHERE manufacturers_id = %u AND languages_id = %u LIMIT 1';
        }
        return $initialized;
    }
    
   
    public function exportPrepareFields(array $fields) 
    {
        global $db;
        
        $fields = parent::exportPrepareFields($fields);
        $default_language_code = $this->first_language_code;

        if ($this->export_language == 'all') {
            $this->debugMessage('Manufacturers::exportPrepareFields, language = ' . $this->export_language . ', default language = ' . $default_language_code . ', sql: ' . $this->saved_data['manufacturers_info_sql'] . ', languages: ' . print_r($this->languages, true));
            $manufacturers_id = $fields['manufacturers_id'];
            foreach ($this->languages as $language_code => $language_id) {
                if ($language_code != $default_language_code) {
                    $description_info = $db->Execute(sprintf($this->saved_data['manufacturers_info_sql'], $manufacturers_id, $language_id));
                    if (!$description_info->EOF) {
                        $encoded_fields = $this->exportEncodeData($description_info->fields);
                        foreach ($encoded_fields as $field_name => $field_value) {
                            if ($field_name != 'manufacturers_id' && $field_name != 'languages_id') {
                                $fields[$field_name . '_' . $language_code] = $field_value;
                            }
                        }
                    }
                }
            }
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
    protected function setHandlerConfiguration() 
    {
        $this->stats['report_name'] = 'Manufacturers';
        $this->config = self::getHandlerInformation();
        $this->config['keys'] = array(
            TABLE_MANUFACTURERS => array(
                'alias' => 'm',
                'capture_key_value' => true,
                'manufacturers_id' => array(
                    'type' => self::DBIO_KEY_IS_VARIABLE | self::DBIO_KEY_SELECTED,
                ),
            ),
        );
        $this->config['tables'] = array(
            TABLE_MANUFACTURERS => array( 
                'alias' => 'm',
            ), 
            TABLE_MANUFACTURERS_INFO => array( 
                'alias' => 'mi',
                'language_field' => 'languages_id',
                'io_field_overrides' => array(
                    'manufacturers_id' => false,
                    'languages_id' => false,
                ),
            ), 
        );
    }

    // -----
    // This function, issued just prior to the database action, allows the I/O handler to make any "last-minute" changes based
    // on the record's 'key' value -- for this report, it's the products_id value.
    //
    // If we're doing an insert (i.e. a new manufacturer), simply add the manufacturers_id field to the non-manufacturers tables' SQL
    // input array.
    //
    // If we're doing an update (i.e. existing manufacturer), need to update the primary where-clause to make sure that the
    // manufacturers table alias isn't part of the string for the non-manufacturers table.
    //
    protected function importUpdateRecordKey($table_name, $table_fields, $products_id) 
    {
        if ($table_name != TABLE_MANUFACTURERS) {
            if ($this->import_is_insert) {
                $table_fields['manufacturers_id'] = array('value' => $products_id, 'type' => 'integer');
            } else {
                $this->where_clause = 'manufacturers_id = ' . (int)$this->key_fields['manufacturers_id'];
            }
        }
        return parent::importUpdateRecordKey($table_name, $table_fields, $products_id);
    }

}  //-END class DbIoManufacturersHandler
