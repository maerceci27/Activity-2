<?php
$servername = "localhost";
$username = "root"; // your DB username
$password = "";     // your DB password
$dbname = "user_system"; // your DB name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
