# Ringover Sync

Esta documentación describe la sincronización de llamadas con Ringover utilizando la estructura actual de la API.

## Estructura de la respuesta

La API devuelve las llamadas dentro de la clave `call_list`. Cada elemento contiene el identificador `cdr_id` y otros campos opcionales:

```json
{
  "total_call_count": 2,
  "call_list_count": 2,
  "call_list": [
    {
      "cdr_id": "123",
      "from_number": "+34900111222",
      "contact_number": "+34900999888"
    }
  ]
}
```

## Paginación

Se utilizan parámetros `page` y `limit`. El servicio incrementa `page` hasta procesar todas las llamadas.

## Mapeo de campos

- `cdr_id` → `ringover_id`
- `from_number` → `phone_number`
- `contact_number` → `contact_number`
- `call_start` → `start_time`
- `record` → `recording_url`
- `voicemail` → `voicemail_url`

## Validaciones adicionales

- Si `call_list` está vacío se detiene la iteración y se registra un error.
- Si una llamada no tiene `ringover_id`, se omite su inserción en la base de datos.

## Despliegue

1. Ejecutar las migraciones para asegurar índice único en `ringover_id` y la columna `voicemail_url`.
2. Ejecutar `phpunit` para validar los cambios.
3. Configurar los flujos de n8n con parámetros `page` y `limit`.
