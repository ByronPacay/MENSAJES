<?php

// Preparar conexion, sesion y herramientas reutilizables.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Validar sesion activa antes de consultar el historial.
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$contactoId = obtenerEnteroPositivo($_GET["contact_id"] ?? 0);

if ($contactoId === 0 || $contactoId === $usuarioId) {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes seleccionar un contacto valido.",
    ]);
}

try {
    // Registrar entrega general y lectura de la conversacion abierta.
    marcarMensajesEntregados($conn, $usuarioId);
    marcarConversacionLeida($conn, $usuarioId, $contactoId);

    $estadoContacto = validarDisponibilidadContacto($conn, $usuarioId, $contactoId);
    $contacto = $estadoContacto["contacto"];

    // Recuperar el historial completo con datos del mensaje respondido.
    $consulta = $conn->prepare(
        "SELECT
            m.id,
            m.remitente_id,
            m.destinatario_id,
            m.mensaje,
            m.reply_to_message_id,
            m.created_at,
            m.updated_at,
            m.delivered_at,
            m.read_at,
            pm.mensaje AS reply_text,
            pm.remitente_id AS reply_sender_id
        FROM mensajes_mensajeria m
        LEFT JOIN mensajes_mensajeria pm
            ON pm.id = m.reply_to_message_id
        WHERE
            (m.remitente_id = ? AND m.destinatario_id = ?)
            OR
            (m.remitente_id = ? AND m.destinatario_id = ?)
        ORDER BY m.created_at ASC, m.id ASC"
    );
    $consulta->bind_param("iiii", $usuarioId, $contactoId, $contactoId, $usuarioId);
    $consulta->execute();
    $resultado = $consulta->get_result();

    $mensajes = [];
    $messageIds = [];

    while ($fila = $resultado->fetch_assoc()) {
        $messageIds[] = (int) $fila["id"];
        $propio = (int) $fila["remitente_id"] === $usuarioId;

        $mensajes[] = [
            "id" => (int) $fila["id"],
            "remitente_id" => (int) $fila["remitente_id"],
            "destinatario_id" => (int) $fila["destinatario_id"],
            "mensaje" => $fila["mensaje"],
            "reply_to_message_id" => obtenerEnteroPositivo($fila["reply_to_message_id"] ?? 0),
            "created_at" => $fila["created_at"],
            "updated_at" => $fila["updated_at"],
            "delivered_at" => $fila["delivered_at"],
            "read_at" => $fila["read_at"],
            "propio" => $propio,
            "delivery_state" => $propio ? obtenerEstadoMensaje($fila) : null,
            "reply_to" => !empty($fila["reply_to_message_id"]) ? [
                "id" => (int) $fila["reply_to_message_id"],
                "sender_name" => (int) ($fila["reply_sender_id"] ?? 0) === $usuarioId
                    ? ($_SESSION["user_name"] ?? "Tu")
                    : $contacto["nombre"],
                "text" => resumirTextoMensaje($fila["reply_text"] ?? ""),
            ] : null,
        ];
    }

    $consulta->close();

    $adjuntosPorMensaje = obtenerAdjuntosPorMensajes($conn, $messageIds);

    foreach ($mensajes as &$mensaje) {
        $mensaje["attachments"] = $adjuntosPorMensaje[$mensaje["id"]] ?? [];
    }
    unset($mensaje);

    responderJson(200, [
        "ok" => true,
        "contact" => [
            "id" => (int) $contacto["id"],
            "nombre" => $contacto["nombre"],
            "username" => $contacto["username"],
            "bio" => $contacto["bio"],
            "last_seen_at" => $contacto["last_seen_at"],
            "online" => $estadoContacto["online"],
            "blocked_by_me" => $estadoContacto["bloqueado_por_mi"],
            "blocked_me" => $estadoContacto["me_bloqueo"],
            "can_send" => !$estadoContacto["bloqueado_por_mi"] && !$estadoContacto["me_bloqueo"],
        ],
        "messages" => $mensajes,
    ]);
} catch (RuntimeException $error) {
    responderJson(422, [
        "ok" => false,
        "message" => $error->getMessage(),
    ]);
}

?>
