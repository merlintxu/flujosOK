# Proceso de rollback

## Revertir migraciones
- Ejecutar el comando de reversión:
  ```bash
  php bin/console migrate:rollback
  ```
- Validar que el esquema vuelva al estado anterior.

## Revertir release
- Identificar el último tag estable y crear un deploy con esa versión.
- Restaurar las variables de entorno previas si fueron modificadas.
- Notificar al equipo sobre la operación y realizar monitoreo adicional.
