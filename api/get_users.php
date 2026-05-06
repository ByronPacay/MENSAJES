<?php

// Cargar utilidades base de la API.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Proteger el acceso y refrescar la presencia del usuario actual.
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];

// Marcar como entregados los mensajes pendientes del usuario autenticado.
marcarMensajesEntregados($conn, $usuarioId);

// Recuperar la lista de contactos del usuario con su ultimo movimiento.
$consulta = $conn->prepare(
    "SELECT
        u.id,
        u.nombre,
        u.username,
        u.bio,
        u.created_at,
        u.last_seen_at,
        c.bloqueado AS bloqueado_por_mi,
        EXISTS(
            SELECT 1
            FROM contactos_mensajeria cb
            WHERE cb.usuario_id = u.id AND cb.contacto_id = ? AND cb.bloqueado = 1
        ) AS me_bloqueo,
        lm.id AS ultimo_mensaje_id,
        lm.mensaje AS ultimo_mensaje,
        lm.created_at AS ultimo_timestamp,
        lm.remitente_id AS ultimo_remitente_id,
        COALESCE(la.total_adjuntos, 0) AS ultimo_total_adjuntos,
        COALESCE(nl.no_leidos, 0) AS no_leidos
    FROM contactos_mensajeria c
    INNER JOIN usuarios_mensajeria u
        ON u.id = c.contacto_id
    LEFT JOIN mensajes_mensajeria lm
        ON lm.id = (
            SELECT m1.id
            FROM mensajes_mensajeria m1
            WHERE
                (m1.remitente_id = ? AND m1.destinatario_id = u.id)
                OR
                (m1.remitente_id = u.id AND m1.destinatario_id = ?)
            ORDER BY m1.created_at DESC, m1.id DESC
            LIMIT 1
        )
    LEFT JOIN (
        SELECT mensaje_id, COUNT(*) AS total_adjuntos
        FROM adjuntos_mensajeria
        GROUP BY mensaje_id
    ) la
        ON la.mensaje_id = lm.id
    LEFT JOIN (
        SELECT remitente_id, COUNT(*) AS no_leidos
        FROM mensajes_mensajeria
        WHERE destinatario_id = ? AND read_at IS NULL
        GROUP BY remitente_id
    ) nl
        ON nl.remitente_id = u.id
    WHERE c.usuario_id = ?
    ORDER BY
        CASE WHEN lm.created_at IS NULL THEN 1 ELSE 0 END ASC,
        COALESCE(lm.created_at, c.created_at) DESC,
        u.nombre ASC"
);
$consulta->bind_param("iiiii", $usuarioId, $usuarioId, $usuarioId, $usuarioId, $usuarioId);
$consulta->execute();
$resultado = $consulta->get_result();

$usuarios = [];

// Convertir cada contacto a un formato amigable para el frontend.
while ($fila = $resultado->fetch_assoc()) {
    $ultimoMensaje = trim((string) ($fila["ultimo_mensaje"] ?? ""));
    $totalAdjuntos = (int) ($fila["ultimo_total_adjuntos"] ?? 0);

    if ($ultimoMensaje === "" && $totalAdjuntos > 0) {
        $ultimoMensaje = $totalAdjuntos === 1 ? "Adjunto multimedia" : "Adjuntos multimedia";
    }

    $usuarios[] = [
        "id" => (int) $fila["id"],
        "nombre" => $fila["nombre"],
        "username" => $fila["username"],
        "bio" => $fila["bio"],
        "created_at" => $fila["created_at"],
        "last_seen_at" => $fila["last_seen_at"],
        "online" => estaUsuarioEnLinea($fila["last_seen_at"] ?? null),
        "blocked_by_me" => (int) $fila["bloqueado_por_mi"] === 1,
        "blocked_me" => (int) $fila["me_bloqueo"] === 1,
        "unread_count" => (int) $fila["no_leidos"],
        "last_message" => $ultimoMensaje,
        "last_message_at" => $fila["ultimo_timestamp"],
        "last_message_from_me" => (int) ($fila["ultimo_remitente_id"] ?? 0) === $usuarioId,
        "last_message_has_media" => $totalAdjuntos > 0,
    ];
}

$consulta->close();

responderJson(200, [
    "ok" => true,
    "users" => $usuarios,
]);

?>
