# Plataforma GES

Aplicación Laravel para gestionar pacientes, registros GES, asignaciones,
documentación, hitos y métricas operativas, con controles de acceso por rol y
confidencialidad de patologías.

## Requisitos

- PHP 8.2 o superior y Composer.
- Node.js y npm.
- MariaDB/MySQL con el esquema base de la plataforma.

Las migraciones del repositorio **amplían una base de datos GES existente**:
no crean las tablas base `usuarios`, `roles`, pacientes ni registros. Antes de
ejecutarlas, configura `DB_*` en `.env`, confirma que apunta a la base correcta
y respalda los datos. La normalización de roles consolida datos y no se puede
revertir automáticamente.

## Instalación y ejecución

1. Instala dependencias:

   ```sh
   composer install
   npm install
   ```

2. Configura `.env` con la conexión MariaDB/MySQL existente y genera `APP_KEY`
   si todavía está vacía:

   ```sh
   php artisan key:generate
   ```

3. Aplica las migraciones después de verificar la conexión y contar con un
   respaldo:

   ```sh
   php artisan migrate --force
   ```

4. Compila los recursos y levanta el entorno de desarrollo:

   ```sh
   npm run build
   composer run dev
   ```

Para el envío real de códigos OTP, configura un servidor de correo en `.env`;
`MAIL_MAILER=log` sirve para desarrollo local. La cola está configurada para
ejecutarse de forma síncrona por defecto.

## Pruebas y compilación

```sh
php artisan test
npm run build
```

Las pruebas automatizadas usan SQLite en memoria y crean los esquemas mínimos
de cada caso; `MariaDbConnectionTest` además comprueba la conexión configurada
con la base de datos existente.

## Roles, permisos y confidencialidad

Los únicos roles del sistema son `1 | Administrador`, `2 | Supervisor` y
`3 | Digitadora`. La migración `2026_10_05_000002_normalize_roles.php` consolida
`admin` en Administrador, `Operador` y duplicados de `digitadora` en Digitadora.
Si encuentra roles desconocidos o usuarios que apuntan a roles inexistentes,
se detiene sin normalizar los datos.

El rol se almacena en `usuarios.id_rol`. El tipo de digitadora es independiente
(`usuarios.tipo_digitadora`) y admite `NO_CONFIDENCIAL` o `CONFIDENCIAL`; solo
aplica a Digitadora y es `NULL` para Administrador y Supervisor. No existen
roles separados como «Digitadora Confidencial».

Los permisos de acción se almacenan en `permisos` y se asocian a roles mediante
`permisos_roles`:

| Rol | Permisos iniciales |
| --- | --- |
| Administrador | Todos los permisos de acción |
| Supervisor | `ver_registros`, `asignar_pacientes`, `reasignar_pacientes`, `ver_dashboard`, `gestionar_hitos` |
| Digitadora | `ver_registros`, `crear_registros`, `editar_registros`, `gestionar_hitos` |

Los permisos por patología (`puede_ver`, `puede_editar`, `puede_asignar`) y el
tipo de digitadora son controles adicionales; no sustituyen los permisos del
rol.

Las patologías confidenciales se marcan con `patologias.confidencial`. Solo
Administrador, Supervisor y Digitadora `CONFIDENCIAL` pueden acceder a ellas.
Una Digitadora `NO_CONFIDENCIAL` no puede verlas, buscarlas, recibir
asignaciones ni editarlas, aunque tenga permisos explícitos por patología.
Cambiar una digitadora a `NO_CONFIDENCIAL` elimina sus permisos sobre patologías
confidenciales.

La confidencialidad se cambia con
`PUT /api/patologias/{id}/confidencialidad`, enviando
`{"confidencial": true}` o `{"confidencial": false}`. Requiere
`administrar_patologias` y deja registro en el log de la aplicación.

El middleware `confidential` protege el grupo `auth:sanctum`. Cuando una persona
sin acceso referencia un registro, paciente asociado, patología, asignación,
hito o documento confidencial, la API responde `403 Forbidden`. Los listados,
las búsquedas y el dashboard también filtran los datos mediante las consultas
de visibilidad y las policies.

## Complejidad y asignaciones

La complejidad es una métrica de carga laboral de digitadoras; no reemplaza ni
modifica la prioridad médica del registro GES. Los endpoints requieren
autenticación Sanctum y responden con el formato `{ "data": [...] }`.

| Método | Endpoint | Descripción |
| --- | --- | --- |
| GET | `/api/complejidad` | Consulta las evaluaciones registradas. |
| GET | `/api/complejidad/promedio-por-tipo` | Promedio y cantidad de evaluaciones por tipo de registro. |
| GET | `/api/complejidad/operadores` | Promedio, puntaje acumulado y carga activa por digitadora. |
| GET | `/api/complejidad/patologias` | Promedio de complejidad de los tipos asociados a cada patología. |

La carga ponderada corresponde a `total_puntaje + carga_actual`. Las sugerencias
de asignación también consideran permisos, confidencialidad y factores
operativos; `prioridad` se mantiene como un factor independiente de la
complejidad clínica.

## Asignación automática periódica

El comando `php artisan asignaciones:automaticas` asigna registros GES
pendientes con las mismas reglas que el botón de asignación automática. Está
programado cada cinco minutos en `routes/console.php`. Para que se ejecute
automáticamente, el servidor debe invocar `php artisan schedule:run` cada
minuto; en Windows se puede configurar con el Programador de tareas y en Linux
con cron. La asignación manual sigue disponible.
