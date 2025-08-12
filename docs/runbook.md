# Runbook operativo

## Reinicio de workers
- Detener los procesos con `pkill -f queue-worker`.
- Iniciar nuevamente los workers:
  ```bash
  php bin/queue-worker &
  ```
- Confirmar que los procesos se encuentren activos.

## Limpieza de colas
- Revisar la cola de mensajes y eliminar trabajos atascados.
- Reiniciar el servicio de cola si es necesario.

## Manejo de alertas
- Monitorizar los dashboards de métricas y logs.
- Para alertas críticas, escalar al equipo de desarrollo.
- Registrar cada incidencia en el sistema de seguimiento.
