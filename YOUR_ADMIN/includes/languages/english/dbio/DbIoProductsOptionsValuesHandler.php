<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define(
    'DBIO_PRODUCTSOPTIONSVALUES_DESCRIPTION',
    'This report-format supports import/export of <b>all</b> fields within the <code>products_options_values</code> table.  Use this report to add or change information about your store\'s product options\' values. One record in the DbIo CSV corresponds to one <em>language-specific</em> product option value.' .
    '<br><br>' .
    '<b>Notes:</b>' .
    '<ol>' .
        '<li>Two additional fields, <code>v_products_options_id</code> and <code>v_products_options_name</code>, are included in the export. While the option\'s name is not used (or required) on a record import, the option\'s ID <b>is</b> as its value is used to create a record in the <code>products_options_values_to_products_options</code> table!</li>' .
        '<li>To add a new option-value, set the <code>v_products_options_values_id</code> column to <code>0</code>.</li>' .
    '</ol>'
);
