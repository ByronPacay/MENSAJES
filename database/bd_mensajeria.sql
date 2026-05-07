-- Crear la base de datos del sistema de mensajeria.
-- Si tu servidor no te permite crear bases de datos, comenta estas lineas
-- y usa directamente: USE nombre_de_tu_base_existente;
-- CREATE DATABASE IF NOT EXISTS bd_mensajeria
-- CHARACTER SET utf8mb4
-- COLLATE utf8mb4_general_ci;

USE bd_mensajeria;

-- Tabla principal de usuarios registrados.
CREATE TABLE IF NOT EXISTS usuarios_mensajeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bio VARCHAR(160) NULL DEFAULT 'Disponible para chatear.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de contactos personales con soporte para bloqueo.
CREATE TABLE IF NOT EXISTS contactos_mensajeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    contacto_id INT NOT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_contacto_por_usuario (usuario_id, contacto_id),
    INDEX idx_contactos_usuario (usuario_id),
    INDEX idx_contactos_contacto (contacto_id),
    CONSTRAINT fk_contactos_mensajeria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios_mensajeria(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_contactos_mensajeria_destino
        FOREIGN KEY (contacto_id) REFERENCES usuarios_mensajeria(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de mensajes para guardar el historial entre dos usuarios.
CREATE TABLE IF NOT EXISTS mensajes_mensajeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    reply_to_message_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL DEFAULT NULL,
    read_at DATETIME NULL DEFAULT NULL,
    INDEX idx_mensajes_conversacion (remitente_id, destinatario_id, created_at),
    CONSTRAINT fk_mensajes_remitente
        FOREIGN KEY (remitente_id) REFERENCES usuarios_mensajeria(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_mensajes_destinatario
        FOREIGN KEY (destinatario_id) REFERENCES usuarios_mensajeria(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de adjuntos multimedia por mensaje.
CREATE TABLE IF NOT EXISTS adjuntos_mensajeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensaje_id INT NOT NULL,
    archivo_original VARCHAR(255) NOT NULL,
    archivo_guardado VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    media_type ENUM('image', 'video', 'audio') NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_adjuntos_mensaje (mensaje_id),
    CONSTRAINT fk_adjuntos_mensaje
        FOREIGN KEY (mensaje_id) REFERENCES mensajes_mensajeria(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Crear contactos a partir de conversaciones previas para no perder visibilidad.
INSERT IGNORE INTO contactos_mensajeria (usuario_id, contacto_id)
SELECT remitente_id, destinatario_id
FROM mensajes_mensajeria
WHERE remitente_id <> destinatario_id;

INSERT IGNORE INTO contactos_mensajeria (usuario_id, contacto_id)
SELECT destinatario_id, remitente_id
FROM mensajes_mensajeria
WHERE remitente_id <> destinatario_id;
