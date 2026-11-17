<?php

require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/Schema.php';

global $pdo, $adminnumber;

$schema = new Schema($pdo, [
    'adminnumber' => $adminnumber,
    'panelusername' => getenv('PANEL_ADMIN_USERNAME') ?: 'admin',
    'panelpassword' => getenv('PANEL_ADMIN_PASSWORD') ?: '',
    'textbotlang' => static fn() => languagechange(),
]);

$schema->renameTables(require __DIR__ . '/renames.php');
$schema->applyTables(__DIR__ . '/tables', require __DIR__ . '/tables.php');
$schema->runMigrations(__DIR__ . '/migrations');
$schema->applyIndexes(require __DIR__ . '/indexes.php');

return $schema;
