<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2018, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define('DBIO_PRODUCTSDISCOUNTQUANTITY_DESCRIPTION', 'This report-format supports import/export of fields within the <code>products</code> and <code>products_discount_quantity</code> tables that are associated with a product\'s quantity-discounts information.  A product\'s export-record includes its <code>v_products_model</code> as a reference value, but is not used on the import.  A matching import record is based <em>solely</em> on a record\'s <code>v_products_id</code> column.<br><br>Each product\'s <em>quantity-discount</em> information, if specified, is formatted as <code>q:p[;q:p]...</code> where <b>q</b> is a <em>unique</em> product quantity that achieves a <b>p</b> discounted price.<br><br>A product\'s <code>products_discount_type</code> value can be one of <ol start="0"><li><b>None</b>: No discount; any defined discount-quantities will be removed.</li><li><b>Percentage</b>: The <em>Price</em> is a percentage discount.</li><li><b>Actual Price</b>: The <em>Price</em> is the actual discounted price.</li><li><b>Amount Off</b>: The <em>Price</em> specified is the fixed amount discount.</li></ol><br>A product\'s <code>products_discount_type_from</code> value can be one of <ol start="0"><li><b>Price</b>: Any <em>Percentage</em> or <em>Amount Off</em> discount is discounted from the product\'s &quot;normal&quot; price.</li><li><b>Special</b>: Any <em>Percentage</em> or <em>Amount Off</em> discount is discounted from the product\'s &quot;special&quot; price.</li></ol>');
