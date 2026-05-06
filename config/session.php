<?php

// Iniciar sesion una sola vez para reutilizarla en todo el proyecto.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si existe un usuario autenticado en la sesion actual.
function usuarioAutenticado(): bool
{
    return isset($_SESSION["user_id"]);
}

// Redirigir al chat si el usuario ya inicio sesion.
function redirigirSiAutenticado(): void
{
    if (usuarioAutenticado()) {
        header("Location: chat.php");
        exit;
    }
}

// Proteger paginas privadas para que solo entren usuarios autenticados.
function exigirAutenticacionPagina(): void
{
    if (!usuarioAutenticado()) {
        header("Location: index.php");
        exit;
    }
}

?>
