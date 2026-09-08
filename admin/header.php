<?php require_once __DIR__.'/../config/config.php'; require_login(); $f=get_flash(); $page=$page??'Dashboard'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($page)?> | Beacon SMS</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="app">
    <aside class="sidebar">
        <div class="side-brand">
            <img src="../assets/images/school-logo.jpeg">
            <div>
                <b>Beacon SMS</b>
                <small>Larkana Campus</small>
            </div>
        </div>
        <nav>
            <a class="<?= $page==='Dashboard'?'active':''?>" href="index.php">▦ Dashboard</a>
            <a href="index.php?page=students">👨‍🎓 Students</a>
            <a href="index.php?page=teachers">👨‍🏫 Teachers</a>
            <a href="index.php?page=classes">🏫 Classes</a>
            <a href="index.php?page=attendance">✓ Attendance</a>
            <a href="index.php?page=results">📊 Results</a>
            <a href="index.php?page=fees">💳 Fees</a>
            
            <?php if (isset(user()['role']) && strtolower(user()['role']) === 'admin'): ?>
                <a class="<?= $page==='Revenue Dashboard'?'active':''?>" href="revenue_dashboard.php">💰 Revenue Dashboard</a>
            <?php endif; ?>

            <a href="index.php?page=admissions">📝 Admissions</a>
            <a href="index.php?page=notices">📢 Notices</a>
            <a href="index.php?page=homework">📚 Homework</a>
            <a href="index.php?page=timetable">🗓 Timetable</a>
            <a href="index.php?page=events">🎉 Events</a>
            <a href="index.php?page=gallery">🖼 Gallery</a>
            <a href="index.php?page=users">🔐 Users</a>
            <a href="index.php?page=settings">⚙ Settings</a>
        </nav>
        <a class="logout" href="logout.php">↪ Logout</a>
    </aside>
    <main class="main">
        <header class="top">
            <button id="sideToggle">☰</button>
            <div>
                <div class="page-title"><?=e($page)?></div>
                <small><?=e(SCHOOL_NAME.' · '.CAMPUS)?></small>
            </div>
            <div class="user-chip">
                <span><?=e(user()['full_name'])?></span>
                <small><?=e(ucfirst(user()['role']))?></small>
            </div>
        </header>
        <?php if($f):?>
            <div class="alert <?=e($f['type'])?>"><?=e($f['msg'])?></div>
        <?php endif;?>
        <div class="content">