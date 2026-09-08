<?php
require_php_file_path:
require_once __DIR__ . '/../config/config.php';
$pdo=db(); $page=$_GET['page']??'dashboard'; $titleMap=['dashboard'=>'Dashboard','students'=>'Students','teachers'=>'Teachers','classes'=>'Classes','attendance'=>'Attendance','results'=>'Results','fees'=>'Fees','admissions'=>'Admissions','notices'=>'Notices','homework'=>'Homework','timetable'=>'Timetable','events'=>'Events','gallery'=>'Gallery','users'=>'Users','settings'=>'Settings']; $page=$page==='dashboard'?'dashboard':(isset($titleMap[$page])?$page:'dashboard'); $GLOBALS['page']=$titleMap[$page]??'Dashboard'; require __DIR__.'/header.php';
if($page==='dashboard'){
 $page='Dashboard'; $students=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn(); $teachers=(int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn(); $classes=(int)$pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(); $newAdmissions=(int)$pdo->query("SELECT COUNT(*) FROM admission_enquiries WHERE status='new'")->fetchColumn(); $today=$pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='present'");$today->execute();$present=(int)$today->fetchColumn(); $due=(float)$pdo->query("SELECT COALESCE(SUM(amount-paid_amount),0) FROM fees WHERE status<>'paid'")->fetchColumn();
 $recent=$pdo->query("SELECT * FROM admission_enquiries ORDER BY id DESC LIMIT 6")->fetchAll();
 include __DIR__.'/pages/dashboard.php';
}else{ $file=__DIR__.'/pages/'.$page.'.php'; if(!is_file($file)){http_response_code(404);echo '<div class="empty">Module not found.</div>';include __DIR__.'/footer.php';exit;} include $file; }
include __DIR__.'/footer.php';
