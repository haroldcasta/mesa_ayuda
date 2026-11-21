# Mesa de ayuda · Unidad Virtual Uninúñez

Primera versión funcional para un servidor de pruebas con cPanel. Implementada con PHP 8.1+, MySQL/MariaDB, HTML, CSS y JavaScript sin dependencias de Composer ni proceso de compilación. La interfaz usa la paleta azul de la Unidad Virtual (`#6279F6`, `#7286F7`, `#8194F8`, `#A1AFFA`, `#D0D7FC`, `#EFF2FE` y tonos oscuros `#273062` y `#141831`).

## Qué permite probar

- Registro e ingreso local de estudiantes para el piloto.
- Ingreso con Google opcional, mediante OpenID Connect, cuando exista un cliente OAuth autorizado.
- Creación de solicitudes en un único formulario, con tipo de soporte (1, 2 o 3), categoría dependiente, descripción, curso opcional y evidencia.
- Número de radicado, estados, mensajes, notas internas, historial y valoración.
- Bandeja para agentes, búsqueda, asignación, prioridades y niveles N1/N2/N3.
- Administración de roles.
- Interfaz adaptable a escritorio y móvil.

El solicitante elige un tipo de soporte y después una categoría de ese tipo. La combinación se valida al enviar, y el tipo inicia el nivel interno del ticket: Tipo 1 = N1 (orientación y acceso básico), Tipo 2 = N2 (uso funcional de Moodle) y Tipo 3 = N3 (continuidad, infraestructura o seguridad). El equipo puede corregir el nivel tras revisar el caso; la clasificación inicial queda en el historial. La prioridad es independiente del tipo. Como el formulario ya no pregunta por urgencia y la tabla instalada todavía exige ese dato, las nuevas solicitudes guardan `media` como valor técnico y comienzan en P3. **No debe interpretarse como urgencia declarada por el solicitante**; los tickets anteriores conservan sus datos originales. Esta versión todavía no ofrece un control de reclasificación manual de prioridad.

La tabla existente todavía exige la columna `impact`. Para evitar una migración de la base instalada, las nuevas solicitudes guardan un valor técnico fijo `individual` en esa columna, sin preguntarlo al solicitante. **Ese valor no debe utilizarse para analizar cuántas personas fueron afectadas**; los tickets anteriores conservan sus datos originales.

## Identidad y permisos

La aplicación identifica a quien radica un ticket por su cuenta (`users.id`, nombre y correo), no por su vinculación institucional. Actualmente solo existen tres roles de **permisos**: `student` (cuenta normal), `agent` (atiende solicitudes) y `admin` (administra usuarios y equipo). Toda cuenta autenticada puede radicar una solicitud. El registro local y el primer ingreso con Google crean la cuenta como `student`, aunque la persona sea docente o administrativa. Por ahora no existe un campo fiable que distinga estudiante, docente y administrativo; eso requerirá una clasificación separada del rol de permisos.

N3 registra la atención especializada dentro de esta aplicación. Todavía no transmite el caso a GLPI ni al portal TIC. Los tiempos del archivo `config.php` son metas de prueba y no se publican como compromisos de atención. La aplicación aún no envía correos automáticos.

## Instalación en cPanel

1. Crea una base MySQL y un usuario en **cPanel → Bases de datos MySQL**. Asigna el usuario a la base con privilegios para crear tablas y leer/escribir datos. Guarda el nombre completo de la base y el usuario (cPanel suele agregar un prefijo).
2. En **Administrador de archivos**, crea una carpeta de prueba, por ejemplo `public_html/mesa-ayuda-uv/`. Sube el ZIP y extráelo allí. El ZIP contiene los archivos directamente, sin una carpeta envolvente; comprueba que `index.php` quede dentro de `mesa-ayuda-uv/`.
   El imagotipo mostrado en el inicio de sesión y la barra lateral está incluido en `assets/imagotipo-uninunez-virtual.png`; debe permanecer en esa carpeta del servidor. No se guarda en la base de datos ni depende del archivo local de Descargas.
3. Duplica `config.example.php` como `config.php`. Edita `base_url`, los cuatro datos de `db` y `install_key`. La URL debe ser la URL pública exacta de la carpeta, sin barra final. Usa una frase aleatoria larga para `install_key`.
4. Selecciona PHP **8.1 o superior** para esa carpeta o dominio. Activa las extensiones `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` y `curl`.
5. Visita `https://TU-DOMINIO/mesa-ayuda-uv/install.php`, introduce la clave de instalación y crea el primer administrador.
6. Borra `install.php` desde cPanel después de instalar. Verifica que `config.php`, `app/` y `storage/` no sean accesibles por URL directa; los archivos `.htaccess` incluidos los protegen en Apache.
7. Ingresa como administrador, registra otra cuenta de prueba y cambia su rol a **Agente** desde **Equipo y roles**. Con una tercera cuenta de estudiante crea un ticket y prueba el ciclo completo.

Si la carpeta `storage/` no permite adjuntar archivos, asígnale permisos de escritura para el usuario PHP del hosting. No uses permisos `777`.

## Activar Google institucional

En Google Cloud, un administrador autorizado debe crear un cliente OAuth de **aplicación web**. Configura como URI de redirección exacta:

`https://TU-DOMINIO/mesa-ayuda-uv/google.php?action=callback`

Coloca su `client_id` y `client_secret` en la sección `google` de `config.php`. El inicio de sesión con Google aparecerá automáticamente. Si solo deben entrar cuentas de un dominio Workspace, define `allowed_domain`, por ejemplo `uninunez.edu.co`. Si hay estudiantes con correos externos, déjalo vacío y define posteriormente una política de admisión. La aplicación valida la firma, audiencia, emisor, vencimiento, nonce y correo verificado del ID Token antes de crear la sesión.

Tras comprobar Google, puedes poner `allow_local_signup` en `false` para impedir nuevos registros locales. Las cuentas locales ya creadas podrán seguir ingresando con contraseña; para deshabilitar también ese acceso se requiere una política posterior de migración.

## Datos y seguridad

- `config.php` contiene credenciales y no debe publicarse en repositorios.
- Los adjuntos se guardan en `storage/`, se descargan mediante `download.php` con autorización y se limitan a PNG, JPG, WEBP o PDF de 5 MB.
- El formulario protege acciones con token CSRF; la salida HTML se escapa; las contraseñas usan `password_hash`.
- Realiza copias periódicas de la base de datos y de `storage/`. Exporta ambas juntas para conservar la relación entre tickets y adjuntos.
- Para uso real con estudiantes, configura HTTPS, políticas de retención y privacidad, correo saliente institucional y una revisión de seguridad del servidor.

## Prueba de aceptación sugerida

1. Crear cuenta de estudiante e iniciar sesión.
2. Elegir Tipo 2 y comprobar que solo aparecen sus categorías. Radicar ticket en el formulario único con captura y comprobar código, categoría, nivel inicial, prioridad y descarga del adjunto.
3. Entrar como administrador/agente, asignar el ticket, cambiar su nivel y comprobar el historial; escribir respuesta y nota interna.
4. Verificar que el estudiante ve la respuesta, pero no la nota interna.
5. Poner el caso en «Esperando respuesta». Responder como estudiante y comprobar que vuelve a «En atención».
6. Resolver, valorar y volver a responder para comprobar la reapertura.
7. Repetir los pasos desde un teléfono o con el navegador en ancho reducido.

## Límites de esta versión

La integración con Portal Uninúñez, Moodle, GLPI o la API institucional requiere autorización y documentación técnica de TIC. Tampoco están conectados todavía correo, WhatsApp o llamadas como canales de entrada de tickets. La aplicación funciona de manera independiente mientras tanto; luego podrá enlazarse desde esas superficies o compartir tickets a través de API.
