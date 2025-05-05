
<?php
$localhost = "localhost";
$username = "root";
$password = "";
$database = "system";

$connection = new mysqli($localhost, $username, $password, $database);


if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}
