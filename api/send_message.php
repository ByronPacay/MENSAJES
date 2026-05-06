<?php

// Cargar herramientas compartidas de la API y del chat.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Solo se permiten solicitudes POST autenticadas.
exigirMetodoPost();
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$contactoId = obtenerEnteroPositivo($_POST["contact_id"] ?? 0);
$replyToMessageId = obtenerEnteroPositivo($_POST["reply_to_message_id"] ?? 0);
$mensaje = limpiarTexto($_POST["mensaje"] ?? "");
$archivos = normalizarArchivosSubidos($_FILES["adjuntos"] ?? []);
$transaccionIniciada = false;

if ($contactoId === 0 || $contactoId === $usuarioId) {
    responderJson(422, [
        "ok" => false,
        "message" => "Debes elegir un destinatario valido.",
    ]);
}

if ($mensaje === "" && !$archivos) {
    responderJson(422, [
        "ok" => false,
        "message" => "Escribe un mensaje o adjunta al menos un archivo.",
    ]);
}

if (mb_strlen($mensaje) > 1500) {
    responderJson(422, [
        "ok" => false,
        "message" => "El mensaje no puede superar los 1500 caracteres.",
    ]);
}

if (count($archivos) > 5) {
    responderJson(422, [
        "ok" => false,
        "message" => "Solo puedes enviar hasta 5 archivos por mensaje.",
    ]);
}

$archivosGuardados = [];
$mensajeId = 0;

try {
    // Asegurar el contacto en ambas direcciones para que la conversacion aparezca a ambos lados.
    asegurarRelacionContacto($conn, $usuarioId, $contactoId, true);

    $estadoContacto = validarDisponibilidadContacto($conn, $usuarioId, $contactoId);

    if ($estadoContacto["me_bloqueo"]) {
        throw new RuntimeException("Este contacto te ha bloqueado y no puede recibir tus mensajes.");
    }

    if ($estadoContacto["bloqueado_por_mi"]) {
        throw new RuntimeException("Desbloquea este contacto antes de enviarle mensajes.");
    }

    validarMensajeRespuesta($conn, $replyToMessageId, $usuarioId, $contactoId);

    $conn->begin_transaction();
    $transaccionIniciada = true;

    // Insertar el mensaje principal antes de registrar sus adjuntos.
    $insertar = $conn->prepare(
        "INSERT INTO mensajes_mensajeria (
            remitente_id,
            destinatario_id,
            mensaje,
            reply_to_message_id
        ) VALUES (?, ?, ?, ?)"
    );
    $insertar->bind_param("iisi", $usuarioId, $contactoId, $mensaje, $replyToMessageId);

    if (!$insertar->execute()) {
        throw new RuntimeException("No fue posible guardar el mensaje.");
    }

    $mensajeId = (int) $insertar->insert_id;
    $insertar->close();

    $archivosGuardados = guardarAdjuntosMensaje($conn, $mensajeId, $archivos);

    $conn->commit();

    responderJson(201, [
        "ok" => true,
        "message" => "Mensaje enviado correctamente.",
        "message_id" => $mensajeId,
        "attachments_count" => count($archivosGuardados),
    ]);
} catch (RuntimeException $error) {
    if ($transaccionIniciada) {
        $conn->rollback();
    }

    foreach ($archivosGuardados as $archivo) {
        $rutaAbsoluta = dirname(__DIR__) . "/" . $archivo["path"];

        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }

    responderJson(422, [
        "ok" => false,
        "message" => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if ($transaccionIniciada) {
        $conn->rollback();
    }

    foreach ($archivosGuardados as $archivo) {
        $rutaAbsoluta = dirname(__DIR__) . "/" . $archivo["path"];

        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }
    }

    responderJson(500, [
        "ok" => false,
        "message" => "No fue posible enviar el mensaje en este momento.",
    ]);
}

?>
