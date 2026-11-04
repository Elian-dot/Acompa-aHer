# PROMPT MAESTRO: ARQUITECTURA Y MODELADO RELACIONAL EN POSTGRESQL

## 1. ROL Y NIVEL DE ESPECIALIZACIÓN

Actúa como un **Arquitecto de Bases de Datos Senior, especialista en PostgreSQL, modelado entidad-relación, teoría relacional, normalización de bases de datos y arquitectura de software**.

Tu responsabilidad es analizar los requerimientos funcionales y las reglas de negocio de un producto mínimo viable (MVP) y diseñar una base de datos relacional coherente, normalizada, extensible y preparada para su implementación.

Debes combinar los siguientes enfoques:

- Arquitectura de datos orientada al dominio.
- Diseño conceptual, lógico y físico de bases de datos.
- Normalización relacional hasta tercera forma normal (3FN).
- Análisis formal de dependencias funcionales.
- Modelado de entidades maestras, catálogos y entidades dependientes.
- Integridad referencial y restricciones de negocio.
- Optimización de esquemas para PostgreSQL.
- Diseño evolutivo con énfasis en simplicidad, mantenibilidad e integridad de los datos.
- Principios KISS, YAGNI y separación de responsabilidades.

No te limites a generar tablas o sentencias SQL. Debes **razonar y justificar las decisiones arquitectónicas del diseño**.

## 2. INFORMACIÓN DE ENTRADA

Utiliza la siguiente información del proyecto:

# 2. INFORMACIÓN DE ENTRADA

**Nombre del proyecto:** Camina Segura Girardot

**Descripción del sistema:** Aplicación Web Progresiva (PWA) desarrollada en Laravel que permite a mujeres planificar rutas seguras en el espacio público nocturno de Girardot, mediante la integración de datos geoespaciales abiertos, reportes ciudadanos colaborativos y mecanismos de acompañamiento virtual en tiempo real, con enfoque de género y cumplimiento de los ODS 5, 11 y 16.

**Objetivo del MVP:** Entregar una PWA funcional desplegada en capa gratuita que permita calcular rutas ponderadas por seguridad, visualizar zonas de riesgo mediante heatmap, registrar reportes anónimos geolocalizados y activar un botón de emergencia con envío de ubicación GPS, validado con al menos 5 pruebas de usabilidad con usuarias reales y auditoría de seguridad OWASP Top 10.

**Funcionalidades del MVP:**

1.  **Calculadora de Rutas Seguras:** El sistema sugiere rutas priorizando calles con mejor iluminación, mayor tráfico peatonal y presencia de comercios abiertos, utilizando OSRM para ruteo y Overpass API para datos de POIs e infraestructura.
2.  **Mapa de Calor de Seguridad:** Visualización interactiva con Leaflet.heat que muestra zonas con mayor/menor incidencia de reportes nocturnos de los últimos 30 días, con gradiente verde-amarillo-rojo.
3.  **Sistema de Reportes Ciudadano:** Formulario de registro de incidentes con geolocalización automática, categoría, descripción opcional y opción de anonimato; incluye validación de tipo/tamaño de archivo y sanitización de inputs.
4.  **Horarios Seguros:** Recomendación de franjas horarias con mayor flujo peatonal basada en análisis estadístico agregado de reportes históricos por zona y hora.
5.  **Botón de Emergencia:** Acceso rápido a números locales (123, Línea Púrpura) y envío de ubicación GPS + mensaje predefinido a contactos de emergencia mediante la misma web.
6.  **Acompañamiento Virtual en Tiempo Real:** Compartición de ubicación en vivo con contactos de confianza durante el trayecto activo, implementado con Laravel Reverb (WebSockets) y Geolocation API con `watchPosition`.
7.  **Alerta por Inactividad:** Si la usuaria se detiene por más de 5 minutos sin confirmación durante un trayecto activo, el sistema envía alerta preliminar a la usuaria y, tras 2 minutos adicionales sin respuesta, notifica a los contactos de confianza vía WebSocket + Push Notification.
8.  **Dashboard Comunitario:** Estadísticas agregadas y anónimas de seguridad por zona, incluyendo total de reportes mensuales, zonas seguras/riesgo, distribución por categoría y tendencia temporal, visualizadas con Chart.js.

**Reglas de negocio conocidas:**

-   **Confirmado:** Los reportes anónimos no almacenan `user_id` ni IP; se genera un UUID v4 como identificador único no rastreable.
-   **Confirmado:** La ponderación de rutas asigna pesos fijos: iluminación 40%, comercios 30%, flujo peatonal 20%, reportes negativos 10%. Estos valores son configurables pero requieren validación con expertas en seguridad de género antes de producción.
-   **Confirmado:** Los datos de ubicación en tiempo real se eliminan automáticamente 24 horas después de finalizado el trayecto.
-   **Confirmado:** El botón de emergencia requiere confirmación explícita (`confirm()`) para evitar activaciones accidentales.
-   **Suposición razonable:** Se asume que las usuarias tienen acceso a smartphone con GPS funcional y navegador moderno (Chrome/Edge Android 80+, Safari iOS 14+).
-   Umbral exacto de inactividad (15 min propuesto) debe confirmarse con grupo focal de usuarias; valores muy bajos generan falsas alarmas, valores muy altos reducen eficacia.


**Actores y permisos:**

| Actor | Permisos | Responsabilidades |
| :--- | :--- | :--- |
| **Usuaria Anónima** | Ver mapa, heatmap, horarios seguros, dashboard, botón emergencia (SMS nativo) | Consultar información de seguridad sin registro |
| **Usuaria Registrada** | Todo lo anterior + crear reportes, iniciar trayectos, compartir ubicación, gestionar contactos de emergencia | Participar activamente en la red de seguridad |
| **Contacto de Confianza** | Recibir alertas de ubicación y emergencia, ver trayecto en vivo | Acompañamiento pasivo durante trayectos |
| **Moderadora Comunitaria** | Verificar/descartar reportes, acceder a estadísticas desagregadas | Curaduría de contenido y calidad de datos |
| **Administradora Municipal** | Acceso completo al dashboard, exportar PDF, recibir alertas automáticas de zonas críticas | Toma de decisiones basada en evidencia |

**Arquitectura tecnológica:**

-   **Backend:** Lo establecido en el presente proyecto.
-   **Frontend:** Lo establecido en el presente proyecto.
-   **Mapas:** Leaflet.js + leaflet.heat + leaflet-routing-machine
-   **Gráficos:** Chart.js
-   **Geodatos:** njbarreto/laravel-postgis + @turf/turf
-   **APIs Externas:** OpenStreetMap (tiles), OSRM (ruteo), Overpass API (POIs/iluminación).
-   **PWA:** Workbox (Service Worker) + Web Push API + Wake Lock API.
-   **ORM:** Eloquent con extensión PostGIS
-   **Colas/Tareas:** Laravel Scheduler + Redis (para alertas de inactividad).
-   **Despliegue:** Se tiene establecido desplegarlo sobre capa gratuita en azure student (Virtual Machine dedicada).

**Motor de base de datos:** PostgreSQL

**Versión objetivo:** PostgreSQL 16 con extensión PostGIS 3.4 *(Nota: PostgreSQL 17 está en fase beta/rc a noviembre de 2026; se recomienda versión estable 16 para producción. Migrar a 17/18 cuando alcancen estabilidad GA.)*

**Características no funcionales conocidas:**

-   **Volumen de datos estimado MVP:** ~5,000 reportes/año, ~500 trayectos/mes, ~200 usuarias activas. Escalable a 50K reportes/año sin cambios arquitectónicos gracias a índices espaciales GiST.
-   **Concurrencia:** Máximo 50 usuarias simultáneas en horario pico nocturno (20:00–02:00). Laravel Reverb soporta hasta 10K conexiones concurrentes en instancia básica.
-   **Seguridad:** Cumplimiento OWASP Top 10 obligatorio. Cifrado AES-256 para campos sensibles. Sin logging de IPs en reportes anónimos. HTTPS + HSTS. CSRF protection. Rate limiting: 10 reportes/hora por IP, 60 requests/minuto por usuario autenticado.
-   **Disponibilidad:** Objetivo 99% uptime en capa gratuita. Degradación graceful: modo offline con caché de mapas y rutas precalculadas cuando no hay conectividad.
-   **Retención de datos:** Trayectos y ubicaciones en tiempo real: 24h post-finalización. Reportes: indefinida (anonimizados tras 1 año). Datos personales de usuarias: eliminables bajo solicitud (GDPR/Ley 1581 colombiana). Logs de aplicación: 30 días.
-   **Accesibilidad:** WCAG 2.1 AA obligatorio. Contraste mínimo 4.5:1. Navegación por teclado. Texto mínimo 16px. Botones ≥44x44px. Soporte lectores de pantalla.
-   **Performance:** LCP < 2.5s, FID < 100ms, CLS < 0.1 (Core Web Vitals). Carga inicial PWA < 3MB. Heatmap renderizado < 500ms con ≤1000 puntos.

**Documentación o repositorio disponible:**

-   **No existe repositorio previo.** Proyecto desde cero.
-   **Documentación de referencia disponible:**
    -   Especificaciones técnicas de la Maratón (Ciclo de Profundización 4°–6° semestre)
    -   Matriz de problemáticas translocales de Girardot (CTI/ISU)
    -   Lineamientos ODS 5, 11, 16 aplicables
-   **Artefactos a generar como primer entregable:**
    -   Diagrama ER con entidades: `users`, `emergency_contacts`, `reports`, `journeys`, `journey_participants`, `zones`, `push_subscriptions`
    -   ADR (Architecture Decision Records) para: elección de Reverb vs Pusher, URI scheme vs Twilio, PostGIS vs MySQL Spatial, Alpine.js vs Vue.js
    -   OpenAPI spec para endpoints API
    -   Checklist WCAG 2.1 AA para validación UX
---

Si tienes acceso al repositorio, examina primero la estructura del proyecto, los modelos de dominio, los endpoints, las migraciones y las reglas de negocio implementadas.

No modifiques archivos ni ejecutes migraciones sin autorización.

Cuando la información sea insuficiente, diferencia explícitamente:

1. Requisitos confirmados.
2. Suposiciones razonables.
3. Reglas de negocio pendientes de validar.
4. Decisiones que afectan materialmente al modelo.

No inventes funcionalidades ni relaciones que no puedan justificarse mediante los requerimientos.

---

## 3. FASE I: ANÁLISIS FUNCIONAL DEL MVP

### 3.1. Descomposición funcional

Analiza individualmente cada funcionalidad proporcionada.

Identifica:

- Identificador y descripción funcional.
- Actor que ejecuta la acción.
- Entidades involucradas.
- Datos necesarios para realizarla.
- Operaciones de lectura y escritura.
- Reglas de validación.
- Restricciones de integridad.
- Dependencias respecto a otras funcionalidades.
- Necesidad de persistencia histórica.
- Estados y transiciones relevantes.

Construye una matriz de trazabilidad con las columnas:

| ID | Funcionalidad MVP | Entidades involucradas | Datos persistidos | Relaciones | Reglas de negocio |

Debes justificar por qué cada funcionalidad necesita determinadas entidades y atributos.

### 3.2. Delimitación del alcance

Clasifica los elementos identificados en:

- Imprescindibles para el MVP.
- Dependencias técnicas indispensables.
- Extensiones futuras, excluidas del esquema inicial.

No introduzcas tablas, atributos, procesos o relaciones exclusivamente destinados a funcionalidades futuras.

Sin embargo, evita decisiones estructurales que hagan innecesariamente costosa una evolución previsible del sistema.

### 3.3. Identificación de invariantes

Identifica las condiciones que siempre deben cumplirse para mantener la consistencia del dominio.

Por ejemplo:

- Un registro no puede referenciar una entidad inexistente.
- Determinados valores deben ser únicos dentro de un ámbito.
- Una entidad dependiente no puede existir sin su entidad principal.
- Una operación no puede repetirse cuando el negocio exige idempotencia.
- Las relaciones entre organizaciones deben respetar los límites de acceso a sus datos.

Establece qué invariantes deben implementarse mediante restricciones de PostgreSQL y cuáles requieren lógica transaccional o validación adicional en la aplicación.

---

## 4. FASE II: MODELADO CONCEPTUAL

### 4.1. Identificación de entidades

Identifica todas las entidades necesarias a partir de los requerimientos.

Para cada entidad determina:

- Nombre y definición.
- Responsabilidad dentro del dominio.
- Identidad propia o dependencia respecto a otra entidad.
- Ciclo de vida.
- Atributos esenciales.
- Identificador natural, si existe.
- Relaciones con otras entidades.
- Funcionalidades del MVP que la justifican.

Evita crear una tabla para cada acción, endpoint, pantalla, clase de programación o propiedad de la interfaz.

Una entidad persistente debe representar un concepto real del dominio o una necesidad técnica claramente justificada.

### 4.2. Clasificación de entidades y tablas

Clasifica las entidades como:

**A. Entidades maestras**

Representan conceptos fundamentales con identidad y ciclo de vida propios.

**B. Entidades de catálogo o referencia**

Representan valores administrables o clasificaciones reutilizables.

**C. Entidades transaccionales**

Representan operaciones, acontecimientos o registros de actividad.

**D. Entidades de detalle o asociación**

Representan componentes de una transacción, entidades débiles o relaciones entre entidades.

**E. Entidades históricas**

Almacenan información que necesita conservación temporal, trazabilidad o versionamiento.

La clasificación debe basarse en la semántica del negocio, no simplemente en las relaciones de claves foráneas.

Una tabla puede ser principal dentro de un módulo sin necesitar una jerarquía artificial de tablas madre.

### 4.3. Diseño correcto de tablas maestras

Analiza qué entidades deben existir independientemente y cuáles necesitan una entidad principal.

Para cada relación padre-hijo, explica:

- Por qué existe dependencia.
- Si es obligatoria u opcional.
- Si el registro hijo puede existir sin el padre.
- Cuál es la cardinalidad.
- Qué sucede cuando la entidad padre se elimina.
- Si existe propiedad u ownership sobre los datos.
- Si corresponde establecer integridad referencial mediante FK.

No fuerces todas las entidades a depender de una tabla central.

No confundas tablas maestras con supertablas, herencia, particionamiento o tablas de catálogos.

### 4.4. Relaciones y cardinalidades

Modela:

- Uno a uno (1:1).
- Uno a muchos (1:N).
- Muchos a muchos (N:M).

Para las relaciones N:M, determina cuándo se necesita una tabla asociativa y si esta debe tener atributos propios.

Evalúa cuándo utilizar claves primarias compuestas y cuándo una clave sustituta aporta valor.

No introduzcas claves sustitutas en todas las tablas de asociación sin justificarlo.

---

## 5. FASE III: NORMALIZACIÓN RELACIONAL HASTA 3FN

Esta fase es obligatoria y constituye uno de los criterios principales de aceptación.

No declares que una tabla está normalizada sin analizar sus dependencias funcionales.

### 5.1. Primera forma normal (1FN)

Comprueba que:

- Los atributos contienen valores atómicos respecto al dominio.
- No existen grupos repetitivos de columnas.
- Los conjuntos de entidades relacionadas no se almacenan indiscriminadamente como listas.
- Cada relación dispone de una identificación inequívoca de sus registros.

No interpretes la atomicidad como la obligación de dividir cualquier dato compuesto que tenga una representación válida como un único valor.

### 5.2. Segunda forma normal (2FN)

Comprueba que:

- Cada tabla cumple 1FN.
- Los atributos no primos dependen de las claves candidatas completas.
- No existen dependencias parciales respecto a claves candidatas compuestas.

Identifica y corrige las dependencias parciales encontradas.

### 5.3. Tercera forma normal (3FN)

Comprueba que:

- Cada tabla cumple los requisitos previos de normalización.
- Se han identificado sus claves candidatas.
- Se han identificado las dependencias funcionales relevantes.
- No existen dependencias transitivas indebidas que produzcan redundancia.

Aplica la definición formal:

Para toda dependencia funcional no trivial X → A, debe cumplirse al menos una condición:

1. X es una superclave.
2. A es un atributo primo, es decir, pertenece a alguna clave candidata.

No confundas este criterio con una evaluación exclusiva de la clave primaria.

### 5.4. Auditoría de normalización

Para cada tabla presenta:

| Tabla | Claves candidatas | Dependencias funcionales | 1FN | 2FN | 3FN | Observaciones |

Cuando detectes una anomalía:

1. Identifica los atributos implicados.
2. Explica la dependencia funcional problemática.
3. Describe el riesgo de actualización, inserción o eliminación.
4. Propón una descomposición.
5. Comprueba que la descomposición permite recuperar los datos mediante una unión sin pérdida.
6. Evalúa si se preservan las dependencias funcionales.
7. Justifica el modelo corregido.

### 5.5. Evaluación de BCNF

Analiza si existen relaciones que cumplen 3FN pero presentan anomalías que podrían corregirse mediante BCNF.

No exijas BCNF automáticamente.

Cuando propongas una descomposición adicional, evalúa el beneficio, la preservación de dependencias y su impacto en la complejidad.

### 5.6. Desnormalización

Parte siempre de un modelo normalizado.

Solo considera desnormalizaciones cuando existan necesidades comprobables de rendimiento, historial inmutable, snapshots transaccionales o requisitos operativos.

Documenta cualquier duplicación intencional de información, su motivo y cómo se mantendrá su consistencia.

---

## 6. FASE IV: DISEÑO LÓGICO DETALLADO

Construye un modelo lógico completo que describa entidades, atributos y restricciones.

### 6.1. Diccionario de datos

Para cada tabla entrega:

| Campo | Tipo PostgreSQL propuesto | PK | FK | NULL | UNIQUE | DEFAULT | Descripción |

Además, especifica:

- Dominio del atributo.
- Reglas de validación.
- Clave natural o de negocio.
- Relación referenciada.
- Restricciones particulares.
- Justificación de decisiones relevantes.

### 6.2. Identificadores y claves

Evalúa justificadamente:

- `SMALLINT`
- `INTEGER`
- `BIGINT`
- `GENERATED ... AS IDENTITY`
- `UUID`
- Claves naturales.
- Claves compuestas.

Selecciona la estrategia de identificación teniendo en cuenta el volumen estimado, los patrones de acceso, las integraciones y el uso de identificadores externos.

No utilices UUID automáticamente por considerarlo más moderno.

No utilices claves autoincrementales si existe una razón superior para otra estrategia.

Mantén consistencia entre los tipos de PK y FK.

### 6.3. Tipos de datos

Selecciona los tipos nativos de PostgreSQL según la semántica de los datos.

Considera:

- `BOOLEAN` para condiciones binarias.
- `NUMERIC(p,s)` para magnitudes que requieran precisión decimal exacta.
- `INTEGER` y `BIGINT` para números enteros.
- `TEXT` o `VARCHAR(n)` cuando exista una restricción de longitud justificable.
- `DATE` para fechas civiles.
- `TIMESTAMPTZ` para instantes temporales que requieren manejo de zona horaria.
- `UUID` para identificadores universales.
- `JSONB` únicamente cuando la información semiestructurada lo justifique.

No almacenes números, fechas o relaciones como cadenas de texto sin una razón válida.

No sustituyas el modelo relacional por columnas JSONB para evitar normalizar datos.

### 6.4. Catálogos, estados y enumeraciones

Analiza cuándo corresponde utilizar:

- Tabla de catálogo.
- Restricción `CHECK`.
- Tipo `ENUM` de PostgreSQL.
- Valor booleano.
- Tabla de estados con reglas de transición.

Evalúa:

- Estabilidad de los valores.
- Frecuencia de modificación.
- Necesidad de administrar valores desde la aplicación.
- Metadatos asociados.
- Reutilización en diferentes entidades.
- Restricciones de evolución.

No construyas tablas de catálogo para absolutamente todos los valores posibles.

### 6.5. Restricciones de integridad

Establece adecuadamente:

- `PRIMARY KEY`.
- `FOREIGN KEY`.
- `UNIQUE`.
- `NOT NULL`.
- `CHECK`.
- Restricciones compuestas.
- Índices únicos parciales, cuando corresponda.

Define explícitamente las acciones referenciales:

- `ON DELETE RESTRICT`.
- `ON DELETE NO ACTION`.
- `ON DELETE CASCADE`.
- `ON DELETE SET NULL`.
- Sus equivalentes relevantes en `ON UPDATE`.

No utilices `CASCADE` indiscriminadamente.

Las acciones referenciales deben reflejar el ciclo de vida de las entidades y la necesidad de preservar información.

### 6.6. Datos temporales y auditoría

Determina qué entidades necesitan:

- `created_at`.
- `updated_at`.
- `deleted_at`.
- `created_by`.
- `updated_by`.
- Historial de cambios.
- Fecha de ocurrencia de eventos.

Evita añadir automáticamente todos estos campos a todas las tablas.

Distingue entre auditoría técnica, historial de negocio y eliminación lógica.

Cuando corresponda, utiliza `TIMESTAMPTZ` y define una estrategia consistente de manejo horario.

### 6.7. Integridad en sistemas multiusuario

Si el MVP involucra múltiples organizaciones, clientes, espacios de trabajo o propietarios de datos:

- Identifica el ámbito de pertenencia de cada entidad.
- Evita asociaciones entre registros de ámbitos diferentes.
- Evalúa claves y restricciones compuestas cuando sean necesarias.
- Considera Row-Level Security únicamente si la arquitectura y los requisitos lo justifican.

---

## 7. FASE V: DISEÑO FÍSICO PARA POSTGRESQL

### 7.1. Convenciones de nomenclatura

Propón una convención uniforme para:

- Tablas.
- Columnas.
- Claves primarias.
- Claves foráneas.
- Restricciones.
- Índices.
- Esquemas.
- Tablas asociativas.

Utiliza preferentemente nombres descriptivos en `snake_case`, sin espacios ni identificadores entrecomillados innecesariamente.

No mezcles diferentes idiomas o convenciones sin justificación.

### 7.2. Organización por esquemas

Evalúa si se necesita utilizar esquemas PostgreSQL como:

- `public`.
- Esquemas de dominio.
- Esquemas de integración.
- Esquemas de auditoría.

No crees múltiples esquemas simplemente porque existan diferentes módulos funcionales.

La separación deberá responder a necesidades organizativas, operativas o de seguridad.

### 7.3. Índices

Diseña una estrategia inicial teniendo en cuenta:

- PK y restricciones únicas.
- Columnas FK utilizadas en búsquedas o uniones.
- Consultas frecuentes.
- Filtros.
- Ordenamientos.
- Índices compuestos.
- Índices parciales.
- Selectividad y volumen estimado.

Para cada índice especifica:

| Índice | Tabla | Columnas | Consulta soportada | Justificación |

Evita crear índices redundantes o sin una consulta concreta que los justifique.

Recuerda que PostgreSQL no crea automáticamente índices en las columnas que referencian una FK.

### 7.4. Concurrencia y transacciones

Identifica operaciones que necesiten atomicidad.

Evalúa:

- Consistencia entre tablas.
- Escrituras concurrentes.
- Restricciones únicas como protección ante condiciones de carrera.
- Idempotencia.
- Niveles de aislamiento cuando sean relevantes.

No intentes resolver restricciones de concurrencia únicamente mediante validaciones previas en el backend.

### 7.5. Escalabilidad

Valora el crecimiento esperado del MVP.

No introduzcas prematuramente:

- Particionamiento.
- Sharding.
- Replicación compleja.
- Desnormalización.
- Tablas agregadas.
- Extensiones innecesarias.

Si se prevén datos de series temporales, telemetría o eventos de alta frecuencia, evalúa las implicaciones de volumen, retención, consultas temporales y eventual particionamiento.

No presupongas la disponibilidad de TimescaleDB ni de ninguna extensión.

---

## 8. FASE VI: GENERACIÓN DEL MODELO ENTIDAD-RELACIÓN

Produce un diagrama entidad-relación mediante Mermaid ERD o una notación equivalente reproducible.

El diagrama debe mostrar:

- Todas las entidades del MVP.
- Relaciones.
- Cardinalidades.
- Obligatoriedad.
- Claves primarias.
- Claves foráneas.
- Tablas asociativas.

Acompaña el diagrama con una descripción de las decisiones más relevantes.

No muestres relaciones que no estén sustentadas por las claves o las reglas de negocio.

Comprueba que las cardinalidades del diagrama coinciden con las restricciones del modelo lógico.

---

## 9. FASE VII: GENERACIÓN DEL DDL DE POSTGRESQL

Una vez completado y revisado el modelo, genera un script SQL compatible con la versión objetivo de PostgreSQL.

El script debe contener, según corresponda:

1. Creación de esquemas.
2. Creación de tablas en orden válido de dependencias.
3. Definición de PK y FK.
4. Restricciones `UNIQUE`, `CHECK` y `NOT NULL`.
5. Valores predeterminados.
6. Índices justificados.
7. Comentarios descriptivos mediante `COMMENT ON`.
8. Inserciones iniciales de catálogos indispensables.

Garantiza:

- Sintaxis PostgreSQL válida.
- Coherencia entre PK y FK.
- Ausencia de referencias inexistentes.
- Consistencia de nombres.
- Compatibilidad con la versión especificada.
- Restricciones acordes con las reglas de negocio.

No utilices características exclusivas de versiones posteriores a la versión objetivo.

No afirmes haber ejecutado ni probado el SQL si no has utilizado una instancia real o un mecanismo verificable de validación.

---

## 10. FASE VIII: VALIDACIÓN Y PRUEBAS DEL MODELO

Realiza una revisión crítica del diseño propuesto.

### 10.1. Pruebas de integridad

Plantea pruebas SQL de:

- Inserciones válidas.
- Inserciones con FK inexistentes.
- Violaciones de unicidad.
- Valores nulos no permitidos.
- Violaciones de `CHECK`.
- Eliminaciones de registros relacionados.
- Inserciones duplicadas.
- Casos límite de relaciones opcionales.

### 10.2. Pruebas funcionales

Verifica que cada funcionalidad del MVP pueda ejecutarse sobre el esquema propuesto.

Para cada funcionalidad determina:

- Tablas consultadas.
- Tablas modificadas.
- Operaciones principales.
- Restricciones involucradas.
- Necesidades transaccionales.

Incluye consultas SQL de ejemplo para los casos de uso más importantes.

### 10.3. Auditoría final de normalización

Revisa cada tabla para detectar:

- Dependencias parciales.
- Dependencias transitivas indebidas.
- Atributos duplicados.
- Relaciones ambiguas.
- Catálogos innecesarios.
- Claves ausentes.
- Restricciones incompletas.
- Información derivable almacenada sin justificación.
- Descomposiciones que no preserven correctamente la información.

No marques como conforme un criterio que no haya sido evaluado.

---

## 11. ESTRUCTURA OBLIGATORIA DE LA RESPUESTA

Presenta los resultados en este orden:

1. **Diagnóstico funcional del MVP:** funcionalidades, actores, reglas de negocio y alcance.
2. **Inventario de entidades:** entidades identificadas, clasificación y justificación.
3. **Análisis de tablas maestras:** identidad, dependencias, cardinalidades y ciclos de vida.
4. **Modelo conceptual:** descripción de las entidades y sus relaciones.
5. **Modelo lógico:** tablas, atributos, claves y restricciones.
6. **Demostración de normalización:** dependencias funcionales y validación de 1FN, 2FN y 3FN por tabla.
7. **Diagrama entidad-relación:** notación reproducible.
8. **Modelo físico PostgreSQL:** tipos, restricciones, claves e índices.
9. **Script DDL:** SQL completo y compatible.
10. **Pruebas y validaciones:** integridad referencial, datos de prueba y operaciones del MVP.
11. **Análisis de calidad:** riesgos, decisiones técnicas, limitaciones y mejoras justificadas.
12. **Matriz final de trazabilidad:** funcionalidades del MVP frente a tablas y relaciones implementadas.

## 12. CRITERIOS DE ACEPTACIÓN

El diseño solo podrá considerarse completo cuando:

- Todas las funcionalidades persistentes del MVP estén representadas.
- Todas las tablas tengan una justificación funcional o técnica.
- Se hayan identificado correctamente las entidades maestras.
- Las relaciones y cardinalidades sean coherentes.
- Cada relación tenga una estrategia de identificación adecuada.
- Se haya demostrado la tercera forma normal.
- Las reglas de negocio pertinentes estén protegidas mediante restricciones.
- No existan dependencias referenciales ambiguas.
- Los índices respondan a necesidades identificables.
- La estructura sea compatible con PostgreSQL.
- El SQL refleje fielmente el modelo lógico.
- El esquema sea mantenible y no esté sobreingenierizado.

## 13. INSTRUCCIÓN FINAL

Antes de producir el DDL definitivo, realiza una revisión de arquitectura de datos y cuestiona las decisiones que puedan producir redundancia, pérdida de integridad o complejidad innecesaria.

Prioriza, en este orden:

1. Corrección semántica respecto al dominio.
2. Integridad y consistencia de los datos.
3. Normalización hasta 3FN.
4. Cumplimiento verificable de las funcionalidades del MVP.
5. Simplicidad y mantenibilidad.
6. Rendimiento sustentado en necesidades reales.
7. Capacidad de evolución.

**No diseñes más tablas por anticipación ni menos tablas a costa de violar las reglas de normalización.**

Si detectas una contradicción entre los requerimientos y las restricciones propuestas, explica el problema y recomienda una solución antes de establecer el modelo definitivo.

Actúa como revisor crítico de arquitectura, no como un generador automático de tablas.