# Flujos n8n

Este directorio contiene plantillas para flujos de trabajo de n8n.

## Importar flujos

1. Accede a tu instancia de n8n en la URL definida por `HOST`.
2. En el panel de Workflows selecciona **Import** y carga el archivo JSON deseado.
3. Configura las credenciales necesarias en cada nodo antes de activar el flujo.

## Variables requeridas

- `HOST`: URL base del servicio n8n (por ejemplo, `https://n8n.example.com`).
- Credenciales para los servicios conectados (por ejemplo `PIPEDRIVE_API_TOKEN`, `RINGOVER_API_KEY`, `BASIC_AUTH_USER` y `BASIC_AUTH_PASSWORD`).

Estos flujos son plantillas y deben ajustarse según el entorno antes de su uso en producción.
