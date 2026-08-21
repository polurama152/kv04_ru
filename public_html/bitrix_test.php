<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER["DOCUMENT_ROOT"] = __DIR__;
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

echo "Starting Bitrix init...<br>";

try {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    echo "✓ Bitrix loaded successfully!<br>";
    echo "Site encoding: " . SITE_CHARSET . "<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>