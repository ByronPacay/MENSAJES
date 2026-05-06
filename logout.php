<?php

// Iniciar la sesion para poder destruirla correctamente.
require_once __DIR__ . "/config/session.php";

// Vaciar y eliminar la sesion del usuario autenticado.
$_SESSION = [];
session_destroy();

// Regresar al formulario principal despues del cierre de sesion.
header("Location: index.php");
exit;

?>
