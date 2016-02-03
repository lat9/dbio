<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

if (!class_exists ('dbio_products')) {
  if (!class_exists ('dbio_handler')) {
    require (DIR_FS_DBIO . 'class.dbio_handler.php');
    
  }
  // -----
  // This dbIO report class handles the customizations required for a Zen Cart product import/export.
  //
  class dbio_products extends dbio_handler {
    function __construct ($log_file_suffix, array $languages) {
      $this->config = array (
        'key' => array ( 'table' => TABLE_PRODUCTS, 'match_field' => 'products_model', 'key_field' => 'products_id', 'key_field_type' => 'integer' ),
        'include_header' => true,
        'tables' => array ( 
          TABLE_PRODUCTS => array ( 
            'short_name' => 'p',
            'insert_now' => 'products_date_added',
            'update_now' => 'products_last_modified',
          ), 
          TABLE_PRODUCTS_DESCRIPTION => array ( 
            'short_name' => 'pd',
            'language_field_name' => 'language_id',
            'key_field' => 'products_id',
          ), 
        ), 
        'description' => array (
          'en' => "This report-format supports import/export of all fields within the <code>products</code> and <code>products_description</code> tables, the basic product information.",
        ),
      );
      parent::__construct ($log_file_suffix, $languages);
      
    }
    
    function export_initialize ($language = 'all') {
      parent::export_initialize ($language);
      if ($this->export['where'] != '') {
        $this->export['where'] .= ' AND ';
        
      }

      $export_language = ($this->export_language == 'all') ? 1 : $this->languages[$this->export_language];
      $this->export['where'] .= "p.products_id = pd.products_id AND pd.language_id = $export_language";
      $this->export['order_by'] .= 'p.products_id ASC';
      
      $this->export['headers'][] = 'v_manufacturers_name';
      $this->export['headers'][] = 'v_tax_class_title';
      $this->export['headers'][] = 'v_categories_name';
      
      $this->export[TABLE_PRODUCTS_DESCRIPTION]['language_sql'] = 
        'SELECT ' . $this->export[TABLE_PRODUCTS_DESCRIPTION]['select'] . ' FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' WHERE products_id = %u AND language_id = %u LIMIT 1';
      
    }
    
    function export_get_sql ($sql_limit = '') {
      return parent::export_get_sql ($sql_limit);
      
    }
    
    function export_prepare_fields (array $fields) {
      $fields = parent::export_prepare_fields ($fields);
      
      $products_id = $fields['products_id'];
      $tax_class_id = $fields['products_tax_class_id'];
      unset ($fields['products_id'], $fields['products_tax_class_id']);
      
      global $db;     
      if ($this->export_language == 'all') {
        foreach ($this->languages as $language_code => $language_id) {
          if ($language_id != 1) {
            $description_info = $db->Execute (sprintf ($this->export[TABLE_PRODUCTS_DESCRIPTION]['language_sql'], $products_id, $language_id));
            if (!$description_info->EOF) {
              foreach ($description_info->fields as $field_name => $field_value) {
                if ($field_name != 'products_id' && $field_name != 'language_id') {
                  $fields[$field_name . '_' . $language_id] = $field_value;
                  
                }
              }
            }
          }
        }
      }
      
      $fields['manufacturers_name'] = zen_get_products_manufacturers_name ($products_id);
      
      $tax_class_info = $db->Execute ("SELECT tax_class_title FROM " . TABLE_TAX_CLASS . " WHERE tax_class_id = $tax_class_id LIMIT 1");
      $fields['tax_class_title'] = ($tax_class_info->EOF) ? '' : $tax_class_info->fields['tax_class_title'];
      
      $cPath_array = explode ('_', zen_get_product_path ($products_id));
      $categories_name = '';
      foreach ($cPath_array as $next_category_id) {
        $category_info = $db->Execute ("SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = $next_category_id AND language_id = 1 LIMIT 1");
        $categories_name .= (($category_info->EOF) ? '--unknown--' : $category_info->fields['categories_name']) . '^';
        
      }
      $fields['categories_name'] = substr ($categories_name, 0, -1);
      
      return $fields;
      
    }
    
    function import_initialize ($language = 'all', $operation = 'check') {
      parent::import_initialize ($language, $operation);
      
      $this->import['headers'][] = 'v_manufacturers_name';
      $this->import['headers'][] = 'v_tax_class_title';
      $this->import['headers'][] = 'v_categories_name';
      
    }
  
// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

    function _initialize () {
      parent::_initialize ();
      
      $this->tables[TABLE_PRODUCTS]['fields']['products_id']['include_in_export'] = 'no-header';
      $this->tables[TABLE_PRODUCTS]['fields']['manufacturers_id']['include_in_export'] = false;
      $this->tables[TABLE_PRODUCTS]['fields']['products_tax_class_id']['include_in_export'] = 'no-header';
      $this->tables[TABLE_PRODUCTS]['fields']['master_categories_id']['include_in_export'] = false;
      
      $this->tables[TABLE_PRODUCTS_DESCRIPTION]['fields']['products_id']['include_in_export'] = false;
      $this->tables[TABLE_PRODUCTS_DESCRIPTION]['fields']['language_id']['include_in_export'] = false;
      
    }
    
    protected function import_field_check ($field_name) {
      switch ($field_name) {
        case 'products_id':
        case 'language_id':
        case 'manufacturers_id':
        case 'products_tax_class_id':
        case 'master_categories_id': {
          $field_name = DBIO_NO_IMPORT;
          break;
        }
        default: {
          break;
        }
      }
      return $field_name;
      
    }
    
    protected function import_finalize_fields ($data) {
      return true;
    }
    
    protected function add_import_field ($table_name, $field_name, $field_value, $field_type) {
      global $db;
      switch ($field_name) {
        case 'manufacturers_name': {
          if (empty ($field_value)) {
            $manufacturers_id = 0;
            
          } else {
            $manufacturer_check_sql = "SELECT manufacturers_id FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_name = :manufacturer_name: LIMIT 1";
            $manufacturer_check = $db->Execute ($db->bindVars ($manufacturer_check_sql, ':manufacturer_name:', $field_value, 'string'));
            if (!$manufacturer_check->EOF) {
              $manufacturers_id = $manufacturer_check->fields['manufacturers_id'];
              
            } else {
              $this->log_message ("Import, creating database entry for manufacturer named \"$field_value\"");
              $sql_data_array = array ();
              $sql_data_array[] = array ( 'fieldName' => 'manufacturers_name', 'value' => $field_value, 'type' => 'string' );
              $sql_data_array[] = array ( 'fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring' );
              $db->perform (TABLE_MANUFACTURERS, $sql_data_array);
              $manufacturers_id = $db->Insert_ID();
              
              foreach ($this->languages as $language_code => $language_id) {
                $sql_data_array = array ();
                $sql_data_array[] = array ( 'fieldName' => 'manufacturers_id', 'value' => $manufacturers_id, 'type' => 'integer' );
                $sql_data_array[] = array ( 'fieldName' => 'languages_id', 'value' => $language_id, 'type' => 'integer' );
                $db->perform (TABLE_MANUFACTURERS_INFO, $sql_data_array);
                
              }
            }
          }
          parent::add_import_field (TABLE_PRODUCTS, 'manufacturers_id', $manufacturers_id, 'integer');
          break;
        }
        case 'tax_class_title': {
          $tax_class_check_sql = "SELECT tax_class_id FROM " . TABLE_TAX_CLASS . " WHERE tax_class_title = :tax_class_title: LIMIT 1";
          $tax_class_check = $db->Execute ($db->bindVars ($tax_class_check_sql, ':tax_class_title:', $field_value, 'string'));
          $tax_class_id = ($tax_class_check->EOF) ? 0 : $tax_class_check->fields['tax_class_id'];
          parent::add_import_field (TABLE_PRODUCTS, 'products_tax_class_id', $tax_class_id, 'integer');
          break;
        }
        case 'categories_name': {
          $parent_category = 0;
          $categories = explode ('^', $field_value);
          foreach ($categories as $current_category_name) {
            $category_info_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                                   WHERE c.parent_id = $parent_category 
                                     AND c.categories_id = cd.categories_id
                                     AND cd.categories_name = :categories_name: 
                                     AND cd.language_id = 1 LIMIT 1";
            $category_info = $db->Execute ($db->bindVars ($category_info_sql, ':categories_name:', $current_category_name, 'string'));
            if (!$category_info->EOF) {
              $parent_category = $category_info->fields['categories_id'];
              
            } else {
              $this->debug_message ("[*] Creating category named \"$current_category_name\", with parent category $parent_category.");
              
              $sql_data_array = array ();
              $sql_data_array[] = array ( 'fieldName' => 'parent_id', 'value' => $parent_category, 'type' => 'integer' );
              $sql_data_array[] = array ( 'fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring' );
              $db->perform (TABLE_CATEGORIES, $sql_data_array);
              
              $categories_id = $db->insert_ID ();
              foreach ($this->languages as $language_code => $language_id) {
                $sql_data_array = array ();
                $sql_data_array[] = array ( 'fieldName' => 'categories_id', 'value' => $categories_id, 'type' => 'integer' );
                $sql_data_array[] = array ( 'fieldName' => 'language_id', 'value' => $language_id, 'type' => 'integer' );
                $sql_data_array[] = array ( 'fieldName' => 'categories_name', 'value' => $current_category_name, 'type' => 'string' );
                $db->perform (TABLE_CATEGORIES_DESCRIPTION, $sql_data_array);
                
              }
              $parent_category = $categories_id;
              
            }
          }
          
          break;
        }
        default: {
          parent::add_import_field ($table_name, $field_name, $field_value, $field_type);
          break;
        }
      }
    }
    
  }  //-END class dbio_products
  
}