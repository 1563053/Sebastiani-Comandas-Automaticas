<?php
$server = "localhost";     
$user   = "root";
$pass   = "";                  
$db     = "sebastiani";      

$conexion = new mysqli($server, $user, $pass, $db);
$conexion->set_charset("utf8mb4");

if ($conexion->connect_errno) {
    die("[+] Error en la conexión: " . $conexion->connect_error);
}
?>
