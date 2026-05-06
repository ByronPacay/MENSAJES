<?php

// Cargar herramientas compartidas para la API.
require_once __DIR__ . "/../config/api_bootstrap.php";

// Este endpoint elimina mensajes propios por POST.
exigirMetodoPost();
exigirAutenticacionApi();

$usuarioId = (int) $_SESSION["user_id"];
$mensajeId = obtenerEnteroPositivo($_POST["message_id"] ?? 0);
$transaccionIniciada = false;

if ($mensajeId === 0) {
    responderJson(422, [
        "ok" => false,
        "message" => "El identificador del mensaje no es valido.",
    ]);
}

try {
    // Confirmar que el mensaje exista y pertenezca al usuario actual.
    $validar = $conn->prepare(
        "SELECT id FROM mensajes_mensajeria WHERE id = ? AND remitente_id = ? LIMIT 1"
    );
    $validar->bind_param("ii", $mensajeId, $usuarioId);
    $validar->execute();
    $resultado = $validar->get_result();
    $existe = $resultado->fetch_assoc();
    $validar->close();

    if (!$existe) {
        throw new RuntimeException("Solo puedes eliminar tus propios mensajes.");
    }

    $conn->begin_transaction();
    $transaccionIniciada = true;

    // Desconectar las respuestas que apuntaban a este mensaje antes de borrarlo.
    $desvincular = $conn->prepare(
        "UPDATE mensajes_mensajeria SET reply_to_message_id = NULL WHERE reply_to_message_id = ?"
    );
    $desvincular->bind_param("i", $mensajeId);
    $desvincular->execute();
    $desvincular->close();

    eliminarAdjuntosFisicosDeMensaje($conn, $mensajeId);

    // Eliminar el mensaje del historial.
    $eliminar = $conn->prepare(
        "DELETE FROM mensajes_mensajeria WHERE id = ? AND remitente_id = ?"
    );
    $eliminar->bind_param("ii", $mensajeId, $usuarioId);

    if (!$eliminar->execute()) {
        throw new RuntimeException("No fue posible eliminar el mensaje.");
    }

    $eliminar->close();
    $conn->commit();

    responderJson(200, [
        "ok" => true,
        "message" => "Mensaje eliminado correctamente.",
    ]);
} catch (RuntimeException $error) {
    if ($transaccionIniciada) {
        $conn->rollback();
    }

    responderJson(422, [
        "ok" => false,
        "message" => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if ($transaccionIniciada) {
        $conn->rollback();
    }

    responderJson(500, [
        "ok" => false,
        "message" => "No fue posible eliminar el mensaje en este momento.",
    ]);
}

?>
