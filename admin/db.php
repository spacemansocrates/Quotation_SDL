<?php
$host = 'srv582.hstgr.io';
$db   = 'u789944046_suppliesdirect';
$user = 'u789944046_socrates';
$pass = 'Naho1386';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
