<?php

$conn = mysqli_connect("localhost","root","root","store2") or die("nope");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>