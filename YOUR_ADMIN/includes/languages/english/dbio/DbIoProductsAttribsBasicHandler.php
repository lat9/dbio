<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2024, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.1
//

// -----
// Defines the handler's descriptive text.
//
define('DBIO_PRODUCTSATTRIBSBASIC_DESCRIPTION', 'This report-format supports import/export of the <em>basic</em> products\' attributes\' values. The report, indexed by the associated product\'s <b>model-number</b>, includes one record per product/product-option pair with the option-specific values separated by ^ characters &mdash; using your store\'s <code>DEFAULT_LANGUAGE</code>.<br><br><b>Notes:</b><ol><li>Your store\'s products <em>must</em> each have a unique model number for any &quot;import&quot; action to successfully complete.</li><li>All options\' names and option values\' names <b>must already exist within your database</b> for an associated attributes\' record to be successfully imported.</li><li><b>Only <i>new</i></b> option-combinations will be successfully added!</li></ol>');
