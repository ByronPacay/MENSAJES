<?php

// Endpoint para mostrar informacion resumida del contacto.
require_once __DIR__ . "/../config/api_bootstrap.php";

exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$contactoId = obtenerEnteroPositivo($_GET["contact_id"] ?? 0);

if ($contactoId === 0 || $contactoId === $usuarioId) {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes seleccionar un contacto valido.",
    ]);
}

$contacto = obtenerUsuarioPorId($conn, $contactoId);

if (!$contacto) {
    responderJson(404, [
        "ok" => false,
        "message" => "El contacto solicitado no existe.",
    ]);
}

$bloqueos = obtenerEstadoBloqueo($conn, $usuarioId, $contactoId);

// Recuperar estadisticas basicas de la conversacion.
$consulta = $conn->prepare(
    "SELECT
        COUNT(*) AS total_mensajes,
        SUM(CASE WHEN remitente_id = ? THEN 1 ELSE 0 END) AS enviados,
        SUM(CASE WHEN remitente_id = ? THEN 1 ELSE 0 END) AS recibidos,
        MIN(created_at) AS primer_intercambio
    FROM mensajes_mensajeria
    WHERE
        (remitente_id = ? AND destinatario_id = ?)
        OR
        (remitente_id = ? AND destinatario_id = ?)"
);
$consulta->bind_param(
    "iiiiii",
    $usuarioId,
    $contactoId,
    $usuarioId,
    $contactoId,
    $contactoId,
    $usuarioId
);
$consulta->execute();
$resultado = $consulta->get_result();
$estadisticas = $resultado->fetch_assoc() ?: [];
$consulta->close();

responderJson(200, [
    "ok" => true,
    "profile" => [
        "id" => (int) $contacto["id"],
        "nombre" => $contacto["nombre"],
        "username" => $contacto["username"],
        "bio" => $contacto["bio"],
        "created_at" => $contacto["created_at"],
        "last_seen_at" => $contacto["last_seen_at"],
        "online" => estaUsuarioEnLinea($contacto["last_seen_at"] ?? null),
        "blocked_by_me" => $bloqueos["bloqueado_por_mi"],
        "blocked_me" => $bloqueos["me_bloqueo"],
        "total_messages" => (int) ($estadisticas["total_mensajes"] ?? 0),
        "messages_sent" => (int) ($estadisticas["enviados"] ?? 0),
        "messages_received" => (int) ($estadisticas["recibidos"] ?? 0),
        "first_exchange_at" => $estadisticas["primer_intercambio"] ?? null,
    ],
]);

?>
