<?php
require_once($_SERVER['DOCUMENT_ROOT']."/bitrix/php_interface/dbconn.php");

$conn = mysqli_connect($DBHost, $DBLogin, $DBPassword, $DBName);

$result = mysqli_query($conn, "SHOW VARIABLES LIKE 'character_set%'");
echo "<h3>Database encoding:</h3>";
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Variable_name'] . ": " . $row['Value'] . "<br>";
}

$result = mysqli_query($conn, "SHOW TABLE STATUS");
echo "<br><h3>Tables encoding:</h3>";
$wrong_encoding = false;
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['Collation'] && !preg_match('/utf8/', $row['Collation'])) {
        echo "⚠ " . $row['Name'] . ": <strong>" . $row['Collation'] . "</strong><br>";
        $wrong_encoding = true;
    }
}

if (!$wrong_encoding) {
    echo "✓ All tables use UTF-8 encoding<br>";
}

mysqli_close($conn);
?>