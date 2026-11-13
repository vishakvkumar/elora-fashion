<?php
// GET api/store.php — the public catalogue: settings, categories and visible products.
require __DIR__ . '/_bootstrap.php';
$s = load_store();
json_out(['ok' => true, 'settings' => $s['settings'], 'categories' => array_values($s['categories']), 'products' => array_values($s['products'])]);
