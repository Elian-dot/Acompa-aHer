# AcompañaHer

Aplicación web para ayudar a las mujeres a tomar decisiones informadas en sus desplazamientos nocturnos por el espacio público. Combina información de rutas, reportes comunitarios y herramientas de acompañamiento, con foco inicial en el municipio de **Girardot (Cundinamarca, Colombia)**.

> **Alcance del producto.** La aplicación orienta, pero no promete seguridad absoluta ni reemplaza a las autoridades. Que una zona tenga pocos reportes no significa que sea segura. Ante peligro inmediato se debe llamar a la línea 123.

## Funcionalidades

| Módulo | Descripción |
| --- | --- |
| Rutas seguras | Compara alternativas de ruta peatonal con tiempo, distancia y explicación de la recomendación. |
| Mapa de calor | Celdas de 500 m con reportes **verificados** nocturnos de los últimos 30 días. Oculta grupos de menos de 5 reportes. |
| Horarios | Distribución por hora de los reportes verificados, con tamaño de muestra y confianza. Mide incidencia observada, no flujo peatonal. |
| Reportes ciudadanos | Reporte con ubicación, categoría y opción anónima, más bandeja de moderación. |
| Botón SOS | Acceso rápido a números de emergencia y a contactos de confianza. |
| Acompañamiento | Ubicación en vivo compartida con contactos mediante un enlace privado y revocable, sin que ellos necesiten cuenta. |
| Alerta por inactividad | Aviso previo a la usuaria y, si no responde, notificación Web Push a sus contactos. Con pausa voluntaria y registro de entrega. |
| Dashboard comunitario | Estadísticas agregadas y anónimas de reportes verificados (categorías, tendencia mensual), con umbral de privacidad. |

AcompañaHer es una **PWA**: se puede instalar en el teléfono, tiene una página sin conexión con acceso al 123 y recibe notificaciones push. Las ocho funciones usan datos reales del servidor; no queda ninguna simulada.

## Stack

- **Backend:** PHP 8.4+, Laravel 13, Laravel Fortify (autenticación, 2FA opcional, llaves de acceso), Spatie Laravel Permission (roles).
- **Frontend:** React 19 + TypeScript con Inertia.js 3, Tailwind CSS 4, Radix UI, Leaflet para los mapas. Rutas tipadas con Laravel Wayfinder.
- **Base de datos:** PostgreSQL 16 con PostGIS 3.
- **Herramientas:** Vite (Vite+), Pest, Larastan/PHPStan, Laravel Pint.
- **PWA y notificaciones:** manifest, service worker propio y Web Push con claves VAPID (`minishlink/web-push`).
- **Servicios externos:** teselas de OpenStreetMap y motor de rutas peatonales OSRM (FOSSGIS).

## Requisitos

- PHP 8.4 o superior con las extensiones `pdo_pgsql`, `intl`, `mbstring`, `bcmath`, `gd`, `zip`
- Composer 2
- Node.js 22 y npm
- PostgreSQL 16 con PostGIS (o Docker para levantarlo con `compose.yaml`)

## Puesta en marcha local

```bash
# 1. Variables de entorno
cp .env.example .env
```

Edita `.env` y define contraseñas distintas para `DB_PASSWORD` y `POSTGRES_ADMIN_PASSWORD` (por ejemplo con `openssl rand -hex 32`).

```bash
# 2. Base de datos con PostGIS (opcional si ya tienes PostgreSQL)
docker compose up -d postgres

# 3. Dependencias, clave, migraciones, datos base y assets
composer setup

# 4. Servidor de desarrollo
composer dev
```

`composer setup` ejecuta `composer install`, genera `APP_KEY`, migra, corre los seeders base y compila el frontend.

Si usas tu propio PostgreSQL, el rol de la aplicación necesita la extensión `postgis`; créala con un superusuario: `CREATE EXTENSION postgis;`.

## Datos iniciales (seeders)

`php artisan db:seed` carga lo imprescindible:

- Roles y permisos (`registered_user`, `community_moderator`, `municipal_administrator`).
- Categorías de reporte.
- Polígono municipal de Girardot (zona de cobertura, código `25307`).

### Datos de demostración

Para ver el mapa de calor y la bandeja de moderación con contenido:

```bash
php artisan db:seed --class=DemoDataSeeder        # crea ~1 300 reportes sintéticos
php artisan db:seed --class=DemoDataPurgeSeeder   # los elimina
```

Todo reporte demo empieza con `[DEMO]` y las cuentas usan el dominio `@demo.rutasegura.test` (sin contraseña utilizable). Son datos inventados: no los publiques como información real. Ninguno de estos seeders se ejecuta con `db:seed` sin `--class`.

## Comandos útiles

| Comando | Qué hace |
| --- | --- |
| `composer dev` | Servidor, colas y Vite en paralelo. |
| `composer test` | Pint, PHPStan y suite de tests. |
| `php artisan test --compact` | Solo los tests. |
| `composer lint` | Formatea el PHP con Pint. |
| `npm run types:check` | Comprueba tipos de TypeScript. |
| `npm run check` | Lint y formato del frontend. |
| `npm run build` | Compila los assets de producción. |
| `php artisan wayfinder:generate --with-form` | Regenera las rutas tipadas para el frontend. |
| `php artisan webpush:keys --write` | Genera las claves VAPID y las guarda en `.env`. |
| `php artisan journeys:process-alerts` | Detecta inactividad, escala alertas y cierra trayectos abandonados (corre cada minuto). |

Los tests usan una base distinta (`camina_segura_testing`); el script `database/postgresql-init.sh` la crea al iniciar el contenedor.

## Estructura

```
app/
  Actions/          Casos de uso (mapa, reportes, rutas, equipos)
  Http/Controllers/ Controladores web y API v1 (Api/V1)
  Models/           Modelos Eloquent
  Policies/         Autorización
config/safety.php   Parámetros de cobertura, mapa e inactividad
database/
  migrations/       Esquema (PostGIS, constraints de integridad)
  seeders/          Datos base y de demostración
resources/js/
  pages/            Páginas Inertia (una por módulo)
  components/ruta/  Componentes propios del sistema de diseño
routes/             web.php, settings.php, safety.php (mapa y API)
.context/           Documentación de análisis, modelo de datos y UX/UI
```

## Configuración relevante

Variables propias de la aplicación (ver `.env.example`):

| Variable | Descripción |
| --- | --- |
| `JOURNEY_INACTIVITY_SECONDS` | Tiempo sin movimiento antes de la alerta (por defecto 900). |
| `JOURNEY_ESCALATION_DELAY_SECONDS` | Espera antes de escalar a los contactos (por defecto 120). |
| `HEATMAP_NIGHT_START_HOUR` / `HEATMAP_NIGHT_END_HOUR` | Franja nocturna del mapa de calor (20 a 2). |
| `MAP_TILE_URL` | Proveedor de teselas. |
| `WALKING_ROUTER_URL` | Motor OSRM de rutas peatonales. |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | Claves de Web Push. Se generan con `php artisan webpush:keys --write`. |
| `VAPID_SUBJECT` | Contacto del remitente de push (`mailto:...`). |
| `JOURNEY_MAX_HOURS` | Duración máxima de un acompañamiento antes de cerrarse solo (por defecto 12). |

El registro **no exige verificación de correo**: al crear la cuenta se entra directo. La autenticación en dos pasos y las llaves de acceso son opcionales desde Ajustes → Seguridad.

## Privacidad y seguridad

- Los contactos de emergencia se guardan **cifrados**.
- Los reportes anónimos no se asocian a ninguna cuenta y el mapa solo muestra agregados por celda.
- Los trayectos y sus ubicaciones se eliminan 24 horas después de cerrarse (`model:prune` programado cada minuto).
- Los contactos ven el trayecto con un enlace privado cuyo secreto solo se guarda como hash; caduca y se puede revocar. Solo reciben ubicación mientras el trayecto está activo.
- Las suscripciones push solo se aceptan de los servicios de los navegadores (lista permitida), para evitar peticiones a direcciones arbitrarias.
- Las alertas no se marcan como enviadas sin confirmación: el registro distingue entregada, fallida y sin dispositivo.
- Autorización por roles, límites de peticiones en los endpoints sensibles y validación de que las coordenadas estén dentro de la cobertura.
- El tratamiento de datos de ubicación debe revisarse frente a la Ley 1581 de 2012 antes de operar con usuarias reales.

## Despliegue

Pensado para un servidor Linux con Nginx, PHP-FPM, PostgreSQL + PostGIS y Node para compilar el frontend. Pasos mínimos:

```bash
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

Además se necesitan:

- Un worker de colas: `php artisan queue:work`, gestionado con systemd o Supervisor. Envía las notificaciones push de las alertas.
- El scheduler cada minuto vía cron: `php artisan schedule:run`.
- `APP_ENV=production`, `APP_DEBUG=false` y HTTPS. Las llaves de acceso (passkeys), la geolocalización, el service worker y las notificaciones push solo funcionan en contextos seguros.
- Claves VAPID generadas (`php artisan webpush:keys --write`). Sin ellas, las alertas se registran como no entregadas.
- Servir `/sw.js` sin caché larga (`Cache-Control: no-cache`) para que las actualizaciones del service worker lleguen.
- Permisos de escritura de `storage/` y `bootstrap/cache/` para el usuario de PHP-FPM, y `.env` legible por ese mismo usuario.
- En producción no se instalan dependencias de desarrollo: Faker y las factories no están disponibles.

## Documentación adicional

En [.context/](.context/) están el análisis funcional, el modelo relacional (`modelo-relacional-postgresql.md`) y la especificación de UX/UI (`especificaciones_ux_ui_ruta_segura.json`).

## Licencia

MIT.
