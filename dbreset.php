<?php
require_once __DIR__ . '/vendor/autoload.php';

use Bucky\Database;

$db = new Database();
$db->initialize();

echo "Database setup successful.\n";
