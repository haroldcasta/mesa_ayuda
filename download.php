<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = require_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit; }
$s = db()->prepare('SELECT a.*, t.user_id FROM attachments a JOIN tickets t ON t.id=a.ticket_id WHERE a.id=?');
$s->execute([$id]); $a = $s->fetch();
if (!$a || (!is_staff($u) && (int)$a['user_id'] !== (int)$u['id'])) { http_response_code(404); exit; }
$path = __DIR__ . '/storage/' . basename($a['stored_name']);
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: ' . $a['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="evidencia"; filename*=UTF-8\'\'' . rawurlencode($a['original_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
