<?php

// Preparar herramientas compartidas para responder en JSON.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Este endpoint solo crea cuentas nuevas por POST.
exigirMetodoPost();

$nombre = limpiarTexto($_POST["nombre"] ?? "");
$username = limpiarTexto($_POST["username"] ?? "");
$password = (string) ($_POST["password"] ?? "");
$confirmPassword = (string) ($_POST["confirm_password"] ?? "");

// Validar los campos minimos del formulario.
if ($nombre === "" || $username === "" || $password === "" || $confirmPassword === "") {
    responderJson(422, [
        "ok" => false,
        "message" => "Todos los campos del registro son obligatorios.",
    ]);
}

if (strlen($nombre) < 3) {
    responderJson(422, [
        "ok" => false,
        "message" => "El nombre debe tener al menos 3 caracteres.",
    ]);
}

if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    responderJson(422, [
        "ok" => false,
        "message" => "El usuario debe tener entre 4 y 20 caracteres y solo usar letras, numeros o guion bajo.",
    ]);
}

if (strlen($password) < 6) {
    responderJson(422, [
        "ok" => false,
        "message" => "La contrasena debe tener al menos 6 caracteres.",
    ]);
}

if ($password !== $confirmPassword) {
    responderJson(422, [
        "ok" => false,
        "message" => "Las contrasenas no coinciden.",
    ]);
}

// Revisar si el nombre de usuario ya existe antes de insertar.
$verificar = $conn->prepare("SELECT id FROM usuarios_mensajeria WHERE username = ? LIMIT 1");
$verificar->bind_param("s", $username);
$verificar->execute();
$resultado = $verificar->get_result();

if ($resultado->num_rows > 0) {
    $verificar->close();
    responderJson(409, [
        "ok" => false,
        "message" => "Ese nombre de usuario ya esta registrado.",
    ]);
}

$verificar->close();

// Cifrar la contrasena para no guardarla en texto plano.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insertar el nuevo usuario en la base de datos.
$insertar = $conn->prepare(
    "INSERT INTO usuarios_mensajeria (nombre, username, password_hash) VALUES (?, ?, ?)"
);
$insertar->bind_param("sss", $nombre, $username, $passwordHash);

if (!$insertar->execute()) {
    $insertar->close();
    responderJson(500, [
        "ok" => false,
        "message" => "No fue posible crear la cuenta en este momento.",
    ]);
}

$insertar->close();

responderJson(201, [
    "ok" => true,
    "message" => "Cuenta creada correctamente. Ya puedes iniciar sesion.",
]);

?>
