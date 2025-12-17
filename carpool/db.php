<?php
$host = 'localhost';
$db   = 'carpool_db';
$user = 'root';
$pass = ''; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Ошибка подключения к БД: " . $conn->connect_error);
}

$conn->set_charset("utf8");
session_start();
?>