<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bloque de configuracion principal para conectarse a MySQL.
$Servidor = "192.168.50.145";
//$Usuario = "root";
//$password = "";

$Usuario = "byron";
$password = "contraseña";

$BaseDeDatos = "bd_mensajeria";

// Crear conexion reutilizable para todos los scripts del sistema.
$conn = new mysqli($Servidor, $Usuario, $password, $BaseDeDatos);

// Ajustar el juego de caracteres para soportar texto normal y simbolos comunes.
$conn->set_charset("utf8mb4");

// Verificar conexion antes de continuar con cualquier operacion.
if ($conn->connect_error) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "ok" => false,
        "message" => "Error de conexión a la base de datos: " . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
