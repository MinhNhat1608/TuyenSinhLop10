<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function db(): PDO {
 static $pdo;
 if (!$pdo) {
  $file = dirname(__DIR__).'/config.php';
  if (!is_file($file)) throw new RuntimeException('Thiếu config.php; sao chép config.example.php thành config.php.');
  $c = require $file;
  $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['dbname'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 return $pdo;
}
function esc($value): string {return htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8');}
function go(string $where): never {header('Location: '.$where);exit;}
function current_user(): ?array {return $_SESSION['auth']??null;}
function must_login(): array {$u=current_user();if(!$u)go('login.php');return $u;}
function require_role(array $allowed): array {$u=must_login();if(!in_array($u['vai_tro'],$allowed,true)){http_response_code(403);exit('Bạn không có quyền thực hiện chức năng này.');}return $u;}
function csrf(): string {if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function verify_post(): void {if($_SERVER['REQUEST_METHOD']!=='POST'||!hash_equals(csrf(),(string)($_POST['_csrf']??'')))throw new RuntimeException('Yêu cầu không hợp lệ hoặc hết phiên.');}
function val(string $key): string {return trim((string)($_POST[$key]??''));}
function required_val(string $key): string {$v=val($key);if($v==='')throw new RuntimeException('Thiếu thông tin bắt buộc: '.$key);return $v;}
function audit(PDO $db,int $id,string $action,string $message): void {$q=$db->prepare('INSERT INTO nhat_ky_he_thong(nguoi_dung,thao_tac,noi_dung) VALUES(?,?,?)');$q->execute([$id,$action,$message]);}
function flash(?string $msg=null): ?string {if($msg!==null){$_SESSION['flash']=$msg;return null;}$v=$_SESSION['flash']??null;unset($_SESSION['flash']);return $v;}
function options(string $sql,string $value,string $label): string {$html='';foreach(db()->query($sql) as $r){$html.='<option value="'.esc($r[$value]).'">'.esc($r[$label]).' ('.esc($r[$value]).')</option>';}return $html;}
function task(string $name, callable $fn): void {try {verify_post();$u=must_login();$db=db();$fn($db,$u);flash('Thao tác thành công.');}catch(Throwable $e){flash('Không thực hiện được: '.$e->getMessage());}go($name);}
