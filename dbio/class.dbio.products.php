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
    function __construct (array $languages) {
      $this->config = array (
        'key' => array ( TABLE_PRODUCTS => 'products_model' ),
        'include_header' => true,
        'tables' => array ( 
          TABLE_PRODUCTS => array ( 
            'short_name' => 'p',
            'insert_now' => 'products_date_added',
            'update_now' => 'products_last_modified',
          ), 
          TABLE_PRODUCTS_DESCRIPTION => array ( 
            'short_name' => 'pd',
          ), 
        ), 
        'description' => array (
          'en' => "This report-format supports import/export of all fields within the <code>products</code> and <code>products_description</code> tables, the basic product information.",
        ),
      );
      parent::__construct ($languages);
      
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
    
  }  //-END class dbio_products
  
}