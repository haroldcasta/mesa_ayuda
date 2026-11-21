<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
$configFile = ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Falta config.php. Consulta README.md para completar la instalación.');
}
$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('Configuración inválida.');
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('America/Bogota');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('uv_helpdesk');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => parse_url((string)($config['base_url'] ?? '/'), PHP_URL_PATH) ?: '/',
    ]);
    session_start();
}

function cfg(string $key, mixed $default = null): mixed {
    global $config;
    return $config[$key] ?? $default;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = cfg('db');
    $pdo = new PDO(
        'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
        $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("SET time_zone = '-05:00'");
    return $pdo;
}
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim((string)cfg('base_url'), '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function check_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Sesión vencida. Recarga la página e inténtalo de nuevo.'); }
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function take_flash(): ?array { $v = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $v; }
function user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cached = null;
    if ($cached !== null) return $cached;
    $s = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $s->execute([(int)$_SESSION['user_id']]);
    return $cached = ($s->fetch() ?: null);
}
function login_user(int $id): void { session_regenerate_id(true); $_SESSION['user_id'] = $id; $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
function require_user(): array { $u = user(); if (!$u) redirect('index.php?page=login'); return $u; }
function is_staff(?array $u = null): bool { $u ??= user(); return $u && in_array($u['role'], ['agent', 'admin'], true); }
function require_staff(): array { $u = require_user(); if (!is_staff($u)) { http_response_code(403); exit('Acceso restringido.'); } return $u; }
function require_admin(): array { $u = require_user(); if ($u['role'] !== 'admin') { http_response_code(403); exit('Acceso restringido.'); } return $u; }
function request_text(string $key, int $max = 4000): string { return trim(mb_substr((string)($_POST[$key] ?? ''), 0, $max)); }
function ticket_code(int $id): string { return 'UV-' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function priority_for_urgency(string $urgency): string {
    return ['alta'=>'P2','media'=>'P3','baja'=>'P4'][$urgency] ?? 'P4';
}
function can_view_ticket(array $ticket, array $u): bool { return is_staff($u) || (int)$ticket['user_id'] === (int)$u['id']; }
function ticket_by_id(int $id): ?array {
    $s = db()->prepare('SELECT t.*, u.name AS student_name, u.email AS student_email, a.name AS agent_name FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN users a ON a.id=t.assigned_to WHERE t.id=?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function event(int $ticket, ?int $actor, string $type, string $details): void {
    $s = db()->prepare('INSERT INTO ticket_events (ticket_id, actor_id, event_type, details) VALUES (?,?,?,?)');
    $s->execute([$ticket, $actor, $type, $details]);
}
function status_label(string $status): string {
    return ['new'=>'Radicada','assigned'=>'Asignada','progress'=>'En atención','waiting'=>'Esperando respuesta','resolved'=>'Resuelta','closed'=>'Cerrada'][$status] ?? $status;
}
function date_label(?string $date): string { return $date ? date('d/m/Y · H:i', strtotime($date)) : '—'; }
function sla_target(array $ticket): array {
    $policy = cfg('sla_hours', [])[$ticket['priority']] ?? ['first'=>8,'resolution'=>72];
    $waitingFirst = empty($ticket['first_response_at']);
    $hours = (int)$policy[$waitingFirst ? 'first' : 'resolution'];
    $deadline = strtotime((string)$ticket['created_at']) + ($hours * 3600);
    return ['label'=>$waitingFirst?'Primera respuesta':'Resolución', 'deadline'=>$deadline, 'overdue'=>$deadline < time() && !in_array($ticket['status'],['resolved','closed'],true)];
}
function support_types(): array {
    return [
        'N1' => [
            'label' => 'Tipo 1 · Orientación y acceso básico',
            'categories' => [
                'Ingreso a la plataforma',
                'Recuperación de acceso',
                'Ubicación de aulas, actividades o recursos',
                'Orientación sobre canales institucionales',
                'Otra consulta básica',
            ],
        ],
        'N2' => [
            'label' => 'Tipo 2 · Soporte funcional de Moodle',
            'categories' => [
                'Roles, permisos o visibilidad de cursos',
                'Fallas en actividades, recursos o notificaciones',
                'Uso funcional de Moodle',
                'Calificaciones y retroalimentación',
                'Otro soporte funcional',
            ],
        ],
        'N3' => [
            'label' => 'Tipo 3 · Continuidad, infraestructura o seguridad',
            'categories' => [
                'Caída o indisponibilidad de la plataforma',
                'Falla de base de datos',
                'Falla generalizada de autenticación',
                'Incidente de integración',
                'Error de servidor, red o almacenamiento',
                'Incidente de seguridad',
                'Otro incidente crítico',
            ],
        ],
    ];
}
function support_type_name(string $level): string {
    return ['N1'=>'Tipo 1','N2'=>'Tipo 2','N3'=>'Tipo 3'][$level] ?? $level;
}
function save_upload(int $ticketId, ?int $messageId, int $userId): void {
    if (empty($_FILES['evidence']['name']) || (int)($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    $f = $_FILES['evidence'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > (int)cfg('upload_max_bytes', 5242880)) throw new RuntimeException('El archivo supera 5 MB o no se pudo cargar.');
    $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','application/pdf'=>'pdf','image/webp'=>'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Adjunta PNG, JPG, WEBP o PDF.');
    $stored = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], ROOT . '/storage/' . $stored)) throw new RuntimeException('No se pudo guardar el archivo.');
    $name = basename(str_replace('\\', '/', (string)$f['name']));
    $s = db()->prepare('INSERT INTO attachments (ticket_id,message_id,uploader_id,stored_name,original_name,mime_type,file_size) VALUES (?,?,?,?,?,?,?)');
    $s->execute([$ticketId,$messageId,$userId,$stored,mb_substr($name,0,180),$mime,(int)$f['size']]);
}
