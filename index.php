<?php

// Cargar manejo de sesion y evitar que un usuario autenticado vuelva al login.
require_once __DIR__ . "/config/session.php";
redirigirSiAutenticado();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Mensajeria</title>

    <!-- Tipografia e identidad visual principal del sistema. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page" data-theme="dark">
    <!-- Fondo decorativo para que la vista inicial tenga una apariencia mas moderna. -->
    <div class="bg-orb orb-one"></div>
    <div class="bg-orb orb-two"></div>

    <header class="app-header auth-header">
        <div class="brand-block">
            <span class="brand-kicker">Mensajeria Web</span>
            <strong class="brand-title">Conectate con tu red</strong>
        </div>

        <div class="header-actions">
            <button type="button" class="ghost-button theme-button" data-theme-toggle>Modo claro</button>
        </div>
    </header>

    <main class="auth-shell">
        <!-- Columna informativa con el resumen del proyecto. -->
        <section class="auth-hero">
            <span class="badge">Proyecto CRUD con MySQL</span>
            <h1>Desarrollo de un sistema de mensajeria con cuentas por usuario.</h1>
            <p>
                Registra usuarios, inicia sesion y administra conversaciones con historial
                persistente en base de datos.
            </p>

            <div class="feature-grid">
                <article class="feature-card">
                    <h2>Cuentas seguras</h2>
                    <p>El registro y el inicio de sesion usan contrasenas cifradas con PHP.</p>
                </article>

                <article class="feature-card">
                    <h2>Historial completo</h2>
                    <p>Cada conversacion queda guardada en MySQL para poder consultarse despues.</p>
                </article>

                <article class="feature-card">
                    <h2>CRUD de mensajes</h2>
                    <p>Los usuarios pueden crear, leer, editar y eliminar sus propios mensajes.</p>
                </article>
            </div>
        </section>

        <!-- Tarjeta principal con formularios de acceso y registro. -->
        <section class="auth-panel">
            <div class="panel-tabs">
                <button type="button" class="tab-button active" data-view-target="login">Iniciar sesion</button>
                <button type="button" class="tab-button" data-view-target="register">Crear cuenta</button>
            </div>

            <div class="panel-content">
                <!-- Formulario para autenticar a un usuario existente. -->
                <form id="loginForm" class="auth-form active" autocomplete="off">
                    <h2>Bienvenido</h2>
                    <p>Ingresa con tu cuenta para abrir el panel de mensajes.</p>

                    <label>
                        <span>Usuario</span>
                        <input type="text" name="username" placeholder="Ejemplo: juan_01" required>
                    </label>

                    <label>
                        <span>Contrasena</span>
                        <input type="password" name="password" placeholder="Minimo 6 caracteres" required>
                    </label>

                    <button type="submit" class="primary-button">Entrar al sistema</button>
                </form>

                <!-- Formulario para crear nuevas cuentas de usuario. -->
                <form id="registerForm" class="auth-form" autocomplete="off">
                    <h2>Nueva cuenta</h2>
                    <p>Completa los datos para registrarte en la plataforma.</p>

                    <label>
                        <span>Nombre completo</span>
                        <input type="text" name="nombre" placeholder="Ejemplo: Juan Perez" required>
                    </label>

                    <label>
                        <span>Nombre de usuario</span>
                        <input type="text" name="username" placeholder="Solo letras, numeros y _" required>
                    </label>

                    <label>
                        <span>Contrasena</span>
                        <input type="password" name="password" placeholder="Minimo 6 caracteres" required>
                    </label>

                    <label>
                        <span>Confirmar contrasena</span>
                        <input type="password" name="confirm_password" placeholder="Repite tu contrasena" required>
                    </label>

                    <button type="submit" class="primary-button">Crear cuenta</button>
                </form>
            </div>
        </section>
    </main>

    <!-- Libreria de alertas solicitada para todos los mensajes emergentes. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Script comun para alternar entre modo oscuro y modo claro. -->
    <script src="assets/js/theme.js"></script>

    <!-- Script encargado del cambio de vistas y de consumir la API de autenticacion. -->
    <script src="assets/js/auth.js"></script>
</body>
</html>
