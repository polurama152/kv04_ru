<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once($_SERVER['DOCUMENT_ROOT']."/bitrix/php_interface/dbconn.php");

$conn = mysqli_connect($DBHost, $DBLogin, $DBPassword, $DBName);
mysqli_set_charset($conn, 'utf8');

// Критичные таблицы Bitrix
$critical_tables = [
    'b_option',
    'b_module',
    'b_module_to_module',
    'b_lang',
    'b_site_template',
    'b_event_type',
    'b_agent'
];

echo "<h3>Checking critical Bitrix tables:</h3>";

foreach ($critical_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        // Проверяем количество записей
        $count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$table`");
        $count = mysqli_fetch_assoc($count_result)['cnt'];
        echo "✓ $table - OK ($count rows)<br>";
    } else {
        echo "✗ $table - <strong>NOT FOUND!</strong><br>";
    }
}

// Проверим общее количество таблиц
$all_tables = mysqli_query($conn, "SHOW TABLES");
$total = mysqli_num_rows($all_tables);
echo "<br><strong>Total tables in database: $total</strong><br>";

mysqli_close($conn);
?>