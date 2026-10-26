<?php
require_once dirname(__DIR__).'/includes/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
if($argc!==5){fwrite(STDERR,"Dùng: php tools/create_user.php ten_dang_nhap mat_khau ho_ten VAI_TRO\nVai trò: SO THU_KY HOI_DONG THPT THCS\n");exit(1);}
[, $login,$password,$name,$role]=$argv;
if(!in_array($role,['SO','THU_KY','HOI_DONG','THPT','THCS'],true)||strlen($password)<8){fwrite(STDERR,"Vai trò không hợp lệ hoặc mật khẩu dưới 8 ký tự.\n");exit(1);}
$q=db()->prepare('INSERT INTO nguoi_dung(ten_dang_nhap,mat_khau_hash,ho_ten,vai_tro) VALUES(?,?,?,?)');$q->execute([$login,password_hash($password,PASSWORD_DEFAULT),$name,$role]);echo "Đã tạo tài khoản $login.\n";
