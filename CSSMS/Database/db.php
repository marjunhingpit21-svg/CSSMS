<?php

$conn = mysqli_connect("localhost","root","","trendywear_store") or die("nope");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>