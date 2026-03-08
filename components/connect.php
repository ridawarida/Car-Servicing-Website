<?php
//$db_name = 'mysql: host=localhost; dbname=carservice_db';
//$username = 'root';
//$password = '';

$host = 'sql201.infinityfree.com';
$database = 'if0_41338446_carservice_db';
$username = 'if0_41338446';
$password = 'WY1g3QI9olWl';

$conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
if (!$conn) {
   echo "Failed to connect to the database.";
}
?>

