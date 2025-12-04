<?php

$conn = mysqli_connect("localhost","root","","altiere2") or die("nope");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>