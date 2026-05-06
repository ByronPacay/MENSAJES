<?php

// Proteger esta pagina para que solo entre un usuario autenticado.
require_once __DIR__ . "/config/session.php";
exigirAutenticacionPagina();

// Funcion local para construir iniciales simples del nombre del usuario.
function obtenerIniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $iniciales = "";

    foreach ($partes as $parte) {
        if ($parte !== "") {
            $iniciales .= strtoupper(substr($parte, 0, 1));
        }

        if (strlen($iniciales) === 2) {
            break;
        }
    }

    return $iniciales !== "" ? $iniciales : "US";
}

$usuarioId = (int) $_SESSION["user_id"];
$nombreUsuario = $_SESSION["user_name"] ?? "Usuario";
$usernameUsuario = $_SESSION["username"] ?? "";
$inicialesUsuario = obtenerIniciales($nombreUsuario);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Mensajeria</title>

    <!-- Cargar la misma identidad visual para mantener consistencia. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="chat-page" data-theme="dark">
    <!-- Contenedor principal del chat con datos del usuario actual para JavaScript. -->
    <main
        id="chatApp"
        class="chat-shell"
        data-user-id="<?= $usuarioId; ?>"
        data-user-name="<?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?>"
        data-user-username="<?= htmlspecialchars($usernameUsuario, ENT_QUOTES, 'UTF-8'); ?>"
    >
        <header class="app-header chat-header">
            <div class="brand-block">
                <span class="brand-kicker">Mensajeria inteligente</span>
                <strong class="brand-title">Tus conversaciones en un solo lugar</strong>
            </div>

            <div class="header-actions">
                <button type="button" id="addContactButton" class="primary-button subtle-button">
                    + Agregar Contacto
                </button>
                <button type="button" class="ghost-button theme-button" data-theme-toggle>Modo claro</button>
                <a href="logout.php" class="ghost-button">Cerrar sesion</a>
            </div>
        </header>

        <div class="workspace-grid">
            <!-- Barra lateral con informacion del usuario y lista de contactos. -->
            <aside class="sidebar">
                <div class="sidebar-card profile-card">
                    <div class="profile-row">
                        <div class="avatar-shell accent-avatar"><?= htmlspecialchars($inicialesUsuario, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div>
                            <span class="badge">Sesion activa</span>
                            <h1><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <p>@<?= htmlspecialchars($usernameUsuario, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <p class="profile-copy">
                        Administra tus contactos, revisa mensajes no leidos y comparte texto o multimedia.
                    </p>
                </div>

                <div class="sidebar-card contacts-card">
                    <div class="section-heading">
                        <h2>Contactos</h2>
                        <button type="button" id="refreshUsersButton" class="text-button">Actualizar</button>
                    </div>

                    <label class="search-field">
                        <span>Buscar contacto</span>
                        <input
                            type="text"
                            id="contactSearchInput"
                            placeholder="Buscar por nombre o usuario"
                            autocomplete="off"
                        >
                    </label>

                    <div id="usersList" class="users-list"></div>
                </div>
            </aside>

            <!-- Panel principal para visualizar el historial y redactar mensajes. -->
            <section class="conversation-panel">
                <header class="conversation-header">
                    <div class="contact-heading">
                        <div id="contactAvatar" class="avatar-shell neutral-avatar">--</div>
                        <div>
                            <span class="badge">Chat activo</span>
                            <h2 id="conversationTitle">Selecciona un contacto</h2>
                            <p id="conversationSubtitle">Elige un usuario para comenzar la conversacion.</p>
                        </div>
                    </div>

                    <div class="contact-actions">
                        <button type="button" id="viewProfileButton" class="ghost-button compact-button" disabled>
                            Ver perfil
                        </button>
                        <button type="button" id="toggleBlockButton" class="ghost-button compact-button" disabled>
                            Bloquear
                        </button>
                        <button type="button" id="deleteContactButton" class="ghost-button compact-button danger-text" disabled>
                            Eliminar contacto
                        </button>
                    </div>
                </header>

                <div id="messagesContainer" class="messages-container">
                    <div class="empty-state">
                        <h3>Aun no hay una conversacion abierta</h3>
                        <p>Cuando selecciones un contacto, aqui aparecera el historial de mensajes.</p>
                    </div>
                </div>

                <div id="replyBar" class="reply-bar hidden">
                    <div class="reply-bar-copy">
                        <strong id="replyAuthor">Responder</strong>
                        <p id="replyPreview">Selecciona un mensaje para responder.</p>
                    </div>
                    <button type="button" id="cancelReplyButton" class="ghost-button compact-button">Cancelar</button>
                </div>

                <div id="filePreviewContainer" class="file-preview-grid hidden"></div>

                <form id="messageForm" class="composer" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" id="contactId" name="contact_id">
                    <input type="hidden" id="replyToMessageId" name="reply_to_message_id">
                    <input
                        type="file"
                        id="mediaInput"
                        name="adjuntos[]"
                        accept="image/*,video/*,audio/*"
                        multiple
                        hidden
                    >

                    <div class="composer-tools">
                        <button type="button" class="mini-button tool-button" data-file-mode="all">Adjuntar</button>
                        <button type="button" class="mini-button tool-button" data-file-mode="audio">Audio</button>
                        <button type="button" class="mini-button tool-button" data-file-mode="image">Foto</button>
                        <button type="button" class="mini-button tool-button" id="emojiToggleButton">Emojis</button>

                        <div id="emojiPicker" class="emoji-picker hidden">
                            <button type="button" class="emoji-item" data-emoji="👍">👍</button>
                            <button type="button" class="emoji-item" data-emoji="❤️">❤️</button>
                            <button type="button" class="emoji-item" data-emoji="😂">😂</button>
                            <button type="button" class="emoji-item" data-emoji="😮">😮</button>
                            <button type="button" class="emoji-item" data-emoji="😢">😢</button>
                            <button type="button" class="emoji-item" data-emoji="🔥">🔥</button>
                        </div>
                    </div>

                    <label class="composer-field">
                        <span>Mensaje</span>
                        <textarea
                            id="messageInput"
                            name="mensaje"
                            rows="3"
                            placeholder="Escribe un mensaje, responde o comparte multimedia..."
                            disabled
                        ></textarea>
                    </label>

                    <button type="submit" id="sendButton" class="primary-button" disabled>Enviar mensaje</button>
                </form>
            </section>
        </div>
    </main>

    <!-- Libreria de alertas usada para confirmar acciones y mostrar errores. -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Script comun para alternar entre modo oscuro y modo claro. -->
    <script src="assets/js/theme.js"></script>

    <!-- Script principal del panel de chat y del CRUD de mensajes. -->
    <script src="assets/js/chat.js"></script>
</body>
</html>
