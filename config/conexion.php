<?php

// Bloque de configuracion principal para conectarse a MySQL.
$Servidor = "localhost";
$Usuario = "root";
$password = "";
$BaseDeDatos = "bd_mensajeria";

// Crear conexion reutilizable para todos los scripts del sistema.
$conn = new mysqli($Servidor, $Usuario, $password, $BaseDeDatos);

// Ajustar el juego de caracteres para soportar texto normal y simbolos comunes.
$conn->set_charset("utf8mb4");

// Verificar conexion antes de continuar con cualquier operacion.
if ($conn->connect_error) {
    die("Conexion fallida: " . $conn->connect_error);
}

/*
|--------------------------------------------------------------------------
| Nota importante
|--------------------------------------------------------------------------
| El codigo base solicitado por el usuario se mantiene en este archivo
| separado. No se imprime "Conexion exitosa" ni se cierra la conexion aqui
| porque este archivo sera incluido por varios endpoints y todos necesitan
| usar la conexion activa durante su ejecucion.
*/

?>
