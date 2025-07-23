# Documentación del Proyecto

Flujos Dimension sincroniza llamadas de Ringover y analiza las grabaciones con OpenAI para integrarlas posteriormente en Pipedrive. La aplicación está escrita en PHP 8.2 y cuenta con un panel de administración opcional.

## Características

- Importación de llamadas de Ringover.
- Procesamiento de audios mediante OpenAI y almacenamiento en base de datos.
- Envío de resultados a Pipedrive como oportunidades.
- API REST autenticada con JWT y endpoints de salud.

## Instalación rápida

1. Ejecuta `composer install` para descargar las dependencias.
2. Copia `.env.example` a `.env` y configura `DB_HOST`, `DB_PORT`, `DB_NAME`,
   `DB_USER` y `DB_PASS`, además de tus tokens de API.
3. Importa `database/flujodimen_db.sql` en tu servidor MySQL/MariaDB.
4. Inicia el servidor con `php -S localhost:8000 -t public`.

### Acceso al panel de administración

Configura `ADMIN_USER` y `ADMIN_PASS` en tu archivo `.env` y accede a
`admin/login.php` para iniciar sesión en el panel. Tras autenticarte se genera
un token CSRF para las peticiones de la interfaz.

Consulta [docs/INSTALL.md](INSTALL.md) para más detalles en inglés.
