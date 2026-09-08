<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect('localhost', 'root', '', 'beacon_school');

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Auto-Fix / Ensure Columns Exist
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `receipt_no` VARCHAR(50) DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `month` VARCHAR(50) DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `amount` DECIMAL(10,2) DEFAULT '0.00'");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `paid` DECIMAL(10,2) DEFAULT '0.00'");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `due_date` DATE DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `notes` TEXT DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE `fees` ADD COLUMN `status` VARCHAR(20) DEFAULT 'unpaid'");

$message = '';
$message_type = '';

// 1. Action: Generate Fee Voucher Only (For Parents - Unpaid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_voucher'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $fee_month  = mysqli_real_escape_string($conn, trim($_POST['fee_month'] ?? date('F Y')));
    $monthly_amount = floatval($_POST['amount'] ?? 4500);
    $due_date   = mysqli_real_escape_string($conn, $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
    $notes      = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? 'Monthly Tuition Fee'));

    if ($student_id > 0) {
        $receipt_no = 'VOUCH-' . date('Ymd') . '-' . rand(100, 999);

        $sqlInsert = "INSERT INTO fees (receipt_no, student_id, month, amount, paid, due_date, notes, status) 
                      VALUES ('$receipt_no', '$student_id', '$fee_month', '$monthly_amount', 0, '$due_date', '$notes', 'unpaid')";

        if (mysqli_query($conn, $sqlInsert)) {
            $message = "Fee Voucher generated successfully for Parent!";
            $message_type = "success";
        } else {
            $message = "Error generating voucher: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = "Please select a student first.";
        $message_type = "error";
    }
}

// 2. Action: Direct Fee Collect (Instant Paid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_direct_fee'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $fee_month  = mysqli_real_escape_string($conn, trim($_POST['fee_month'] ?? date('F Y')));
    $paid_amount = floatval($_POST['amount'] ?? 4500);
    $notes      = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? 'Direct Fee Payment Received'));
    $today_date = date('Y-m-d');

    if ($student_id > 0 && $paid_amount > 0) {
        $receipt_no = 'REC-' . date('Ymd') . '-' . rand(100, 999);

        $sqlInsert = "INSERT INTO fees (receipt_no, student_id, month, amount, paid, due_date, notes, status) 
                      VALUES ('$receipt_no', '$student_id', '$fee_month', '$paid_amount', '$paid_amount', '$today_date', '$notes', 'paid')";

        if (mysqli_query($conn, $sqlInsert)) {
            $message = "Direct Fee collected and recorded as PAID!";
            $message_type = "success";
        } else {
            $message = "Error recording fee: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = "Please select a student and enter a valid amount.";
        $message_type = "error";
    }
}

// 3. Action: Delete Fee Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_voucher'])) {
    $voucher_id = intval($_POST['voucher_id'] ?? 0);
    if ($voucher_id > 0) {
        if (mysqli_query($conn, "DELETE FROM fees WHERE id = '$voucher_id'")) {
            $message = "Record deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting record.";
            $message_type = "error";
        }
    }
}

// Action: Collect Fee from Voucher (Update Status or Delete & Move)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fee_now'])) {
    $voucher_id = intval($_POST['voucher_id'] ?? 0);
    $receiving_amount = floatval($_POST['receiving_amount'] ?? 0);

    if ($voucher_id > 0 && $receiving_amount > 0) {
        // Option A: Agar aap chahte hain ke PURANA VOUCHER HI 'PAID' me convert ho jaye (Recommended)
        $today_date = date('Y-m-d');
        $new_receipt_no = 'REC-' . date('Ymd') . '-' . rand(100, 999);

        $sqlUpdate = "UPDATE fees 
                      SET paid = '$receiving_amount', 
                          status = 'paid', 
                          due_date = '$today_date', 
                          receipt_no = '$new_receipt_no' 
                      WHERE id = '$voucher_id'";

        if (mysqli_query($conn, $sqlUpdate)) {
            $message = "Fee collected successfully! Voucher updated to PAID.";
            $message_type = "success";
        } else {
            $message = "Error updating payment: " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// Filter Logic
$selected_class_id = intval($_GET['filter_class_id'] ?? $_POST['filter_class_id'] ?? 0);

// Fetch All Classes
$classList = [];
$qCls = @mysqli_query($conn, "SELECT * FROM classes ORDER BY id ASC");
if ($qCls) {
    while ($rCls = mysqli_fetch_assoc($qCls)) {
        $classList[] = $rCls;
    }
}

// Fetch Filtered Students
$students = [];
$stdQuery = "SELECT * FROM students ";
if ($selected_class_id > 0) {
    $stdQuery .= " WHERE class_id = '$selected_class_id' ";
}
$stdQuery .= " ORDER BY id DESC";

$qStd = @mysqli_query($conn, $stdQuery);
if ($qStd && mysqli_num_rows($qStd) > 0) {
    while ($rStd = mysqli_fetch_assoc($qStd)) {
        $sName = $rStd['student_name'] ?? $rStd['name'] ?? $rStd['full_name'] ?? ('Student #' . $rStd['id']);
        $sAdm  = $rStd['admission_no'] ?? $rStd['adm_no'] ?? $rStd['roll_no'] ?? ('ADM-' . $rStd['id']);
        
        $students[] = [
            'id' => $rStd['id'],
            'name' => $sName,
            'admission_no' => $sAdm
        ];
    }
}

// Fetch Totals
$totalBilled = $totalCollected = 0;
$qSum = @mysqli_query($conn, "SELECT SUM(amount) as total_amt, SUM(paid) as total_paid FROM fees");
if ($qSum && $rSum = mysqli_fetch_assoc($qSum)) {
    $totalBilled = floatval($rSum['total_amt'] ?? 0);
    $totalCollected = floatval($rSum['total_paid'] ?? 0);
}
$outstanding = max(0, $totalBilled - $totalCollected);

// Fetch Unpaid Vouchers vs Paid Records
$unpaidVouchers = [];
$paidRecords = [];

$qFees = @mysqli_query($conn, "SELECT * FROM fees ORDER BY id DESC");
if ($qFees && mysqli_num_rows($qFees) > 0) {
    while ($rFee = mysqli_fetch_assoc($qFees)) {
        $sId = intval($rFee['student_id']);
        
        $sName = 'Student #' . $sId;
        $sAdm = 'ADM-' . $sId;
        $sClass = '';
        
        $qS = @mysqli_query($conn, "SELECT s.*, c.class_name, c.section FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = '$sId'");
        if ($qS && $rS = mysqli_fetch_assoc($qS)) {
            $sName = $rS['student_name'] ?? $rS['name'] ?? $rS['full_name'] ?? $sName;
            $sAdm  = $rS['admission_no'] ?? $rS['adm_no'] ?? $rS['roll_no'] ?? $sAdm;
            $sClass = trim(($rS['class_name'] ?? '') . ' ' . ($rS['section'] ?? ''));
        }

        $rFee['std_name'] = $sName;
        $rFee['adm_no'] = $sAdm;
        $rFee['cls_name'] = $sClass;

        if (strtolower($rFee['status']) === 'paid') {
            $paidRecords[] = $rFee;
        } else {
            $unpaidVouchers[] = $rFee;
        }
    }
}
?>

<style>
.fee-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; margin-bottom: 25px; }
.card-box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #334155; }
.form-control { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; box-sizing: border-box; }

.tab-btn { padding: 10px 18px; border: none; background: #e2e8f0; font-weight: bold; font-size: 14px; cursor: pointer; border-radius: 6px 6px 0 0; color: #475569; }
.tab-btn.active { background: #b45309; color: #fff; }
.tab-btn.active-green { background: #16a34a; color: #fff; }

.btn-submit { background: #b45309; color: #fff; border: none; padding: 11px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; font-size: 15px; }
.btn-submit-green { background: #16a34a; color: #fff; border: none; padding: 11px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; font-size: 15px; }

.badge-st { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
.badge-paid { background: #d1fae5; color: #065f46; }
.badge-unpaid { background: #fee2e2; color: #991b1b; }

.table-title { font-size: 18px; font-weight: bold; color: #1e293b; margin: 25px 0 10px 0; display: flex; align-items: center; justify-content: space-between; }
.table-wrap { overflow-x: auto; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px; }
table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
th, td { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
th { background: #f8fafc; font-weight: 600; color: #475569; }

.btn-print { background: #0284c7; color: #fff; padding: 6px 12px; border-radius: 4px; border:none; cursor:pointer; font-weight:bold; font-size:12px; }
.btn-pay { background: #16a34a; color: #fff; padding: 6px 12px; border-radius: 4px; border:none; cursor:pointer; font-weight:bold; font-size:12px; margin-left:5px; }
.btn-delete { background: #dc2626; color: #fff; padding: 6px 12px; border-radius: 4px; border:none; cursor:pointer; font-weight:bold; font-size:12px; margin-left:5px; }

@media print {
    body * { visibility: hidden; }
    #printableVoucher, #printableVoucher * { visibility: visible; }
    #printableVoucher { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<div class="fee-grid">
    <div class="card-box">
        <?php if ($message): ?>
            <div style="padding:10px; border-radius:6px; margin-bottom:15px; background:<?=$message_type==='success'?'#d1fae5':'#fee2e2'?>; color:<?=$message_type==='success'?'#065f46':'#991b1b'?>;">
                <?=e($message)?>
            </div>
        <?php endif; ?>

        <!-- Class Filter -->
        <form method="GET" action="index.php" id="classFilterForm" style="margin-bottom: 15px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <input type="hidden" name="page" value="fees">
            <div class="form-group" style="margin-bottom:0;">
                <label>Step 1: Search / Select Class</label>
                <select name="filter_class_id" class="form-control" onchange="document.getElementById('classFilterForm').submit();" style="border-color:#0284c7; font-weight:bold;">
                    <option value="0">-- All Classes --</option>
                    <?php foreach ($classList as $c): ?>
                        <?php $cName = trim(($c['class_name'] ?? $c['name'] ?? '') . ' ' . ($c['section'] ?? '')); ?>
                        <option value="<?=$c['id']?>" <?=$selected_class_id == $c['id'] ? 'selected' : ''?>>
                            <?=e($cName)?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Tabs Navigation -->
        <div style="display:flex; gap:5px; border-bottom: 2px solid #e2e8f0; margin-bottom: 15px;">
            <button type="button" class="tab-btn active" id="tabVoucherBtn" onclick="switchTab('voucher')">🎫 Generate Fee Voucher</button>
            <button type="button" class="tab-btn" id="tabDirectBtn" onclick="switchTab('direct')">💵 Direct Collect Fee</button>
        </div>

        <!-- Form 1: Generate Voucher (Unpaid) -->
        <form method="POST" action="index.php?page=fees" id="formVoucher">
            <input type="hidden" name="filter_class_id" value="<?=$selected_class_id?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label>Step 2: Select Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php if(!empty($students)): ?>
                            <?php foreach($students as $s): ?>
                                <option value="<?=$s['id']?>"><?=e($s['admission_no'])?> · <?=e($s['name'])?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No Students Found</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee Month</label>
                    <input type="text" name="fee_month" class="form-control" value="<?=date('F Y')?>" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label>Monthly Fee Amount (Rs.)</label>
                    <input type="number" name="amount" class="form-control" value="4500" required>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?=date('Y-m-d', strtotime('+7 days'))?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Notes / Voucher Details</label>
                <input type="text" name="notes" class="form-control" value="Monthly Tuition Fee">
            </div>

            <button type="submit" name="generate_voucher" class="btn-submit">Generate Fee Voucher For Parent</button>
        </form>

        <!-- Form 2: Direct Collect Fee -->
        <form method="POST" action="index.php?page=fees" id="formDirect" style="display:none;">
            <input type="hidden" name="filter_class_id" value="<?=$selected_class_id?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php if(!empty($students)): ?>
                            <?php foreach($students as $s): ?>
                                <option value="<?=$s['id']?>"><?=e($s['admission_no'])?> · <?=e($s['name'])?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No Students Found</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee Month</label>
                    <input type="text" name="fee_month" class="form-control" value="<?=date('F Y')?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Paid Amount (Rs.)</label>
                <input type="number" name="amount" class="form-control" value="4500" required style="font-size:16px; font-weight:bold; color:#16a34a;">
            </div>

            <div class="form-group">
                <label>Notes / Receipt Details</label>
                <input type="text" name="notes" class="form-control" value="Direct Cash Payment Received">
            </div>

            <button type="submit" name="collect_direct_fee" class="btn-submit-green">✓ Pay & Save Direct Fee</button>
        </form>
    </div>

    <!-- Fee Summary -->
    <div class="card-box" style="background:#f8fafc;">
        <h3 style="margin-top:0;">Fee Summary</h3>
        <div style="font-size:32px; font-weight:bold; color:#0f172a; margin:10px 0;">
            Rs. <?=number_format($outstanding, 2)?>
        </div>
        <div style="font-size:13px; color:#64748b; font-weight:bold; margin-bottom:15px;">Outstanding Balance</div>
        
        <hr style="border:0; border-top:1px solid #e2e8f0; margin:15px 0;">
        
        <p style="font-size:14px; color:#334155; margin:8px 0;">
            <b>Total Billed:</b> Rs. <?=number_format($totalBilled, 2)?>
        </p>
        <p style="font-size:14px; color:#16a34a; margin:8px 0;">
            <b>Total Collected:</b> Rs. <?=number_format($totalCollected, 2)?>
        </p>
    </div>
</div>

<!-- ================= TABLE 1: UNPAID / PENDING VOUCHERS ================= -->
<div class="table-title">
    <span>📋 Unpaid / Pending Fee Vouchers</span>
    <span style="font-size:12px; font-weight:normal; background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:20px;">
        Total Unpaid: <?=count($unpaidVouchers)?>
    </span>
</div>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Voucher #</th>
                <th>Student</th>
                <th>Month</th>
                <th>Total Fee (Rs.)</th>
                <th>Paid (Rs.)</th>
                <th>Remaining (Rs.)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($unpaidVouchers)): ?>
                <?php foreach($unpaidVouchers as $f): ?>
                    <?php 
                        $amt = floatval($f['amount'] ?? 0);
                        $pd = floatval($f['paid'] ?? 0);
                        $bal = max(0, $amt - $pd);
                        $st = strtolower($f['status'] ?? 'unpaid');
                        $rcpt = $f['receipt_no'] ?? ('VOUCHER-#' . $f['id']);
                        $mth = $f['month'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><b><?=e($rcpt)?></b></td>
                        <td>
                            <b><?=e($f['std_name'])?></b><br>
                            <small style="color:#64748b;"><?=e($f['adm_no'])?> <?= $f['cls_name'] ? '('.e($f['cls_name']).')' : '' ?></small>
                        </td>
                        <td><?=e($mth)?></td>
                        <td><b>Rs. <?=number_format($amt, 2)?></b></td>
                        <td style="color:#16a34a;"><b>Rs. <?=number_format($pd, 2)?></b></td>
                        <td style="color:#dc2626;"><b>Rs. <?=number_format($bal, 2)?></b></td>
                        <td><span class="badge-st badge-<?=$st?>"><?=e($st)?></span></td>
                        <td>
                            <button onclick="printVoucher('<?=e($rcpt)?>', '<?=e($f['std_name'])?>', '<?=e($f['adm_no'])?>', '<?=e($f['cls_name'])?>', '<?=e($mth)?>', '<?=$amt?>', '<?=$pd?>', '<?=$bal?>', '<?=e($f['due_date'] ?? '')?>')" class="btn-print">📄 Print</button>
                            <button onclick="openPayModal('<?=$f['id']?>', '<?=e($f['std_name'])?>', '<?=$bal?>')" class="btn-pay">💵 Collect Fee</button>
                            
                            <form method="POST" action="index.php?page=fees" style="display:inline;" onsubmit="return confirm('Delete this voucher?');">
                                <input type="hidden" name="voucher_id" value="<?=$f['id']?>">
                                <button type="submit" name="delete_voucher" class="btn-delete">🗑️ Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center; padding:20px; color:#888;">No pending vouchers found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ================= TABLE 2: PAID FEES HISTORY ================= -->
<div class="table-title">
    <span style="color:#16a34a;">✅ Paid Fees History</span>
    <span style="font-size:12px; font-weight:normal; background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:20px;">
        Total Paid Transactions: <?=count($paidRecords)?>
    </span>
</div>
<div class="table-wrap">
    <table>
        <thead>
            <tr style="background:#f0fdf4;">
                <th>Receipt #</th>
                <th>Student</th>
                <th>Month</th>
                <th>Paid Amount (Rs.)</th>
                <th>Payment Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($paidRecords)): ?>
                <?php foreach($paidRecords as $f): ?>
                    <?php 
                        $amt = floatval($f['amount'] ?? 0);
                        $rcpt = $f['receipt_no'] ?? ('REC-#' . $f['id']);
                        $mth = $f['month'] ?? 'N/A';
                    ?>
                    <tr>
                        <td><b><?=e($rcpt)?></b></td>
                        <td>
                            <b><?=e($f['std_name'])?></b><br>
                            <small style="color:#64748b;"><?=e($f['adm_no'])?> <?= $f['cls_name'] ? '('.e($f['cls_name']).')' : '' ?></small>
                        </td>
                        <td><?=e($mth)?></td>
                        <td style="color:#16a34a;"><b>Rs. <?=number_format($amt, 2)?></b></td>
                        <td><?=e($f['due_date'] ?? 'N/A')?></td>
                        <td><span class="badge-st badge-paid">PAID</span></td>
                        <td>
                            <button onclick="printVoucher('<?=e($rcpt)?>', '<?=e($f['std_name'])?>', '<?=e($f['adm_no'])?>', '<?=e($f['cls_name'])?>', '<?=e($mth)?>', '<?=$amt?>', '<?=$amt?>', '0', '<?=e($f['due_date'] ?? '')?>')" class="btn-print">📄 Print Receipt</button>
                            
                            <form method="POST" action="index.php?page=fees" style="display:inline;" onsubmit="return confirm('Delete this paid fee entry?');">
                                <input type="hidden" name="voucher_id" value="<?=$f['id']?>">
                                <button type="submit" name="delete_voucher" class="btn-delete">🗑️ Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding:20px; color:#888;">No paid fee records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal 1: Printable Voucher/Receipt -->
<div id="voucherModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; width:90%; max-width:500px; padding:25px; border-radius:8px;" id="printableVoucher">
        <div style="text-align:center; border-bottom:2px solid #333; padding-bottom:10px; margin-bottom:15px;">
            <h2 style="margin:0;">BEACON SCHOOL SYSTEM</h2>
            <small>FEE VOUCHER & RECEIPT</small>
        </div>
        <table width="100%" style="font-size:14px; margin-bottom:15px;">
            <tr><td><b>Receipt/Voucher #:</b> <span id="v_rcpt"></span></td><td align="right"><b>Date:</b> <?=date('d-M-Y')?></td></tr>
            <tr><td><b>Student:</b> <span id="v_std"></span></td><td align="right"><b>Adm No:</b> <span id="v_adm"></span></td></tr>
            <tr><td><b>Class:</b> <span id="v_cls"></span></td><td align="right"><b>Month:</b> <span id="v_month"></span></td></tr>
            <tr><td><b>Due Date:</b> <span id="v_due"></span></td><td></td></tr>
        </table>
        <table width="100%" border="1" cellpadding="8" style="border-collapse:collapse; margin-bottom:15px; font-size:14px;">
            <tr style="background:#f2f2f2;"><th>Description</th><th align="right">Amount (Rs.)</th></tr>
            <tr><td>Monthly Tuition Fee</td><td align="right" id="v_amt"></td></tr>
            <tr><td>Paid Amount</td><td align="right" id="v_paid" style="color:green;"></td></tr>
            <tr style="font-weight:bold; background:#fafafa;"><td>Remaining Balance</td><td align="right" id="v_bal" style="color:red;"></td></tr>
        </table>
        <div style="display:flex; justify-content:space-between; margin-top:30px; font-size:12px;">
            <div>___________________<br>Accounts Officer</div>
            <div>___________________<br>Parent/Student Sign</div>
        </div>
        <div style="text-align:center; margin-top:20px;" class="no-print">
            <button onclick="window.print()" style="background:#16a34a; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">Print Receipt</button>
            <button onclick="closeModal('voucherModal')" style="background:#64748b; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold; margin-left:10px;">Close</button>
        </div>
    </div>
</div>

<!-- Modal 2: Collect Fee Payment -->
<div id="payModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; width:90%; max-width:400px; padding:25px; border-radius:8px;">
        <h3 style="margin-top:0; color:#16a34a;">Collect Fee Payment</h3>
        <p style="font-size:14px; margin-bottom:15px;">Student: <b id="pay_student_name"></b></p>
        
        <form method="POST" action="index.php?page=fees">
            <input type="hidden" name="voucher_id" id="pay_voucher_id">
            
            <div class="form-group">
                <label>Receiving Amount (Rs.)</label>
                <input type="number" name="receiving_amount" id="pay_amount_input" class="form-control" required style="font-size:16px; font-weight:bold;">
            </div>

            <button type="submit" name="pay_fee_now" class="btn-submit" style="background:#16a34a;">✓ Save Payment</button>
            <button type="button" onclick="closeModal('payModal')" class="form-control" style="margin-top:10px; background:#e2e8f0; border:none; cursor:pointer; font-weight:bold;">Cancel</button>
        </form>
    </div>
</div>

<script>
function switchTab(type) {
    var vForm = document.getElementById('formVoucher');
    var dForm = document.getElementById('formDirect');
    var vBtn = document.getElementById('tabVoucherBtn');
    var dBtn = document.getElementById('tabDirectBtn');

    if (type === 'voucher') {
        vForm.style.display = 'block';
        dForm.style.display = 'none';
        vBtn.className = 'tab-btn active';
        dBtn.className = 'tab-btn';
    } else {
        vForm.style.display = 'none';
        dForm.style.display = 'block';
        vBtn.className = 'tab-btn';
        dBtn.className = 'tab-btn active-green';
    }
}

function printVoucher(rcpt, std, adm, cls, month, amt, paid, bal, due) {
    document.getElementById('v_rcpt').innerText = rcpt;
    document.getElementById('v_std').innerText = std;
    document.getElementById('v_adm').innerText = adm;
    document.getElementById('v_cls').innerText = cls;
    document.getElementById('v_month').innerText = month;
    document.getElementById('v_amt').innerText = parseFloat(amt).toLocaleString('en-PK', {minimumFractionDigits:2});
    document.getElementById('v_paid').innerText = parseFloat(paid).toLocaleString('en-PK', {minimumFractionDigits:2});
    document.getElementById('v_bal').innerText = parseFloat(bal).toLocaleString('en-PK', {minimumFractionDigits:2});
    document.getElementById('v_due').innerText = due;

    document.getElementById('voucherModal').style.display = 'flex';
}

function openPayModal(vId, stdName, balAmt) {
    document.getElementById('pay_voucher_id').value = vId;
    document.getElementById('pay_student_name').innerText = stdName;
    document.getElementById('pay_amount_input').value = balAmt;

    document.getElementById('payModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>