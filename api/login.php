<?php

// Preparar sesion, conexion y funciones de respuesta.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Este endpoint solo autentica usuarios por POST.
exigirMetodoPost();

$username = limpiarTexto($_POST["username"] ?? "");
$password = (string) ($_POST["password"] ?? "");

// Validar que el formulario venga completo.
if ($username === "" || $password === "") {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes completar usuario y contrasena.",
    ]);
}

// Buscar el usuario y su hash para verificar credenciales.
$consulta = $conn->prepare(
    "SELECT id, nombre, username, password_hash FROM usuarios_mensajeria WHERE username = ? LIMIT 1"
);
$consulta->bind_param("s", $username);
$consulta->execute();
$resultado = $consulta->get_result();
$usuario = $resultado->fetch_assoc();
$consulta->close();

if (!$usuario || !password_verify($password, $usuario["password_hash"])) {
    responderJson(401, [
        "ok" => false,
        "message" => "Credenciales incorrectas.",
    ]);
}

// Regenerar el identificador de sesion para mayor seguridad.
session_regenerate_id(true);

$_SESSION["user_id"] = (int) $usuario["id"];
$_SESSION["user_name"] = $usuario["nombre"];
$_SESSION["username"] = $usuario["username"];

// Registrar presencia inmediatamente despues del inicio de sesion.
actualizarPresenciaUsuario($conn, (int) $usuario["id"]);

responderJson(200, [
    "ok" => true,
    "message" => "Inicio de sesion exitoso.",
]);

?>
