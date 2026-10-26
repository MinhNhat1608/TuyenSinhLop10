<?php
require_once __DIR__.'/includes/bootstrap.php';
if(current_user())go('index.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{verify_post();$q=db()->prepare('SELECT * FROM nguoi_dung WHERE ten_dang_nhap=? AND trang_thai=1');$q->execute([val('username')]);$u=$q->fetch();if(!$u||!password_verify(val('password'),$u['mat_khau_hash']))throw new RuntimeException('Sai tên đăng nhập hoặc mật khẩu.');session_regenerate_id(true);$_SESSION['auth']=['id'=>(int)$u['id'],'ho_ten'=>$u['ho_ten'],'vai_tro'=>$u['vai_tro']];go('index.php');}catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đăng nhập | Một Nhà</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="login-bg"><form method="post" class="login"><span class="pill">NHÓM MỘT NHÀ</span><h1 style="margin-top:18px">Đăng nhập hệ thống</h1><p>Hệ thống tuyển sinh lớp 10 · PHP và MySQL</p><?php if($error):?><div class="note"><?=esc($error)?></div><?php endif;?><input type="hidden" name="_csrf" value="<?=esc(csrf())?>"><div class="field"><label>Tên đăng nhập</label><input name="username" required autocomplete="username"></div><div class="field"><label>Mật khẩu</label><input type="password" name="password" required autocomplete="current-password"></div><button class="btn">Đăng nhập</button><p class="small">Tài khoản phải được tạo trong database bằng công cụ CLI đi kèm. Không có tài khoản mặc định.</p></form></div></body></html>
