# Sistema de Mensajeria

Proyecto web desarrollado con PHP, HTML, CSS, JavaScript y MySQL para gestionar cuentas de usuario y conversaciones con historial persistente.

## Funcionalidades

- Registro de usuarios.
- Inicio y cierre de sesion.
- Modo oscuro y claro con preferencia persistente en el navegador.
- Sidebar con busqueda de contactos en tiempo real.
- Estados online/offline y badge de mensajes no leidos.
- Historial de mensajes por conversacion con reply y estados de entrega/lectura.
- CRUD de mensajes:
  - Crear mensajes.
  - Leer historial.
  - Editar mensajes propios.
  - Eliminar mensajes propios.
- Copiar y responder mensajes.
- Adjuntos multimedia con vista previa:
  - Imagenes.
  - Videos.
  - Audios.
- Gestion de contactos:
  - Agregar contacto.
  - Eliminar contacto.
  - Bloquear y desbloquear.
  - Ver perfil del contacto.
- Alertas y confirmaciones con SweetAlert2.

## Estructura principal

- `index.php`: pantalla de acceso y registro.
- `chat.php`: panel principal del sistema de mensajeria.
- `logout.php`: cierre de sesion.
- `config/conexion.php`: conexion reutilizable a MySQL.
- `api/`: endpoints para autenticacion y CRUD.
- `database/bd_mensajeria.sql`: script para crear la base de datos y sus tablas.
- `uploads/messages/`: carpeta donde se almacenan los adjuntos multimedia.

Nota: el proyecto usa las tablas `usuarios_mensajeria`, `contactos_mensajeria`, `mensajes_mensajeria` y `adjuntos_mensajeria` para evitar conflictos con otras tablas preexistentes dentro de `bd_mensajeria`.

## Pasos para ejecutar en XAMPP

1. Copiar la carpeta del proyecto dentro de `C:\xampp\htdocs\`.
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Importar el archivo `database/bd_mensajeria.sql` en phpMyAdmin o con consola.
4. Abrir en el navegador:

```text
http://localhost/MENSAJES/
```

## Importar la base de datos por consola

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\MENSAJES\database\bd_mensajeria.sql
```

Si tu servidor no te permite `CREATE DATABASE`, abre `database/bd_mensajeria.sql`, comenta las lineas de `CREATE DATABASE` y `USE`, y cambia `config/conexion.php` para apuntar a una base que tu usuario ya tenga autorizada.

## Publicar en GitHub

```powershell
git init
git add .
git commit -m "Sistema de mensajeria con CRUD y MySQL"
git branch -M main
git remote add origin https://github.com/TU_USUARIO/TU_REPOSITORIO.git
git push -u origin main
```

## Credenciales de conexion

La conexion usa estos datos en `config/conexion.php`:

- Servidor: `localhost`
- Usuario: `root`
- Password: vacio
- Base de datos: `bd_mensajeria`
