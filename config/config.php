<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
const DB_HOST = '127.0.0.1';
const DB_NAME = 'beacon_school';
const DB_USER = 'root';
const DB_PASS = '';
const SCHOOL_NAME = 'The New Beacon School System';
const CAMPUS = 'Larkana Campus';
const PHONE = '03337132010';
const EMAIL = 'mohammadumarsoomro200@gmail.com';
const ADDRESS = 'Behind Arts Council, Larkana';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid security token. Refresh and try again.'); } }
function redirect(string $url): never { header('Location: '.$url); exit; }
function flash(string $type,string $msg): void { $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function get_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(array $roles=[]): void { if (!user()) redirect('login.php'); if ($roles && !in_array(user()['role'],$roles,true)) { http_response_code(403); exit('Access denied.'); } }
function audit(string $action,string $module,string $details=''): void { try { $s=db()->prepare('INSERT INTO audit_logs(user_id,action,module,details,ip_address) VALUES(?,?,?,?,?)'); $s->execute([user()['id']??null,$action,$module,$details,$_SERVER['REMOTE_ADDR']??'']); } catch(Throwable $e){} }
function setting(string $key,string $default=''): string { try{$s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;}catch(Throwable $e){return $default;} }