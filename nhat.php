<?php
/** Phê duyệt kết quả tuyển sinh – tích hợp TS10_PHP_NhomMotNha (MySQL). */
declare(strict_types=1);
require_once __DIR__.'/includes/layout.php';
require_role(['SO']);
function n_query(PDO $db,string $sql,array $args=[]):array {
    $s=$db->prepare($sql);
    $s->execute($args);
    return $s->fetchAll();
}
function n_fail(string $m):never {
    throw new RuntimeException($m);
}
function n_ky(PDO $db,string $ky):array {
    $r=n_query($db,'SELECT ma_ky_thi,ten_ky_thi FROM ky_thi WHERE ma_ky_thi=?',[$ky]);
    if(!$r)n_fail('Không tìm thấy kỳ thi.');
    return $r[0];
}
function n_rule(PDO $db,string $ky):array {
    $r=n_query($db,"SELECT cau_hinh_json FROM quy_tac_xet_tuyen WHERE ma_ky_thi=? AND trang_thai='DANG_AP_DUNG'",[$ky]);
    if(count($r)!==1)n_fail('Kỳ thi phải có đúng một quy tắc xét tuyển đang áp dụng. Xem SQL bổ sung.');
    $cfg=json_decode($r[0]['cau_hinh_json'],true);
    if(!is_array($cfg)||!isset($cfg['heSoMon'])||!is_array($cfg['heSoMon'])||!$cfg['heSoMon'])n_fail('Thiếu cấu hình heSoMon trong quy tắc JSON.');
    foreach($cfg['heSoMon'] as $mon=>$he) {
        if(!is_string($mon)||!preg_match('/^[A-Za-z0-9_-]{1,10}$/',$mon)||!is_numeric($he)||(float)$he<=0)n_fail('Hệ số môn không hợp lệ: '.$mon);
    }
    if(!isset($cfg['diemUuTien'])||!is_numeric($cfg['diemUuTien'])||(float)$cfg['diemUuTien']<0)n_fail('Thiếu diemUuTien (đặt 0 nếu quy tắc không cộng điểm ưu tiên).');
    return $cfg;
}
/** Xây dựng bản dự kiến trong bộ nhớ, không lưu khi dữ liệu thiếu. */
function n_build(PDO $db,string $ky):array {
    n_ky($db,$ky);
    $cfg=n_rule($db,$ky);
    $subjects=n_query($db,'SELECT ma_mon FROM ky_thi_mon_thi WHERE ma_ky_thi=?',[$ky]);
    if(!$subjects)n_fail('Chưa cấu hình môn thi bắt buộc cho kỳ thi.');
    $required=[];
    foreach($subjects as $s) {
        $required[$s['ma_mon']]=true;
        if(!array_key_exists($s['ma_mon'],$cfg['heSoMon']))n_fail('Thiếu hệ số môn '.$s['ma_mon']);
    }
    foreach($cfg['heSoMon'] as $mon=>$w)if(!isset($required[$mon]))n_fail('Quy tắc có môn không thuộc kỳ thi: '.$mon);
    $qrows=n_query($db,'SELECT ma_truong,ma_he,so_luong,trang_thai FROM chi_tieu WHERE ma_ky_thi=?',[$ky]);
    if(!$qrows)n_fail('Kỳ thi chưa có chỉ tiêu tuyển sinh.');
    $quota=[];
    foreach($qrows as $r) {
        if($r['trang_thai']!=='DA_DUYET')n_fail('Còn chỉ tiêu chưa được Sở phê duyệt.');
        $quota[$r['ma_truong'].'|'.$r['ma_he']]=(int)$r['so_luong'];
    }
    $students=n_query($db,"SELECT ma_thi_sinh,ho_ten,ho_so_hop_le FROM thi_sinh WHERE ma_ky_thi=? AND (? <> 'KT2027' OR ma_thi_sinh BETWEEN 'TS0001' AND 'TS0180') ORDER BY ma_thi_sinh",[$ky,$ky]);
    if(!$students)n_fail('Chưa có thí sinh trong kỳ thi.');
    $prefs=n_query($db,'SELECT n.ma_thi_sinh,n.ma_truong,n.ma_he,n.thu_tu,n.da_xac_nhan FROM nguyen_vong n JOIN thi_sinh t ON t.ma_thi_sinh=n.ma_thi_sinh WHERE t.ma_ky_thi=? ORDER BY n.thu_tu',[$ky]);
    $byPref=[];
    foreach($prefs as $p)$byPref[$p['ma_thi_sinh']][]=$p;
    $grades=n_query($db,"SELECT b.ma_thi_sinh,t.ma_mon,d.diem,d.trang_thai AS diem_trang_thai,b.da_thong_nhat_diem
   FROM bai_thi b JOIN tui_bai_thi t ON t.ma_tui=b.ma_tui
   LEFT JOIN diem_thi d ON d.ma_bai=b.ma_bai WHERE t.ma_ky_thi=?",[$ky]);
    $byGrade=[];
    foreach($grades as $g)$byGrade[$g['ma_thi_sinh']][]=$g;
    $alloc=n_query($db,'SELECT ma_thi_sinh,so_bao_danh,trang_thai FROM phan_bo_thi_sinh WHERE ma_ky_thi=?',[$ky]);
    $byAlloc=[];
    foreach($alloc as $p)$byAlloc[$p['ma_thi_sinh']]=$p;
    $candidates=[];
    foreach($students as $s) {
        $id=$s['ma_thi_sinh'];
        if((int)$s['ho_so_hop_le']!==1)n_fail('Hồ sơ chưa hợp lệ: '.$id);
        if(!isset($byAlloc[$id])||$byAlloc[$id]['trang_thai']!=='DA_XAC_NHAN')n_fail('Chưa xác nhận phân bổ/SBD cho '.$id);
        $nv=$byPref[$id]??[];
        if(!$nv||count($nv)>3)n_fail('Thiếu hoặc quá 3 nguyện vọng: '.$id);
        $ordered=[];
        $uniqueSchool=[];
        foreach($nv as $p) {
            $rank=(int)$p['thu_tu'];
            $key=$p['ma_truong'].'|'.$p['ma_he'];
            if((int)$p['da_xac_nhan']!==1||$rank<1||$rank>3||isset($ordered[$rank]))n_fail('Nguyện vọng chưa xác nhận hoặc trùng thứ tự: '.$id);
            if(!array_key_exists($key,$quota))n_fail('Nguyện vọng chưa có chỉ tiêu được duyệt: '.$id.' / '.$key);
            if(isset($uniqueSchool[$key]))n_fail('Nguyện vọng trùng trường/hệ: '.$id);
            $uniqueSchool[$key]=true;
            $ordered[$rank]=$p;
        }
        $subjectScores=[];
        foreach($byGrade[$id]??[] as $g) {
            $mon=$g['ma_mon'];
            if(!isset($required[$mon])||isset($subjectScores[$mon]))n_fail('Bài thi trùng môn hoặc môn không hợp lệ: '.$id.' / '.$mon);
            if($g['diem']===null||!in_array($g['diem_trang_thai'],['DA_NHAP','DA_CHOT'],true))n_fail('Thí sinh chưa có điểm thi hợp lệ: '.$id.' / '.$mon);
            $x=(float)$g['diem'];
            if($x<0||$x>10)n_fail('Điểm ngoài khoảng 0–10: '.$id);
            $subjectScores[$mon]=$x;
        }
        foreach($required as $mon=>$_)if(!isset($subjectScores[$mon]))n_fail('Thiếu môn '.$mon.' của thí sinh '.$id);
        $total=(float)$cfg['diemUuTien'];
        foreach($cfg['heSoMon'] as $mon=>$he)$total+=$subjectScores[$mon]*(float)$he;
        if($total>999.99)n_fail('Tổng điểm vượt kiểu DECIMAL(5,2) ở '.$id);
        $candidates[$id]=['ma_thi_sinh'=>$id,'ho_ten'=>$s['ho_ten'],'sbd'=>$byAlloc[$id]['so_bao_danh'],'tong_diem'=>round($total,2),'prefs'=>$ordered,'ma_truong'=>null,'nv'=>null,'ket_qua'=>'KHONG_TRUNG_TUYEN'];
    }
    $used=array_fill_keys(array_keys($quota),0);
    for($round=1;
    $round<=3;
    $round++) {
        $groups=[];
        foreach($candidates as $id=>$c) {
            if($c['ma_truong']!==null||!isset($c['prefs'][$round]))continue;
            $p=$c['prefs'][$round];
            $groups[$p['ma_truong'].'|'.$p['ma_he']][]=$id;
        }
        foreach($groups as $key=>$ids) {
            usort($ids,fn($a,$b)=>$candidates[$b]['tong_diem']<=>$candidates[$a]['tong_diem']);
            $remaining=$quota[$key]-$used[$key];
            if($remaining<=0)continue;
            if($remaining<count($ids) && $candidates[$ids[$remaining-1]]['tong_diem']===$candidates[$ids[$remaining]]['tong_diem'])n_fail('Đồng điểm tại ranh giới chỉ tiêu (NV'.$round.', '.$key.'). Chưa có quy tắc đồng điểm, hệ thống không tự quyết định.');
            foreach(array_slice($ids,0,$remaining) as $id) {
                $candidates[$id]['ma_truong']=$candidates[$id]['prefs'][$round]['ma_truong'];
                $candidates[$id]['nv']=$round;
                $candidates[$id]['ket_qua']='TRUNG_TUYEN';
                $used[$key]++;
            }
        }
    }
    return array_values($candidates);
}
function n_compare(array $a,array $b):bool {
    if(count($a)!==count($b))return false;
    $map=[];
    foreach($b as $r)$map[$r['ma_thi_sinh']]=$r;
    foreach($a as $r) {
        $o=$map[$r['ma_thi_sinh']]??null;
        if(!$o||$r['ket_qua']!==$o['ket_qua']||($r['ma_truong']??null)!==($o['ma_truong']??null)||($r['nv']??null)!==($o['nguyen_vong_trung_tuyen']===null?null:(int)$o['nguyen_vong_trung_tuyen'])||abs($r['tong_diem']-(float)$o['tong_diem'])>0.001)return false;
    }
    return true;
}
function n_save_draft(PDO $db,string $ky,array $rows):void {
    $q=$db->prepare("INSERT INTO ket_qua_xet_tuyen(ma_thi_sinh,ma_ky_thi,ma_truong,nguyen_vong_trung_tuyen,tong_diem,ket_qua,trang_thai) VALUES(?,?,?,?,?,?,'DU_KIEN')");
    foreach($rows as $r)$q->execute([$r['ma_thi_sinh'],$ky,$r['ma_truong'],$r['nv'],$r['tong_diem'],$r['ket_qua']]);
}
if($_SERVER['REQUEST_METHOD']==='POST')task('nhat.php',function(PDO $db,array $u) {
    $act=required_val('action');
    $ky=required_val('ma_ky_thi');
    $db->beginTransaction();
    try {
        n_ky($db,$ky);
        // Serialize requests for this exam to prevent approving stale drafts simultaneously.
        $lock=$db->prepare('SELECT ma_ky_thi FROM ky_thi WHERE ma_ky_thi=? FOR UPDATE');
        $lock->execute([$ky]);
        $current=n_query($db,'SELECT * FROM ket_qua_xet_tuyen WHERE ma_ky_thi=? ORDER BY ma_thi_sinh FOR UPDATE',[$ky]);
        if($act==='create') {
            if($current)n_fail('Kỳ thi đã có kết quả. Không tự ghi đè danh sách dự kiến/chính thức.');
            $plan=n_build($db,$ky);
            n_save_draft($db,$ky,$plan);
            audit($db,(int)$u['id'],'TAO_KET_QUA_DU_KIEN','Kỳ thi '.$ky.'; '.count($plan).' thí sinh');
        }
        elseif($act==='approve') {
            if(!$current)n_fail('Chưa có kết quả dự kiến. Phải tạo trước khi duyệt.');
            foreach($current as $r)if($r['trang_thai']!=='DU_KIEN')n_fail('Có kết quả đã duyệt, không thể duyệt lại.');
            $plan=n_build($db,$ky);
            if(!n_compare($plan,$current))n_fail('Dữ liệu đầu vào đã thay đổi kể từ khi tạo dự kiến; kết quả không khớp. Cần kiểm tra lại trước khi duyệt.');
            $q=$db->prepare("UPDATE ket_qua_xet_tuyen SET trang_thai='DA_DUYET',nguoi_duyet=?,ngay_duyet=NOW() WHERE ma_ky_thi=? AND trang_thai='DU_KIEN'");
            $q->execute([(int)$u['id'],$ky]);
            if($q->rowCount()!==count($current))n_fail('Không phê duyệt đủ toàn bộ kết quả.');
            audit($db,(int)$u['id'],'PHE_DUYET_KET_QUA','Kỳ thi '.$ky.'; '.count($current).' kết quả');
        }
        else n_fail('Thao tác không hỗ trợ.');
        $db->commit();
    }
    catch(Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
);
$ky=(string)($_GET['ma_ky_thi']??'');
$exams=db()->query('SELECT ma_ky_thi,ten_ky_thi,nam_tuyen_sinh FROM ky_thi ORDER BY nam_tuyen_sinh DESC')->fetchAll();
if($ky===''&&$exams)$ky=$exams[0]['ma_ky_thi'];
$rows=[];
$info=[];
$checkError='';
if($ky!=='') {
    try {
        $rows=n_query(db(),'SELECT k.id,k.ma_thi_sinh,t.ho_ten,p.so_bao_danh,k.tong_diem,k.nguyen_vong_trung_tuyen,k.ma_truong,s.ten_truong,k.ket_qua,k.trang_thai FROM ket_qua_xet_tuyen k JOIN thi_sinh t ON t.ma_thi_sinh=k.ma_thi_sinh LEFT JOIN phan_bo_thi_sinh p ON p.ma_thi_sinh=k.ma_thi_sinh AND p.ma_ky_thi=k.ma_ky_thi LEFT JOIN truong_thpt s ON s.ma_truong=k.ma_truong WHERE k.ma_ky_thi=? ORDER BY p.so_bao_danh',[$ky]);
        $info=['total'=>count($rows),'passed'=>count(array_filter($rows,fn($r)=>$r['ket_qua']==='TRUNG_TUYEN'))];
        if(!$rows)n_build(db(),$ky);
        elseif(($rows[0]['trang_thai']??'')==='DU_KIEN')n_build(db(),$ky);
    }
    catch(Throwable $e) {
        $checkError=$e->getMessage();
    }
}
page_start('Phê duyệt kết quả tuyển sinh','nhat');
?>
<style>
/* Giao diện riêng cho trang Nhật; không ảnh hưởng các phân hệ khác */
.n-dashboard {
    --navy:#142b53;
    --royal:#315fd1;
    --pale:#eff4ff;
    --line:#e2e9f4;
    --muted:#70809b;
    color:#20304d;
}
.n-dashboard .n-hero {
    background:linear-gradient(115deg,#122b5d 0%,#2d55b0 72%,#4979dd 100%);
    border-radius:20px;
    padding:29px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:24px;
    box-shadow:0 15px 32px #17376c24;
    color:white;
    margin-bottom:22px;
    overflow:hidden;
    position:relative;
}
.n-dashboard .n-hero:after {
    content:'';
    position:absolute;
    right:-90px;
    top:-115px;
    width:380px;
    height:380px;
    border:1px solid #ffffff30;
    border-radius:50%;
    box-shadow:0 0 0 60px #ffffff0a,0 0 0 120px #ffffff07;
    pointer-events:none;
}
.n-dashboard .n-eyebrow {
    font-size:11px;
    letter-spacing:1.5px;
    text-transform:uppercase;
    font-weight:800;
    color:#b9cffd;
}
.n-dashboard .n-hero h2 {
    margin:10px 0 11px;
    font-size:25px;
    line-height:1.4;
    color:white;
    letter-spacing:-.5px;
}
.n-dashboard .n-hero p {
    margin:0;
    max-width:620px;
    color:#dce7ff;
    line-height:1.8;
    font-size:12px;
}
.n-dashboard .n-hero-flag {
    position:relative;
    z-index:1;
    padding:11px 15px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
    background:#ffffff27;
    border:1px solid #ffffff60;
    backdrop-filter:blur(4px);
}
.n-dashboard .n-top-grid {
    display:grid;
    grid-template-columns:1.3fr .85fr;
    gap:18px;
    margin-bottom:18px;
}
.n-dashboard .n-card {
    background:white;
    border:1px solid var(--line);
    border-radius:17px;
    padding:22px;
    box-shadow:0 4px 18px #1e3a6609;
}
.n-dashboard .n-section-title {
    font-size:15px;
    font-weight:800;
    color:#1f3154;
    margin:0 0 5px;
    display:flex;
    align-items:center;
    gap:10px;
}
.n-dashboard .n-step {
    background:#e9efff;
    color:#315ac8;
    border-radius:9px;
    padding:6px 9px;
    line-height:1;
    font-size:12px;
}
.n-dashboard .n-helper {
    font-size:12px;
    color:var(--muted);
    line-height:1.7;
    margin:5px 0 16px;
}
.n-dashboard .n-picker {
    display:flex;
    gap:9px;
    align-items:stretch;
}
.n-dashboard .n-picker select {
    width:100%;
    min-width:0;
    flex:1;
    border-radius:10px;
    padding:12px 13px;
    border:1px solid #d8e2f0;
    background:white;
    color:#263b61;
    outline:none;
}
.n-dashboard .n-btn {
    border:0;
    border-radius:10px;
    background:#315fd1;
    color:#fff;
    padding:12px 17px;
    font-weight:800;
    font-size:12px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    transition:transform .15s,box-shadow .15s;
}
.n-dashboard .n-btn:hover:not(:disabled) {
    transform:translateY(-1px);
    box-shadow:0 8px 18px #2c5dcc25;
}
.n-dashboard .n-btn:disabled {
    opacity:.48;
    cursor:not-allowed;
}
.n-dashboard .n-btn-ghost {
    background:#eef3ff;
    color:#305cc2;
}
.n-dashboard .n-btn-success {
    background:#117a6a;
}
.n-dashboard .n-info-status {
    display:flex;
    gap:12px;
    align-items:flex-start;
}
.n-dashboard .n-status-icon {
    width:40px;
    height:40px;
    background:#eaf8f1;
    border-radius:12px;
    color:#14916f;
    display:grid;
    place-items:center;
    flex:none;
    font-size:18px;
}
.n-dashboard .n-status-icon.warn {
    color:#be7b17;
    background:#fff4df;
}
.n-dashboard .n-status-icon.complete {
    color:#2e67b9;
    background:#e8f0ff;
}
.n-dashboard .n-status-text {
    font-weight:800;
    color:#264164;
    font-size:13px;
    margin:1px 0 5px;
}
.n-dashboard .n-status-small {
    font-size:12px;
    color:#72809a;
    line-height:1.7;
}
.n-dashboard .n-kpis {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}
.n-dashboard .n-kpi {
    background:white;
    border:1px solid var(--line);
    border-radius:15px;
    padding:19px 20px;
    position:relative;
    overflow:hidden;
}
.n-dashboard .n-kpi:before {
    content:'';
    height:4px;
    width:100%;
    position:absolute;
    top:0;
    left:0;
    background:#6487eb;
}
.n-dashboard .n-kpi.green:before {
    background:#2db995}.n-dashboard .n-kpi.red:before{background:#f0a462}.n-dashboard .n-kpi.purple:before{background:#9882f0;
}
.n-dashboard .n-kpi-label {
    font-size:11px;
    color:#738198;
    font-weight:700}.n-dashboard .n-kpi-num{font-size:27px;
    color:#1d3154;
    font-weight:800;
    margin:11px 0 5px;
    line-height:1.1}.n-dashboard .n-kpi-foot{font-size:11px;
    color:#8b99ab;
}
.n-dashboard .n-layout {
    display:grid;
    grid-template-columns:1.25fr .75fr;
    gap:18px;
    margin-bottom:18px;
}
.n-dashboard .n-action-line {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}
.n-dashboard .n-progress {
    display:flex;
    align-items:center;
    gap:9px;
    margin:16px 0 10px;
    flex-wrap:wrap;
}
.n-dashboard .n-progress-item {
    font-size:11px;
    font-weight:700;
    padding:9px 10px;
    border-radius:9px;
    background:#edf2fc;
    color:#627591}.n-dashboard .n-progress-item.current{background:#deebff;
    color:#2856be}.n-dashboard .n-progress-item.done{background:#e5f7ee;
    color:#178063;
}
.n-dashboard .n-arrow {
    font-size:13px;
    color:#9aa9bd;
}
.n-dashboard .n-inline-note {
    background:#f7faff;
    border:1px dashed #cfddf3;
    color:#60718f;
    font-size:11px;
    line-height:1.75;
    padding:11px 12px;
    border-radius:10px;
    margin-top:15px;
}
.n-dashboard .n-table-card {
    padding:0;
    overflow:hidden;
    margin-bottom:18px;
}
.n-dashboard .n-table-head {
    padding:22px 22px 16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.n-dashboard .n-search {
    background:#f8fafd;
    border:1px solid #dce5f1;
    border-radius:10px;
    padding:10px 12px;
    max-width:290px;
    width:100%;
    font-size:12px;
    outline:none;
}
.n-dashboard .n-table-scroll {
    overflow-x:auto;
    max-width:100%;
}
.n-dashboard .n-results {
    width:100%;
    border-collapse:collapse;
    font-size:12px;
}
.n-dashboard .n-results th {
    background:#f6f9fe;
    color:#6b7b95;
    text-transform:uppercase;
    letter-spacing:.45px;
    font-size:10px;
    font-weight:800;
    padding:15px 18px;
    text-align:left;
}
.n-dashboard .n-results td {
    padding:15px 18px;
    border-bottom:1px solid #edf1f7;
    color:#334360;
}
.n-dashboard .n-results tr:hover td {
    background:#fbfcff;
}
.n-dashboard .n-results .n-name {
    font-weight:700;
    color:#1b2d4d;
}
.n-dashboard .n-chip {
    border-radius:999px;
    padding:6px 10px;
    font-weight:800;
    font-size:10px;
    white-space:nowrap;
    display:inline-block;
}
.n-dashboard .n-chip.ok {
    background:#ddf7e9;
    color:#14734c}.n-dashboard .n-chip.no{background:#fff0e5;
    color:#ad5f2b}.n-dashboard .n-chip.wait{background:#e9efff;
    color:#305dc4}.n-dashboard .n-chip.final{background:#dcf6eb;
    color:#0e8065;
}
.n-dashboard .n-empty {
    text-align:center;
    padding:35px!important;
    color:#8694a9!important;
}
.n-dashboard .n-approve {
    border:1px solid #c8e8dd;
    background:linear-gradient(110deg,#f8fffc,#eefbf6);
}
.n-dashboard .n-error {
    border:1px solid #f0dab1;
    background:#fff9eb;
    color:#8a5d1c;
    padding:15px 18px;
    border-radius:13px;
    font-size:12px;
    line-height:1.8;
    margin-bottom:18px;
}
.n-dashboard .n-table-count {
    font-size:11px;
    color:#8a97aa;
    margin:10px 22px 17px;
}
@media(max-width:1050px) {
    .n-dashboard .n-kpis{grid-template-columns:repeat(2,1fr)}.n-dashboard .n-layout,.n-dashboard .n-top-grid{grid-template-columns:1fr};
}
@media(max-width:650px) {
    .n-dashboard .n-hero{padding:22px;
    display:block}.n-dashboard .n-hero h2{font-size:21px}.n-dashboard .n-hero-flag{margin-top:16px;
    display:inline-block}.n-dashboard .n-kpis{grid-template-columns:1fr 1fr}.n-dashboard .n-card{padding:17px}.n-dashboard .n-table-card{padding:0}.n-dashboard .n-picker{flex-direction:column}.n-dashboard .n-btn{width:100%};
}

.n-dashboard .n-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px 16px;
}

.n-dashboard .n-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.n-dashboard .n-page-button {
    min-width: 36px;
    padding: 8px 12px;
    border: 1px solid #d7e2ef;
    border-radius: 9px;
    background: #fff;
    color: #234269;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.n-dashboard .n-page-button:hover:not(:disabled) {
    background: #edf5ff;
    border-color: #93b8e8;
}

.n-dashboard .n-page-button.active {
    color: #fff;
    background: #1767b2;
    border-color: #1767b2;
}

.n-dashboard .n-page-button:disabled {
    opacity: .45;
    cursor: not-allowed;
}
</style>
<?php
$n_total=count($rows);
$n_pass=(int)($info['passed']??0);
$n_failCount=$n_total-$n_pass;
$n_ratio=$n_total>0?number_format($n_pass*100/$n_total,1,',','.').'%' : '0%';
$n_drafts=$rows&&count(array_filter($rows,fn($r)=>$r['trang_thai']==='DU_KIEN'))===count($rows);
$n_approved=$rows&&count(array_filter($rows,fn($r)=>$r['trang_thai']==='DA_DUYET'))===count($rows);
$n_canBuild=$ky!==''&&!$rows&&$checkError==='';
$n_canApprove=$ky!==''&&$n_drafts&&$checkError==='';
$n_summary=$ky!==''?n_query(db(),"SELECT k.ma_truong,t.ten_truong,COUNT(*) AS so_trung_tuyen FROM ket_qua_xet_tuyen k JOIN truong_thpt t ON t.ma_truong=k.ma_truong WHERE k.ma_ky_thi=? AND k.ket_qua='TRUNG_TUYEN' GROUP BY k.ma_truong,t.ten_truong ORDER BY so_trung_tuyen DESC,t.ten_truong",[$ky]):[];
?>
<div class="n-dashboard">
 <div class="n-hero">
  <div><div class="n-eyebrow">SỞ GIÁO DỤC VÀ ĐÀO TẠO</div><h2>Quản lý & phê duyệt kết quả xét tuyển</h2><p>Kiểm tra dữ liệu đầu vào, tạo danh sách dự kiến và xác nhận kết quả chính thức theo từng kỳ thi.</p></div>
 </div>
 <div class="n-top-grid">
  <div class="n-card">
   <div class="n-section-title"><span class="n-step">01</span> Lựa chọn kỳ thi</div><p class="n-helper">Chọn kỳ thi bạn muốn kiểm tra hoặc phê duyệt kết quả tuyển sinh.</p>
   <form method="get"><div class="n-picker"><select name="ma_ky_thi" aria-label="Chọn kỳ thi" required>
    <?php
foreach($exams as $x):
?><option value="<?=esc($x['ma_ky_thi'])?>" <?=$ky===$x['ma_ky_thi']?'selected':''?>>Kỳ thi tuyển sinh lớp 10 năm <?=esc($x['nam_tuyen_sinh'])?> (<?=esc($x['ma_ky_thi'])?>)</option><?php
endforeach;
?>
   </select><button class="n-btn" type="submit">Xem dữ liệu →</button></div></form>
  </div>
  <div class="n-card">
   <div class="n-section-title">Tình trạng kỳ thi</div><p class="n-helper">Trạng thái xử lý xét tuyển hiện tại.</p>
   <div class="n-info-status">
    <div class="n-status-icon <?=$checkError!==''?'warn':($n_approved?'complete':'')?>"><?=$checkError!==''?'!':($n_approved?'✓':'✓')?></div>
    <div><div class="n-status-text"><?=$checkError!==''?'Chưa đủ điều kiện xét tuyển':($n_approved?'Đã phê duyệt chính thức':($n_drafts?'Đang chờ phê duyệt':($n_canBuild?'Sẵn sàng tạo dự kiến':'Chưa có kỳ thi')))?></div>
    <div class="n-status-small"><?=$checkError!==''?'Cần hoàn thiện dữ liệu bắt buộc trước khi tiếp tục.':($n_approved?'Toàn bộ kết quả đã chuyển sang trạng thái đã duyệt.':($n_drafts?'Đã có danh sách dự kiến, cần xác nhận từ cán bộ Sở.':'Tạo danh sách khi dữ liệu đầu vào đã hợp lệ.'))?></div></div>
   </div>
  </div>
 </div>
 <?php
if($checkError!==''):
?><div class="n-error"><strong>⚠ Chưa thể thực hiện:</strong> <?=esc($checkError)?></div><?php
endif;
?>
 <div class="n-kpis">
  <div class="n-kpi"><div class="n-kpi-label">Tổng hồ sơ xét tuyển</div><div class="n-kpi-num"><?=number_format($n_total,0,',','.')?></div><div class="n-kpi-foot">Kết quả đã được tạo</div></div>
  <div class="n-kpi green"><div class="n-kpi-label">Trúng tuyển</div><div class="n-kpi-num"><?=number_format($n_pass,0,',','.')?></div><div class="n-kpi-foot">Đủ điều kiện theo xét tuyển</div></div>
  <div class="n-kpi red"><div class="n-kpi-label">Không trúng tuyển</div><div class="n-kpi-num"><?=number_format($n_failCount,0,',','.')?></div><div class="n-kpi-foot">Chưa có nguyện vọng phù hợp</div></div>
  <div class="n-kpi purple"><div class="n-kpi-label">Tỷ lệ trúng tuyển</div><div class="n-kpi-num"><?=esc($n_ratio)?></div><div class="n-kpi-foot">Trên kết quả hiện có</div></div>
 </div>
 <?php
if($ky!==''):
?>
 <div class="n-layout">
  <div class="n-card">
   <div class="n-section-title"><span class="n-step">02</span> Tạo kết quả xét tuyển dự kiến</div>
   <p class="n-helper">Chương trình chỉ tạo kết quả khi các môn có điểm thi hợp lệ (đã nhập hoặc đã chốt), nguyện vọng đã xác nhận, chỉ tiêu đã duyệt và có quy tắc hợp lệ.</p>
   <div class="n-progress"><span class="n-progress-item <?=$checkError===''?'done':'current'?>">Kiểm tra dữ liệu</span><span class="n-arrow">→</span><span class="n-progress-item <?=$n_drafts||$n_approved?'done':'current'?>">Kết quả dự kiến</span><span class="n-arrow">→</span><span class="n-progress-item <?=$n_approved?'done':''?>">Phê duyệt</span></div>
   <form method="post"><input type="hidden" name="_csrf" value="<?=esc(csrf())?>"><input type="hidden" name="action" value="create"><input type="hidden" name="ma_ky_thi" value="<?=esc($ky)?>"><button type="submit" class="n-btn" <?=$n_canBuild?'':'disabled'?>><?=$n_approved?'Đã hoàn tất xét tuyển':($n_drafts?'Đã tạo kết quả dự kiến':'＋ Tạo kết quả xét tuyển')?></button></form>
   <div class="n-inline-note">Hệ thống không tự đặt hệ số môn hoặc quy tắc đồng điểm. Nếu thiếu quy tắc, việc tạo kết quả được chặn để tránh sai lệch.</div>
  </div>
  <div class="n-card n-approve">
   <div class="n-section-title"><span class="n-step">03</span> Phê duyệt kết quả</div>
   <p class="n-helper">Duyệt toàn bộ danh sách dự kiến của kỳ thi. Kết quả chính thức sẽ được ghi nhận cùng cán bộ và thời gian phê duyệt.</p>
   <form method="post" onsubmit="return confirm('Bạn có chắc chắn phê duyệt TOÀN BỘ kết quả kỳ thi này? Thao tác sẽ ghi nhận kết quả chính thức.')"><input type="hidden" name="_csrf" value="<?=esc(csrf())?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="ma_ky_thi" value="<?=esc($ky)?>"><button type="submit" class="n-btn n-btn-success" <?=$n_canApprove?'':'disabled'?>><?=$n_approved?'✓ Đã phê duyệt':'✓ Xác nhận phê duyệt toàn bộ'?></button></form>
   <div class="n-inline-note"><?php
if($n_approved):
?>Đã khóa bước phê duyệt lại.<?php
elseif($n_drafts):
?>Vui lòng kiểm tra bảng tổng hợp và danh sách trước khi xác nhận.<?php
else:
?>Nút phê duyệt chỉ khả dụng sau khi đã tạo kết quả dự kiến hợp lệ.<?php
endif;
?></div>
  </div>
 </div>
 <div class="n-card n-table-card">
  <div class="n-table-head"><div><div class="n-section-title">Kết quả theo trường THPT</div><div class="n-helper" style="margin-bottom:0">Thống kê số lượng thí sinh trúng tuyển tại mỗi trường.</div></div><span class="n-chip wait"><?=count($n_summary)?> trường có kết quả</span></div>
  <div class="n-table-scroll"><table class="n-results"><thead><tr><th>Mã trường</th><th>Trường THPT</th><th>Số trúng tuyển</th></tr></thead><tbody>
  <?php
if(!$n_summary):
?><tr><td colspan="3" class="n-empty">Chưa có dữ liệu thống kê.</td></tr><?php
else: foreach($n_summary as $s):
?><tr><td><?=esc($s['ma_truong'])?></td><td class="n-name"><?=esc($s['ten_truong'])?></td><td><span class="n-chip ok"><?=esc($s['so_trung_tuyen'])?> thí sinh</span></td></tr><?php
endforeach;
endif;
?>
  </tbody></table></div>
 </div>
 <div class="n-card n-table-card">
  <div class="n-table-head"><div><div class="n-section-title">Danh sách kết quả xét tuyển</div><div class="n-helper" style="margin-bottom:0">Theo dõi điểm, nguyện vọng trúng tuyển và trạng thái xét duyệt.</div></div><input id="nSearch" class="n-search" type="search" placeholder="⌕  Tìm SBD, họ tên, trường..." aria-label="Tìm kiếm kết quả"></div>
  <div class="n-table-scroll"><table class="n-results" id="nResultTable"><thead><tr><th>Số báo danh</th><th>Họ và tên</th><th>Tổng điểm</th><th>NV trúng</th><th>Trường trúng tuyển</th><th>Kết quả</th><th>Trạng thái</th></tr></thead><tbody>
  <?php
if(!$rows):
?><tr><td colspan="7" class="n-empty">Chưa có kết quả. Hãy hoàn tất dữ liệu và tạo danh sách dự kiến.</td></tr><?php
else: foreach($rows as $r):
?><tr class="n-data-row"><td><?=esc($r['so_bao_danh']??'—')?></td><td class="n-name"><?=esc($r['ho_ten'])?></td><td><strong><?=esc($r['tong_diem'])?></strong></td><td><?=esc($r['nguyen_vong_trung_tuyen']??'—')?></td><td><?=esc($r['ten_truong']??'—')?></td><td><span class="n-chip <?=$r['ket_qua']==='TRUNG_TUYEN'?'ok':'no'?>"><?=$r['ket_qua']==='TRUNG_TUYEN'?'Trúng tuyển':'Không trúng tuyển'?></span></td><td><span class="n-chip <?=$r['trang_thai']==='DA_DUYET'?'final':'wait'?>"><?=$r['trang_thai']==='DA_DUYET'?'Đã phê duyệt':'Dự kiến'?></span></td></tr><?php
endforeach;
endif;
?>
  </tbody></table></div>
  <div class="n-pagination-bar">
    <span class="n-table-count" id="nTableCount">Hiển thị <?=count($rows)?> kết quả</span>
    <nav class="n-pagination" id="nPagination" aria-label="Phân trang kết quả"></nav>
  </div>
 </div>
 <?php
endif;
?>
</div>
<script>
(function () {
    const searchInput = document.getElementById('nSearch');
    const table = document.getElementById('nResultTable');
    const counter = document.getElementById('nTableCount');
    const pagination = document.getElementById('nPagination');

    if (!searchInput || !table || !counter || !pagination) {
        return;
    }

    const allRows = Array.from(table.querySelectorAll('tbody .n-data-row'));
    const pageSize = 20;
    let currentPage = 1;
    let filteredRows = allRows;

    function makeButton(label, targetPage, disabled, isCurrent = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'n-page-button' + (isCurrent ? ' active' : '');
        button.textContent = label;
        button.disabled = disabled;

        if (isCurrent) {
            button.setAttribute('aria-current', 'page');
        }

        button.addEventListener('click', function () {
            currentPage = targetPage;
            renderPage();
        });

        return button;
    }

    function renderPage() {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));

        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        const visibleRows = new Set(filteredRows.slice(start, end));

        allRows.forEach(function (row) {
            row.hidden = !visibleRows.has(row);
            row.style.display = visibleRows.has(row) ? '' : 'none';
        });

        counter.textContent = total === 0
            ? 'Không tìm thấy kết quả phù hợp'
            : 'Hiển thị ' + (start + 1) + '–' + end + ' / ' + total + ' kết quả';

        pagination.replaceChildren();
        if (totalPages <= 1) {
            return;
        }

        pagination.appendChild(makeButton('Trước', currentPage - 1, currentPage === 1));

        const fromPage = Math.max(1, currentPage - 2);
        const toPage = Math.min(totalPages, Math.max(5, currentPage + 2));
        const actualFrom = Math.max(1, Math.min(fromPage, toPage - 4));

        for (let page = actualFrom; page <= toPage; page++) {
            pagination.appendChild(makeButton(String(page), page, false, page === currentPage));
        }

        pagination.appendChild(makeButton('Sau', currentPage + 1, currentPage === totalPages));
    }

    searchInput.addEventListener('input', function () {
        const keyword = searchInput.value.trim().toLocaleLowerCase('vi');

        filteredRows = allRows.filter(function (row) {
            return row.textContent.toLocaleLowerCase('vi').includes(keyword);
        });

        currentPage = 1;
        renderPage();
    });

    renderPage();
})();
</script>
<?php
page_end();
