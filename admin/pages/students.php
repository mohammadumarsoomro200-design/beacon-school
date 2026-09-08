<?php 
require_login(['admin','teacher']); 
$pdo = db(); 
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        trim($_POST['admission_no']),
        trim($_POST['student_name']),
        trim($_POST['father_name']),
        trim($_POST['mother_name']),
        $_POST['gender'],
        ($_POST['dob'] ?: null),
        (int)($_POST['class_id'] ?: 0),
        trim($_POST['phone']),
        trim($_POST['parent_phone']),
        trim($_POST['email']),
        trim($_POST['address']),
        ($_POST['admission_date'] ?: null),
        $_POST['status']
    ];

    if ($id) {
        $s = $pdo->prepare('UPDATE students SET admission_no=?,student_name=?,father_name=?,mother_name=?,gender=?,dob=?,class_id=?,phone=?,parent_phone=?,email=?,address=?,admission_date=?,status=? WHERE id=?');
        $s->execute([...$data, $id]);
        audit('update', 'students', 'Student #'.$id);
        flash('success', 'Student updated.');
    } else {
        $s = $pdo->prepare('INSERT INTO students(admission_no,student_name,father_name,mother_name,gender,dob,class_id,phone,parent_phone,email,address,admission_date,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute($data);
        audit('create', 'students', $data[1]);
        flash('success', 'Student added.');
    }
    redirect('index.php?page=students');
}

if (isset($_GET['delete']) && user()['role'] === 'admin') {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);
    audit('delete', 'students', 'Student #'.$id);
    flash('success', 'Student deleted.');
    redirect('index.php?page=students');
}

$classes = $pdo->query('SELECT * FROM classes ORDER BY class_name,section')->fetchAll();
$edit = null;

if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM students WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
    $action = 'new';
}

$q = trim($_GET['q'] ?? '');
$s = $pdo->prepare("SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.student_name LIKE ? OR s.admission_no LIKE ? ORDER BY s.id DESC LIMIT 200");
$s->execute(['%'.$q.'%', '%'.$q.'%']);
$rows = $s->fetchAll();
?>

<div class="toolbar">
    <form class="search">
        <input name="q" value="<?=e($q)?>" placeholder="Search student or admission no">
        <input type="hidden" name="page" value="students">
        <button class="btn">Search</button>
    </form>
    <a class="btn primary" href="index.php?page=students&action=new">＋ Add Student</a>
</div>

<?php if ($action === 'new'): ?>
<section class="panel">
    <div class="panel-head">
        <h2><?=$edit ? 'Edit' : 'Add'?> Student</h2>
        <a href="index.php?page=students">Cancel</a>
    </div>
    <form class="form-grid" method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>">
        <input type="hidden" name="id" value="<?=e($edit['id'] ?? '')?>">
        
        <label>Admission No
            <input required name="admission_no" value="<?=e($edit['admission_no'] ?? 'STU-'.date('Y').'-'.rand(1000,9999))?>">
        </label>
        <label>Student Name
            <input required name="student_name" value="<?=e($edit['student_name'] ?? '')?>">
        </label>
        <label>Father Name
            <input name="father_name" value="<?=e($edit['father_name'] ?? '')?>">
        </label>
        <label>Mother Name
            <input name="mother_name" value="<?=e($edit['mother_name'] ?? '')?>">
        </label>
        <label>Gender
            <select name="gender">
                <option>Male</option>
                <option <?=($edit['gender'] ?? '') === 'Female' ? 'selected' : ''?>>Female</option>
                <option <?=($edit['gender'] ?? '') === 'Other' ? 'selected' : ''?>>Other</option>
            </select>
        </label>
        <label>Date of Birth
            <input type="date" name="dob" value="<?=e($edit['dob'] ?? '')?>">
        </label>
        <label>Class
            <select name="class_id">
                <option value="0">Not assigned</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=((int)($edit['class_id'] ?? 0) == $c['id']) ? 'selected' : ''?>>
                        <?=e($c['class_name'].' - '.$c['section'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Student Phone
            <input name="phone" value="<?=e($edit['phone'] ?? '')?>">
        </label>
        <label>Parent Phone
            <input name="parent_phone" value="<?=e($edit['parent_phone'] ?? '')?>">
        </label>
        <label>Email
            <input type="email" name="email" value="<?=e($edit['email'] ?? '')?>">
        </label>
        <label>Admission Date
            <input type="date" name="admission_date" value="<?=e($edit['admission_date'] ?? date('Y-m-d'))?>">
        </label>
        <label>Status
            <select name="status">
                <option value="paid" <?=($edit['status'] ?? '') === 'paid' ? 'selected' : ''?>>paid</option>
                <option value="unpaid" <?=($edit['status'] ?? '') === 'unpaid' ? 'selected' : ''?>>unpaid</option>
                <option value="active" <?=($edit['status'] ?? '') === 'active' ? 'selected' : ''?>>active</option>
                <option value="inactive" <?=($edit['status'] ?? '') === 'inactive' ? 'selected' : ''?>>inactive</option>
            </select>
        </label>
        <label class="wide">Address
            <textarea name="address"><?=e($edit['address'] ?? '')?></textarea>
        </label>
        
        <button class="btn primary wide">Save Student</button>
    </form>
</section>

<?php else: ?>

<section class="panel">
    <div class="panel-head">
        <h2>Students</h2>
        <span><?=count($rows)?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Admission</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Parent Phone</th>
                    <th>Status</th>
                    <th>Fee Portal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?=e($r['admission_no'])?></td>
                    <td>
                        <b><?=e($r['student_name'])?></b><br>
                        <small><?=e($r['father_name'])?></small>
                    </td>
                    <td><?=e(($r['class_name'] ?? '—').' '.($r['section'] ?? ''))?></td>
                    <td><?=e($r['parent_phone'])?></td>
                    <td><span class="badge <?=e($r['status'])?>"><?=e($r['status'])?></span></td>
                    
                    <!-- FEE PORTAL BLOCK/UNBLOCK BUTTON -->
                    <td>
                        <?php if (in_array(strtolower(trim($r['status'] ?? '')), ['unpaid', 'blocked', 'inactive'])): ?>
                            <a style="color: #10b981; font-weight: bold; text-decoration: none;" 
                               href="update_student_status.php?id=<?=$r['id']?>&status=paid" 
                               onclick="return confirm('Kya aap is student ka portal Unlock karna chahte hain?');">
                               🟢 Unblock
                            </a>
                        <?php else: ?>
                            <a style="color: #ef4444; font-weight: bold; text-decoration: none;" 
                               href="update_student_status.php?id=<?=$r['id']?>&status=unpaid" 
                               onclick="return confirm('Kya aap is student ka portal Lock karna chahte hain?');">
                               🔴 Block
                            </a>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <a class="action" href="index.php?page=students&edit=<?=$r['id']?>">Edit</a>
                        <?php if (user()['role'] === 'admin'): ?>
                            <a class="action danger-text" data-confirm="Delete this student?" href="index.php?page=students&delete=<?=$r['id']?>">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>