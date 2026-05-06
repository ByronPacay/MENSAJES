<?php

// Cargar sesion y conexion para los endpoints que responden JSON.
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/chat_helpers.php";

// Enviar respuestas JSON de forma consistente a todo el frontend.
function responderJson(int $codigo, array $datos): void
{
    http_response_code($codigo);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Limpiar y normalizar textos provenientes de formularios.
function limpiarTexto(?string $valor): string
{
    return trim((string) $valor);
}

// Asegurar que ciertos endpoints solo acepten peticiones POST.
function exigirMetodoPost(): void
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responderJson(405, [
            "ok" => false,
            "message" => "Metodo no permitido.",
        ]);
    }
}

// Validar sesion activa antes de permitir operaciones privadas.
function exigirAutenticacionApi(): void
{
    if (!usuarioAutenticado()) {
        responderJson(401, [
            "ok" => false,
            "message" => "Debes iniciar sesion para continuar.",
        ]);
    }

    // Registrar presencia del usuario para alimentar el estado online/offline.
    global $conn;
    actualizarPresenciaUsuario($conn, (int) $_SESSION["user_id"]);
}

// Convertir a entero positivo un identificador recibido por GET o POST.
function obtenerEnteroPositivo(mixed $valor): int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    return ($numero !== false && $numero > 0) ? $numero : 0;
}

?>
