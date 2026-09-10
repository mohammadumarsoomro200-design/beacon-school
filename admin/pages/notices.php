<?php 
require_login(['admin','teacher']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $audience_input = $_POST['audience'] ?? [];

    if (!empty($title) && !empty($body)) {
        if (is_array($audience_input)) {
            if (in_array('all', $audience_input) || empty($audience_input)) {
                $audience = 'all';
            } else {
                // Strip out 'all' if specific classes are checked alongside
                $filtered = array_filter($audience_input, function($val) {
                    return $val !== 'all' && trim($val) !== '';
                });
                $audience = !empty($filtered) ? implode(',', array_map('trim', $filtered)) : 'all';
            }
        } else {
            $audience = trim($audience_input);
        }

        $s = $pdo->prepare('INSERT INTO notices(title, body, audience) VALUES(?, ?, ?)');
        $s->execute([$title, $body, $audience]);
        
        flash('success', 'Notice published successfully.');
        redirect('index.php?page=notices');
    }
}

if (isset($_GET['delete']) && user()['role'] === 'admin') {
    $pdo->prepare('DELETE FROM notices WHERE id=?')->execute([(int)$_GET['delete']]);
    flash('success', 'Notice deleted.');
    redirect('index.php?page=notices');
}

// Fetch Classes
$classes_list = [];
$class_map = [];
try {
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY id ASC");
    $classes_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes_list as $c) {
        $c_id = (string)$c['id'];
        $c_name = $c['class_name'] ?? $c['name'] ?? 'Class '.$c_id;
        $c_sec = $c['section'] ?? $c['sec'] ?? '';
        $full_c_name = trim($c_name . ' ' . $c_sec);
        
        $class_map[$c_id] = $full_c_name;
        $class_map[strtolower($full_c_name)] = $full_c_name;
    }
} catch (Exception $e) {}

// Fetch All Notices
$rows = $pdo->query('SELECT * FROM notices ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .target-audience-box {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        padding: 14px;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);
    }
    .all-select-card {
        background: #f1f5f9;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 12px;
        border: 1px solid #e2e8f0;
    }
    .class-grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
        max-height: 220px;
        overflow-y: auto;
        padding-right: 4px;
    }
    .class-chip-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 8px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }
    .class-chip-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .class-chip-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #b8860b;
    }
    .class-chip-item span {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="grid2">
    <section class="panel">
        <h2>Publish Notice</h2>
        <form class="form-grid" method="post">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            
            <label class="wide">Title
                <input required name="title" placeholder="Notice Title">
            </label>

            <div class="wide" style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px;">Audience / Target Class</label>
                <div class="target-audience-box">
                    
                    <!-- Master Select All -->
                    <div class="all-select-card">
                        <label class="class-chip-item" style="background: transparent; border: none; padding: 0;">
                            <input type="checkbox" id="selectAllClasses" name="audience[]" value="all" onclick="toggleAllClasses(this)">
                            <span style="font-weight: 700; color: #0f172a;">-- All Classes (Students & Parents) --</span>
                        </label>
                    </div>

                    <!-- Clean Class Grid -->
                    <div class="class-grid-container">
                        <?php foreach($classes_list as $c): ?>
                            <?php 
                                $c_id = (string)$c['id'];
                                $c_name = $c['class_name'] ?? $c['name'] ?? 'Class '.$c_id;
                                $c_sec = $c['section'] ?? $c['sec'] ?? '';
                                $display_name = trim($c_name . ' ' . $c_sec);
                            ?>
                            <label class="class-chip-item">
                                <input type="checkbox" name="audience[]" value="<?=e($c_id)?>" class="class-checkbox" onclick="uncheckMaster()">
                                <span title="<?=e($display_name)?>"><?=e($display_name)?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <label class="wide">Message
                <textarea required name="body" rows="6" placeholder="Write notice content here..."></textarea>
            </label>

            <button class="btn primary wide">Publish Notice</button>
        </form>
    </section>

    <section class="panel">
        <h2>Published Notices</h2>
        <?php foreach($rows as $r):?>
            <?php
                $aud = trim((string)($r['audience'] ?? 'all'));
                $aud_lower = strtolower($aud);
                
                if ($aud_lower === 'all') {
                    $badge_label = 'All Classes';
                } elseif ($aud_lower === 'parents') {
                    $badge_label = 'All Parents';
                } elseif ($aud_lower === 'students') {
                    $badge_label = 'All Students';
                } else {
                    $aud_items = explode(',', $aud);
                    $labels = [];
                    foreach ($aud_items as $item) {
                        $item = trim($item);
                        if (isset($class_map[$item])) {
                            $labels[] = $class_map[$item];
                        } else {
                            $labels[] = $item;
                        }
                    }
                    $badge_label = 'Class: ' . implode(', ', $labels);
                }
            ?>
            <article class="notice">
                <div>
                    <span class="badge"><?= e($badge_label) ?></span> 
                    <small><?=e(date('d M Y', strtotime($r['published_at'] ?? $r['created_at'] ?? 'now')))?></small>
                </div>
                <h3><?=e($r['title'])?></h3>
                <p><?=nl2br(e($r['body'] ?? $r['message'] ?? ''))?></p>
                <?php if(user()['role']==='admin'):?>
                    <a class="action danger-text" data-confirm="Delete notice?" href="index.php?page=notices&delete=<?=$r['id']?>">Delete</a>
                <?php endif;?>
            </article>
        <?php endforeach;?>
    </section>
</div>

<script>
function toggleAllClasses(master) {
    let checkboxes = document.querySelectorAll('.class-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
}

function uncheckMaster() {
    let master = document.getElementById('selectAllClasses');
    let total = document.querySelectorAll('.class-checkbox').length;
    let checkedCount = document.querySelectorAll('.class-checkbox:checked').length;
    
    if (checkedCount < total) {
        master.checked = false;
    } else if (checkedCount === total && total > 0) {
        master.checked = true;
    }
}
</script>