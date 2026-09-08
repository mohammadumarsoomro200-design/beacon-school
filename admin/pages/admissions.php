<?php
require_login(['admin', 'teacher']);
$pdo = db();

// Helper Function: Send SMS (API Gateway Integration)
function send_admission_sms($phone, $student_name) {
    $api_key = "YOUR_SMS_API_KEY";
    $mask = "BeaconSchool";
    $message = "Dear Parent, Mubarak ho! $student_name ka admission The New Beacon School System mein confirm ho gaya hai. Shukriya.";

    $encoded_msg = urlencode($message);
    $url = "https://smsprovider.com/api/send_sms?api_key=$api_key&to=$phone&mask=$mask&message=$encoded_msg";
    // @file_get_contents($url);
}

// Handle Status Change Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    check_csrf();
    
    $enquiry_id = (int)$_POST['enquiry_id'];
    $new_status = trim($_POST['status']);
    
    $valid_statuses = ['new', 'contacted', 'admitted', 'rejected'];
    
    if (in_array($new_status, $valid_statuses)) {
        // Fetch enquiry details
        $stmt = $pdo->prepare("SELECT * FROM admission_enquiries WHERE id = ?");
        $stmt->execute([$enquiry_id]);
        $enquiry = $stmt->fetch();
        
        if ($enquiry) {
            // Update Enquiry Status
            $u_stmt = $pdo->prepare("UPDATE admission_enquiries SET status = ? WHERE id = ?");
            $u_stmt->execute([$new_status, $enquiry_id]);
            
            // If Admitted, Insert into Students Table
            if ($new_status === 'admitted') {
                $student_name = $enquiry['student_name'] ?? $enquiry['name'] ?? '';
                $parent_name = $enquiry['parent_name'] ?? $enquiry['father_name'] ?? '';
                $phone = $enquiry['phone'] ?? '';
                $class_req = trim($enquiry['class_level'] ?? $enquiry['class'] ?? '');

                // Check Duplicate Entry
                $chk_stmt = $pdo->prepare("SELECT id FROM students WHERE student_name = ? AND (phone = ? OR father_name = ?)");
                $chk_stmt->execute([$student_name, $phone, $parent_name]);
                
                if (!$chk_stmt->fetch()) {
                    // Safe Class Lookup (Foreign Key Safeguard)
                    $class_id = null;
                    if (!empty($class_req)) {
                        $class_stmt = $pdo->prepare("SELECT id FROM classes WHERE class_name LIKE ? OR section LIKE ? LIMIT 1");
                        $class_stmt->execute(["%$class_req%", "%$class_req%"]);
                        $cls = $class_stmt->fetch();
                        if ($cls) {
                            $class_id = $cls['id'];
                        }
                    }

                    // Fallback to First Available Class if No Match Found
                    if (!$class_id) {
                        $first_cls = $pdo->query("SELECT id FROM classes ORDER BY id ASC LIMIT 1")->fetch();
                        $class_id = $first_cls['id'] ?? null;
                    }
                    
                    // Admission Number Generate
                    $adm_no = 'ADM-' . date('Y') . '-' . rand(1000, 9999);
                    
                    // Direct Insertion into Students Table
                    $ins_stmt = $pdo->prepare("
                        INSERT INTO students (admission_no, student_name, father_name, class_id, phone, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    
                    $ins_stmt->execute([$adm_no, $student_name, $parent_name, $class_id, $phone]);
                    
                    if (!empty($phone)) {
                        send_admission_sms($phone, $student_name);
                    }
                    
                    flash('success', "Mubarakan! $student_name Admitted ho gaya hai aur Direct Students List me add ho gaya hai!");
                } else {
                    flash('success', 'Status updated to Admitted (Student pehle se list me tha).');
                }
            } else {
                flash('success', 'Status updated to ' . ucfirst($new_status));
            }
        }
    }
    redirect('index.php?page=admissions');
}

// Fetch All Enquiries
$enquiries = $pdo->query("SELECT * FROM admission_enquiries ORDER BY id DESC")->fetchAll();
?>

<style>
.badge-status { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
.badge-new { background: #e3f2fd; color: #0d47a1; }
.badge-contacted { background: #fff3e0; color: #e65100; }
.badge-admitted { background: #e8f5e9; color: #1b5e20; }
.badge-rejected { background: #ffebee; color: #b71c1c; }
</style>

<section class="panel">
    <div class="panel-head" style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Admission Enquiries</h2>
        <span><b><?=count($enquiries)?></b> enquiries</span>
    </div>

    <div class="table-wrap">
        <table width="100%" cellpadding="10" style="border-collapse:collapse; text-align:left;">
            <thead>
                <tr style="border-bottom:2px solid #eee; background:#f9f9f9;">
                    <th>DATE</th>
                    <th>STUDENT</th>
                    <th>PARENT</th>
                    <th>CLASS</th>
                    <th>PHONE</th>
                    <th>MESSAGE</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($enquiries)): ?>
                    <?php foreach ($enquiries as $eq): 
                        $current_status = strtolower($eq['status'] ?? 'new');
                    ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td><?=date('d M Y', strtotime($eq['created_at'] ?? date('Y-m-d')))?></td>
                        <td><b><?=e($eq['student_name'] ?? $eq['name'] ?? '-')?></b></td>
                        <td><?=e($eq['parent_name'] ?? $eq['father_name'] ?? '-')?></td>
                        <td><?=e($eq['class_level'] ?? $eq['class'] ?? '-')?></td>
                        <td><?=e($eq['phone'] ?? '-')?></td>
                        <td><small><?=e($eq['message'] ?? '-')?></small></td>
                        <td>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="enquiry_id" value="<?=$eq['id']?>">
                                
                                <select name="status" onchange="this.form.submit()" style="padding:6px; border-radius:6px; font-weight:600; cursor:pointer;">
                                    <option value="new" <?=$current_status === 'new' ? 'selected' : ''?>>New</option>
                                    <option value="contacted" <?=$current_status === 'contacted' ? 'selected' : ''?>>Contacted</option>
                                    <option value="admitted" <?=$current_status === 'admitted' ? 'selected' : ''?>>Admitted</option>
                                    <option value="rejected" <?=$current_status === 'rejected' ? 'selected' : ''?>>Rejected</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#888;">No admission enquiries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>