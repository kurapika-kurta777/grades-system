<?php
/*
 * ADMIN GRADE REPORTS & SUMMARY
 * This file is included by index.php (router)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole([4]);

// ── Filter parameters ──
$selected_period     = isset($_GET['period'])     ? $_GET['period']              : '';
$selected_subject    = isset($_GET['subject'])    ? (int)$_GET['subject']        : 0;
$selected_section    = isset($_GET['section'])    ? $_GET['section']             : '';
$selected_year_level = isset($_GET['year_level']) ? (int)$_GET['year_level']     : 0;
$selected_faculty    = isset($_GET['faculty'])    ? (int)$_GET['faculty']        : 0;
$selected_status     = isset($_GET['status'])     ? $_GET['status']              : '';

$filters_applied = !empty($selected_period) || $selected_subject > 0
    || !empty($selected_section) || $selected_year_level > 0
    || $selected_faculty > 0     || !empty($selected_status);

$periods = [
    '1st Year - 1st Semester', '1st Year - 2nd Semester',
    '2nd Year - 1st Semester', '2nd Year - 2nd Semester',
    '3rd Year - 1st Semester', '3rd Year - 2nd Semester',
];

// ── Filter dropdown data ──
$subjects_stmt = $conn->prepare("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$subjects_stmt->execute();
$subjects = $subjects_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subjects_stmt->close();

$sections_stmt = $conn->prepare("SELECT DISTINCT section FROM users WHERE section IS NOT NULL AND section != '' ORDER BY section");
$sections_stmt->execute();
$sections = array_column($sections_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'section');
$sections_stmt->close();

$faculty_stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE role_id = 1 ORDER BY full_name");
$faculty_stmt->execute();
$faculty_list = $faculty_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$faculty_stmt->close();

// ── Fetch grade data ──
$grade_reports = [];
$subject_summary = [];
$student_averages = [];   // renamed from student_gpas — uses Philippine 1-5 scale

if ($filters_applied) {
    $query = "
        SELECT
            u.user_id,
            u.full_name   AS student_name,
            u.section,
            u.year_level,
            s.subject_id,
            s.subject_code,
            s.subject_name,
            f.full_name   AS faculty_name,
            g.academic_period,
            g.percentage,
            g.numeric_grade,
            g.remarks,
            g.status
        FROM grades g
        JOIN subjects s ON g.subject_id  = s.subject_id
        JOIN users    u ON g.student_id  = u.user_id
        JOIN users    f ON s.faculty_id  = f.user_id
        WHERE 1=1
    ";
    $params = []; $types = '';

    if (!empty($selected_status)) {
        $query   .= " AND g.status = ?"; $params[] = $selected_status; $types .= 's';
    } else {
        $query   .= " AND g.status = 'Approved'";
    }
    if (!empty($selected_period))     { $query .= " AND g.academic_period = ?"; $params[] = $selected_period;     $types .= 's'; }
    if ($selected_subject > 0)        { $query .= " AND g.subject_id = ?";      $params[] = $selected_subject;    $types .= 'i'; }
    if (!empty($selected_section))    { $query .= " AND u.section = ?";          $params[] = $selected_section;   $types .= 's'; }
    if ($selected_year_level > 0)     { $query .= " AND u.year_level = ?";       $params[] = $selected_year_level;$types .= 'i'; }
    if ($selected_faculty > 0)        { $query .= " AND s.faculty_id = ?";       $params[] = $selected_faculty;   $types .= 'i'; }

    $query .= " ORDER BY u.full_name, s.subject_code, g.academic_period";

    $stmt = $conn->prepare($query);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grade_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ── Build summaries ──
    foreach ($grade_reports as $g) {
        $sk = $g['subject_id'];
        $uk = $g['user_id'];

        // Subject pass/fail  (passing = numeric_grade <= 3.00 in Phil. system)
        if (!isset($subject_summary[$sk])) {
            $subject_summary[$sk] = [
                'subject_code' => $g['subject_code'],
                'subject_name' => $g['subject_name'],
                'total'  => 0, 'passed' => 0, 'failed' => 0,
                'grades' => [],
            ];
        }
        $subject_summary[$sk]['total']++;
        $subject_summary[$sk]['grades'][] = (float)$g['numeric_grade'];
        if ((float)$g['numeric_grade'] <= 3.00) $subject_summary[$sk]['passed']++;
        else                                     $subject_summary[$sk]['failed']++;

        // Per-student average (lower = better in Phil. scale)
        if (!isset($student_averages[$uk])) {
            $student_averages[$uk] = [
                'student_name'   => $g['student_name'],
                'section'        => $g['section'],
                'grades'         => [],
                'total_subjects' => 0,
            ];
        }
        $student_averages[$uk]['grades'][] = (float)$g['numeric_grade'];
        $student_averages[$uk]['total_subjects']++;
    }

    // Compute average per student
    foreach ($student_averages as &$s) {
        $s['avg'] = !empty($s['grades'])
            ? array_sum($s['grades']) / count($s['grades'])
            : 0;
    }
    unset($s);

    // ── Sort: lower average = better (1.0 scale) ──
    usort($student_averages, fn($a, $b) => $a['avg'] <=> $b['avg']);

    // Average grade per subject
    foreach ($subject_summary as &$sub) {
        $sub['avg'] = !empty($sub['grades'])
            ? array_sum($sub['grades']) / count($sub['grades'])
            : 0;
    }
    unset($sub);
}

/**
 * Map a Philippine numeric grade (1.00–5.00) to a descriptive label.
 * Mirrors grading_logic.php but works in reverse direction.
 */
function gradeLabel(float $g): string {
    if ($g <= 1.25) return 'Excellent';
    if ($g <= 1.75) return 'Very Good';
    if ($g <= 2.25) return 'Good';
    if ($g <= 2.75) return 'Satisfactory';
    if ($g <= 3.00) return 'Passed';
    return 'Failed';
}
function gradeLabelClass(float $g): string {
    return $g <= 3.00 ? 'adm-badge-pass' : 'adm-badge-fail';
}
?>

<style>
:root {
    --adm-primary:    #3B82F6;
    --adm-primary-dk: #2563EB;
    --adm-secondary:  #10B981;
    --adm-accent:     #F59E0B;
    --adm-danger:     #EF4444;
    --adm-surface:    #FFFFFF;
    --adm-bg:         #F8FAFC;
    --adm-border:     #E2E8F0;
    --adm-text:       #1E293B;
    --adm-text-muted: #64748B;
    --adm-shadow:     0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
    --adm-shadow-lg:  0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
    --adm-radius:     12px;
    --adm-transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
}

.adm-page-header { margin-bottom: 2rem; }
.adm-page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--adm-text); margin-bottom: .25rem; }
.adm-page-header p  { color: var(--adm-text-muted); font-size: .9rem; }

/* ── Filters ── */
.adm-filters {
    background: var(--adm-surface); border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius); box-shadow: var(--adm-shadow);
    padding: 1.4rem; margin-bottom: 2rem;
}
.adm-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 1rem; align-items: end;
}
.adm-filter-group { display: flex; flex-direction: column; gap: .4rem; }
.adm-filter-group label {
    font-size: .78rem; font-weight: 700; color: var(--adm-text);
    text-transform: uppercase; letter-spacing: .05em;
}
.adm-filter-group select {
    padding: .6rem .85rem; border: 1.5px solid var(--adm-border);
    border-radius: 8px; font-size: .875rem; background: var(--adm-surface);
    color: var(--adm-text); font-family: inherit; transition: var(--adm-transition);
}
.adm-filter-group select:focus {
    outline: none; border-color: var(--adm-primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}
.adm-filter-actions { display: flex; gap: .5rem; align-items: flex-end; }
.adm-btn-apply {
    padding: .6rem 1.2rem; background: var(--adm-primary); color: #fff;
    border: none; border-radius: 8px; font-size: .875rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; gap: .4rem;
    transition: var(--adm-transition); white-space: nowrap;
}
.adm-btn-apply:hover { background: var(--adm-primary-dk); transform: translateY(-1px); }
.adm-btn-reset {
    padding: .6rem 1rem; background: var(--adm-surface); color: var(--adm-text);
    border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem;
    font-weight: 600; cursor: pointer; text-decoration: none;
    display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition);
    white-space: nowrap;
}
.adm-btn-reset:hover { border-color: var(--adm-text-muted); background: var(--adm-bg); }

/* ── Prompt card ── */
.adm-prompt {
    background: var(--adm-surface); border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius); box-shadow: var(--adm-shadow);
    padding: 3rem 2rem; text-align: center; color: var(--adm-text-muted);
}
.adm-prompt i  { font-size: 2.8rem; display: block; margin-bottom: .75rem; opacity: .4; }
.adm-prompt h3 { font-size: 1.05rem; font-weight: 700; color: var(--adm-text); margin-bottom: .4rem; }
.adm-prompt p  { font-size: .88rem; }

/* ── Summary cards ── */
.adm-summary-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1.25rem; margin-bottom: 2rem;
}
.adm-summary-card {
    background: var(--adm-surface); border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius); box-shadow: var(--adm-shadow);
    padding: 1.3rem 1.5rem;
}
.adm-summary-card h3 {
    font-size: .8rem; font-weight: 700; color: var(--adm-text-muted);
    text-transform: uppercase; letter-spacing: .05em; margin-bottom: .9rem;
    display: flex; align-items: center; gap: .5rem;
}
.adm-summary-card h3 i { color: var(--adm-primary); }
.adm-summary-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.adm-stat-box { text-align: center; padding: .85rem .75rem; background: var(--adm-bg); border: 1px solid var(--adm-border); border-radius: 8px; }
.adm-stat-box-num   { font-size: 1.75rem; font-weight: 700; color: var(--adm-primary); display: block; margin-bottom: .2rem; line-height: 1; }
.adm-stat-box-label { font-size: .73rem; color: var(--adm-text-muted); font-weight: 500; }

/* Scale note */
.adm-scale-note {
    background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px;
    padding: .65rem 1rem; font-size: .8rem; color: #1E40AF;
    display: flex; align-items: center; gap: .5rem; margin-bottom: 1.25rem;
}
.adm-scale-note i { flex-shrink: 0; font-size: 1rem; }

/* ── Table card ── */
.adm-table-card {
    background: var(--adm-surface); border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius); box-shadow: var(--adm-shadow);
    overflow: hidden; margin-bottom: 1.5rem;
}
.adm-table-card-header {
    padding: .9rem 1.25rem; border-bottom: 1px solid var(--adm-border);
    display: flex; justify-content: space-between; align-items: center;
}
.adm-table-card-title    { font-size: 1rem; font-weight: 700; color: var(--adm-text); }
.adm-table-card-subtitle { font-size: .82rem; color: var(--adm-text-muted); }
.adm-table-wrap          { overflow-x: auto; }

.adm-report-table { width: 100%; border-collapse: collapse; }
.adm-report-table th {
    padding: .8rem 1rem; text-align: center;
    background: var(--adm-bg);
    font-size: .73rem; font-weight: 700; color: var(--adm-text-muted);
    text-transform: uppercase; letter-spacing: .05em;
    border-bottom: 1px solid var(--adm-border);
}
.adm-report-table td {
    padding: .8rem 1rem; text-align: center;
    border-bottom: 1px solid var(--adm-border);
    font-size: .875rem; color: var(--adm-text);
    vertical-align: middle;
}
.adm-report-table tbody tr:hover { background: var(--adm-bg); }
.adm-report-table tbody tr:last-child td { border-bottom: none; }

/* ── Badges ── */
.adm-badge {
    display: inline-flex; align-items: center; gap: .25rem;
    padding: .28rem .7rem; border-radius: 5px;
    font-size: .75rem; font-weight: 700;
}
.adm-badge-pass { background: rgba(16,185,129,.1); color: #065F46; border: 1px solid rgba(16,185,129,.25); }
.adm-badge-fail { background: rgba(239,68,68,.1);  color: #991B1B; border: 1px solid rgba(239,68,68,.25); }
.adm-badge-approved { background: rgba(16,185,129,.1); color: #065F46; border: 1px solid rgba(16,185,129,.25); }
.adm-badge-pending  { background: rgba(245,158,11,.1); color: #92400E; border: 1px solid rgba(245,158,11,.25); }
.adm-badge-returned { background: rgba(239,68,68,.1);  color: #991B1B; border: 1px solid rgba(239,68,68,.25); }

/* Rank pill */
.adm-rank { font-size: .72rem; font-weight: 700; color: var(--adm-text-muted); }
.adm-rank-1 { color: #D97706; }
.adm-rank-2 { color: #6B7280; }
.adm-rank-3 { color: #92400E; }

/* ── No data ── */
.adm-no-data {
    text-align: center; padding: 2.5rem 1.5rem;
    color: var(--adm-text-muted); font-size: .9rem;
}
.adm-no-data h3 { color: var(--adm-text); font-size: 1rem; margin-bottom: .3rem; }

@media (max-width: 700px) {
    .adm-filter-grid { grid-template-columns: 1fr; }
    .adm-summary-grid { grid-template-columns: 1fr; }
}
</style>

<div class="adm-page-header">
    <h2>Grade Reports &amp; Summary</h2>
    <p>Comprehensive overview of approved grades with filtering and academic performance analysis.</p>
</div>

<!-- Filters -->
<div class="adm-filters">
    <form method="GET" class="adm-filter-grid">
        <input type="hidden" name="page" value="grade_reports">

        <div class="adm-filter-group">
            <label>Academic Period</label>
            <select name="period">
                <option value="">All Periods</option>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= htmlspecialchars($p, ENT_QUOTES) ?>" <?= $selected_period === $p ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="adm-filter-group">
            <label>Subject</label>
            <select name="subject">
                <option value="0">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['subject_id'] ?>" <?= $selected_subject == $s['subject_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['subject_code'] . ' – ' . $s['subject_name'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="adm-filter-group">
            <label>Section</label>
            <select name="section">
                <option value="">All Sections</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?= htmlspecialchars($sec, ENT_QUOTES) ?>" <?= $selected_section === $sec ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sec, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="adm-filter-group">
            <label>Year Level</label>
            <select name="year_level">
                <option value="0">All Years</option>
                <option value="1" <?= $selected_year_level == 1 ? 'selected' : '' ?>>1st Year</option>
                <option value="2" <?= $selected_year_level == 2 ? 'selected' : '' ?>>2nd Year</option>
                <option value="3" <?= $selected_year_level == 3 ? 'selected' : '' ?>>3rd Year</option>
                <option value="4" <?= $selected_year_level == 4 ? 'selected' : '' ?>>4th Year</option>
            </select>
        </div>

        <div class="adm-filter-group">
            <label>Faculty</label>
            <select name="faculty">
                <option value="0">All Faculty</option>
                <?php foreach ($faculty_list as $f): ?>
                    <option value="<?= $f['user_id'] ?>" <?= $selected_faculty == $f['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['full_name'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="adm-filter-group">
            <label>Grade Status</label>
            <select name="status">
                <option value="">Approved Only</option>
                <option value="Approved" <?= $selected_status === 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Pending"  <?= $selected_status === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                <option value="Returned" <?= $selected_status === 'Returned' ? 'selected' : '' ?>>Returned</option>
            </select>
        </div>

        <div class="adm-filter-actions">
            <button type="submit" class="adm-btn-apply">
                <i class='bx bx-filter-alt'></i> Apply
            </button>
            <a href="?page=grade_reports" class="adm-btn-reset">
                <i class='bx bx-reset'></i> Reset
            </a>
        </div>
    </form>
</div>

<?php if (!$filters_applied): ?>
    <div class="adm-prompt">
        <i class='bx bx-filter-alt'></i>
        <h3>Apply Filters to View Reports</h3>
        <p>Use the filters above and click <strong>Apply</strong> to load grade reports and performance summaries.</p>
    </div>
<?php else: ?>

<!-- Philippine grading scale notice -->
<div class="adm-scale-note">
    <i class='bx bx-info-circle'></i>
    <span><strong>Philippine Grading Scale:</strong> 1.00 (Excellent) → 3.00 (Passed) → 5.00 (Failed).
    A <em>lower</em> numeric grade denotes better performance. Passing threshold: ≤ 3.00.</span>
</div>

<!-- Summary cards -->
<div class="adm-summary-grid">
    <div class="adm-summary-card">
        <h3><i class='bx bx-book'></i> Subject Performance</h3>
        <div class="adm-summary-stats">
            <?php
            $total_subj = count($subject_summary);
            $total_pass = array_sum(array_column($subject_summary, 'passed'));
            $total_fail = array_sum(array_column($subject_summary, 'failed'));
            $denom      = max(1, $total_pass + $total_fail);
            $pass_rate  = round(($total_pass / $denom) * 100, 1);
            ?>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $total_subj ?></span>
                <span class="adm-stat-box-label">Subjects</span>
            </div>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $pass_rate ?>%</span>
                <span class="adm-stat-box-label">Pass Rate</span>
            </div>
        </div>
    </div>

    <div class="adm-summary-card">
        <h3><i class='bx bx-user'></i> Student Performance</h3>
        <div class="adm-summary-stats">
            <?php
            $total_students = count($student_averages);
            // Cohort average (lower = better reminder in label)
            $cohort_avg = $total_students > 0
                ? array_sum(array_column($student_averages, 'avg')) / $total_students
                : 0;
            ?>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $total_students ?></span>
                <span class="adm-stat-box-label">Students</span>
            </div>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= number_format($cohort_avg, 2) ?></span>
                <span class="adm-stat-box-label">Cohort Avg. Grade</span>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Grade Records -->
<?php if (!empty($grade_reports)): ?>
<div class="adm-table-card">
    <div class="adm-table-card-header">
        <span class="adm-table-card-title">Grade Records</span>
        <span class="adm-table-card-subtitle"><?= count($grade_reports) ?> record<?= count($grade_reports) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="adm-table-wrap">
        <table class="adm-report-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Period</th>
                    <th>Percentage</th>
                    <th>Numeric Grade</th>
                    <th>Performance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade_reports as $g): ?>
                <tr>
                    <td style="text-align:left;font-weight:600;"><?= htmlspecialchars($g['student_name'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($g['section'], ENT_QUOTES) ?></td>
                    <td style="text-align:left;">
                        <strong><?= htmlspecialchars($g['subject_code'], ENT_QUOTES) ?></strong><br>
                        <small style="color:var(--adm-text-muted);"><?= htmlspecialchars($g['subject_name'], ENT_QUOTES) ?></small>
                    </td>
                    <td><?= htmlspecialchars($g['academic_period'], ENT_QUOTES) ?></td>
                    <td><strong><?= number_format($g['percentage'], 2) ?>%</strong></td>
                    <td style="font-weight:700;font-size:1rem;"><?= number_format($g['numeric_grade'], 2) ?></td>
                    <td>
                        <span class="adm-badge <?= gradeLabelClass((float)$g['numeric_grade']) ?>">
                            <?= gradeLabel((float)$g['numeric_grade']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="adm-badge adm-badge-<?= strtolower($g['status']) ?>">
                            <?= htmlspecialchars($g['status'], ENT_QUOTES) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="adm-table-card"><div class="adm-no-data"><h3>No Grade Records Found</h3><p>No approved grades match the selected filters.</p></div></div>
<?php endif; ?>

<!-- Pass / Fail Summary by Subject -->
<?php if (!empty($subject_summary)): ?>
<div class="adm-table-card">
    <div class="adm-table-card-header">
        <span class="adm-table-card-title">Pass / Fail Summary by Subject</span>
    </div>
    <div class="adm-table-wrap">
        <table class="adm-report-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject Name</th>
                    <th>Students</th>
                    <th>Passed (≤ 3.00)</th>
                    <th>Failed (> 3.00)</th>
                    <th>Pass Rate</th>
                    <th>Avg. Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subject_summary as $sub): ?>
                <?php
                    $pr  = $sub['total'] > 0 ? round(($sub['passed'] / $sub['total']) * 100, 1) : 0;
                    $col = $pr >= 75 ? 'color:var(--adm-secondary);' : 'color:var(--adm-danger);';
                ?>
                <tr>
                    <td style="font-weight:700;"><?= htmlspecialchars($sub['subject_code'], ENT_QUOTES) ?></td>
                    <td style="text-align:left;"><?= htmlspecialchars($sub['subject_name'], ENT_QUOTES) ?></td>
                    <td><?= $sub['total'] ?></td>
                    <td style="color:var(--adm-secondary);font-weight:700;"><?= $sub['passed'] ?></td>
                    <td style="color:var(--adm-danger);font-weight:700;"><?= $sub['failed'] ?></td>
                    <td><span style="font-weight:700;<?= $col ?>"><?= $pr ?>%</span></td>
                    <td style="font-weight:700;"><?= number_format($sub['avg'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Student Average Grade Ranking -->
<?php if (!empty($student_averages)): ?>
<div class="adm-table-card">
    <div class="adm-table-card-header">
        <span class="adm-table-card-title">Student Average Grade Ranking</span>
        <span class="adm-table-card-subtitle">
            Sorted by average numeric grade — <strong>lower = better</strong> (Phil. 1.0–5.0 scale)
        </span>
    </div>
    <div class="adm-table-wrap">
        <table class="adm-report-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Student</th>
                    <th>Section</th>
                    <th>Subjects</th>
                    <th>Avg. Numeric Grade</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($student_averages as $rank => $stu): ?>
                <?php $r = $rank + 1; ?>
                <tr>
                    <td>
                        <span class="adm-rank <?= $r <= 3 ? 'adm-rank-' . $r : '' ?>">
                            <?= $r <= 3 ? ['🥇','🥈','🥉'][$r - 1] . ' ' : '' ?><?= $r ?>
                        </span>
                    </td>
                    <td style="text-align:left;font-weight:600;"><?= htmlspecialchars($stu['student_name'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($stu['section'], ENT_QUOTES) ?></td>
                    <td><?= $stu['total_subjects'] ?></td>
                    <td style="font-weight:700;font-size:1rem;"><?= number_format($stu['avg'], 2) ?></td>
                    <td>
                        <span class="adm-badge <?= gradeLabelClass($stu['avg']) ?>">
                            <?= gradeLabel($stu['avg']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; // filters_applied ?>