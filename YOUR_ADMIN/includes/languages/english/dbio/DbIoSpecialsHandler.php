<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2020, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define('DBIO_SPECIALS_DESCRIPTION', 'This report-format supports import/export of all fields within the <code>specials</code> table, the <em>Specials Products</em> information.<br><br><b>Notes</b><ol><li>For an import to be successful, at a minimum the <code>v_products_id</code> and <code>v_specials_new_products_price</code> columns must be present.</li><li>The <code>v_products_id</code> supplied on an import must be associated with a valid product.</li><li>The <code>v_specials_new_products_price</code> value can be either a <em>specific</em> sales price, e.g. <em>5.99</em>, or a percent-off, e.g. <em>7.5%</em>.  The special price for a percent-off value is calculated using the product\'s current base price.</li><li>Unless <b><em>Modules::Order Total::Gift Certificates</em></b> is enabled and configured to enable Gift Certificates to be placed on special, such products cannot be placed on special.</li><li>A product\'s special price can be removed from the database by including a <code>v_dbio_command</code> column with <b>REMOVE</b> for a specific products_id.</li></ol>');
