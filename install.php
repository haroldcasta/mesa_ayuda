<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$error = '';

function database_connection_message(Throwable $e): string {
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        return 'El servidor no tiene habilitada la extensión pdo_mysql. Actívala para esta versión de PHP en cPanel.';
    }
    $message = $e->getMessage();
    if (stripos($message, 'Access denied') !== false) {
        return 'MySQL rechazó el usuario o la contraseña configurados. Revisa únicamente los campos user y pass de config.php.';
    }
    if (stripos($message, 'Unknown database') !== false) {
        return 'MySQL no encontró la base configurada. Revisa el campo name de config.php.';
    }
    if (stripos($message, 'Connection refused') !== false || stripos($message, 'No such file or directory') !== false) {
        return 'El servidor no pudo localizar MySQL con el host configurado. Revisa el campo host o consulta el host MySQL de cPanel.';
    }
    return 'No se pudo conectar a la base de datos. Revisa config.php y la configuración MySQL de cPanel.';
}

try {
    $exists = (bool)db()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
} catch (Throwable $e) { $error = database_connection_message($e); $exists = false; }
if ($exists) { http_response_code(403); exit('La aplicación ya está instalada. Elimina install.php del servidor.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $key = (string)($_POST['install_key'] ?? '');
    $name = request_text('name', 120);
    $email = strtolower(request_text('email', 190));
    $pass = (string)($_POST['password'] ?? '');
    if (!hash_equals((string)cfg('install_key'), $key) || str_starts_with((string)cfg('install_key'), 'CAMBIA_')) $error = 'Clave de instalación incorrecta.';
    elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 12) $error = 'Indica nombre, correo válido y contraseña de al menos 12 caracteres.';
    else {
        try {
            $sql = [
                "CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NULL, role ENUM('student','agent','admin') NOT NULL DEFAULT 'student', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE tickets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NULL UNIQUE, user_id BIGINT UNSIGNED NOT NULL, assigned_to BIGINT UNSIGNED NULL, subject VARCHAR(180) NOT NULL, category VARCHAR(80) NOT NULL, description TEXT NOT NULL, course VARCHAR(180) NULL, course_url VARCHAR(500) NULL, impact ENUM('individual','grupo','general') NOT NULL, urgency ENUM('baja','media','alta') NOT NULL, priority ENUM('P1','P2','P3','P4') NOT NULL, level ENUM('N1','N2','N3') NOT NULL DEFAULT 'N1', status ENUM('new','assigned','progress','waiting','resolved','closed') NOT NULL DEFAULT 'new', first_response_at DATETIME NULL, resolved_at DATETIME NULL, rating TINYINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_queue(status,priority,created_at), INDEX idx_user(user_id,created_at), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (assigned_to) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, internal TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ticket(ticket_id,created_at), FOREIGN KEY (ticket_id) REFERENCES tickets(id), FOREIGN KEY (user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE ticket_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NULL, event_type VARCHAR(40) NOT NULL, details VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ticket(ticket_id,created_at), FOREIGN KEY (ticket_id) REFERENCES tickets(id), FOREIGN KEY (actor_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, message_id BIGINT UNSIGNED NULL, uploader_id BIGINT UNSIGNED NOT NULL, stored_name VARCHAR(80) NOT NULL, original_name VARCHAR(180) NOT NULL, mime_type VARCHAR(80) NOT NULL, file_size INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ticket(ticket_id), FOREIGN KEY (ticket_id) REFERENCES tickets(id), FOREIGN KEY (message_id) REFERENCES messages(id), FOREIGN KEY (uploader_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ];
            foreach ($sql as $statement) db()->exec($statement);
            $s = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')");
            $s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);
            flash('Instalación completada. Ingresa con tu cuenta de administrador.');
            redirect('index.php?page=login');
        } catch (Throwable $e) { error_log($e); $error = 'No se pudo completar la instalación. Revisa los permisos de MySQL y el registro de errores.'; }
    }
}
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar · Mesa de ayuda UV</title><link rel="stylesheet" href="assets/style.css"><body class="auth-body"><main class="auth-card"><div class="brand-mark">UV<span>●</span></div><h1>Instalación inicial</h1><p>Crea el primer administrador. Antes, configura la base de datos y la clave de instalación en <code>config.php</code>.</p><?php if ($error): ?><div class="alert error"><?=h($error)?></div><?php endif ?><form method="post" class="stack"><?=csrf_field()?><label>Clave de instalación<input name="install_key" type="password" required></label><label>Nombre del administrador<input name="name" required maxlength="120"></label><label>Correo electrónico<input name="email" type="email" required></label><label>Contraseña inicial<input name="password" type="password" required minlength="12"></label><button class="button primary">Instalar aplicación</button></form></main></body></html>
