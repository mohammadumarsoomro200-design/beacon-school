<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_login();

if (!isset($db)) {
    if (isset($conn)) { $db = $conn; }
    elseif (isset($pdo)) { $db = $pdo; }
    elseif (function_exists('db')) { $db = db(); }
}

if (!isset(user()['role']) || strtolower(user()['role']) !== 'admin') {
    die("<div style='padding:20px; color:red;'><h2>Access Denied</h2></div>");
}

$page = 'Revenue Dashboard';

// Metrics Initialization
$total_students = 0;
$total_fees = 0;
$todays_collection = 0;
$net_collection = 0;
$net_fees = 0;
$outstanding_balance = 0;
$exempt_amount = 0;
$discount_amount = 0;
$collection_discount = 0;
$recovery_percentage = 0;

$todays_students_list = [];
$monthly_data = [];
$monthly_students_breakdown = []; // Holds list of students paid per month

try {
    if ($db) {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        // 1. Total Active Students Count
        if (in_array('students', $tables)) {
            $stmt = $db->query("SELECT COUNT(*) FROM students");
            $total_students = $stmt->fetchColumn() ?: 0;
        }

        // 2. Fees Data & Calculations
        if (in_array('fees', $tables)) {
            $f_cols = $db->query("DESCRIBE `fees`")->fetchAll(PDO::FETCH_COLUMN);

            $paid_col = in_array('paid_amount', $f_cols) ? 'paid_amount' : (in_array('paid', $f_cols) ? 'paid' : null);
            $amt_col = in_array('amount', $f_cols) ? 'amount' : (in_array('total_amount', $f_cols) ? 'total_amount' : (in_array('fee', $f_cols) ? 'fee' : null));
            $date_col = in_array('created_at', $f_cols) ? 'created_at' : (in_array('date', $f_cols) ? 'date' : null);

            // Net Collection (Total Fees Received)
            if ($paid_col) {
                $stmt = $db->query("SELECT SUM(`$paid_col`) FROM fees");
                $net_collection = floatval($stmt->fetchColumn() ?: 0);

                // Today's Collection & Students List
                if ($date_col) {
                    $stmt = $db->query("SELECT SUM(`$paid_col`) FROM fees WHERE DATE(`$date_col`) = CURDATE()");
                    $todays_collection = floatval($stmt->fetchColumn() ?: 0);

                    if (in_array('students', $tables)) {
                        $s_cols = $db->query("DESCRIBE `students`")->fetchAll(PDO::FETCH_COLUMN);
                        $s_name = in_array('name', $s_cols) ? 'name' : (in_array('student_name', $s_cols) ? 'student_name' : 'id');

                        $stmt_today = $db->query("
                            SELECT f.*, s.`$s_name` as student_name 
                            FROM fees f 
                            LEFT JOIN students s ON f.student_id = s.id 
                            WHERE DATE(f.`$date_col`) = CURDATE() AND f.`$paid_col` > 0 
                            ORDER BY f.id DESC
                        ");
                        $todays_students_list = $stmt_today->fetchAll();

                        // Fetch Monthly Paid Students Breakdown
                        $stmt_all_paid = $db->query("
                            SELECT f.*, DATE_FORMAT(f.`$date_col`, '%b-%y') as month_key, DATE_FORMAT(f.`$date_col`, '%Y-%m') as y_m, s.`$s_name` as student_name 
                            FROM fees f 
                            LEFT JOIN students s ON f.student_id = s.id 
                            WHERE f.`$paid_col` > 0 
                            ORDER BY f.`$date_col` DESC
                        ");
                        $all_payments = $stmt_all_paid->fetchAll();

                        foreach ($all_payments as $pm) {
                            $m_key = $pm['month_key'];
                            if (!isset($monthly_students_breakdown[$m_key])) {
                                $monthly_students_breakdown[$m_key] = [];
                            }
                            $monthly_students_breakdown[$m_key][] = $pm;
                        }
                    }
                }
            }

            // Total Fees Amount
            if ($amt_col) {
                $stmt = $db->query("SELECT SUM(`$amt_col`) FROM fees");
                $total_fees = floatval($stmt->fetchColumn() ?: 0);
            }
        }

        // Fallback Total Fees from Students table if vouchers total is 0
        if ($total_fees == 0 && in_array('students', $tables)) {
            $s_cols = $db->query("DESCRIBE `students`")->fetchAll(PDO::FETCH_COLUMN);
            $s_fee = in_array('monthly_fee', $s_cols) ? 'monthly_fee' : (in_array('fee', $s_cols) ? 'fee' : null);
            if ($s_fee) {
                $stmt = $db->query("SELECT SUM(`$s_fee`) FROM students");
                $total_fees = floatval($stmt->fetchColumn() ?: 0);
            }
        }

        // Net Fees and Outstanding Calculations
        $net_fees = max(0, $total_fees - $discount_amount - $exempt_amount);
        $outstanding_balance = max(0, $net_fees - $net_collection);

        // Recovery Percentage
        if ($net_fees > 0) {
            $recovery_percentage = round(($net_collection / $net_fees) * 100, 2);
        }

        // Monthly Breakdown Summary
        if (in_array('fees', $tables) && isset($date_col) && isset($paid_col)) {
            $monthly_stmt = $db->query("SELECT DATE_FORMAT(`$date_col`, '%b-%y') as month, SUM(`$paid_col`) as total FROM fees WHERE `$paid_col` > 0 GROUP BY YEAR(`$date_col`), MONTH(`$date_col`) ORDER BY `$date_col` DESC LIMIT 7");
            $monthly_data = $monthly_stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<!-- UI & Modal Styling -->
<style>
    .rev-wrapper { max-width: 520px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; padding: 15px; border-radius: 12px; }
    .session-bar { background: #1e3a8a; color: white; text-align: center; padding: 10px; border-radius: 8px; font-weight: bold; font-size: 16px; margin-bottom: 12px; }
    .month-dropdown { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: bold; color: #1e3a8a; margin-bottom: 12px; }
    
    .top-card { background: linear-gradient(135deg, #1e3a8a, #0f172a); color: white; padding: 15px; border-radius: 10px; display: flex; justify-content: space-between; margin-bottom: 12px; }
    .top-card h4 { margin: 0; font-size: 12px; text-transform: uppercase; opacity: 0.8; }
    .top-card .val { font-size: 20px; font-weight: bold; margin-top: 4px; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
    .info-box { background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .info-box.clickable { cursor: pointer; transition: all 0.2s; }
    .info-box.clickable:hover { background-color: #f1f5f9; border-color: #cbd5e1; }
    .info-box .title { font-size: 11px; color: #64748b; font-weight: bold; }
    .info-box .num { font-size: 15px; font-weight: bold; color: #0f172a; text-align: right; }
    .info-box .subnum { font-size: 12px; color: #ef4444; font-weight: bold; }

    .btn-card { padding: 12px; border-radius: 8px; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .btn-net { background: #1e3a8a; }
    .btn-out { background: #f59e0b; }

    .bottom-section { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .monthly-box, .gauge-box { background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .month-row { display: flex; justify-content: space-between; padding: 7px 5px; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .month-row:hover { background-color: #f8fafc; }
    .month-row.highlight { color: #10b981; }

    /* Circle Progress Gauge */
    .gauge-circle { width: 90px; height: 90px; border-radius: 50%; background: conic-gradient(#10b981 <?= $recovery_percentage ?>%, #e2e8f0 0); display: flex; align-items: center; justify-content: center; margin: 10px auto; }
    .gauge-inner { width: 72px; height: 72px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .gauge-inner .pct { font-size: 13px; font-weight: bold; color: #10b981; }
    .gauge-inner .lbl { font-size: 9px; color: #64748b; }

    /* Modal Styling */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
    .modal-content { background: white; width: 90%; max-width: 450px; border-radius: 12px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
    .modal-header h3 { margin: 0; font-size: 16px; color: #1e3a8a; }
    .close-btn { cursor: pointer; font-size: 20px; font-weight: bold; color: #64748b; border: none; background: none; }
    .modal-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modal-table th, .modal-table td { padding: 8px; text-align: left; border-bottom: 1px solid #f1f5f9; }
    .modal-table th { background: #f8fafc; color: #475569; }
</style>

<div class="rev-wrapper">
    <!-- Session Bar -->
    <div class="session-bar">&lt; Session 26-27 &gt;</div>
    
    <!-- Month Select -->
    <select class="month-dropdown">
        <option><?= date('M-y') ?></option>
    </select>

    <!-- Top Total Outstanding Banner -->
    <div class="top-card">
        <div>
            <h4>📈 Total Outstanding</h4>
            <div class="val">Rs <?= number_format($outstanding_balance) ?></div>
        </div>
        <div style="text-align: right;">
            <h4>Total Fees</h4>
            <div class="val">Rs <?= number_format($total_fees) ?></div>
        </div>
    </div>

    <!-- Info Grid 1 -->
    <div class="grid-2">
        <div class="info-box">
            <div>👥 <span class="title">Total Students</span></div>
            <div class="num"><?= $total_students ?></div>
        </div>
        
        <!-- Today's Collection Clickable Box -->
        <div class="info-box clickable" onclick="openTodayModal()">
            <div>⏰ <span class="title">Today's Collection 🔍</span></div>
            <div class="num" style="color: #10b981;">Rs <?= number_format($todays_collection) ?></div>
        </div>
    </div>

    <!-- Info Grid 2 (Exempt & Net) -->
    <div class="grid-2">
        <div class="info-box">
            <div>👛 <span class="title" style="color:#ef4444;">Exempt Amount</span></div>
            <div>
                <div class="subnum">0</div>
                <div class="subnum">Rs <?= number_format($exempt_amount) ?></div>
            </div>
        </div>
        <div class="info-box">
            <div>💵 <span class="title" style="color:#10b981;">Net Collection</span></div>
            <div>
                <div class="num" style="color:#10b981;"><?= number_format($net_collection) ?></div>
            </div>
        </div>
    </div>

    <!-- Info Grid 3 (Discounts) -->
    <div class="grid-2">
        <div class="info-box">
            <div>🏷️ <span class="title">Discount Amount</span></div>
            <div class="num">0</div>
        </div>
        <div class="info-box">
            <div>🏷️ <span class="title" style="color:#ef4444;">Collection Discount</span></div>
            <div class="subnum"><?= number_format($collection_discount) ?></div>
        </div>
    </div>

    <!-- Net Fees & Outstanding Buttons -->
    <div class="grid-2">
        <div class="btn-card btn-net">
            <span>Net Fees</span>
            <span>Rs <?= number_format($net_fees) ?></span>
        </div>
        <div class="btn-card btn-out">
            <span>Outstanding</span>
            <span>Rs <?= number_format($outstanding_balance) ?></span>
        </div>
    </div>

    <!-- Bottom Breakdown & Gauge Chart -->
    <div class="bottom-section">
        <!-- Monthly Collection Table with Click Feature -->
        <div class="monthly-box">
            <div class="month-row highlight" onclick="openMonthModal('<?= date('M-y') ?>')">
                <span>Current Month 🔍</span>
                <span><?= number_format($todays_collection) ?></span>
            </div>
            <?php if (!empty($monthly_data)): ?>
                <?php foreach ($monthly_data as $row): ?>
                    <div class="month-row" onclick="openMonthModal('<?= $row['month'] ?>')">
                        <span>↗ <?= $row['month'] ?> 🔍</span>
                        <span><?= number_format($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="month-row" onclick="openMonthModal('<?= date('M-y') ?>')">
                    <span>↗ <?= date('M-y') ?> 🔍</span>
                    <span><?= number_format($net_collection) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Gauge Chart -->
        <div class="gauge-box" style="text-align: center;">
            <div class="gauge-circle">
                <div class="gauge-inner">
                    <span class="pct"><?= $recovery_percentage ?> %</span>
                    <span class="lbl">Recovery</span>
                </div>
            </div>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin:8px 0;">
            <div style="display:flex; justify-content:space-between; font-size:11px; text-align:left; color:#64748b;">
                <div>Fine Amount</div>
                <div style="font-weight:bold; color:#10b981; margin-left:auto;">0</div>
            </div>
            <div style="display:flex; justify-style:space-between; font-size:11px; text-align:left; color:#64748b; margin-top:4px;">
                <div>Security Payable</div>
                <div style="font-weight:bold; margin-left:auto;">0</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Popup for Today's Paid Students -->
<div id="todayModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📋 Today's Fee Payments Details</h3>
            <button class="close-btn" onclick="closeModal('todayModal')">&times;</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($todays_students_list)): ?>
                        <?php foreach ($todays_students_list as $st): ?>
                            <tr>
                                <td><b><?= e($st['student_name'] ?? 'Student') ?></b></td>
                                <td style="color:#10b981; font-weight:bold;">Rs <?= number_format($st['paid_amount'] ?? $st['paid'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" style="text-align:center; color:#94a3b8; padding: 15px;">Aaj koi fee collection record nahi mila.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Popup for Specific Month Paid Students -->
<div id="monthModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📅 <span id="monthTitle"></span> Fee Collection</h3>
            <button class="close-btn" onclick="closeModal('monthModal')">&times;</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Paid Amount</th>
                    </tr>
                </thead>
                <tbody id="monthStudentsTableBody">
                    <!-- Dynamic Data loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript to Handle Modals and Data Dynamic Binding -->
<script>
const monthlyDataJS = <?= json_encode($monthly_students_breakdown) ?>;

function openTodayModal() {
    document.getElementById('todayModal').style.display = 'flex';
}

function openMonthModal(monthKey) {
    document.getElementById('monthTitle').innerText = monthKey;
    const tbody = document.getElementById('monthStudentsTableBody');
    tbody.innerHTML = '';

    if (monthlyDataJS[monthKey] && monthlyDataJS[monthKey].length > 0) {
        monthlyDataJS[monthKey].forEach(st => {
            const amount = st.paid_amount || st.paid || 0;
            const name = st.student_name || 'Student';
            const row = `<tr>
                <td><b>${name}</b></td>
                <td style="color:#10b981; font-weight:bold;">Rs ${Number(amount).toLocaleString()}</td>
            </tr>`;
            tbody.innerHTML += row;
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="2" style="text-align:center; color:#94a3b8; padding: 15px;">Is maheene ki koi collection details nahi milin.</td></tr>`;
    }

    document.getElementById('monthModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = "none";
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>