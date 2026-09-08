<?php 
require_login(['admin','teacher']); 
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    // Create Exam Logic
    if (($_POST['mode'] ?? '') === 'exam') { 
        $s = $pdo->prepare('INSERT INTO exams(exam_name,class_id,exam_date,academic_year) VALUES(?,?,?,?)');
        $s->execute([
            trim($_POST['exam_name']),
            (int)$_POST['class_id'],
            ($_POST['exam_date'] ?: null),
            date('Y').'-'.(date('Y')+1)
        ]);
        flash('success','Exam created.');
        redirect('index.php?page=results'); 
    } 

    // Save Result Logic
    $s = $pdo->prepare('INSERT INTO results(exam_id,student_id,subject_id,marks,total_marks,remarks) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE marks=VALUES(marks),total_marks=VALUES(total_marks),remarks=VALUES(remarks)');
    $s->execute([
        (int)$_POST['exam_id'],
        (int)$_POST['student_id'],
        (int)$_POST['subject_id'],
        (float)$_POST['marks'],
        (float)$_POST['total_marks'],
        trim($_POST['remarks'])
    ]);
    flash('success','Result saved.');
    redirect('index.php?page=results');
}

// Class Filter Logic
$selected_class_id = (int)($_GET['class_id'] ?? 0);

// Fetch Dropdown Data
$exams = $pdo->query('SELECT e.*, c.class_name, c.section FROM exams e LEFT JOIN classes c ON c.id=e.class_id ORDER BY e.id DESC')->fetchAll();
$classes = $pdo->query('SELECT * FROM classes ORDER BY class_name,section')->fetchAll();
$subjects = $pdo->query('SELECT * FROM subjects ORDER BY subject_name')->fetchAll();

// Fetch ALL active students with joined class information
$all_students = $pdo->query("SELECT s.id, s.admission_no, s.student_name, s.class_id, c.class_name, c.section 
                             FROM students s 
                             LEFT JOIN classes c ON c.id = s.class_id 
                             ORDER BY s.student_name ASC")->fetchAll();

// Fetch Raw Results
$sql = 'SELECT r.*, e.exam_name, s.student_name, s.admission_no, c.class_name, c.section, sub.subject_name 
        FROM results r 
        JOIN exams e ON e.id=r.exam_id 
        JOIN students s ON s.id=r.student_id 
        LEFT JOIN classes c ON c.id=e.class_id
        JOIN subjects sub ON sub.id=r.subject_id';

if ($selected_class_id > 0) {
    $sql .= ' WHERE e.class_id = ' . $selected_class_id;
}
$sql .= ' ORDER BY r.id DESC';
$raw_results = $pdo->query($sql)->fetchAll();

// Group Results by Exam and Student
$grouped_results = [];
foreach ($raw_results as $row) {
    $key = $row['exam_id'] . '_' . $row['student_id'];
    if (!isset($grouped_results[$key])) {
        $grouped_results[$key] = [
            'exam_name' => $row['exam_name'],
            'student_name' => $row['student_name'],
            'admission_no' => $row['admission_no'],
            'class_name' => ($row['class_name'] ?? '') . ' ' . ($row['section'] ?? ''),
            'obtained_total' => 0,
            'max_total' => 0,
            'subjects' => []
        ];
    }
    $grouped_results[$key]['subjects'][] = [
        'name' => $row['subject_name'],
        'marks' => $row['marks'],
        'total' => $row['total_marks']
    ];
    $grouped_results[$key]['obtained_total'] += $row['marks'];
    $grouped_results[$key]['max_total'] += $row['total_marks'];
}
?>

<div class="grid2">
    <!-- Create Exam Section -->
    <section class="panel">
        <h2>Create Exam</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="mode" value="exam">
            <label>Exam Name<input required name="exam_name" placeholder="First Term 2026"></label>
            <label>Class
                <select name="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach($classes as $c):?>
                        <option value="<?=$c['id']?>"><?=e($c['class_name'].' - '.$c['section'])?></option>
                    <?php endforeach;?>
                </select>
            </label>
            <label>Exam Date<input type="date" name="exam_date"></label>
            <button class="btn primary wide">Create Exam</button>
        </form>
    </section>

    <!-- Enter Result Section -->
    <section class="panel">
        <h2>Enter Result</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            
            <label>Exam
                <select name="exam_id" id="exam_select" required onchange="filterStudentsByExam()">
                    <option value="">-- Select Exam --</option>
                    <?php foreach($exams as $x): 
                        $c_title = trim(($x['class_name'] ?? '').' '.($x['section'] ?? ''));
                    ?>
                        <option value="<?=$x['id']?>" data-class-id="<?=$x['class_id']?>">
                            <?=e($x['exam_name'].' · '.$c_title)?>
                        </option>
                    <?php endforeach;?>
                </select>
            </label>
            
            <label>Student
                <select name="student_id" id="student_select" required>
                    <option value="">-- Select Exam First --</option>
                </select>
            </label>
            
            <label>Subject
                <select name="subject_id" required>
                    <option value="">-- Select Subject --</option>
                    <?php foreach($subjects as $x):?>
                        <option value="<?=$x['id']?>"><?=e($x['subject_name'])?></option>
                    <?php endforeach;?>
                </select>
            </label>
            
            <label>Marks<input type="number" step="0.01" name="marks" required></label>
            <label>Total Marks<input type="number" step="0.01" name="total_marks" value="100" required></label>
            <label>Remarks<input name="remarks"></label>
            <button class="btn primary wide">Save Result</button>
        </form>
    </section>
</div>

<!-- Results Table Grouped by Student -->
<section class="panel">
    <div class="panel-head" style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Student Result Cards</h2>
        <form method="get" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="page" value="results">
            <label style="margin:0; font-weight:600;">Class Filter:</label>
            <select name="class_id" onchange="this.form.submit()" style="padding:6px; border-radius:4px;">
                <option value="0">All Classes</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$selected_class_id == $c['id'] ? 'selected' : ''?>>
                        <?=e($c['class_name'].' - '.$c['section'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Student</th>
                    <th>Subjects Entered</th>
                    <th>Total Marks</th>
                    <th>%</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($grouped_results)): ?>
                    <?php foreach($grouped_results as $gr): 
                        $perc = ($gr['max_total'] > 0) ? ($gr['obtained_total'] / $gr['max_total']) * 100 : 0;
                        $json_data = htmlspecialchars(json_encode($gr), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td><?=e($gr['exam_name'])?></td>
                        <td><b><?=e($gr['student_name'])?></b> <small>(<?=e($gr['admission_no'])?>)</small></td>
                        <td>
                            <?php 
                                $sub_names = array_map(function($s){ return $s['name']; }, $gr['subjects']);
                                echo e(implode(', ', $sub_names));
                            ?>
                        </td>
                        <td><?=number_format($gr['obtained_total'], 2)?> / <?=number_format($gr['max_total'], 2)?></td>
                        <td><b><?=number_format($perc, 1)?>%</b></td>
                        <td>
                            <button type="button" class="btn" style="padding:5px 10px; font-size:12px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;" 
                                onclick='printCombinedCard(<?=$json_data?>)'>
                                📄 Print Result Card
                            </button>
                        </td>
                    </tr>
                    <?php endforeach;?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No results found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Dynamic Student Filtering Script -->
<script>
var ALL_STUDENTS = <?=json_encode($all_students)?>;

function filterStudentsByExam() {
    var examSelect = document.getElementById('exam_select');
    var studentSelect = document.getElementById('student_select');
    var selectedOption = examSelect.options[examSelect.selectedIndex];

    if (!selectedOption || !selectedOption.value) {
        studentSelect.innerHTML = '<option value="">-- Select Exam First --</option>';
        return;
    }

    var targetClassId = String(selectedOption.getAttribute('data-class-id') || '');

    studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
    var matchedStudents = [];

    if (ALL_STUDENTS && ALL_STUDENTS.length > 0) {
        ALL_STUDENTS.forEach(function(st) {
            var stClassId = String(st.class_id || '');
            if (targetClassId && stClassId === targetClassId) {
                matchedStudents.push(st);
            }
        });

        // Safe Fallback: Agar kisi class id mismatch ki waja se zero match milay toh saare students show karo
        if (matchedStudents.length === 0) {
            matchedStudents = ALL_STUDENTS;
        }

        matchedStudents.forEach(function(st) {
            var opt = document.createElement('option');
            opt.value = st.id;
            var classInfo = (st.class_name ? ' [' + st.class_name + ' ' + (st.section || '') + ']' : '');
            opt.textContent = st.student_name + ' (' + (st.admission_no || 'ID: ' + st.id) + ')' + classInfo;
            studentSelect.appendChild(opt);
        });
    }
}

// Print Combined Window Script
function printCombinedCard(data) {
    var perc = data.max_total > 0 ? ((data.obtained_total / data.max_total) * 100).toFixed(1) : '0.0';
    
    var rowsHtml = '';
    data.subjects.forEach(function(sub) {
        var p = sub.total > 0 ? ((sub.marks / sub.total) * 100).toFixed(1) : '0.0';
        rowsHtml += `
            <tr>
                <td style="text-align:left; padding:8px;">${sub.name}</td>
                <td>${sub.marks}</td>
                <td>${sub.total}</td>
                <td><b>${p}%</b></td>
            </tr>
        `;
    });

    var win = window.open('', '', 'width=800,height=650');
    win.document.write(`
        <html>
        <head>
            <title>Result Card - ${data.student_name}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 25px; text-align: center; }
                .card { border: 3px double #b8860b; padding: 25px; border-radius: 10px; max-width: 650px; margin: auto; }
                h1 { margin: 0; color: #1a2a3a; font-size: 22px; }
                p { margin: 4px 0; font-size: 13px; }
                .info { text-align: left; margin: 15px 0; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #333; padding: 8px; text-align: center; font-size: 13px; }
                th { background: #f4f4f4; }
                .tfoot { font-weight: bold; background: #fafafa; }
                .footer { margin-top: 45px; display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>THE NEW BEACON SCHOOL SYSTEM</h1>
                <p>Larkana Campus - Official Result Card</p>
                <hr style="margin: 10px 0;">
                <div class="info">
                    <p><b>Student Name:</b> ${data.student_name} (Adm No: ${data.admission_no})</p>
                    <p><b>Class:</b> ${data.class_name}</p>
                    <p><b>Examination:</b> ${data.exam_name}</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align:left;">Subject</th>
                            <th>Obtained Marks</th>
                            <th>Total Marks</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                    <tfoot>
                        <tr class="tfoot">
                            <td style="text-align:left; padding:8px;">TOTAL</td>
                            <td>${data.obtained_total}</td>
                            <td>${data.max_total}</td>
                            <td>${perc}%</td>
                        </tr>
                    </tfoot>
                </table>
                <div class="footer">
                    <span>Class Teacher Signature</span>
                    <span>Principal Signature</span>
                </div>
            </div>
            <script>window.print();<\/script>
        </body>
        </html>
    `);
    win.document.close();
}
</script>