<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$g = cfg('google', []);
if (empty($g['client_id']) || empty($g['client_secret'])) { http_response_code(404); exit('Google todavía no está configurado.'); }
$callback = url('google.php?action=callback');
$action = (string)($_GET['action'] ?? 'start');
if ($action === 'start') {
    $state = bin2hex(random_bytes(24)); $nonce = bin2hex(random_bytes(24));
    $_SESSION['google_state'] = $state; $_SESSION['google_nonce'] = $nonce;
    $params = ['client_id'=>$g['client_id'],'redirect_uri'=>$callback,'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'nonce'=>$nonce,'prompt'=>'select_account'];
    if (!empty($g['allowed_domain'])) $params['hd'] = $g['allowed_domain'];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params)); exit;
}
if ($action !== 'callback' || !hash_equals((string)($_SESSION['google_state'] ?? ''), (string)($_GET['state'] ?? '')) || empty($_GET['code'])) {
    http_response_code(400); exit('No se pudo validar el inicio de sesión.');
}
unset($_SESSION['google_state']);
function http_json(string $url, ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);
    if ($body !== null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($body)]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (!is_string($raw) || $code !== 200) throw new RuntimeException('Error al consultar Google.');
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}
try {
    $tokens = http_json('https://oauth2.googleapis.com/token', ['code'=>(string)$_GET['code'],'client_id'=>$g['client_id'],'client_secret'=>$g['client_secret'],'redirect_uri'=>$callback,'grant_type'=>'authorization_code']);
    $parts = explode('.', (string)($tokens['id_token'] ?? ''));
    if (count($parts) !== 3) throw new RuntimeException('Token inválido.');
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    if (($header['alg'] ?? '') !== 'RS256') throw new RuntimeException('Algoritmo inválido.');
    $certs = http_json('https://www.googleapis.com/oauth2/v1/certs');
    $cert = $certs[$header['kid'] ?? ''] ?? null;
    $sig = base64_decode(strtr($parts[2], '-_', '+/'), true);
    if (!$cert || !$sig || openssl_verify($parts[0] . '.' . $parts[1], $sig, $cert, OPENSSL_ALGO_SHA256) !== 1) throw new RuntimeException('Firma inválida.');
    $now = time();
    if (!in_array($claims['iss'] ?? '', ['accounts.google.com','https://accounts.google.com'], true) || ($claims['aud'] ?? '') !== $g['client_id'] || ($claims['exp'] ?? 0) <= $now || ($claims['iat'] ?? $now + 1) > $now || !hash_equals((string)($_SESSION['google_nonce'] ?? ''), (string)($claims['nonce'] ?? '')) || empty($claims['email_verified'])) throw new RuntimeException('Identidad inválida.');
    unset($_SESSION['google_nonce']);
    if (!empty($g['allowed_domain']) && ($claims['hd'] ?? '') !== $g['allowed_domain']) throw new RuntimeException('Cuenta fuera del dominio permitido.');
    $email = strtolower((string)$claims['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo inválido.');
    $s = db()->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$email]); $id = $s->fetchColumn();
    if (!$id) { $s = db()->prepare("INSERT INTO users (name,email,role) VALUES (?,?,'student')"); $s->execute([mb_substr((string)($claims['name'] ?? $email),0,120),$email]); $id=db()->lastInsertId(); }
    login_user((int)$id); redirect('index.php');
} catch (Throwable $e) { error_log($e); flash('No fue posible ingresar con Google. Contacta al administrador o usa el acceso local.', 'error'); redirect('index.php?page=login'); }
