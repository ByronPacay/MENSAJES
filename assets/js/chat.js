// Referencias base del DOM para controlar el panel de mensajeria completo.
const chatApp = document.getElementById("chatApp");
const usersList = document.getElementById("usersList");
const messagesContainer = document.getElementById("messagesContainer");
const conversationTitle = document.getElementById("conversationTitle");
const conversationSubtitle = document.getElementById("conversationSubtitle");
const contactAvatar = document.getElementById("contactAvatar");
const contactSearchInput = document.getElementById("contactSearchInput");
const messageForm = document.getElementById("messageForm");
const messageInput = document.getElementById("messageInput");
const contactIdInput = document.getElementById("contactId");
const replyToMessageIdInput = document.getElementById("replyToMessageId");
const sendButton = document.getElementById("sendButton");
const refreshUsersButton = document.getElementById("refreshUsersButton");
const addContactButton = document.getElementById("addContactButton");
const viewProfileButton = document.getElementById("viewProfileButton");
const toggleBlockButton = document.getElementById("toggleBlockButton");
const deleteContactButton = document.getElementById("deleteContactButton");
const replyBar = document.getElementById("replyBar");
const replyAuthor = document.getElementById("replyAuthor");
const replyPreview = document.getElementById("replyPreview");
const cancelReplyButton = document.getElementById("cancelReplyButton");
const filePreviewContainer = document.getElementById("filePreviewContainer");
const mediaInput = document.getElementById("mediaInput");
const emojiToggleButton = document.getElementById("emojiToggleButton");
const emojiPicker = document.getElementById("emojiPicker");

// Datos del usuario autenticado que vienen desde el servidor.
const currentUserId = Number(chatApp.dataset.userId);
const currentUserName = chatApp.dataset.userName || "Tu";

// Tiempos de refresco del sistema para contactos y mensajes.
const MESSAGE_REFRESH_MS = 1200;
const USERS_REFRESH_MS = 4000;
const MAX_FILES_PER_MESSAGE = 5;
const MAX_FILE_SIZE = 15 * 1024 * 1024;

// Estado global del frontend para la sesion actual del chat.
const state = {
    selectedContactId: 0,
    activeContact: null,
    contactsCache: [],
    lastUsersSignature: "",
    lastMessagesSignature: "",
    pendingFiles: [],
    replyTo: null,
    usersRefreshTimer: null,
    messagesRefreshTimer: null,
    isUsersRefreshRunning: false,
    isMessagesRefreshRunning: false,
};

// Formatear fecha y hora en un estilo legible para la interfaz.
function formatDateTime(value) {
    const normalized = value ? value.replace(" ", "T") : "";
    const date = normalized ? new Date(normalized) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return "Fecha no disponible";
    }

    return date.toLocaleString("es-GT", {
        dateStyle: "short",
        timeStyle: "short",
    });
}

// Formatear una marca de tiempo compacta para la lista de contactos.
function formatCompactDate(value) {
    const normalized = value ? value.replace(" ", "T") : "";
    const date = normalized ? new Date(normalized) : null;

    if (!date || Number.isNaN(date.getTime())) {
        return "";
    }

    const now = new Date();
    const sameDay = now.toDateString() === date.toDateString();

    return date.toLocaleString("es-GT", sameDay
        ? { hour: "2-digit", minute: "2-digit" }
        : { day: "2-digit", month: "2-digit" });
}

// Construir iniciales simples para los avatares textuales.
function buildInitials(name) {
    const parts = String(name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    const initials = parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join("");
    return initials || "US";
}

// Escapar texto antes de insertarlo dentro de HTML construido manualmente.
function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

// Generar una firma sencilla para detectar cambios reales en los contactos.
function buildUsersSignature(users) {
    return users.map((user) => [
        user.id,
        user.nombre,
        user.username,
        user.online ? 1 : 0,
        user.unread_count,
        user.last_message,
        user.last_message_at,
        user.blocked_by_me ? 1 : 0,
        user.blocked_me ? 1 : 0,
    ].join("|")).join("||");
}

// Generar una firma del historial actual para evitar renderizados innecesarios.
function buildMessagesSignature(contact, messages) {
    const messagePart = messages.map((message) => [
        message.id,
        message.mensaje,
        message.updated_at,
        message.delivered_at,
        message.read_at,
        message.reply_to_message_id,
        (message.attachments || []).length,
    ].join("|")).join("||");

    return [
        contact.id,
        contact.online ? 1 : 0,
        contact.blocked_by_me ? 1 : 0,
        contact.blocked_me ? 1 : 0,
        contact.can_send ? 1 : 0,
        messagePart,
    ].join("###");
}

// Saber si el usuario estaba cerca del final antes de redibujar mensajes.
function shouldAutoScroll() {
    const distanceToBottom =
        messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;

    return distanceToBottom < 120;
}

// Consumir respuestas JSON con un manejo comun de errores.
async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const rawText = await response.text();
    let data = null;

    try {
        data = JSON.parse(rawText);
    } catch (error) {
        throw new Error("La respuesta del servidor no tuvo un formato valido.");
    }

    if (!response.ok || !data.ok) {
        throw new Error(data.message || "Ocurrio un error en la solicitud.");
    }

    return data;
}

// Derivar un estado amigable de presencia para el encabezado del chat.
function buildContactStatus(contact) {
    if (!contact) {
        return "Elige un usuario para comenzar la conversacion.";
    }

    if (contact.blocked_me) {
        return "Este contacto te bloqueo. Puedes ver el historial, pero no enviarle mensajes.";
    }

    if (contact.blocked_by_me) {
        return "Tienes a este contacto bloqueado. Desbloquealo para volver a escribir.";
    }

    if (contact.online) {
        return "En linea ahora";
    }

    if (contact.last_seen_at) {
        return `Ultima conexion: ${formatDateTime(contact.last_seen_at)}`;
    }

    return "Sin actividad reciente";
}

// Actualizar la cabecera del chat segun el contacto activo.
function renderConversationHeader(contact) {
    if (!contact) {
        contactAvatar.textContent = "--";
        contactAvatar.className = "avatar-shell neutral-avatar";
        conversationTitle.textContent = "Selecciona un contacto";
        conversationSubtitle.textContent = "Elige un usuario para comenzar la conversacion.";
        viewProfileButton.disabled = true;
        toggleBlockButton.disabled = true;
        deleteContactButton.disabled = true;
        return;
    }

    contactAvatar.textContent = buildInitials(contact.nombre);
    contactAvatar.className = `avatar-shell ${contact.online ? "accent-avatar" : "neutral-avatar"}`;
    conversationTitle.textContent = contact.nombre;
    conversationSubtitle.textContent = buildContactStatus(contact);
    viewProfileButton.disabled = false;
    toggleBlockButton.disabled = false;
    deleteContactButton.disabled = false;
    toggleBlockButton.textContent = contact.blocked_by_me ? "Desbloquear" : "Bloquear";
}

// Habilitar o deshabilitar el composer segun el estado del contacto y del mensaje.
function updateComposerState() {
    const hasText = messageInput.value.trim() !== "";
    const hasFiles = state.pendingFiles.length > 0;
    const canSendToContact = Boolean(
        state.activeContact &&
        !state.activeContact.blocked_by_me &&
        !state.activeContact.blocked_me &&
        state.activeContact.can_send
    );

    messageInput.disabled = !state.activeContact || !canSendToContact;
    mediaInput.disabled = !state.activeContact || !canSendToContact;
    sendButton.disabled = !canSendToContact || (!hasText && !hasFiles);

    if (!state.activeContact) {
        messageInput.placeholder = "Selecciona un contacto para comenzar.";
        return;
    }

    if (state.activeContact.blocked_me) {
        messageInput.placeholder = "No puedes responder porque este contacto te bloqueo.";
        return;
    }

    if (state.activeContact.blocked_by_me) {
        messageInput.placeholder = "Desbloquea este contacto para volver a enviar mensajes.";
        return;
    }

    messageInput.placeholder = "Escribe un mensaje, responde o comparte multimedia...";
}

// Vaciar la respuesta activa cuando el usuario cancela el reply.
function resetReply() {
    state.replyTo = null;
    replyToMessageIdInput.value = "";
    replyBar.classList.add("hidden");
    replyAuthor.textContent = "Responder";
    replyPreview.textContent = "Selecciona un mensaje para responder.";
}

// Mostrar en pantalla el mensaje que sera respondido.
function renderReplyBar() {
    if (!state.replyTo) {
        resetReply();
        return;
    }

    replyAuthor.textContent = state.replyTo.senderName;
    replyPreview.textContent = state.replyTo.preview;
    replyToMessageIdInput.value = String(state.replyTo.id);
    replyBar.classList.remove("hidden");
}

// Revocar las URL temporales y limpiar los archivos pendientes.
function clearPendingFiles() {
    state.pendingFiles.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });

    state.pendingFiles = [];
    filePreviewContainer.innerHTML = "";
    filePreviewContainer.classList.add("hidden");
    mediaInput.value = "";
    updateComposerState();
}

// Clasificar archivos desde el navegador para renderizar sus previews.
function classifyClientFile(file) {
    if (file.type.startsWith("image/")) {
        return "image";
    }

    if (file.type.startsWith("video/")) {
        return "video";
    }

    if (file.type.startsWith("audio/")) {
        return "audio";
    }

    return "";
}

// Mostrar en pantalla los archivos listos para enviarse.
function renderPendingFiles() {
    filePreviewContainer.innerHTML = "";

    if (!state.pendingFiles.length) {
        filePreviewContainer.classList.add("hidden");
        updateComposerState();
        return;
    }

    state.pendingFiles.forEach((item) => {
        const card = document.createElement("article");
        card.className = "file-preview-card";

        const mediaWrapper = document.createElement("div");
        mediaWrapper.className = "attachment-media";

        if (item.mediaType === "image") {
            const image = document.createElement("img");
            image.src = item.previewUrl;
            image.alt = item.file.name;
            mediaWrapper.appendChild(image);
        } else if (item.mediaType === "video") {
            const video = document.createElement("video");
            video.src = item.previewUrl;
            video.controls = true;
            mediaWrapper.appendChild(video);
        } else if (item.mediaType === "audio") {
            const audio = document.createElement("audio");
            audio.src = item.previewUrl;
            audio.controls = true;
            mediaWrapper.appendChild(audio);
        }

        const meta = document.createElement("div");
        meta.className = "file-preview-meta";
        meta.innerHTML = `
            <strong>${escapeHtml(item.file.name)}</strong>
            <span>${item.mediaType.toUpperCase()} · ${(item.file.size / 1024 / 1024).toFixed(2)} MB</span>
        `;

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "mini-button danger remove-file-button";
        removeButton.dataset.fileId = item.id;
        removeButton.textContent = "Quitar";

        card.append(mediaWrapper, meta, removeButton);
        filePreviewContainer.appendChild(card);
    });

    filePreviewContainer.classList.remove("hidden");
    updateComposerState();
}

// Agregar archivos nuevos respetando limites de cantidad, tipo y tamano.
function addFiles(fileList) {
    const files = Array.from(fileList || []);

    if (!files.length) {
        return;
    }

    const errores = [];

    files.forEach((file) => {
        if (state.pendingFiles.length >= MAX_FILES_PER_MESSAGE) {
            errores.push("Solo puedes adjuntar hasta 5 archivos por mensaje.");
            return;
        }

        const mediaType = classifyClientFile(file);

        if (!mediaType) {
            errores.push(`El archivo ${file.name} no tiene un formato permitido.`);
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            errores.push(`El archivo ${file.name} supera los 15 MB.`);
            return;
        }

        state.pendingFiles.push({
            id: `${Date.now()}_${Math.random().toString(16).slice(2)}`,
            file,
            mediaType,
            previewUrl: URL.createObjectURL(file),
        });
    });

    renderPendingFiles();

    if (errores.length) {
        Swal.fire({
            icon: "warning",
            title: "Algunos archivos no se agregaron",
            text: errores.join(" "),
            confirmButtonText: "Entendido",
        });
    }
}

// Crear el bloque visual para los adjuntos guardados en un mensaje.
function createAttachmentCard(attachment) {
    const article = document.createElement("article");
    article.className = "attachment-card";

    const mediaWrapper = document.createElement("div");
    mediaWrapper.className = "attachment-media";

    if (attachment.media_type === "image") {
        const image = document.createElement("img");
        image.src = attachment.url;
        image.alt = attachment.original_name;
        mediaWrapper.appendChild(image);
    } else if (attachment.media_type === "video") {
        const video = document.createElement("video");
        video.src = attachment.url;
        video.controls = true;
        mediaWrapper.appendChild(video);
    } else if (attachment.media_type === "audio") {
        const audio = document.createElement("audio");
        audio.src = attachment.url;
        audio.controls = true;
        mediaWrapper.appendChild(audio);
    }

    const caption = document.createElement("div");
    caption.className = "attachment-caption";
    caption.innerHTML = `
        <strong>${escapeHtml(attachment.original_name)}</strong>
        <span>${attachment.media_type.toUpperCase()}</span>
    `;

    const link = document.createElement("a");
    link.href = attachment.url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.className = "text-button";
    link.textContent = "Abrir archivo";

    article.append(mediaWrapper, caption, link);
    return article;
}

// Obtener un resumen breve para copiar o responder un mensaje.
function getMessageSummary(message) {
    const text = String(message.mensaje || "").trim();

    if (text !== "") {
        return text.length > 120 ? `${text.slice(0, 120)}...` : text;
    }

    if ((message.attachments || []).length) {
        return "Adjunto multimedia";
    }

    return "Mensaje";
}

// Filtrar localmente los contactos mientras el usuario escribe.
function getFilteredContacts() {
    const query = contactSearchInput.value.trim().toLowerCase();

    if (query === "") {
        return state.contactsCache;
    }

    return state.contactsCache.filter((contact) =>
        contact.nombre.toLowerCase().includes(query) ||
        contact.username.toLowerCase().includes(query)
    );
}

// Dibujar la lista de contactos con avatar, preview, badge y estado.
function renderUsers() {
    usersList.innerHTML = "";
    const filteredContacts = getFilteredContacts();

    if (!filteredContacts.length) {
        const empty = document.createElement("div");
        empty.className = "empty-users";
        empty.innerHTML = contactSearchInput.value.trim() !== ""
            ? "<p>No se encontraron contactos con ese criterio de busqueda.</p>"
            : "<p>No tienes contactos todavia. Usa el boton + Agregar Contacto.</p>";
        usersList.appendChild(empty);
        return;
    }

    filteredContacts.forEach((contact) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = `user-item ${state.selectedContactId === contact.id ? "active" : ""} ${
            contact.blocked_by_me ? "is-blocked" : ""
        }`;
        button.dataset.userId = String(contact.id);

        const preview = contact.last_message
            ? `${contact.last_message_from_me ? "Tu: " : ""}${contact.last_message}`
            : "Sin mensajes todavia";
        const timestamp = contact.last_message_at ? formatCompactDate(contact.last_message_at) : "";

        button.innerHTML = `
            <div class="user-header">
                <div class="user-main">
                    <div class="avatar-shell ${contact.online ? "accent-avatar" : "neutral-avatar"}">
                        ${escapeHtml(buildInitials(contact.nombre))}
                    </div>
                    <div class="user-copy">
                        <div class="user-line">
                            <strong>${escapeHtml(contact.nombre)}</strong>
                            ${contact.blocked_by_me ? '<span class="contact-flag">Bloqueado</span>' : ""}
                            ${contact.blocked_me ? '<span class="contact-flag">Te bloqueo</span>' : ""}
                        </div>
                        <span>@${escapeHtml(contact.username)}</span>
                        <small>${escapeHtml(preview)}</small>
                    </div>
                </div>

                <div class="user-copy">
                    <div class="user-meta-row">
                        <span class="status-dot ${contact.online ? "online" : "offline"}"></span>
                        ${timestamp ? `<span class="timestamp-pill">${escapeHtml(timestamp)}</span>` : ""}
                    </div>
                    ${contact.unread_count > 0 ? `<span class="unread-badge">${contact.unread_count}</span>` : ""}
                </div>
            </div>
        `;

        usersList.appendChild(button);
    });
}

// Restaurar el panel cuando no hay contacto activo.
function resetConversation() {
    state.selectedContactId = 0;
    state.activeContact = null;
    state.lastMessagesSignature = "";
    contactIdInput.value = "";
    messagesContainer.innerHTML = `
        <div class="empty-state">
            <h3>Aun no hay una conversacion abierta</h3>
            <p>Cuando selecciones un contacto, aqui aparecera el historial de mensajes.</p>
        </div>
    `;
    renderConversationHeader(null);
    resetReply();
    clearPendingFiles();
    messageInput.value = "";
    updateComposerState();
}

// Dibujar el historial del contacto seleccionado con acciones y multimedia.
function renderMessages(contact, messages, { forceScroll = false } = {}) {
    const autoScroll = forceScroll || shouldAutoScroll();

    state.activeContact = contact;
    renderConversationHeader(contact);
    messagesContainer.innerHTML = "";

    if (!messages.length) {
        const empty = document.createElement("div");
        empty.className = "empty-state";
        empty.innerHTML = `
            <h3>Sin mensajes por ahora</h3>
            <p>Escribe el primer mensaje o comparte un archivo para iniciar la conversacion.</p>
        `;
        messagesContainer.appendChild(empty);
        updateComposerState();
        return;
    }

    messages.forEach((message) => {
        const article = document.createElement("article");
        article.className = `message-bubble ${message.propio ? "own" : "foreign"}`;
        article.dataset.messageId = String(message.id);

        if (message.reply_to) {
            const replySnippet = document.createElement("div");
            replySnippet.className = "message-reply-snippet";
            replySnippet.innerHTML = `
                <strong>${escapeHtml(message.reply_to.sender_name)}</strong>
                <p>${escapeHtml(message.reply_to.text)}</p>
            `;
            article.appendChild(replySnippet);
        }

        if (String(message.mensaje || "").trim() !== "") {
            const text = document.createElement("p");
            text.className = "message-text";
            text.textContent = message.mensaje;
            article.appendChild(text);
        }

        if ((message.attachments || []).length) {
            const attachmentsGrid = document.createElement("div");
            attachmentsGrid.className = "message-attachments";

            message.attachments.forEach((attachment) => {
                attachmentsGrid.appendChild(createAttachmentCard(attachment));
            });

            article.appendChild(attachmentsGrid);
        }

        const meta = document.createElement("div");
        meta.className = "message-meta";

        const leftMeta = document.createElement("div");
        leftMeta.className = "message-meta-left";

        const time = document.createElement("span");
        time.className = "message-timestamp";
        time.textContent = message.updated_at !== message.created_at
            ? `${formatDateTime(message.updated_at)} (editado)`
            : formatDateTime(message.created_at);
        leftMeta.appendChild(time);

        if (message.propio && message.delivery_state) {
            const delivery = document.createElement("span");
            delivery.className = "delivery-state";
            delivery.textContent = `${message.delivery_state.icon} ${message.delivery_state.label}`;
            leftMeta.appendChild(delivery);
        }

        const actions = document.createElement("div");
        actions.className = "message-actions";

        const replyButton = document.createElement("button");
        replyButton.type = "button";
        replyButton.className = "mini-button";
        replyButton.dataset.action = "reply";
        replyButton.dataset.messageId = String(message.id);
        replyButton.textContent = "Responder";
        actions.appendChild(replyButton);

        const copyButton = document.createElement("button");
        copyButton.type = "button";
        copyButton.className = "mini-button";
        copyButton.dataset.action = "copy";
        copyButton.dataset.messageId = String(message.id);
        copyButton.textContent = "Copiar";
        actions.appendChild(copyButton);

        if (message.propio) {
            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.className = "mini-button";
            editButton.dataset.action = "edit";
            editButton.dataset.messageId = String(message.id);
            editButton.textContent = "Editar";

            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "mini-button danger";
            deleteButton.dataset.action = "delete";
            deleteButton.dataset.messageId = String(message.id);
            deleteButton.textContent = "Eliminar";

            actions.append(editButton, deleteButton);
        }

        meta.append(leftMeta, actions);
        article.appendChild(meta);
        article.dataset.messagePayload = JSON.stringify(message);
        messagesContainer.appendChild(article);
    });

    updateComposerState();

    if (autoScroll) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

// Obtener el contacto seleccionado directamente desde el cache local.
function getSelectedContactFromCache() {
    return state.contactsCache.find((contact) => contact.id === state.selectedContactId) || null;
}

// Cargar la agenda del usuario con su lista de contactos.
async function loadUsers({ keepSelection = true, loadConversation = true, forceRender = false } = {}) {
    const data = await requestJson("api/get_users.php");
    const previousSelectedContactId = state.selectedContactId;
    const nextSignature = buildUsersSignature(data.users);

    state.contactsCache = data.users;

    const selectedStillExists = state.contactsCache.some((contact) => contact.id === state.selectedContactId);

    if ((!keepSelection || !selectedStillExists) && state.contactsCache.length > 0) {
        state.selectedContactId = previousSelectedContactId && selectedStillExists
            ? previousSelectedContactId
            : state.contactsCache[0].id;
    }

    if (!state.contactsCache.length) {
        state.lastUsersSignature = nextSignature;
        renderUsers();
        resetConversation();
        return;
    }

    if (
        forceRender ||
        nextSignature !== state.lastUsersSignature ||
        previousSelectedContactId !== state.selectedContactId
    ) {
        renderUsers();
        state.lastUsersSignature = nextSignature;
    }

    if (state.selectedContactId && loadConversation) {
        await loadMessages(state.selectedContactId, {
            forceRender: previousSelectedContactId !== state.selectedContactId || forceRender,
            forceScroll: previousSelectedContactId !== state.selectedContactId,
        });
    } else if (state.activeContact) {
        const cachedContact = getSelectedContactFromCache();

        if (cachedContact) {
            state.activeContact = {
                ...state.activeContact,
                ...cachedContact,
                can_send: !cachedContact.blocked_by_me && !cachedContact.blocked_me,
            };
            renderConversationHeader(state.activeContact);
            updateComposerState();
        }
    }
}

// Cargar el historial de la conversacion abierta.
async function loadMessages(contactId, { forceRender = false, forceScroll = false } = {}) {
    const data = await requestJson(`api/get_messages.php?contact_id=${contactId}`);
    const nextSignature = buildMessagesSignature(data.contact, data.messages);

    state.selectedContactId = contactId;
    contactIdInput.value = String(contactId);
    state.activeContact = data.contact;

    if (forceRender || nextSignature !== state.lastMessagesSignature) {
        state.lastMessagesSignature = nextSignature;
        renderUsers();
        renderMessages(data.contact, data.messages, { forceScroll });
        return;
    }

    renderConversationHeader(data.contact);
    updateComposerState();
    renderUsers();
}

// Abrir un modal sencillo para agregar nuevos contactos por username.
async function openAddContactModal() {
    const { value: username } = await Swal.fire({
        title: "Agregar contacto",
        input: "text",
        inputLabel: "Nombre de usuario",
        inputPlaceholder: "Ejemplo: maria_01",
        showCancelButton: true,
        confirmButtonText: "Agregar",
        cancelButtonText: "Cancelar",
        inputValidator: (value) => {
            if (!value.trim()) {
                return "Debes escribir un nombre de usuario.";
            }

            return null;
        },
    });

    if (!username) {
        return;
    }

    const formData = new FormData();
    formData.append("username", username.trim());

    try {
        const data = await requestJson("api/add_contact.php", {
            method: "POST",
            body: formData,
        });

        state.selectedContactId = Number(data.contact.id);
        await loadUsers({ keepSelection: true, forceRender: true });

        Swal.fire({
            icon: "success",
            title: "Contacto agregado",
            text: data.message,
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No fue posible agregar el contacto",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
}

// Consultar y mostrar un resumen del perfil del contacto activo.
async function showContactProfile() {
    if (!state.selectedContactId) {
        return;
    }

    try {
        const data = await requestJson(`api/get_contact_profile.php?contact_id=${state.selectedContactId}`);
        const profile = data.profile;

        await Swal.fire({
            title: escapeHtml(profile.nombre),
            html: `
                <div style="text-align:left; display:grid; gap:0.55rem;">
                    <div><strong>Usuario:</strong> @${escapeHtml(profile.username)}</div>
                    <div><strong>Bio:</strong> ${escapeHtml(profile.bio || "Sin descripcion.")}</div>
                    <div><strong>Estado:</strong> ${profile.online ? "En linea" : `Ultima conexion ${escapeHtml(formatDateTime(profile.last_seen_at))}`}</div>
                    <div><strong>Miembro desde:</strong> ${escapeHtml(formatDateTime(profile.created_at))}</div>
                    <div><strong>Total de mensajes:</strong> ${profile.total_messages}</div>
                    <div><strong>Enviados por ti:</strong> ${profile.messages_sent}</div>
                    <div><strong>Recibidos:</strong> ${profile.messages_received}</div>
                    <div><strong>Primer intercambio:</strong> ${profile.first_exchange_at ? escapeHtml(formatDateTime(profile.first_exchange_at)) : "Sin conversaciones"}</div>
                </div>
            `,
            confirmButtonText: "Cerrar",
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo cargar el perfil",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
}

// Bloquear o desbloquear al contacto seleccionado.
async function toggleBlockSelectedContact() {
    if (!state.selectedContactId || !state.activeContact) {
        return;
    }

    const actionLabel = state.activeContact.blocked_by_me ? "desbloquear" : "bloquear";
    const result = await Swal.fire({
        icon: "warning",
        title: `${actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)} contacto`,
        text: `Vas a ${actionLabel} a ${state.activeContact.nombre}.`,
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append("contact_id", String(state.selectedContactId));

        await requestJson("api/toggle_block_contact.php", {
            method: "POST",
            body: formData,
        });

        await loadUsers({ keepSelection: true, forceRender: true });

        Swal.fire({
            icon: "success",
            title: "Estado actualizado",
            text: `El contacto fue ${actionLabel === "bloquear" ? "bloqueado" : "desbloqueado"} correctamente.`,
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo cambiar el bloqueo",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
}

// Eliminar un contacto de la lista lateral del usuario actual.
async function deleteSelectedContact() {
    if (!state.selectedContactId || !state.activeContact) {
        return;
    }

    const result = await Swal.fire({
        icon: "warning",
        title: "Eliminar contacto",
        text: `Vas a quitar a ${state.activeContact.nombre} de tu lista.`,
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const deletedContactId = state.selectedContactId;
        const formData = new FormData();
        formData.append("contact_id", String(deletedContactId));

        await requestJson("api/delete_contact.php", {
            method: "POST",
            body: formData,
        });

        if (state.selectedContactId === deletedContactId) {
            resetConversation();
        }

        await loadUsers({ keepSelection: false, forceRender: true });

        Swal.fire({
            icon: "success",
            title: "Contacto eliminado",
            text: "El contacto fue retirado de tu lista.",
            timer: 1400,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo eliminar el contacto",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
}

// Copiar el contenido del mensaje seleccionado al portapapeles.
async function copyMessageContent(message) {
    const text = String(message.mensaje || "").trim() || getMessageSummary(message);

    try {
        await navigator.clipboard.writeText(text);
        Swal.fire({
            icon: "success",
            title: "Mensaje copiado",
            text: "El contenido fue copiado al portapapeles.",
            timer: 1200,
            showConfirmButton: false,
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo copiar",
            text: "Tu navegador no permitio copiar el mensaje.",
            confirmButtonText: "Entendido",
        });
    }
}

// Preparar el estado interno para responder un mensaje concreto.
function prepareReply(message) {
    state.replyTo = {
        id: message.id,
        senderName: message.propio ? currentUserName : (state.activeContact?.nombre || "Contacto"),
        preview: getMessageSummary(message),
    };

    renderReplyBar();
    messageInput.focus();
    updateComposerState();
}

// Seleccionar contacto al hacer click en la sidebar.
usersList.addEventListener("click", async (event) => {
    const button = event.target.closest(".user-item");

    if (!button) {
        return;
    }

    const contactId = Number(button.dataset.userId);

    if (!contactId || contactId === state.selectedContactId) {
        return;
    }

    try {
        await loadMessages(contactId, {
            forceRender: true,
            forceScroll: true,
        });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo abrir la conversacion",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
});

// Reaccionar a las acciones sobre cada mensaje mediante delegacion.
messagesContainer.addEventListener("click", async (event) => {
    const actionButton = event.target.closest("[data-action]");

    if (!actionButton) {
        return;
    }

    const bubble = actionButton.closest(".message-bubble");

    if (!bubble) {
        return;
    }

    const rawPayload = bubble.dataset.messagePayload || "{}";
    const message = JSON.parse(rawPayload);
    const action = actionButton.dataset.action;

    if (action === "reply") {
        prepareReply(message);
        return;
    }

    if (action === "copy") {
        await copyMessageContent(message);
        return;
    }

    if (action === "edit") {
        const { value: newText } = await Swal.fire({
            title: "Editar mensaje",
            input: "textarea",
            inputValue: message.mensaje || "",
            inputLabel: "Nuevo contenido",
            inputPlaceholder: "Escribe el nuevo mensaje",
            showCancelButton: true,
            confirmButtonText: "Guardar cambios",
            cancelButtonText: "Cancelar",
        });

        if (typeof newText !== "string") {
            return;
        }

        try {
            const formData = new FormData();
            formData.append("message_id", String(message.id));
            formData.append("mensaje", newText.trim());

            await requestJson("api/update_message.php", {
                method: "POST",
                body: formData,
            });

            await loadUsers({ keepSelection: true, loadConversation: false });
            await loadMessages(state.selectedContactId, { forceRender: true });

            Swal.fire({
                icon: "success",
                title: "Mensaje actualizado",
                text: "Los cambios se guardaron correctamente.",
                timer: 1400,
                showConfirmButton: false,
            });
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "No se pudo editar",
                text: error.message,
                confirmButtonText: "Entendido",
            });
        }

        return;
    }

    if (action === "delete") {
        const result = await Swal.fire({
            icon: "warning",
            title: "Eliminar mensaje",
            text: "Esta accion quitara el mensaje del historial.",
            showCancelButton: true,
            confirmButtonText: "Si, eliminar",
            cancelButtonText: "Cancelar",
        });

        if (!result.isConfirmed) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append("message_id", String(message.id));

            await requestJson("api/delete_message.php", {
                method: "POST",
                body: formData,
            });

            if (state.replyTo && state.replyTo.id === message.id) {
                resetReply();
            }

            await loadUsers({ keepSelection: true, loadConversation: false });
            await loadMessages(state.selectedContactId, { forceRender: true });

            Swal.fire({
                icon: "success",
                title: "Mensaje eliminado",
                text: "El mensaje fue retirado del historial.",
                timer: 1400,
                showConfirmButton: false,
            });
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "No se pudo eliminar",
                text: error.message,
                confirmButtonText: "Entendido",
            });
        }
    }
});

// Permitir eliminar archivos pendientes desde su tarjeta de preview.
filePreviewContainer.addEventListener("click", (event) => {
    const button = event.target.closest("[data-file-id]");

    if (!button) {
        return;
    }

    const fileId = button.dataset.fileId;
    const index = state.pendingFiles.findIndex((item) => item.id === fileId);

    if (index === -1) {
        return;
    }

    const [removed] = state.pendingFiles.splice(index, 1);

    if (removed.previewUrl) {
        URL.revokeObjectURL(removed.previewUrl);
    }

    renderPendingFiles();
});

// Enviar un nuevo mensaje con texto, reply y adjuntos opcionales.
messageForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!state.selectedContactId || !state.activeContact) {
        Swal.fire({
            icon: "warning",
            title: "Selecciona un contacto",
            text: "Debes elegir un usuario antes de enviar mensajes.",
            confirmButtonText: "Entendido",
        });
        return;
    }

    if (state.activeContact.blocked_me || state.activeContact.blocked_by_me) {
        Swal.fire({
            icon: "warning",
            title: "No puedes enviar mensajes",
            text: buildContactStatus(state.activeContact),
            confirmButtonText: "Entendido",
        });
        return;
    }

    const messageText = messageInput.value.trim();

    if (messageText === "" && !state.pendingFiles.length) {
        Swal.fire({
            icon: "warning",
            title: "Mensaje vacio",
            text: "Escribe un mensaje o adjunta un archivo antes de enviar.",
            confirmButtonText: "Entendido",
        });
        return;
    }

    const formData = new FormData();
    formData.append("contact_id", String(state.selectedContactId));
    formData.append("mensaje", messageText);

    if (state.replyTo) {
        formData.append("reply_to_message_id", String(state.replyTo.id));
    }

    state.pendingFiles.forEach((item) => {
        formData.append("adjuntos[]", item.file);
    });

    try {
        sendButton.disabled = true;

        await requestJson("api/send_message.php", {
            method: "POST",
            body: formData,
        });

        messageInput.value = "";
        resetReply();
        clearPendingFiles();

        await loadUsers({ keepSelection: true, loadConversation: false, forceRender: true });
        await loadMessages(state.selectedContactId, {
            forceRender: true,
            forceScroll: true,
        });

        messageInput.focus();
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudo enviar el mensaje",
            text: error.message,
            confirmButtonText: "Revisar",
        });
    } finally {
        updateComposerState();
    }
});

// Mantener actualizado el boton de envio mientras el usuario escribe.
messageInput.addEventListener("input", updateComposerState);

// Aplicar el filtro de busqueda en tiempo real.
contactSearchInput.addEventListener("input", renderUsers);

// Botones principales de la cabecera y acciones del contacto actual.
addContactButton.addEventListener("click", openAddContactModal);
refreshUsersButton.addEventListener("click", async () => {
    try {
        await loadUsers({ keepSelection: true, forceRender: true });
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No se pudieron actualizar los contactos",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
});
viewProfileButton.addEventListener("click", showContactProfile);
toggleBlockButton.addEventListener("click", toggleBlockSelectedContact);
deleteContactButton.addEventListener("click", deleteSelectedContact);
cancelReplyButton.addEventListener("click", () => {
    resetReply();
    updateComposerState();
});

// Abrir el selector de archivos con distintos filtros segun el boton pulsado.
document.querySelectorAll("[data-file-mode]").forEach((button) => {
    button.addEventListener("click", () => {
        if (!state.activeContact || !state.activeContact.can_send || state.activeContact.blocked_by_me || state.activeContact.blocked_me) {
            return;
        }

        const mode = button.dataset.fileMode;

        if (mode === "audio") {
            mediaInput.accept = "audio/*";
        } else if (mode === "image") {
            mediaInput.accept = "image/*";
        } else {
            mediaInput.accept = "image/*,video/*,audio/*";
        }

        mediaInput.value = "";
        mediaInput.click();
    });
});

// Capturar archivos elegidos desde el input oculto.
mediaInput.addEventListener("change", () => {
    addFiles(mediaInput.files);
    mediaInput.value = "";
});

// Mostrar u ocultar el picker simple de emojis.
emojiToggleButton.addEventListener("click", () => {
    emojiPicker.classList.toggle("hidden");
});

// Insertar el emoji en el textarea y ocultar el picker despues de elegirlo.
emojiPicker.addEventListener("click", (event) => {
    const button = event.target.closest("[data-emoji]");

    if (!button) {
        return;
    }

    const emoji = button.dataset.emoji || "";
    const start = messageInput.selectionStart || messageInput.value.length;
    const end = messageInput.selectionEnd || messageInput.value.length;
    const currentValue = messageInput.value;

    messageInput.value = `${currentValue.slice(0, start)}${emoji}${currentValue.slice(end)}`;
    emojiPicker.classList.add("hidden");
    messageInput.focus();
    messageInput.selectionStart = start + emoji.length;
    messageInput.selectionEnd = start + emoji.length;
    updateComposerState();
});

// Cerrar el picker si el usuario hace click fuera de el.
document.addEventListener("click", (event) => {
    if (!emojiPicker.contains(event.target) && event.target !== emojiToggleButton) {
        emojiPicker.classList.add("hidden");
    }
});

// Refrescar la lista de contactos con menor frecuencia que el historial.
function startUsersAutoRefresh() {
    state.usersRefreshTimer = window.setInterval(async () => {
        if (state.isUsersRefreshRunning) {
            return;
        }

        state.isUsersRefreshRunning = true;

        try {
            await loadUsers({
                keepSelection: true,
                loadConversation: false,
            });
        } catch (error) {
            console.error(error);
        } finally {
            state.isUsersRefreshRunning = false;
        }
    }, USERS_REFRESH_MS);
}

// Refrescar el historial activo para que el chat se sienta inmediato.
function startMessagesAutoRefresh() {
    state.messagesRefreshTimer = window.setInterval(async () => {
        if (state.isMessagesRefreshRunning || !state.selectedContactId) {
            return;
        }

        state.isMessagesRefreshRunning = true;

        try {
            await loadMessages(state.selectedContactId);
        } catch (error) {
            console.error(error);
        } finally {
            state.isMessagesRefreshRunning = false;
        }
    }, MESSAGE_REFRESH_MS);
}

// Iniciar la aplicacion cargando contactos y activando los refrescos automaticos.
async function initializeChat() {
    try {
        await loadUsers({
            keepSelection: false,
            forceRender: true,
        });
        startUsersAutoRefresh();
        startMessagesAutoRefresh();
    } catch (error) {
        resetConversation();
        Swal.fire({
            icon: "error",
            title: "No se pudo cargar el sistema",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
}

initializeChat();
