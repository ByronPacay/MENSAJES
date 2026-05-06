<?php

// Cargar utilidades comunes del backend.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Este endpoint edita mensajes existentes por POST.
exigirMetodoPost();
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$mensajeId = obtenerEnteroPositivo($_POST["message_id"] ?? 0);
$mensajeNuevo = limpiarTexto($_POST["mensaje"] ?? "");

if ($mensajeId === 0) {
    responderJson(422, [
        "ok" => false,
        "message" => "El identificador del mensaje no es valido.",
    ]);
}

try {
    // Verificar propiedad del mensaje y si tiene adjuntos para permitir texto vacio o no.
    $validar = $conn->prepare(
        "SELECT
            m.id,
            COUNT(a.id) AS total_adjuntos
        FROM mensajes_mensajeria m
        LEFT JOIN adjuntos_mensajeria a
            ON a.mensaje_id = m.id
        WHERE m.id = ? AND m.remitente_id = ?
        GROUP BY m.id
        LIMIT 1"
    );
    $validar->bind_param("ii", $mensajeId, $usuarioId);
    $validar->execute();
    $resultado = $validar->get_result();
    $fila = $resultado->fetch_assoc();
    $validar->close();

    if (!$fila) {
        throw new RuntimeException("Solo puedes editar tus propios mensajes.");
    }

    $totalAdjuntos = (int) ($fila["total_adjuntos"] ?? 0);

    if ($mensajeNuevo === "" && $totalAdjuntos === 0) {
        throw new RuntimeException("Debes conservar texto o adjuntos en el mensaje.");
    }

    if (mb_strlen($mensajeNuevo) > 1500) {
        throw new RuntimeException("El mensaje editado no puede superar los 1500 caracteres.");
    }

    // Actualizar el texto manteniendo el historial y los adjuntos del mensaje.
    $actualizar = $conn->prepare(
        "UPDATE mensajes_mensajeria SET mensaje = ? WHERE id = ? AND remitente_id = ?"
    );
    $actualizar->bind_param("sii", $mensajeNuevo, $mensajeId, $usuarioId);

    if (!$actualizar->execute()) {
        throw new RuntimeException("No fue posible editar el mensaje.");
    }

    $actualizar->close();

    responderJson(200, [
        "ok" => true,
        "message" => "Mensaje actualizado correctamente.",
    ]);
} catch (RuntimeException $error) {
    responderJson(422, [
        "ok" => false,
        "message" => $error->getMessage(),
    ]);
}

?>
