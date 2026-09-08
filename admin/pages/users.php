<?php 
require_login(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        // Update existing user
        $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $s = $pdo->prepare('UPDATE users SET full_name=?, role=?, active=?, student_id=? WHERE id=?');
        $s->execute([trim($_POST['full_name']), $_POST['role'], (int)$_POST['active'], $student_id, $id]);

        if (!empty($_POST['password'])) {
            $s = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $s->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
        }
        flash('success', 'User updated.');
    } else {
        // Insert new user with student_id
        $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $s = $pdo->prepare('INSERT INTO users(username, password_hash, full_name, role, active, student_id) VALUES(?, ?, ?, ?, 1, ?)');
        $s->execute([
            trim($_POST['username']),
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            trim($_POST['full_name']),
            $_POST['role'],
            $student_id
        ]);
        flash('success', 'User created.');
    }
    redirect('index.php?page=users');
}

if (isset($_GET['toggle'])) {
    $pdo->prepare('UPDATE users SET active=1-active WHERE id=?')->execute([(int)$_GET['toggle']]);
    flash('success', 'User status changed.');
    redirect('index.php?page=users');
}

// Fetch all students (safe fallback logic)
try {
    $students = $pdo->query("SELECT id, admission_no, student_name FROM students ORDER BY student_name ASC")->fetchAll();
} catch (Exception $ex) {
    // Fallback if column names vary
    $students = $pdo->query("SELECT * FROM students ORDER BY id DESC")->fetchAll();
}

$rows = $pdo->query('SELECT id, username, full_name, role, active, created_at FROM users ORDER BY id DESC')->fetchAll();
?>

<div class="grid2">
    <section class="panel">
        <h2>Create User</h2>
        <form class="form-grid" method="post">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            
            <label>Full Name
                <input required name="full_name">
            </label>
            
            <label>Username
                <input required name="username">
            </label>
            
            <label>Password
                <input required type="password" minlength="8" name="password">
            </label>
            
            <label>Role
                <select name="role">
                    <option value="admin">admin</option>
                    <option value="teacher">teacher</option>
                    <option value="accountant">accountant</option>
                    <option value="parent">parent</option>
                    <option value="student">student</option>
                </select>
            </label>
            
            <label>Link Student (for student/parent portal)
                <select name="student_id">
                    <option value="">Not linked</option>
                    <?php if(!empty($students)): ?>
                        <?php foreach($students as $st): ?>
                            <?php 
                                $sId   = $st['id'];
                                $sName = $st['student_name'] ?? $st['name'] ?? $st['full_name'] ?? ('Student #' . $sId);
                                $sAdm  = $st['admission_no'] ?? $st['adm_no'] ?? $st['roll_no'] ?? ('ADM-' . $sId);
                            ?>
                            <option value="<?=$sId?>"><?=e($sAdm . ' · ' . $sName)?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No Students Found in Database</option>
                    <?php endif; ?>
                </select>
            </label>
            
            <button class="btn primary wide">Create User</button>
        </form>
    </section>

    <section class="panel">
        <h2>Users</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><?=e($r['username'])?></td>
                            <td><?=e($r['full_name'])?></td>
                            <td><?=e($r['role'])?></td>
                            <td><?=$r['active'] ? '<span class="badge active">Active</span>' : '<span class="badge inactive">Disabled</span>'?></td>
                            <td>
                                <?php if($r['id'] !== user()['id']): ?>
                                    <a class="action" href="index.php?page=users&toggle=<?=$r['id']?>">Toggle</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>