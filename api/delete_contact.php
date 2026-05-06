<?php

// Endpoint para quitar un contacto de la agenda del usuario.
require_once __DIR__ . "/../config/api_bootstrap.php";

exigirMetodoPost();
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$contactoId = obtenerEnteroPositivo($_POST["contact_id"] ?? 0);

if ($contactoId === 0 || $contactoId === $usuarioId) {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes seleccionar un contacto valido.",
    ]);
}

// Eliminar la relacion solo del lado del usuario actual.
$consulta = $conn->prepare(
    "DELETE FROM contactos_mensajeria WHERE usuario_id = ? AND contacto_id = ?"
);
$consulta->bind_param("ii", $usuarioId, $contactoId);
$consulta->execute();
$afectadas = $consulta->affected_rows;
$consulta->close();

if ($afectadas === 0) {
    responderJson(404, [
        "ok" => false,
        "message" => "Ese contacto ya no estaba en tu lista.",
    ]);
}

responderJson(200, [
    "ok" => true,
    "message" => "Contacto eliminado de tu lista.",
]);

?>
