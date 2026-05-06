<?php

// Endpoint para agregar un nuevo contacto por nombre de usuario.
require_once __DIR__ . "/../config/api_bootstrap.php";

exigirMetodoPost();
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$username = limpiarTexto($_POST["username"] ?? "");

if ($username === "") {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes escribir el nombre de usuario del contacto.",
    ]);
}

// Buscar al usuario por su username para crear la relacion de contacto.
$consulta = $conn->prepare(
    "SELECT id, nombre, username FROM usuarios_mensajeria WHERE username = ? LIMIT 1"
);
$consulta->bind_param("s", $username);
$consulta->execute();
$resultado = $consulta->get_result();
$contacto = $resultado->fetch_assoc();
$consulta->close();

if (!$contacto) {
    responderJson(404, [
        "ok" => false,
        "message" => "No existe un usuario con ese nombre.",
    ]);
}

if ((int) $contacto["id"] === $usuarioId) {
    responderJson(422, [
        "ok" => false,
        "message" => "No puedes agregarte a ti mismo como contacto.",
    ]);
}

// Insertar o refrescar el contacto en la agenda del usuario actual.
$consulta = $conn->prepare(
    "INSERT INTO contactos_mensajeria (usuario_id, contacto_id)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE updated_at = NOW()"
);
$contactoId = (int) $contacto["id"];
$consulta->bind_param("ii", $usuarioId, $contactoId);
$consulta->execute();
$consulta->close();

responderJson(201, [
    "ok" => true,
    "message" => "Contacto agregado correctamente.",
    "contact" => [
        "id" => $contactoId,
        "nombre" => $contacto["nombre"],
        "username" => $contacto["username"],
    ],
]);

?>
