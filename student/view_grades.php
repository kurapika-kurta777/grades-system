<?php
/* 
 * STUDENT VIEW GRADES
 * This file is included by index.php (router)
 * Do not include HTML head/body tags - only content
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';

// Student access control
requireRole([3]);

$student_id = $_SESSION['user_id'];

// Period options
$periods = [
    '1st Year - 1st Semester',
    '1st Year - 2nd Semester',
    '2nd Year - 1st Semester',
    '2nd Year - 2nd Semester',
    '3rd Year - 1st Semester',
    '3rd Year - 2nd Semester'
];

// Get selected period from GET, default to '3rd Year - 2nd Semester'
$selected_period = $_GET['period'] ?? '3rd Year - 2nd Semester';
if (!in_array($selected_period, $periods)) {
    $selected_period = '3rd Year - 2nd Semester';
}

// Parse semester and school year from period string
// e.g. "3rd Year - 2nd Semester" → semester = "2nd Semester", year label = "3rd Year"
$period_parts   = explode(' - ', $selected_period);
$year_part      = $period_parts[0] ?? '';
$semester_part  = $period_parts[1] ?? '';

// Derive a plausible school year from year_level + current year
$current_year   = (int)date('Y');
$school_year    = ($current_year - 1) . ' - ' . $current_year;

// Query grades
$stmt = $conn->prepare("
    SELECT
        s.subject_code,
        s.subject_name,
        u.full_name AS teacher_name,
        g.numeric_grade,
        g.remarks,
        g.status
    FROM grades g
    JOIN subjects s ON g.subject_id = s.subject_id
    JOIN users u ON s.faculty_id = u.user_id
    WHERE g.student_id = ?
    AND g.academic_period = ?
    ORDER BY s.subject_code
");
$stmt->bind_param("is", $student_id, $selected_period);
$stmt->execute();
$result = $stmt->get_result();
$grades = [];
while ($row = $result->fetch_assoc()) {
    $grades[] = $row;
}
$stmt->close();

// Fetch student info
$stmt = $conn->prepare("SELECT full_name, program, section, year_level, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$year_labels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];

// Grade Point Average — average of approved numeric grades
$approved_grades = array_filter($grades, fn($g) => $g['status'] === 'Approved');
$gpa = 0;
if (count($approved_grades) > 0) {
    $gpa = array_sum(array_column($approved_grades, 'numeric_grade')) / count($approved_grades);
}

$total_subjects  = count($grades);
$credits_per_subject = 3.0;
$total_credits_enrolled = $total_subjects * $credits_per_subject;
$total_credits_earned   = count($approved_grades) * $credits_per_subject;
?>

<style>
:root {
    --navy: #0f246c;
    --blue-500: #3B82F6;
    --blue-600: #2563EB;
    --blue-700: #1E40AF;
    --blue-light: #93C5FD;
    --bg: #F0F4FF;
    --surface: #FFFFFF;
    --border: rgba(59, 130, 246, 0.14);
    --text-900: #0F1E4A;
    --text-600: #4B5E8A;
    --text-400: #8EA0C4;
    --shadow-sm: 0 2px 8px rgba(15, 36, 108, 0.08);
    --shadow-md: 0 6px 20px rgba(15, 36, 108, 0.12);
    --r-md: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-header { margin-bottom: 2rem; }
.page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-900); margin-bottom: 0.5rem; }
.page-header p  { color: var(--text-600); font-size: 0.9rem; }

.period-selector-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
}

.btn-download {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.65rem 1.5rem;
    background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
    color: white; border: none; border-radius: var(--r-md);
    font-size: 0.9rem; font-weight: 600; cursor: pointer;
    transition: var(--transition); box-shadow: var(--shadow-sm);
    font-family: 'DM Sans', sans-serif;
}
.btn-download:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); opacity: .92; }
.btn-download i { font-size: 1.1rem; }

.period-selector { margin-bottom: 0; display: flex; align-items: center; gap: 1rem; }
.period-selector label { font-weight: 600; color: var(--text-900); font-size: .95rem; }
.period-selector form { display: flex; align-items: center; gap: 1rem; }
.period-selector select {
    padding: .65rem 1rem; border: 1px solid var(--border); border-radius: var(--r-md);
    font-size: .9rem; color: var(--text-900); background: var(--surface);
    cursor: pointer; font-family: 'DM Sans', sans-serif; transition: var(--transition);
}
.period-selector select:hover  { border-color: var(--blue-600); }
.period-selector select:focus  { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }

.content-section { margin-bottom: 3rem; }

.table-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r-md); box-shadow: var(--shadow-sm);
    overflow: hidden; transition: var(--transition);
}
.table-card:hover { box-shadow: var(--shadow-md); border-color: var(--blue-600); }
.table-wrap { overflow-x: auto; }

table { border-collapse: collapse; width: 100%; }
th {
    padding: 1rem 1.25rem; text-align: center;
    font-size: .75rem; font-weight: 700; color: var(--text-400);
    text-transform: uppercase; letter-spacing: .05em;
    background: var(--bg); border-bottom: 1px solid var(--border);
}
td {
    padding: 1rem 1.25rem; font-size: .875rem; color: black;
    text-align: center; border-bottom: 1px solid var(--border);
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #f8f9ff; }

.badge-approved { background: #f0fdf4; color: #166534; border: 1px solid #22c55e; }
.badge-status {
    display: inline-block; padding: .4rem .75rem; border-radius: 6px;
    font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
}

.empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-600); }
.empty-state-icon { font-size: 3rem; margin-bottom: 1rem; opacity: .5; }

/* ── Hidden print area ── */
#grade-print-area { display: none; }
</style>

<div>
    <div class="page-header">
        <h2>View Grades</h2>
        <p>Review your grades across all academic periods.</p>
    </div>

    <div class="period-selector-row">
        <div class="period-selector">
            <form method="GET">
                <input type="hidden" name="page" value="view_grades">
                <label for="period">Academic Period:</label>
                <select id="period" name="period" onchange="this.form.submit()">
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= htmlspecialchars($period, ENT_QUOTES) ?>"
                            <?= $period === $selected_period ? 'selected' : '' ?>>
                            <?= htmlspecialchars($period, ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (count($grades) > 0): ?>
            <button class="btn-download" onclick="downloadGrades()">
                <i class='bx bx-download'></i> Download Grade
            </button>
        <?php endif; ?>
    </div>

    <!-- On-screen grade table -->
    <div class="content-section">
        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Instructor</th>
                            <th>Grade</th>
                            <th>Credited Units</th>
                            <th>Remarks</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($grades) > 0): ?>
                            <?php foreach ($grades as $grade): ?>
                                <tr>
                                    <td><?= htmlspecialchars($grade['subject_code'], ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($grade['subject_name'], ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($grade['teacher_name'], ENT_QUOTES) ?></td>
                                    <td>
                                        <?= $grade['status'] === 'Approved'
                                            ? number_format($grade['numeric_grade'], 2)
                                            : '—' ?>
                                    </td>
                                    <td>3.0</td>
                                    <td>
                                        <?php if ($grade['status'] === 'Approved'): ?>
                                            <?= (strpos($grade['remarks'], 'Failed') !== false || $grade['numeric_grade'] == 5.00)
                                                ? 'Failed' : 'Passed' ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($grade['status'] === 'Approved'): ?>
                                            <span class="badge-status badge-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background:#fef3c7;color:#92400e;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:2rem 1rem;">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📊</div>
                                        <p>No grades found for this period.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     HIDDEN PRINT TEMPLATE
══════════════════════════════════════════ -->
<?php if (count($grades) > 0): ?>
<div id="grade-print-area">
<!-- All content is injected into the print window via JS below -->
</div>
<?php endif; ?>

<script>
function downloadGrades() {

    /* ── Data passed from PHP ── */
    const studentName   = <?= json_encode($student_info['full_name']  ?? '') ?>;
    const program       = <?= json_encode($student_info['program']    ?? '') ?>;
    const section       = <?= json_encode($student_info['section']    ?? '') ?>;
    const yearLevel     = <?= json_encode($year_labels[$student_info['year_level']] ?? '') ?>;
    const semesterPart  = <?= json_encode($semester_part) ?>;
    const schoolYear    = <?= json_encode($school_year) ?>;
    const selectedPeriod = <?= json_encode($selected_period) ?>;
    const totalSubjects  = <?= json_encode($total_subjects) ?>;
    const totalCredEnrolled = <?= json_encode(number_format($total_credits_enrolled, 1)) ?>;
    const totalCredEarned   = <?= json_encode(number_format($total_credits_earned,   1)) ?>;
    const gpa               = <?= json_encode($gpa > 0 ? number_format($gpa, 2) : '—') ?>;

    const grades = <?= json_encode(array_map(function($g) {
        return [
            'code'    => $g['subject_code'],
            'name'    => $g['subject_name'],
            'grade'   => $g['status'] === 'Approved' ? number_format($g['numeric_grade'], 2) : '—',
            'remarks' => $g['status'] === 'Approved'
                            ? ((strpos($g['remarks'], 'Failed') !== false || $g['numeric_grade'] == 5.00) ? 'Failed' : 'Passed')
                            : '—',
            'status'  => $g['status'],
        ];
    }, $grades)) ?>;

    /* ── Build grade rows ── */
    const gradeRows = grades.map(g => `
        <tr>
            <td>${g.code}</td>
            <td style="text-align:left;">${g.name}</td>
            <td>${g.grade}</td>
            <td>3.0</td>
            <td>${g.remarks}</td>
        </tr>
    `).join('');

    /* ── Timestamp ── */
    const now = new Date();
    const months = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    const timestamp = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}  ${now.toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit',second:'2-digit'})}`;

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grade Report</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }

  @page {
      size: letter landscape;
      margin: 10mm 14mm;
  }

  body {
      font-family: Arial, sans-serif;
      font-size: 9pt;
      color: #000;
      background: #fff;
  }

  /* ── Page layout: flex column fills the full page height ── */
  .page {
      width: 100%;
      min-height: calc(216mm - 20mm); /* letter landscape height minus margins */
      margin: 0 auto;
      padding: 0;
      display: flex;
      flex-direction: column;
  }

  /* ── Header block ── */
  .report-header {
      text-align: center;
      margin-bottom: 10pt;
      padding-bottom: 0;
  }

  .report-title-main {
      font-size: 12pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #000;
      margin-bottom: 4pt;
  }

  .report-subtitle {
      font-size: 8.5pt;
      font-weight: normal;
      color: #000;
      margin-bottom: 4pt;
  }

  .report-semester {
      font-size: 8.5pt;
      font-weight: normal;
      color: #000;
      margin-bottom: 5pt;
  }

  .report-document-title {
      font-size: 10pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #000;
      margin-top: 9pt;
  }

  /* ── Student info block ── */
  .student-info {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4pt 20pt;
      margin-bottom: 10pt;
      padding: 4pt 0;
  }

  .info-row {
      display: flex;
      gap: 5pt;
      font-size: 8.5pt;
  }

  .info-label {
      font-weight: bold;
      white-space: nowrap;
      color: #000;
  }

  .info-value {
      color: #000;
      flex: 1;
      min-width: 80pt;
  }

  /* ── Grade table ── */
  .grade-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10pt;
      font-size: 8.5pt;
      table-layout: fixed;
  }

  /* Plain white header — no black background ── */
  .grade-table thead tr {
      background: #fff;
      color: #000;
  }

  .grade-table th {
      padding: 5pt 6pt;
      text-align: center;
      font-weight: bold;
      font-size: 8pt;
      border: 1pt solid #000;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      white-space: nowrap;
      overflow: hidden;
  }

  .grade-table td {
      padding: 4pt 6pt;
      border: 1pt solid #000;
      text-align: center;
      color: #000;
      vertical-align: middle;
  }


  .grade-table tbody tr:nth-child(even) {
      background: #f5f5f5;
  }

  /* ── Summary block ── */
  .summary-block {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4pt 20pt;
      margin-bottom: 10pt;
      font-size: 8.5pt;
      padding: 4pt 0;
  }

  .summary-row {
      display: flex;
      justify-content: space-between;
      gap: 6pt;
      padding: 2pt 0;
      border-bottom: 0.5pt dashed #ccc;
  }

  .summary-row:last-child { border-bottom: none; }

  .summary-label { font-weight: bold; color: #000; }
  .summary-value { color: #000; font-weight: normal; }

  /* ── Grading system ── */
  .grading-system {
      border: 1pt solid #000;
      padding: 6pt 10pt;
      margin-bottom: 10pt;
      font-size: 8pt;
  }

  .grading-title {
      font-weight: bold;
      font-size: 8.5pt;
      text-transform: uppercase;
      border-bottom: 1pt solid #000;
      padding-bottom: 4pt;
      margin-bottom: 5pt;
      color: #000;
  }

  .grading-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 1fr;
      gap: 3pt 0;
  }

  .grading-col { display: flex; flex-direction: column; gap: 2pt; }
  .grading-entry { color: #000; white-space: nowrap; }

  /* ── Bottom block: signatures + disclaimer, pinned to bottom ── */
  .bottom-block {
      margin-top: auto;
      padding-top: 8pt;
  }

  .signature-block {
      display: flex;
      justify-content: space-around;
      margin-bottom: 10pt;
  }

  .sig-item {
      text-align: center;
      width: 180pt;
  }

  .sig-placeholder {
      height: 28pt;
  }

  .sig-line {
      border-top: 1pt solid #000;
      width: 160pt;
      margin: 0 auto 4pt auto;
  }

  .sig-name {
      font-weight: bold;
      font-size: 9pt;
      color: #000;
  }

  .sig-title {
      font-size: 8.5pt;
      color: #000;
  }

  /* ── Disclaimer ── */
  .disclaimer {
      text-align: center;
      font-style: italic;
      font-size: 8pt;
      color: #000;
      border-top: 1pt solid #000;
      padding-top: 6pt;
      margin-top: 4pt;
  }

  @media print {
      body { margin: 0; }
      .page { margin: 0; padding: 0; }
      /* Suppress browser-added headers/footers */
      @page { margin: 10mm 14mm; }
  }
</style>
</head>
<body>
<div class="page">

  <!-- ── HEADER ── -->
  <div class="report-header">
    <div class="report-title-main">Grades &amp; Assessment Management Subsystem</div>
    <div class="report-subtitle">A centralized system for recording, managing, and monitoring student academic performance and evaluations.</div>
    <div class="report-semester">${semesterPart} &nbsp;&bull;&nbsp; School Year ${schoolYear}</div>
    <div class="report-document-title">Unofficial Grade Report</div>
  </div>

  <!-- ── STUDENT INFORMATION ── -->
  <div class="student-info">
    <div class="info-row">
      <span class="info-label">Student Name:</span>
      <span class="info-value">${studentName}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Section:</span>
      <span class="info-value">${section}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Program:</span>
      <span class="info-value">${program}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Year Level:</span>
      <span class="info-value">${yearLevel}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Academic Period:</span>
      <span class="info-value">${selectedPeriod}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Date Generated:</span>
      <span class="info-value">${timestamp.split('  ')[0]}</span>
    </div>
  </div>

  <!-- ── GRADE TABLE ── -->
  <table class="grade-table">
    <colgroup>
      <col style="width:85pt;">
      <col style="width:160pt;">
      <col style="width:48pt;">
      <col style="width:75pt;">
      <col style="width:58pt;">
    </colgroup>
    <thead>
      <tr>
        <th>Subject Code</th>
        <th>Subject Name</th>
        <th>Grade</th>
        <th>Credited Units</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      ${gradeRows}
    </tbody>
  </table>

  <!-- ── SUMMARY ── -->
  <div class="summary-block">
    <div class="summary-row">
      <span class="summary-label">Total Subjects Enrolled:</span>
      <span class="summary-value">${totalSubjects}</span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Total Credits Earned:</span>
      <span class="summary-value">${totalCredEarned}</span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Total Credits Enrolled:</span>
      <span class="summary-value">${totalCredEnrolled}</span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Grade Point Average:</span>
      <span class="summary-value">${gpa}</span>
    </div>
  </div>

  <!-- ── GRADING SYSTEM ── -->
  <div class="grading-system">
    <div class="grading-title">Grading System:</div>
    <div class="grading-grid">
      <div class="grading-col">
        <span class="grading-entry">1.00 = 98 &ndash; 100</span>
        <span class="grading-entry">1.25 = 95 &ndash; 97</span>
        <span class="grading-entry">1.50 = 92 &ndash; 94</span>
        <span class="grading-entry">1.75 = 89 &ndash; 91</span>
        <span class="grading-entry">2.00 = 86 &ndash; 88</span>
      </div>
      <div class="grading-col">
        <span class="grading-entry">2.25 = 83 &ndash; 85</span>
        <span class="grading-entry">2.50 = 80 &ndash; 82</span>
        <span class="grading-entry">2.75 = 77 &ndash; 79</span>
        <span class="grading-entry">3.00 = 75 &ndash; 76</span>
        <span class="grading-entry">5.00 = 1 &ndash; 74</span>
      </div>
      <div class="grading-col">
        <span class="grading-entry">DRP = Dropped</span>
        <span class="grading-entry">INC = Incomplete</span>
        <span class="grading-entry">N &nbsp; = No Credit</span>
        <span class="grading-entry">NA &nbsp;= Not Attending</span>
        <span class="grading-entry">NG &nbsp;= No Grade</span>
      </div>
      <div class="grading-col">
        <span class="grading-entry">OD &nbsp;= Officially Dropped</span>
        <span class="grading-entry">UD &nbsp;= Unofficially Dropped</span>
        <span class="grading-entry">UW = Unauthorized Withdrawal</span>
        <span class="grading-entry">W &nbsp;&nbsp;= Withdrawal</span>
      </div>
    </div>
  </div>

  <!-- ── BOTTOM: SIGNATURES + DISCLAIMER ── -->
  <div class="bottom-block">
    <div class="signature-block">
      <div class="sig-item">
        <div class="sig-placeholder"></div>
        <div class="sig-line"></div>
        <div class="sig-name">Registrar</div>
        <div class="sig-title">Office of the Registrar</div>
      </div>
      <div class="sig-item">
        <div class="sig-placeholder"></div>
        <div class="sig-line"></div>
        <div class="sig-name">Student Signature</div>
        <div class="sig-title">Received by</div>
      </div>
    </div>
    <div class="disclaimer">This is an unofficial document. Any alterations render this document invalid.</div>
  </div>

</div>
<script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`;

    const blob = new Blob([html], {type: 'text/html'});
    const url = URL.createObjectURL(blob);
    const win = window.open(url, '_blank');
    win.addEventListener('load', () => URL.revokeObjectURL(url));
}
</script>