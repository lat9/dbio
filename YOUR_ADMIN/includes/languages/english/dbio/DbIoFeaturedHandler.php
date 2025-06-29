<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2025, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define ('DBIO_FEATURED_DESCRIPTION', 'This report-format supports import/export of all fields within the &quot;featured&quot; table, the <em>Featured Products</em> information.<br><br><b>Notes</b><ol><li>For an import to be successful, at a minimum the <code>v_products_id</code> column must be present.</li><li>The <code>v_products_id</code> supplied on an import must be associated with a valid product.</li><li>A product can be removed as a featured product by including a <code>v_dbio_command</code> column with <b>REMOVE</b> for a specific products_id.</li></ol>');
