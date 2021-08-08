<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2020, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define('DBIO_PRODUCTSATTRIBSRAW_DESCRIPTION', 'This report-format supports import/export of <b>all</b> fields within the <code>products_attributes</code> and <code>products_attributes_download</code> tables, allowing you to add or change information about your store\'s product-attributes. Use the <code>v_dbio_command</code> column, set to <b>REMOVE</b>, to remove an option/value pair from a product.<br><br>One line in the CSV file corresponds to a single product-specific attribute.  All product-option and product-option-value entries must pre-exist in the database for a successful import of the attribute-related information.<br><br>The export includes each option and option-value name, <em>in your store\'s default language</em>, to make the output a bit easier to read.  These values are not used for an import action.');
