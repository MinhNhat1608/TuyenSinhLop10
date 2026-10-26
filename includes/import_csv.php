<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
/** Each page owns its import action. CSV is UTF-8, exported from Excel. */
function csv_records(string $type): array {
 $f=$_FILES['import_file']??null;
 if(!$f || $f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Hãy chọn tệp CSV hợp lệ.');
 if($f['size']>2*1024*1024)throw new RuntimeException('File vượt quá giới hạn 2 MB.');
 if(strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION))!=='csv')throw new RuntimeException('Hãy lưu Excel thành CSV UTF-8 (*.csv) trước khi tải lên.');
 $fp=fopen($f['tmp_name'],'rb');if(!$fp)throw new RuntimeException('Không đọc được file.');
 $line=fgets($fp);if($line===false)throw new RuntimeException('File trống.');
 $line=preg_replace('/^\xEF\xBB\xBF/','',$line);
 $delimiter=substr_count($line,';')>substr_count($line,',')?';':',';
 rewind($fp);$header=fgetcsv($fp,0,$delimiter);if(!$header)throw new RuntimeException('Thiếu dòng tiêu đề.');$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);$header=array_map('trim',$header);
 $expected=[
  'thai'=>['ma_ky_thi','ma_truong','ma_he','so_luong'],
  'tan'=>['ma_ky_thi','ma_thi_sinh','ma_phong','so_bao_danh'],
  'nghia'=>['ma_tui','ma_cbct1','ma_cbct2'],
  'loc'=>['ma_phach','diem'],
  'nhat'=>['ma_thi_sinh','ma_ky_thi','ma_truong','nguyen_vong_trung_tuyen','tong_diem','ket_qua']
 ];
 if($header!==$expected[$type])throw new RuntimeException('Tên cột hoặc thứ tự cột chưa đúng. Hãy tải file mẫu trên trang. Cần: '.implode(', ',$expected[$type]));
 $records=[];$n=1;
 while(($cols=fgetcsv($fp,0,$delimiter))!==false){$n++;if(count($cols)===1&&trim((string)$cols[0])==='')continue;
  if(count($cols)!==count($header))throw new RuntimeException('Dòng '.$n.' không đủ số cột.');
  $row=array_combine($header,array_map('trim',$cols));foreach($row as $v)if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v))throw new RuntimeException('Dòng '.$n.' có ký tự không hợp lệ.');
  $row['_line']=$n;$records[]=$row;if(count($records)>500)throw new RuntimeException('Tối đa 500 dòng mỗi file.');
 }
 fclose($fp);if(!$records)throw new RuntimeException('File chưa có dòng dữ liệu.');return $records;
}
function importer(string $type, string $back):void {
 try {
  verify_post();$u=must_login();$role=$u['vai_tro'];
  if(!in_array($type,['thai','tan','nghia','loc','nhat'],true))throw new RuntimeException('Chức năng không hợp lệ.');
  $allowed=['thai'=>['SO'],'tan'=>['SO'],'nghia'=>['SO','HOI_DONG'],'loc'=>['THU_KY'],'nhat'=>['SO']];
  if(!in_array($role,$allowed[$type],true))throw new RuntimeException('Tài khoản không có quyền nhập file cho chức năng này.');
  $rows=csv_records($type);$db=db();$db->beginTransaction();$count=0;
  try{foreach($rows as $r){try{import_one($db,$type,$r,(int)$u['id']);$count++;}catch(Throwable $err){throw new RuntimeException('Dòng '.$r['_line'].': '.$err->getMessage(),0,$err);}}
   audit($db,(int)$u['id'],'IMPORT_'.strtoupper($type),'Nhập '.$count.' dòng CSV vào '.$back);$db->commit();
  }catch(Throwable $e){$db->rollBack();throw $e;}
  flash('Đã nhập thành công '.$count.' dòng từ file CSV.');
 }catch(Throwable $e){flash('Không nhập được file (chưa lưu dòng nào): '.$e->getMessage());}
 go($back);
}
function row_required(array $r,string $key):string {$v=(string)($r[$key]??'');if($v==='')throw new RuntimeException('Thiếu '.$key);return $v;}
function int_strict(string $v,int $min=0,int $max=999999):int {if(!preg_match('/^\d+$/',$v)|| (int)$v<$min||(int)$v>$max)throw new RuntimeException('Số nguyên không hợp lệ: '.$v);return (int)$v;}
function import_one(PDO $db,string $type,array $r,int $uid):void {
 if($type==='thai'){
  $ky=row_required($r,'ma_ky_thi');$school=row_required($r,'ma_truong');$he=row_required($r,'ma_he');$num=int_strict(row_required($r,'so_luong'));
  $q=$db->prepare('SELECT kha_nang_tiep_nhan FROM truong_thpt WHERE ma_truong=? FOR UPDATE');$q->execute([$school]);$cap=$q->fetchColumn();if($cap===false)throw new RuntimeException('Không có trường THPT '.$school);
  $q=$db->prepare('SELECT COALESCE(SUM(so_luong),0) FROM chi_tieu WHERE ma_ky_thi=? AND ma_truong=? AND ma_he<>?');$q->execute([$ky,$school,$he]);if($num+(int)$q->fetchColumn()>(int)$cap)throw new RuntimeException('Tổng chỉ tiêu vượt khả năng tiếp nhận trường '.$school);
  $q=$db->prepare('SELECT trang_thai FROM chi_tieu WHERE ma_ky_thi=? AND ma_truong=? AND ma_he=? FOR UPDATE');$q->execute([$ky,$school,$he]);if($q->fetchColumn()==='DA_DUYET')throw new RuntimeException('Chỉ tiêu đã duyệt, không được sửa.');
  $q=$db->prepare('INSERT INTO chi_tieu(ma_ky_thi,ma_truong,ma_he,so_luong) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE so_luong=VALUES(so_luong)');$q->execute([$ky,$school,$he,$num]);return;
 }
 if($type==='tan'){
  $ky=row_required($r,'ma_ky_thi');$student=row_required($r,'ma_thi_sinh');$room=row_required($r,'ma_phong');$sbd=row_required($r,'so_bao_danh');
  if(!preg_match('/^[A-Za-z0-9_-]{2,20}$/',$sbd))throw new RuntimeException('SBD chỉ được gồm chữ, số, gạch ngang.');
  $q=$db->prepare('SELECT ma_ky_thi,suc_chua FROM phong_thi WHERE ma_phong=? FOR UPDATE');$q->execute([$room]);$p=$q->fetch();if(!$p||$p['ma_ky_thi']!==$ky)throw new RuntimeException('Phòng không thuộc kỳ thi.');
  $q=$db->prepare('SELECT ho_so_hop_le FROM thi_sinh WHERE ma_thi_sinh=? AND ma_ky_thi=?');$q->execute([$student,$ky]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Thí sinh không tồn tại hoặc hồ sơ chưa hợp lệ.');
  $q=$db->prepare('SELECT COUNT(*) FROM phan_bo_thi_sinh WHERE ma_phong=? AND ma_ky_thi=?');$q->execute([$room,$ky]);if((int)$q->fetchColumn()>=min(24,(int)$p['suc_chua']))throw new RuntimeException('Phòng đã đủ sức chứa (tối đa 24).');
  $q=$db->prepare('INSERT INTO phan_bo_thi_sinh(ma_ky_thi,ma_thi_sinh,ma_phong,so_bao_danh) VALUES(?,?,?,?)');$q->execute([$ky,$student,$room,$sbd]);return;
 }
 if($type==='nghia'){
  $bag=row_required($r,'ma_tui');$a=row_required($r,'ma_cbct1');$b=row_required($r,'ma_cbct2');if($a===$b)throw new RuntimeException('Hai cán bộ không được trùng nhau.');
  $q=$db->prepare("SELECT COUNT(*) FROM nhan_su WHERE ma_ns IN (?,?) AND loai_nhan_su='CHAM_THI'");$q->execute([$a,$b]);if((int)$q->fetchColumn()!==2)throw new RuntimeException('Cán bộ chưa thuộc nhân sự chấm thi.');
  $q=$db->prepare('INSERT INTO phan_cong_cham_thi(ma_tui,ma_cbct1,ma_cbct2) VALUES(?,?,?)');$q->execute([$bag,$a,$b]);return;
 }
 if($type==='loc'){
  $phach=row_required($r,'ma_phach');$str=str_replace(',','.',row_required($r,'diem'));
  if(!preg_match('/^(?:10(?:\.0{1,2})?|[0-9](?:\.\d{1,2})?)$/',$str))throw new RuntimeException('Điểm phải từ 0–10 và tối đa 2 số thập phân.');
  $q=$db->prepare('SELECT b.ma_bai,b.da_thong_nhat_diem FROM bai_thi b JOIN tui_bai_thi t ON b.ma_tui=t.ma_tui JOIN phan_cong_cham_thi pc ON pc.ma_tui=t.ma_tui WHERE b.ma_phach=? FOR UPDATE');$q->execute([$phach]);$paper=$q->fetch();if(!$paper)throw new RuntimeException('Mã phách chưa có phân công chấm.');
  if((int)$paper['da_thong_nhat_diem']!==1)throw new RuntimeException('Chưa có xác nhận thống nhất điểm của hai cán bộ.');
  $q=$db->prepare('INSERT INTO diem_thi(ma_bai,diem,nguoi_nhap) VALUES(?,?,?)');$q->execute([$paper['ma_bai'],$str,$uid]);return;
 }
 if($type==='nhat'){
  // Import preliminary results only: never change them to DA_DUYET.
  $student=row_required($r,'ma_thi_sinh');$ky=row_required($r,'ma_ky_thi');$school=trim($r['ma_truong']);$nv=trim($r['nguyen_vong_trung_tuyen']);$score=str_replace(',','.',row_required($r,'tong_diem'));$result=row_required($r,'ket_qua');
  if(!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/',$score))throw new RuntimeException('Tổng điểm không hợp lệ.');
  if(!in_array($result,['TRUNG_TUYEN','KHONG_TRUNG_TUYEN'],true))throw new RuntimeException('Kết quả không hợp lệ.');
  $q=$db->prepare('SELECT 1 FROM thi_sinh WHERE ma_thi_sinh=? AND ma_ky_thi=?');$q->execute([$student,$ky]);if(!$q->fetchColumn())throw new RuntimeException('Thí sinh không thuộc kỳ thi.');
  if($result==='TRUNG_TUYEN'){
   if($school===''||!in_array($nv,['1','2','3'],true))throw new RuntimeException('Trúng tuyển phải có trường và nguyện vọng 1–3.');
   $q=$db->prepare('SELECT COUNT(*) FROM nguyen_vong WHERE ma_thi_sinh=? AND ma_truong=? AND thu_tu=? AND da_xac_nhan=1');$q->execute([$student,$school,(int)$nv]);if(!(int)$q->fetchColumn())throw new RuntimeException('Trường/nguyện vọng chưa được xác nhận.');
  }elseif($school!==''||$nv!=='')throw new RuntimeException('Không trúng tuyển thì bỏ trống trường và nguyện vọng.');
  $q=$db->prepare('INSERT INTO ket_qua_xet_tuyen(ma_thi_sinh,ma_ky_thi,ma_truong,nguyen_vong_trung_tuyen,tong_diem,ket_qua) VALUES(?,?,?,?,?,?)');$q->execute([$student,$ky,$school!==''?$school:null,$nv!==''?(int)$nv:null,$score,$result]);return;
 }
 throw new RuntimeException('Loại nhập không hỗ trợ.');
}
function import_panel(string $type,string $title):void {
 $access=['thai'=>['SO'],'tan'=>['SO'],'nghia'=>['SO','HOI_DONG'],'loc'=>['THU_KY'],'nhat'=>['SO']];
 echo '<div class="panel"><h2>Nhập file CSV – '.esc($title).'</h2><p>Nhập dữ liệu của chức năng này bằng file CSV UTF-8 xuất từ Excel. <b>Không tự động phê duyệt/chốt dữ liệu.</b> Nếu bất kỳ dòng nào lỗi, toàn bộ file được hoàn tác.</p>';
 echo '<p><a class="btn secondary" href="mau_csv/'.esc($type).'.csv" download>Tải CSV mẫu cho chức năng này</a></p>';
 if(!in_array(current_user()['vai_tro'],$access[$type],true))echo '<div class="note">Tài khoản hiện tại không có quyền nhập file chức năng này.</div>';
 else echo '<form method="post" enctype="multipart/form-data" action="'.esc($type).'.php"><input type="hidden" name="_csrf" value="'.esc(csrf()).'"><input type="hidden" name="action" value="import_csv"><div class="controls"><div class="field"><label>Chọn file .csv (UTF-8, tối đa 2 MB)</label><input type="file" name="import_file" accept=".csv,text/csv" required></div></div><div class="actions"><button class="btn" type="submit">Kiểm tra và nhập MySQL</button></div></form>';
 echo '</div>';
}
