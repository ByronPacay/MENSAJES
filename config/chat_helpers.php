<?php

/*
|--------------------------------------------------------------------------
| Utilidades compartidas del sistema de mensajeria
|--------------------------------------------------------------------------
| Este archivo concentra la logica reutilizable para contactos, presencia,
| estados de mensajes, respuestas y archivos multimedia.
*/

// Actualizar la ultima actividad del usuario para el indicador online/offline.
function actualizarPresenciaUsuario(mysqli $conn, int $usuarioId): void
{
    $consulta = $conn->prepare(
        "UPDATE usuarios_mensajeria SET last_seen_at = NOW() WHERE id = ?"
    );
    $consulta->bind_param("i", $usuarioId);
    $consulta->execute();
    $consulta->close();
}

// Marcar como entregados los mensajes pendientes para el usuario autenticado.
function marcarMensajesEntregados(mysqli $conn, int $usuarioId): void
{
    $consulta = $conn->prepare(
        "UPDATE mensajes_mensajeria
        SET delivered_at = NOW()
        WHERE destinatario_id = ? AND delivered_at IS NULL"
    );
    $consulta->bind_param("i", $usuarioId);
    $consulta->execute();
    $consulta->close();
}

// Marcar como leidos los mensajes de una conversacion abierta.
function marcarConversacionLeida(mysqli $conn, int $usuarioId, int $contactoId): void
{
    $consulta = $conn->prepare(
        "UPDATE mensajes_mensajeria
        SET
            delivered_at = COALESCE(delivered_at, NOW()),
            read_at = NOW()
        WHERE remitente_id = ? AND destinatario_id = ? AND read_at IS NULL"
    );
    $consulta->bind_param("ii", $contactoId, $usuarioId);
    $consulta->execute();
    $consulta->close();
}

// Crear la relacion de contacto cuando se necesite en una o ambas direcciones.
function asegurarRelacionContacto(
    mysqli $conn,
    int $usuarioId,
    int $contactoId,
    bool $bidireccional = false
): void {
    $consulta = $conn->prepare(
        "INSERT IGNORE INTO contactos_mensajeria (usuario_id, contacto_id) VALUES (?, ?)"
    );
    $consulta->bind_param("ii", $usuarioId, $contactoId);
    $consulta->execute();

    if ($bidireccional) {
        $consulta->bind_param("ii", $contactoId, $usuarioId);
        $consulta->execute();
    }

    $consulta->close();
}

// Obtener el estado de bloqueo mutuo entre dos usuarios.
function obtenerEstadoBloqueo(mysqli $conn, int $usuarioId, int $contactoId): array
{
    $consulta = $conn->prepare(
        "SELECT
            MAX(CASE WHEN usuario_id = ? AND contacto_id = ? THEN bloqueado ELSE 0 END) AS bloqueado_por_mi,
            MAX(CASE WHEN usuario_id = ? AND contacto_id = ? THEN bloqueado ELSE 0 END) AS me_bloqueo
        FROM contactos_mensajeria
        WHERE
            (usuario_id = ? AND contacto_id = ?)
            OR
            (usuario_id = ? AND contacto_id = ?)"
    );
    $consulta->bind_param(
        "iiiiiiii",
        $usuarioId,
        $contactoId,
        $contactoId,
        $usuarioId,
        $usuarioId,
        $contactoId,
        $contactoId,
        $usuarioId
    );
    $consulta->execute();
    $resultado = $consulta->get_result();
    $fila = $resultado->fetch_assoc() ?: [];
    $consulta->close();

    return [
        "bloqueado_por_mi" => (int) ($fila["bloqueado_por_mi"] ?? 0) === 1,
        "me_bloqueo" => (int) ($fila["me_bloqueo"] ?? 0) === 1,
    ];
}

// Recuperar un usuario por identificador para validar operaciones.
function obtenerUsuarioPorId(mysqli $conn, int $usuarioId): ?array
{
    $consulta = $conn->prepare(
        "SELECT id, nombre, username, created_at, last_seen_at, bio
        FROM usuarios_mensajeria
        WHERE id = ?
        LIMIT 1"
    );
    $consulta->bind_param("i", $usuarioId);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $usuario = $resultado->fetch_assoc() ?: null;
    $consulta->close();

    return $usuario;
}

// Comprobar si un contacto puede participar en la conversacion actual.
function validarDisponibilidadContacto(mysqli $conn, int $usuarioId, int $contactoId): array
{
    $contacto = obtenerUsuarioPorId($conn, $contactoId);

    if (!$contacto) {
        throw new RuntimeException("El contacto seleccionado no existe.");
    }

    $bloqueos = obtenerEstadoBloqueo($conn, $usuarioId, $contactoId);

    return [
        "contacto" => $contacto,
        "bloqueado_por_mi" => $bloqueos["bloqueado_por_mi"],
        "me_bloqueo" => $bloqueos["me_bloqueo"],
        "online" => estaUsuarioEnLinea($contacto["last_seen_at"] ?? null),
    ];
}

// Saber si un usuario se considera activo recientemente.
function estaUsuarioEnLinea(?string $lastSeenAt): bool
{
    if (!$lastSeenAt) {
        return false;
    }

    $timestamp = strtotime($lastSeenAt);

    return $timestamp !== false && $timestamp >= (time() - 30);
}

// Convertir el estado del mensaje a una estructura amigable para el frontend.
function obtenerEstadoMensaje(array $mensaje): array
{
    if (!empty($mensaje["read_at"])) {
        return [
            "code" => "read",
            "icon" => "✓✓",
            "label" => "Leido",
        ];
    }

    if (!empty($mensaje["delivered_at"])) {
        return [
            "code" => "delivered",
            "icon" => "✓✓",
            "label" => "Entregado",
        ];
    }

    return [
        "code" => "sent",
        "icon" => "✓",
        "label" => "Enviado",
    ];
}

// Validar que el mensaje respondido pertenezca a la misma conversacion.
function validarMensajeRespuesta(
    mysqli $conn,
    int $replyToId,
    int $usuarioId,
    int $contactoId
): ?array {
    if ($replyToId === 0) {
        return null;
    }

    $consulta = $conn->prepare(
        "SELECT id, remitente_id, destinatario_id, mensaje
        FROM mensajes_mensajeria
        WHERE
            id = ?
            AND (
                (remitente_id = ? AND destinatario_id = ?)
                OR
                (remitente_id = ? AND destinatario_id = ?)
            )
        LIMIT 1"
    );
    $consulta->bind_param("iiiii", $replyToId, $usuarioId, $contactoId, $contactoId, $usuarioId);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $mensaje = $resultado->fetch_assoc() ?: null;
    $consulta->close();

    if (!$mensaje) {
        throw new RuntimeException("El mensaje que intentas responder no es valido.");
    }

    return $mensaje;
}

// Unificar el acceso a los archivos subidos en formularios multipart.
function normalizarArchivosSubidos(array $files): array
{
    if (empty($files) || !isset($files["name"])) {
        return [];
    }

    if (!is_array($files["name"])) {
        return ($files["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }

    $archivos = [];
    $total = count($files["name"]);

    for ($indice = 0; $indice < $total; $indice++) {
        if (($files["error"][$indice] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $archivos[] = [
            "name" => $files["name"][$indice],
            "type" => $files["type"][$indice],
            "tmp_name" => $files["tmp_name"][$indice],
            "error" => $files["error"][$indice],
            "size" => $files["size"][$indice],
        ];
    }

    return $archivos;
}

// Detectar si un adjunto es permitido y clasificarlo por familia multimedia.
function clasificarAdjunto(array $archivo): array
{
    $mime = (string) ($archivo["type"] ?? "");
    $extension = strtolower(pathinfo((string) ($archivo["name"] ?? ""), PATHINFO_EXTENSION));

    $mimePermitido = [
        "image/jpeg" => "image",
        "image/png" => "image",
        "image/gif" => "image",
        "image/webp" => "image",
        "video/mp4" => "video",
        "video/webm" => "video",
        "video/ogg" => "video",
        "audio/mpeg" => "audio",
        "audio/mp3" => "audio",
        "audio/wav" => "audio",
        "audio/webm" => "audio",
        "audio/ogg" => "audio",
        "audio/mp4" => "audio",
    ];

    $extPermitida = [
        "jpg" => "image",
        "jpeg" => "image",
        "png" => "image",
        "gif" => "image",
        "webp" => "image",
        "mp4" => "video",
        "webm" => "video",
        "ogv" => "video",
        "mp3" => "audio",
        "wav" => "audio",
        "ogg" => "audio",
        "m4a" => "audio",
    ];

    $mediaType = $mimePermitido[$mime] ?? ($extPermitida[$extension] ?? "");

    if ($mediaType === "") {
        throw new RuntimeException("Solo se permiten imagenes, videos o audios.");
    }

    if ((int) ($archivo["size"] ?? 0) > 15 * 1024 * 1024) {
        throw new RuntimeException("Cada archivo debe pesar menos de 15 MB.");
    }

    return [
        "mime_type" => $mime !== "" ? $mime : "application/octet-stream",
        "media_type" => $mediaType,
    ];
}

// Guardar en disco y registrar en base de datos los adjuntos del mensaje.
function guardarAdjuntosMensaje(mysqli $conn, int $mensajeId, array $archivos): array
{
    if (!$archivos) {
        return [];
    }

    $directorioBase = dirname(__DIR__) . "/uploads/messages";

    if (!is_dir($directorioBase) && !mkdir($directorioBase, 0777, true) && !is_dir($directorioBase)) {
        throw new RuntimeException("No fue posible preparar la carpeta de adjuntos.");
    }

    $consulta = $conn->prepare(
        "INSERT INTO adjuntos_mensajeria (
            mensaje_id,
            archivo_original,
            archivo_guardado,
            ruta_archivo,
            mime_type,
            media_type,
            file_size
        ) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $adjuntosGuardados = [];

    foreach ($archivos as $archivo) {
        if (($archivo["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Ocurrio un problema al subir uno de los archivos.");
        }

        $clasificacion = clasificarAdjunto($archivo);
        $nombreOriginal = basename((string) $archivo["name"]);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $nombreGuardado = uniqid("msg_", true) . ($extension !== "" ? "." . $extension : "");
        $rutaRelativa = "uploads/messages/" . $nombreGuardado;
        $rutaAbsoluta = dirname(__DIR__) . "/" . $rutaRelativa;

        if (!move_uploaded_file((string) $archivo["tmp_name"], $rutaAbsoluta)) {
            throw new RuntimeException("No fue posible guardar uno de los adjuntos.");
        }

        $fileSize = (int) ($archivo["size"] ?? 0);
        $mimeType = $clasificacion["mime_type"];
        $mediaType = $clasificacion["media_type"];

        $consulta->bind_param(
            "isssssi",
            $mensajeId,
            $nombreOriginal,
            $nombreGuardado,
            $rutaRelativa,
            $mimeType,
            $mediaType,
            $fileSize
        );

        if (!$consulta->execute()) {
            if (is_file($rutaAbsoluta)) {
                @unlink($rutaAbsoluta);
            }

            throw new RuntimeException("No fue posible registrar uno de los adjuntos.");
        }

        $adjuntosGuardados[] = [
            "original_name" => $nombreOriginal,
            "stored_name" => $nombreGuardado,
            "path" => $rutaRelativa,
            "mime_type" => $mimeType,
            "media_type" => $mediaType,
            "file_size" => $fileSize,
        ];
    }

    $consulta->close();

    return $adjuntosGuardados;
}

// Recuperar adjuntos agrupados por mensaje para consumirlos desde la API.
function obtenerAdjuntosPorMensajes(mysqli $conn, array $messageIds): array
{
    if (!$messageIds) {
        return [];
    }

    $ids = array_values(array_filter(array_map("intval", $messageIds)));

    if (!$ids) {
        return [];
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $tipos = str_repeat("i", count($ids));
    $consulta = $conn->prepare(
        "SELECT
            id,
            mensaje_id,
            archivo_original,
            ruta_archivo,
            mime_type,
            media_type,
            file_size
        FROM adjuntos_mensajeria
        WHERE mensaje_id IN ($placeholders)
        ORDER BY id ASC"
    );
    $consulta->bind_param($tipos, ...$ids);
    $consulta->execute();
    $resultado = $consulta->get_result();

    $adjuntos = [];

    while ($fila = $resultado->fetch_assoc()) {
        $mensajeId = (int) $fila["mensaje_id"];

        if (!isset($adjuntos[$mensajeId])) {
            $adjuntos[$mensajeId] = [];
        }

        $adjuntos[$mensajeId][] = [
            "id" => (int) $fila["id"],
            "original_name" => $fila["archivo_original"],
            "path" => $fila["ruta_archivo"],
            "url" => $fila["ruta_archivo"],
            "mime_type" => $fila["mime_type"],
            "media_type" => $fila["media_type"],
            "file_size" => (int) $fila["file_size"],
        ];
    }

    $consulta->close();

    return $adjuntos;
}

// Eliminar fisicamente los adjuntos cuando un mensaje se borra.
function eliminarAdjuntosFisicosDeMensaje(mysqli $conn, int $mensajeId): void
{
    $consulta = $conn->prepare(
        "SELECT ruta_archivo FROM adjuntos_mensajeria WHERE mensaje_id = ?"
    );
    $consulta->bind_param("i", $mensajeId);
    $consulta->execute();
    $resultado = $consulta->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $rutaRelativa = (string) ($fila["ruta_archivo"] ?? "");
        $rutaAbsoluta = dirname(__DIR__) . "/" . $rutaRelativa;

        if ($rutaRelativa !== "" && is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }

    $consulta->close();
}

// Formar una previsualizacion corta del mensaje respondido.
function resumirTextoMensaje(?string $texto, int $limite = 90): string
{
    $texto = trim((string) $texto);

    if ($texto === "") {
        return "Adjunto multimedia";
    }

    return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite) . "..." : $texto;
}

?>
