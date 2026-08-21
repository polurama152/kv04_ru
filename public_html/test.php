<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP works<br>";
echo "PHP version: " . phpversion() . "<br>";

// Проверка подключения к БД
require_once($_SERVER['DOCUMENT_ROOT']."/bitrix/php_interface/dbconn.php");

$conn = mysqli_connect($DBHost, $DBLogin, $DBPassword, $DBName);

if ($conn) {
    echo "Database connection: OK<br>";
    echo "Database name: " . $DBName . "<br>";
    mysqli_close($conn);
} else {
    echo "Database connection ERROR: " . mysqli_connect_error();
}
?>