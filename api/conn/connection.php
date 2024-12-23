<?php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'parceria_Programador_Nelio');
define('DB_PASSWORD', 'alfa1vsomega2M@');
define('DB_NAME', 'parceria_Selfluence');

function getDatabaseConnection()
{
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}