<?php
require __DIR__.'/config/config.php';
$message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 $u=trim($_POST['username']??'admin'); $p=$_POST['password']??''; $name=trim($_POST['full_name']??'School Administrator');
 if(strlen($u)<4||strlen($p)<8){$error='Username must be 4+ characters and password 8+ characters.';}
 else{try{$pdo=db();$s=$pdo->prepare('SELECT id FROM users WHERE username=?');$s->execute([$u]);if($s->fetch()){$error='Username already exists.';}else{$s=$pdo->prepare("INSERT INTO users(username,password_hash,full_name,role) VALUES(?,?,?,'admin')");$s->execute([$u,password_hash($p,PASSWORD_DEFAULT),$name]);$message='Admin account created. Delete setup.php from the server now, then login.';}}catch(Throwable $e){$error='Database error: '.$e->getMessage();}}
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Beacon SMS Setup</title><link rel="stylesheet" href="assets/css/app.css"></head><body class="auth"><div class="auth-card"><img src="assets/images/school-logo.jpeg" class="auth-logo"><h1>Beacon SMS Setup</h1><p>Create the first administrator.</p><?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Full name<input name="full_name" value="School Administrator" required></label><label>Username<input name="username" value="admin" required></label><label>Password<input type="password" name="password" minlength="8" required></label><button class="btn primary">Create Admin</button></form></div></body></html>
