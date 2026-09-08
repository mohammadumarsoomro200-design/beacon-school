<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';

if (function_exists('require_login')) {
    require_login();
}

$db = null;
if (isset($conn) && $conn) { $db = $conn; }
elseif (isset($pdo) && $pdo) { $db = $pdo; }
elseif (function_exists('db')) { $db = db(); }

if (!$db) {
    $db = mysqli_connect('localhost', 'root', '', 'beacon_school');
}

$user_role = '';
if (function_exists('user') && isset(user()['role'])) {
    $user_role = user()['role'];
} elseif (isset($_SESSION['user']['role'])) {
    $user_role = $_SESSION['user']['role'];
}

if ($user_role !== '' && strtolower($user_role) !== 'admin') {
    die("<div style='padding:20px; color:red;'><h2>Access Denied</h2></div>");
}

$page = 'Revenue Dashboard';

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function dbQuery($db, $sql) {
    if ($db instanceof PDO) {
        return $db->query($sql);
    } elseif ($db instanceof mysqli) {
        return mysqli_query($db, $sql);
    }
    return false;
}

function dbFetchAll($stmt) {
    if ($stmt instanceof PDOStatement) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($stmt instanceof mysqli_result) {
        return mysqli_fetch_all($stmt, MYSQLI_ASSOC);
    }
    return [];
}

function dbFetchColumn($stmt) {
    if ($stmt instanceof PDOStatement) {
        return $stmt->fetchColumn();
    } elseif ($stmt instanceof mysqli_result) {
        $row = mysqli_fetch_row($stmt);
        return $row ? $row[0] : null;
    }
    return null;
}

// Selected Month Filter
$selected_month = $_GET['month'] ?? 'Sep-26';

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
$unpaid_students_list = [];
$monthly_data = [];
$monthly_students_breakdown = [];
$all_months = [];

try {
    if ($db) {
        // Fetch All Available Months for Dropdown
        $m_stmt = dbQuery($db, "SELECT DISTINCT COALESCE(month, DATE_FORMAT(due_date, '%b-%y')) as m_name FROM fees WHERE month IS NOT NULL OR due_date IS NOT NULL");
        $m_rows = dbFetchAll($m_stmt);
        foreach ($m_rows as $mr) {
            if ($mr['m_name']) $all_months[] = $mr['m_name'];
        }
        if (empty($all_months)) { $all_months[] = 'Sep-26'; }

        // 1. Total Active Students Count
        $stmt = dbQuery($db, "SELECT COUNT(*) FROM students");
        $total_students = dbFetchColumn($stmt) ?: 0;

        // 2. Total Fees Generated
        $stmt_total = dbQuery($db, "SELECT SUM(amount) FROM fees");
        $total_fees = floatval(dbFetchColumn($stmt_total) ?: 0);

        // 3. Net Collection (ONLY paid records where status='paid' OR paid_amount > 0)
        $stmt_paid = dbQuery($db, "
            SELECT SUM(CASE WHEN paid_amount > 0 THEN paid_amount ELSE amount END) 
            FROM fees 
            WHERE LOWER(status) = 'paid' OR paid_amount > 0
        ");
        $net_collection = floatval(dbFetchColumn($stmt_paid) ?: 0);

        // 4. Net Fees & Outstanding Balance
        $net_fees = $total_fees - $discount_amount - $exempt_amount;
        $outstanding_balance = max(0, $net_fees - $net_collection);

        // 5. Recovery Percentage
        if ($net_fees > 0) {
            $recovery_percentage = round(($net_collection / $net_fees) * 100, 1);
        }

        // 6. Today's Collection List & Sum
        $stmt_today_sum = dbQuery($db, "
            SELECT SUM(CASE WHEN paid_amount > 0 THEN paid_amount ELSE amount END) 
            FROM fees 
            WHERE (LOWER(status) = 'paid' OR paid_amount > 0) 
            AND (DATE(payment_date) = CURDATE() OR DATE(due_date) = CURDATE())
        ");
        $todays_collection = floatval(dbFetchColumn($stmt_today_sum) ?: 0);

        $stmt_today_list = dbQuery($db, "
            SELECT f.*, s.name as student_name 
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            WHERE (LOWER(f.status) = 'paid' OR f.paid_amount > 0) 
            AND (DATE(f.payment_date) = CURDATE() OR DATE(f.due_date) = CURDATE())
            ORDER BY f.id DESC
        ");
        $todays_students_list = dbFetchAll($stmt_today_list);

        // 7. Unpaid Students (Outstanding Details List)
        $stmt_unpaid = dbQuery($db, "
            SELECT f.*, s.name as student_name 
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            WHERE LOWER(f.status) = 'unpaid' AND (f.paid_amount = 0 OR f.paid_amount IS NULL)
            ORDER BY f.id DESC
        ");
        $unpaid_students_list = dbFetchAll($stmt_unpaid);

        // 8. Monthly Paid Breakdown Data
        $stmt_all_paid = dbQuery($db, "
            SELECT f.*, 
                   COALESCE(f.month, DATE_FORMAT(f.due_date, '%M %Y')) as month_key, 
                   s.name as student_name,
                   (CASE WHEN f.paid_amount > 0 THEN f.paid_amount ELSE f.amount END) as effective_paid
            FROM fees f 
            LEFT JOIN students s ON f.student_id = s.id 
            WHERE LOWER(f.status) = 'paid' OR f.paid_amount > 0
            ORDER BY f.id DESC
        ");
        $all_payments = dbFetchAll($stmt_all_paid);

        foreach ($all_payments as $pm) {
            $m_key = $pm['month_key'];
            if (!isset($monthly_students_breakdown[$m_key])) {
                $monthly_students_breakdown[$m_key] = [];
            }
            $monthly_students_breakdown[$m_key][] = $pm;
        }

        // Summary for Left Bottom Monthly Box
        $stmt_monthly = dbQuery($db, "
            SELECT COALESCE(month, DATE_FORMAT(due_date, '%M %Y')) as month, 
                   SUM(CASE WHEN paid_amount > 0 THEN paid_amount ELSE amount END) as total 
            FROM fees 
            WHERE LOWER(status) = 'paid' OR paid_amount > 0 
            GROUP BY month 
            ORDER BY id DESC LIMIT 7
        ");
        $monthly_data = dbFetchAll($stmt_monthly);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/header.php';
?>

<style>
    .rev-wrapper { max-width: 520px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; padding: 15px; border-radius: 12px; }
    .session-bar { background: #1e3a8a; color: white; text-align: center; padding: 10px; border-radius: 8px; font-weight: bold; font-size: 16px; margin-bottom: 12px; }
    .month-dropdown { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: bold; color: #1e3a8a; margin-bottom: 12px; background: white; cursor: pointer; }
    
    .top-card { background: linear-gradient(135deg, #1e3a8a, #0f172a); color: white; padding: 15px; border-radius: 10px; display: flex; justify-content: space-between; margin-bottom: 12px; cursor: pointer; }
    .top-card h4 { margin: 0; font-size: 11px; text-transform: uppercase; opacity: 0.8; }
    .top-card .val { font-size: 20px; font-weight: bold; margin-top: 4px; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
    .info-box { background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; }
    .info-box:hover { background-color: #f1f5f9; border-color: #cbd5e1; }
    .info-box .title { font-size: 11px; color: #64748b; font-weight: bold; }
    .info-box .num { font-size: 15px; font-weight: bold; color: #0f172a; text-align: right; }
    .info-box .subnum { font-size: 12px; color: #ef4444; font-weight: bold; }

    .btn-card { padding: 12px; border-radius: 8px; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; cursor: pointer; }
    .btn-net { background: #1e3a8a; }
    .btn-out { background: #f59e0b; }

    .bottom-section { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .monthly-box, .gauge-box { background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .month-row { display: flex; justify-content: space-between; padding: 7px 5px; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
    .month-row:hover { background-color: #f8fafc; }

    .gauge-circle { width: 90px; height: 90px; border-radius: 50%; background: conic-gradient(#10b981 <?= $recovery_percentage ?>%, #e2e8f0 0); display: flex; align-items: center; justify-content: center; margin: 10px auto; cursor: pointer; }
    .gauge-inner { width: 72px; height: 72px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .gauge-inner .pct { font-size: 13px; font-weight: bold; color: #10b981; }
    .gauge-inner .lbl { font-size: 9px; color: #64748b; }

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
    <div class="session-bar">&lt; Session 26-27 &gt;</div>
    
    <!-- Filter Dropdown -->
    <select class="month-dropdown" onchange="location.href='?month='+this.value;">
        <?php foreach ($all_months as $m): ?>
            <option value="<?= e($m) ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
    </select>

    <!-- Top Total Outstanding Card -->
    <div class="top-card" onclick="openModal('unpaidModal')">
        <div>
            <h4>☑ TOTAL OUTSTANDING 🔍</h4>
            <div class="val">Rs <?= number_format($outstanding_balance) ?></div>
        </div>
        <div style="text-align: right;">
            <h4>TOTAL FEES</h4>
            <div class="val">Rs <?= number_format($total_fees) ?></div>
        </div>
    </div>

    <!-- Info Grid 1 -->
    <div class="grid-2">
        <div class="info-box" onclick="openModal('infoModal', 'Total Students', 'Total Active Registered Students: <?= $total_students ?>')">
            <div>👥 <span class="title">Total Students</span></div>
            <div class="num"><?= $total_students ?></div>
        </div>
        
        <div class="info-box" onclick="openModal('todayModal')">
            <div>⏰ <span class="title">Today's Collection 🔍</span></div>
            <div class="num" style="color: #10b981;">Rs <?= number_format($todays_collection) ?></div>
        </div>
    </div>

    <!-- Info Grid 2 -->
    <div class="grid-2">
        <div class="info-box" onclick="openModal('infoModal', 'Exempt Amount', 'No exempted amount records found in current session.')">
            <div>👛 <span class="title" style="color:#ef4444;">Exempt Amount</span></div>
            <div>
                <div class="subnum">0</div>
                <div class="subnum">Rs <?= number_format($exempt_amount) ?></div>
            </div>
        </div>
        <div class="info-box" onclick="openMonthModal('<?= e($selected_month) ?>')">
            <div>💵 <span class="title" style="color:#10b981;">Net Collection 🔍</span></div>
            <div>
                <div class="num" style="color:#10b981;"><?= number_format($net_collection) ?></div>
            </div>
        </div>
    </div>

    <!-- Info Grid 3 -->
    <div class="grid-2">
        <div class="info-box" onclick="openModal('infoModal', 'Discount Amount', 'Total Standard Discount Given: Rs <?= number_format($discount_amount) ?>')">
            <div>🏷️ <span class="title">Discount Amount</span></div>
            <div class="num">0</div>
        </div>
        <div class="info-box" onclick="openModal('infoModal', 'Collection Discount', 'Total Collection Discount Allowed: Rs <?= number_format($collection_discount) ?>')">
            <div>🏷️ <span class="title" style="color:#ef4444;">Collection Discount</span></div>
            <div class="subnum"><?= number_format($collection_discount) ?></div>
        </div>
    </div>

    <!-- Net Fees & Outstanding Buttons -->
    <div class="grid-2">
        <div class="btn-card btn-net" onclick="openModal('infoModal', 'Net Fees Breakdown', 'Total Net Printable Fees = Total Fees (Rs <?= number_format($total_fees) ?>) - Discounts (Rs 0) = Rs <?= number_format($net_fees) ?>')">
            <span>Net Fees</span>
            <span>Rs <?= number_format($net_fees) ?></span>
        </div>
        <div class="btn-card btn-out" onclick="openModal('unpaidModal')">
            <span>Outstanding 🔍</span>
            <span>Rs <?= number_format($outstanding_balance) ?></span>
        </div>
    </div>

    <!-- Bottom Breakdown & Gauge -->
    <div class="bottom-section">
        <div class="monthly-box">
            <?php if (!empty($monthly_data)): ?>
                <?php foreach ($monthly_data as $row): ?>
                    <div class="month-row" onclick="openMonthModal('<?= e($row['month']) ?>')">
                        <span>↗ <?= e($row['month']) ?> 🔍</span>
                        <span><?= number_format($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="month-row" onclick="openMonthModal('September 2026')">
                    <span>↗ September 2026 🔍</span>
                    <span><?= number_format($net_collection) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="gauge-box" style="text-align: center;">
            <div class="gauge-circle" onclick="openModal('infoModal', 'Recovery Rate', 'Current Fee Recovery Percentage is <?= $recovery_percentage ?>% calculated from Total Collected vs Total Net Fees.')">
                <div class="gauge-inner">
                    <span class="pct"><?= $recovery_percentage ?> %</span>
                    <span class="lbl">Recovery</span>
                </div>
            </div>
            <hr style="border:0; border-top:1px solid #f1f5f9; margin:8px 0;">
            <div style="display:flex; justify-content:space-between; font-size:11px; color:#64748b; cursor:pointer;" onclick="openModal('infoModal', 'Fine Amount', 'Total Fine Collected: Rs 0')">
                <div>Fine Amount</div>
                <div style="font-weight:bold; color:#10b981; margin-left:auto;">0</div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:11px; color:#64748b; margin-top:4px; cursor:pointer;" onclick="openModal('infoModal', 'Security Payable', 'Total Security Payable: Rs 0')">
                <div>Security Payable</div>
                <div style="font-weight:bold; margin-left:auto;">0</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Today's Paid List -->
<div id="todayModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📋 Today's Fee Payments</h3>
            <button class="close-btn" onclick="closeModal('todayModal')">&times;</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="modal-table">
                <thead><tr><th>Student Name</th><th>Paid</th></tr></thead>
                <tbody>
                    <?php if (!empty($todays_students_list)): ?>
                        <?php foreach ($todays_students_list as $st): ?>
                            <tr>
                                <td><b><?= e($st['student_name'] ?? 'Student ID: ' . $st['student_id']) ?></b></td>
                                <td style="color:#10b981; font-weight:bold;">Rs <?= number_format($st['paid_amount'] > 0 ? $st['paid_amount'] : $st['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align:center; color:#94a3b8; padding: 15px;">Aaj koi fee collection nahi hui.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 2: Unpaid / Outstanding List -->
<div id="unpaidModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color:#ef4444;">⚠️ Outstanding Fee Students</h3>
            <button class="close-btn" onclick="closeModal('unpaidModal')">&times;</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="modal-table">
                <thead><tr><th>Student Name</th><th>Due Amount</th></tr></thead>
                <tbody>
                    <?php if (!empty($unpaid_students_list)): ?>
                        <?php foreach ($unpaid_students_list as $st): ?>
                            <tr>
                                <td><b><?= e($st['student_name'] ?? 'Student ID: ' . $st['student_id']) ?></b></td>
                                <td style="color:#ef4444; font-weight:bold;">Rs <?= number_format($st['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align:center; color:#10b981; padding: 15px;">Koi outstanding balance baaki nahi hai.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 3: Monthly Breakdown -->
<div id="monthModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📅 <span id="monthTitle"></span> Fee Collection</h3>
            <button class="close-btn" onclick="closeModal('monthModal')">&times;</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <table class="modal-table">
                <thead><tr><th>Student Name</th><th>Paid Amount</th></tr></thead>
                <tbody id="monthStudentsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 4: Generic Info Box -->
<div id="infoModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="infoTitle">Details</h3>
            <button class="close-btn" onclick="closeModal('infoModal')">&times;</button>
        </div>
        <div id="infoBody" style="font-size:14px; color:#334155; padding: 10px 0;"></div>
    </div>
</div>

<script>
const monthlyDataJS = <?= json_encode($monthly_students_breakdown) ?>;

function openModal(id, title = '', body = '') {
    if (title) document.getElementById('infoTitle').innerText = title;
    if (body) document.getElementById('infoBody').innerText = body;
    document.getElementById(id).style.display = 'flex';
}

function openMonthModal(monthKey) {
    document.getElementById('monthTitle').innerText = monthKey;
    const tbody = document.getElementById('monthStudentsTableBody');
    tbody.innerHTML = '';

    if (monthlyDataJS[monthKey] && monthlyDataJS[monthKey].length > 0) {
        monthlyDataJS[monthKey].forEach(st => {
            const amount = st.effective_paid || st.amount || 0;
            const name = st.student_name || ('Student ID: ' + st.student_id);
            const row = `<tr>
                <td><b>${name}</b></td>
                <td style="color:#10b981; font-weight:bold;">Rs ${Number(amount).toLocaleString()}</td>
            </tr>`;
            tbody.innerHTML += row;
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="2" style="text-align:center; color:#94a3b8; padding: 15px;">Is maheene ki koi paid collection details nahi milin.</td></tr>`;
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