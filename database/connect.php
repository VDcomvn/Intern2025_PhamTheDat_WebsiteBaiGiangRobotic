<?php
$server = 'localhost';
$user = 'root';
$pass = '';
$database = 'letscode';

$conn = new mysqli($server, $user, $pass, $database);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
} else {
    mysqli_query($conn, "SET NAMES 'utf8'");
}
?>