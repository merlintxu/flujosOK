# Guía de despliegue

## Precheck
- Confirmar que el repositorio está limpio y en la rama correcta.
- Ejecutar la suite de pruebas y revisar los resultados.
- Verificar el acceso a la base de datos y servicios externos.

## Migraciones
- Ejecutar las migraciones pendientes:
  ```bash
  php bin/console migrate
  ```
- Revisar los logs para detectar errores.

## Variables de entorno
- Actualizar el archivo `.env` con las variables requeridas para el entorno objetivo.
- Validar que no existan valores faltantes o credenciales erróneas.

## Verificación post-deploy
- Comprobar que la aplicación responde en el endpoint de salud.
- Revisar los logs en busca de errores.
- Ejecutar una transacción de prueba para asegurar el correcto funcionamiento.
