<?php

// Endpoint para bloquear o desbloquear contactos.
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

if (!obtenerUsuarioPorId($conn, $contactoId)) {
    responderJson(404, [
        "ok" => false,
        "message" => "El contacto no existe.",
    ]);
}

// Asegurar que exista la relacion antes de cambiar su estado de bloqueo.
asegurarRelacionContacto($conn, $usuarioId, $contactoId);

$consulta = $conn->prepare(
    "UPDATE contactos_mensajeria
    SET bloqueado = CASE WHEN bloqueado = 1 THEN 0 ELSE 1 END
    WHERE usuario_id = ? AND contacto_id = ?"
);
$consulta->bind_param("ii", $usuarioId, $contactoId);
$consulta->execute();
$consulta->close();

$estado = obtenerEstadoBloqueo($conn, $usuarioId, $contactoId);

responderJson(200, [
    "ok" => true,
    "message" => $estado["bloqueado_por_mi"]
        ? "Contacto bloqueado correctamente."
        : "Contacto desbloqueado correctamente.",
    "blocked" => $estado["bloqueado_por_mi"],
]);

?>
