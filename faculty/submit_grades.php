<?php
/* 
 * FACULTY SUBMIT GRADES
 * This file is included by index.php (router)
 * Do not include HTML head/body tags - only content
 */

// Start output buffering to allow header redirects after content output
ob_start();

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';

require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/grading_logic.php';

// Faculty access control
requireRole([1]);



$faculty_id = $_SESSION['user_id'];
$message = '';
$msg_type = 'success';
$error = '';


// Helper: Fetch grade categories for a subject
function getGradeCategories($conn, $subject_id, $term) {
    $stmt = $conn->prepare("SELECT gc.category_id, gc.category_name, gc.weight, gc.input_mode, gci.item_id, gci.item_label, gci.item_order 
        FROM grade_categories gc 
        LEFT JOIN grade_category_items gci ON gc.category_id = gci.category_id 
        WHERE gc.subject_id = ? AND gc.term = ?
        ORDER BY gc.category_id, gci.item_order");
    $stmt->bind_param("is", $subject_id, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $cid = $row['category_id'];
        if (!isset($categories[$cid])) {
            $categories[$cid] = [
                'category_id' => $cid,
                'category_name' => $row['category_name'],
                'weight' => $row['weight'],
                'input_mode' => $row['input_mode'],
                'items' => []
            ];
        }
        if ($row['item_id']) {
            $categories[$cid]['items'][] = [
                'item_id' => $row['item_id'],
                'item_label' => $row['item_label'],
                'item_order' => $row['item_order']
            ];
        }
    }
    $stmt->close();
    return array_values($categories);
}

// Helper: Check if any grades exist for a subject and term
function hasGradesForSubject($conn, $subject_id, $term) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM grades WHERE subject_id = ? AND term = ?");
    $stmt->bind_param("is", $subject_id, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['cnt'] > 0;
}

// Helper: Fetch existing grade scores per student
function getExistingScores($conn, $student_id, $subject_id, $academic_period, $term) {
    $stmt = $conn->prepare("SELECT item_id, raw_score, max_score FROM grade_components WHERE student_id = ? AND subject_id = ? AND academic_period = ? AND term = ?");
    $stmt->bind_param("iiss", $student_id, $subject_id, $academic_period, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $scores = [];
    while ($row = $result->fetch_assoc()) {
        $scores[$row['item_id']] = $row;
    }
    $stmt->close();
    return $scores;
}

// Helper: Fetch grade status and values
function getGradeStatus($conn, $student_id, $subject_id, $academic_period, $term) {
    $stmt = $conn->prepare("SELECT percentage, numeric_grade, remarks, status, is_locked FROM grades WHERE student_id = ? AND subject_id = ? AND academic_period = ? AND term = ?");
    $stmt->bind_param("iiss", $student_id, $subject_id, $academic_period, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

// Helper: Fetch semestral grades for display
function getSemestralGrades($conn, $subject_id, $academic_period, $section) {
    if (empty($section)) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            u.user_id,
            u.full_name,
            sg.semestral_grade_id,
            sg.prelim_grade,
            sg.midterm_grade,
            sg.finals_grade,
            sg.final_grade,
            sg.final_numeric,
            sg.final_remarks,
            sg.status,
            COALESCE(sg.prelim_grade,
                (SELECT percentage FROM grades
                 WHERE student_id = u.user_id
                   AND subject_id = ?
                   AND academic_period = ?
                   AND term = 'Prelim'
                 LIMIT 1)
            ) AS computed_prelim,
            COALESCE(sg.midterm_grade,
                (SELECT percentage FROM grades
                 WHERE student_id = u.user_id
                   AND subject_id = ?
                   AND academic_period = ?
                   AND term = 'Midterm'
                 LIMIT 1)
            ) AS computed_midterm,
            COALESCE(sg.finals_grade,
                (SELECT percentage FROM grades
                 WHERE student_id = u.user_id
                   AND subject_id = ?
                   AND academic_period = ?
                   AND term = 'Finals'
                 LIMIT 1)
            ) AS computed_finals
        FROM users u
        JOIN enrollments e
            ON u.user_id = e.student_id
            AND e.subject_id = ?
            AND e.status = 'Active'
        LEFT JOIN semestral_grades sg
            ON sg.student_id = u.user_id
            AND sg.subject_id = ?
            AND sg.academic_period = ?
        WHERE u.section = ?
        ORDER BY u.full_name
    ");

    $stmt->bind_param(
        "isisisiiss",
        $subject_id, $academic_period,
        $subject_id, $academic_period,
        $subject_id, $academic_period,
        $subject_id,
        $subject_id, $academic_period,
        $section
    );

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $p = floatval($row['computed_prelim'] ?? 0);
        $m = floatval($row['computed_midterm'] ?? 0);
        $f = floatval($row['computed_finals'] ?? 0);
        if ($p > 0 || $m > 0 || $f > 0) {
            $row['computed_final'] = ($p * 0.30) + ($m * 0.30) + ($f * 0.40);
        } else {
            $row['computed_final'] = null;
        }
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Helper: Fetch assessment components for a subject (legacy fallback)
function getAssessmentComponents($conn, $subject_id) {
    $stmt = $conn->prepare("SELECT component_id, component_name, weight FROM assessment_components WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $components = [];
    while ($row = $result->fetch_assoc()) {
        $components[] = $row;
    }
    $stmt->close();
    return $components;
}

// Helper: Fetch grade components for a student/subject/period
function getGradeComponents($conn, $student_id, $subject_id, $academic_period) {
    $stmt = $conn->prepare("SELECT component_id, raw_score, max_score FROM grade_components WHERE student_id = ? AND subject_id = ? AND academic_period = ?");
    $stmt->bind_param("iis", $student_id, $subject_id, $academic_period);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[$row['component_id']] = $row;
    }
    $stmt->close();
    return $data;
}

// --- Handle Semestral Grade Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_semestral') {
    if (empty($_POST['csrf_token']) || !csrf_check($_POST['csrf_token'])) {
        setFlash('Invalid security token.', 'error');
        $redirect_url = '?page=submit_grades&period=' . urlencode($_POST['academic_period'] ?? '') . '&section=' . urlencode($_POST['section'] ?? '');
        echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
        exit;
    }

    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $academic_period = trim($_POST['academic_period'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $semestral_data = $_POST['semestral'] ?? [];

    if ($subject_id <= 0 || !$academic_period || empty($semestral_data)) {
        setFlash('Missing required fields.', 'error');
        $redirect_url = '?page=submit_grades&period=' . urlencode($academic_period) . '&section=' . urlencode($section);
        echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
        exit;
    }

    $success_count = 0;
    foreach ($semestral_data as $student_id => $data) {
        $student_id = (int)$student_id;
        $prelim = floatval($data['prelim'] ?? 0);
        $midterm = floatval($data['midterm'] ?? 0);
        $finals = floatval($data['finals'] ?? 0);
        $final_grade = ($prelim * 0.30) + ($midterm * 0.30) + ($finals * 0.40);

        list($final_numeric, $final_remarks) = convertGrade($final_grade);

        $stmt = $conn->prepare("
            INSERT INTO semestral_grades
                (student_id, subject_id, academic_period, prelim_grade, midterm_grade, finals_grade, final_grade, final_numeric, final_remarks, status, submitted_at, submitted_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW(), ?)
            ON DUPLICATE KEY UPDATE
                prelim_grade = VALUES(prelim_grade),
                midterm_grade = VALUES(midterm_grade),
                finals_grade = VALUES(finals_grade),
                final_grade = VALUES(final_grade),
                final_numeric = VALUES(final_numeric),
                final_remarks = VALUES(final_remarks),
                status = 'Submitted',
                submitted_at = NOW(),
                submitted_by = VALUES(submitted_by)
        ");
        $stmt->bind_param(
            "iisddddssi",
            $student_id, $subject_id, $academic_period,
            $prelim, $midterm, $finals, $final_grade,
            $final_numeric, $final_remarks,
            $faculty_id
        );
        $stmt->execute();
        $stmt->close();

        logAction($conn, $faculty_id, "Submitted semestral grade for student $student_id in subject $subject_id ($academic_period): Final=$final_grade ($final_numeric)");
        $success_count++;
    }

    setFlash("Semestral grades submitted successfully for $success_count student(s).", 'success');
    $redirect_url = '?page=submit_grades&period=' . urlencode($academic_period) . '&section=' . urlencode($section);
    echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
    exit;
}

// --- Handle POST submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (empty($_POST['csrf_token']) || !csrf_check($_POST['csrf_token'])) {
        setFlash('Invalid security token. Please try again.', 'error');
        $redirect_url = '?page=submit_grades&period=' . urlencode($_POST['academic_period'] ?? '') . '&section=' . urlencode($_POST['section'] ?? '');
        echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
        exit;
    }
    
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    $academic_period = isset($_POST['academic_period']) ? trim($_POST['academic_period']) : '';
    $term = isset($_POST['term']) ? trim($_POST['term']) : '';
    $all_scores = isset($_POST['scores']) ? $_POST['scores'] : [];
    $section = isset($_POST['section']) ? trim($_POST['section']) : '';
    
    if ($subject_id <= 0 || !$academic_period || !$term || empty($all_scores)) {
        setFlash('Please provide all required fields.', 'error');
        $redirect_url = '?page=submit_grades&period=' . urlencode($academic_period) . '&section=' . urlencode($section);
        echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
        exit;
    }
    
    // Fetch categories with weights and input modes
    $stmt = $conn->prepare("SELECT gc.category_id, gc.weight, gc.input_mode FROM grade_categories gc WHERE gc.subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category_weights = [];
    $category_modes = [];
    while ($row = $result->fetch_assoc()) {
        $category_weights[$row['category_id']] = $row['weight'];
        $category_modes[$row['category_id']] = $row['input_mode'];
    }
    $stmt->close();

    $success_count = 0;

    foreach ($all_scores as $student_id => $student_scores) {
        $student_id = (int)$student_id;
        if ($student_id <= 0) continue;

        $final_percentage = 0;
        $category_sum = [];
        $category_count = [];

        foreach ($student_scores as $cat_id => $categories) {
            if (!isset($category_count[$cat_id])) {
                $category_count[$cat_id] = 0;
                $category_sum[$cat_id] = 0;
            }
            $inputMode = isset($category_modes[$cat_id]) ? $category_modes[$cat_id] : 'raw';

            foreach ($categories as $item_id => $values) {
                $raw = isset($values['raw']) ? floatval($values['raw']) : 0;
                $max = isset($values['max']) ? floatval($values['max']) : 0;

                if ($inputMode === 'percentage') {
                    $item_pct = max(0, min(100, $raw));
                } else {
                    if ($max <= 0) continue;
                    $item_pct = ($raw / $max) * 100;
                }

                $category_sum[$cat_id] += $item_pct;
                $category_count[$cat_id]++;
            }
        }

        foreach ($category_weights as $cat_id => $weight) {
            if (isset($category_count[$cat_id]) && $category_count[$cat_id] > 0) {
                $category_avg = $category_sum[$cat_id] / $category_count[$cat_id];
                $final_percentage += ($category_avg * ($weight / 100));
            }
        }

        list($numeric_grade, $remarks) = convertGrade($final_percentage);

        $stmt = $conn->prepare("INSERT INTO grades (student_id, subject_id, academic_period, term, percentage, numeric_grade, remarks, status, is_locked)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 1)
            ON DUPLICATE KEY UPDATE percentage=VALUES(percentage), numeric_grade=VALUES(numeric_grade), remarks=VALUES(remarks), status='Pending', is_locked=1");
        $stmt->bind_param("iissdss", $student_id, $subject_id, $academic_period, $term, $final_percentage, $numeric_grade, $remarks);
        $stmt->execute();
        $stmt->close();

        foreach ($student_scores as $cat_id => $categories) {
            foreach ($categories as $item_id => $values) {
                $raw = floatval($values['raw']);
                $max = floatval($values['max']);
                $stmt = $conn->prepare("INSERT INTO grade_components (student_id, subject_id, academic_period, term, category_id, item_id, raw_score, max_score, component_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE raw_score=VALUES(raw_score), max_score=VALUES(max_score)");
                $stmt->bind_param("iissiidd", $student_id, $subject_id, $academic_period, $term, $cat_id, $item_id, $raw, $max);
                $stmt->execute();
                $stmt->close();
            }
        }

        logAction($conn, $faculty_id, "Encoded $term grade for student $student_id in subject $subject_id ($academic_period): $final_percentage% ($numeric_grade)");
        $success_count++;
    }

    setFlash("$term grades submitted successfully for $success_count student(s).", 'success');
    $redirect_url = '?page=submit_grades&period=' . urlencode($academic_period) . '&section=' . urlencode($section);
    echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
    exit;
}


// Fetch faculty's subjects
$subjects = [];
$stmt = $conn->prepare("SELECT subject_id, subject_code, subject_name FROM subjects WHERE faculty_id = ? ORDER BY subject_name");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Fetch all enrolled students for faculty's subjects
$students = [];
$sections = [];
if (!empty($subjects)) {
    $subject_ids = array_column($subjects, 'subject_id');
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    $stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.full_name, u.program, u.year_level, u.section
        FROM enrollments e
        JOIN users u ON e.student_id = u.user_id
        WHERE e.subject_id IN ($placeholders) AND e.status = 'Active'
        ORDER BY u.full_name");
    $stmt->bind_param(str_repeat('i', count($subject_ids)), ...$subject_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
        if ($row['section'] && !in_array($row['section'], $sections)) $sections[] = $row['section'];
    }
    $stmt->close();
}
sort($sections);

// Fetch all grades for this faculty's subjects
$existing_grades = [];
if (!empty($subjects)) {
    $subject_ids = array_column($subjects, 'subject_id');
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    $stmt = $conn->prepare("SELECT g.student_id, g.subject_id, g.academic_period, g.percentage, g.numeric_grade, g.is_locked, g.status
        FROM grades g
        WHERE g.subject_id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($subject_ids)), ...$subject_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = $row['student_id'] . '_' . $row['subject_id'] . '_' . $row['academic_period'];
        $existing_grades[$key] = $row;
    }
    $stmt->close();
}

// Period options
$periods = [
    '1st Year - 1st Semester',
    '1st Year - 2nd Semester',
    '2nd Year - 1st Semester',
    '2nd Year - 2nd Semester',
    '3rd Year - 1st Semester',
    '3rd Year - 2nd Semester'
];

$selected_period = $_GET['period'] ?? '3rd Year - 2nd Semester';
if (!in_array($selected_period, $periods)) {
    $selected_period = '3rd Year - 2nd Semester';
}

$all_sections = [
    'BSIT-32001-IM', 'BSIT-32002-IM', 'BSIT-32003-IM',
    'BSIT-32004-IM', 'BSIT-32005-IM', 'BSIT-32006-IM',
    'BSIT-32007-IM', 'BSIT-32008-IM', 'BSIT-32009-IM',
    'BSIT-32010-IM', 'BSIT-32011-IM', 'BSIT-32012-IM',
    'BSIT-32013-IM', 'BSIT-32014-IM', 'BSIT-32015-IM'
];

$selected_section = $_GET['section'] ?? '';
if ($selected_section && !in_array($selected_section, $all_sections)) {
    $selected_section = '';
}

$csrf_token = csrf_token();
?>

<style>
    /* ══════════════════════════════════════════
       GRADE SUBMISSION CONFIRMATION MODAL
    ══════════════════════════════════════════ */
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }
    .modal-backdrop.open { display: flex; }

    .modal-box {
        background: var(--surface);
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        width: 90%;
        max-width: 480px;
        animation: modalIn .25s cubic-bezier(0.34, 1.56, 0.64, 1);
        overflow: hidden;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(.92) translateY(16px); }
        to   { opacity: 1; transform: scale(1)   translateY(0); }
    }

    .modal-box-header {
        padding: 1.4rem 1.5rem 1rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        border-bottom: 1px solid var(--border);
    }
    .modal-box-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(59, 130, 246, 0.12);
        color: var(--primary);
    }
    .modal-box-icon.semestral {
        background: rgba(16, 185, 129, 0.12);
        color: var(--secondary);
    }
    .modal-box-header h3 {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    .modal-box-header p {
        font-size: .82rem;
        color: var(--text-secondary);
        margin: .2rem 0 0;
    }

    .modal-box-body { padding: 1.25rem 1.5rem; }

    .modal-info-grid {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: .9rem 1rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem .75rem;
        margin-bottom: 1rem;
    }
    .modal-info-grid span   { color: var(--text-secondary); font-size: .78rem; display: block; margin-bottom: .1rem; }
    .modal-info-grid strong { font-size: .875rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }

    .modal-warning-box {
        background: rgba(245, 158, 11, 0.08);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: 8px;
        padding: .85rem 1rem;
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        font-size: .875rem;
        color: #92400e;
        line-height: 1.5;
    }
    .modal-warning-box i { font-size: 1.1rem; flex-shrink: 0; margin-top: .1rem; }

    .modal-box-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: .6rem;
        background: var(--background);
    }
    .modal-btn-cancel {
        padding: .65rem 1.3rem;
        background: var(--surface);
        color: var(--text-primary);
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    .modal-btn-cancel:hover { background: var(--background); border-color: var(--text-secondary); }

    .modal-btn-confirm {
        padding: .65rem 1.3rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .modal-btn-confirm:hover { transform: translateY(-1px); box-shadow: var(--shadow); opacity: .92; }

    .modal-btn-confirm.semestral-confirm {
        background: linear-gradient(135deg, var(--secondary), #059669);
    }

    /* Dynamic Component Manager Styles */
    .component-manager {
        background: var(--surface);
        border-top: 0;
        border-bottom: 2px solid var(--border);
        border-top: none;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .component-term-switcher {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: var(--background);
        margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        border-radius: 8px 8px 0 0;
    }

    .component-term-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .component-term-btn {
        padding: 0.4rem 1rem;
        border: 2px solid var(--border);
        border-radius: 6px;
        background: var(--surface);
        color: var(--text-primary);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }

    .component-term-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
    }

    .component-term-btn.active {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }
    
    .component-manager-header {
        display: none;
    }
    
    .component-lock-message {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--danger);
        color: #991b1b;
        padding: 1rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 500;
    }
    
    .category-block {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .category-header {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1rem;
    }
    
    .category-input-group {
        display: flex;
        flex-direction: column;
    }
    
    .category-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.4rem;
    }
    
    .category-input,
    .item-input,
    .weight-input {
        padding: 0.7rem 0.9rem;
        border: 2px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
        color: var(--text-primary);
        background: var(--surface);
        transition: var(--transition);
    }
    
    .category-input:focus,
    .item-input:focus,
    .weight-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .weight-input {
        width: 100%;
    }
    
    .category-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-remove {
        padding: 0.6rem 1rem;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    
    .btn-remove:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }
    
    .items-container {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .items-header {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
    }

    .items-example {
        color: var(--text-secondary);
        font-weight: 400;
    }
    
    .item-row {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .item-row:last-child {
        margin-bottom: 0;
    }
    
    .item-input-wrapper {
        flex: 1;
    }
    
    .item-input {
        width: 100%;
    }
    
    .item-display {
        display: inline-block;
        padding: 0.7rem 0.9rem;
        background: var(--background);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 0.9rem;
        word-break: break-word;
    }
    
    .btn-remove-item {
        padding: 0.6rem 0.8rem;
        background: #f97316;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        transition: var(--transition);
    }
    
    .btn-remove-item:hover {
        background: #ea580c;
    }
    
    .btn-add-item {
        padding: 0.6rem 1rem;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
        justify-content: center;
        margin-top: 0.5rem;
    }
    
    .btn-add-item:hover {
        background: #059669;
        transform: translateY(-2px);
    }
    
    .btn-add-category {
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .btn-add-category:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }

    .btn-edit-components {
        transition: var(--transition);
    }

    .btn-edit-components:hover {
        background: var(--primary-dark) !important;
        transform: translateY(-2px);
    }
    
    .component-manager.hidden {
        display: none;
    }
    
    .weight-total {
        padding: 1rem;
        background: rgba(59, 130, 246, 0.05);
        border: 1px solid var(--primary);
        border-radius: 6px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .weight-total-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .weight-total-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .weight-total-value.invalid {
        color: var(--danger);
    }
    
    .btn-save-components {
        padding: 0.85rem 2rem;
        background: linear-gradient(135deg, var(--secondary) 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        justify-content: center;
    }
    
    .btn-save-components:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }
    
    .btn-save-components:active {
        transform: translateY(0);
    }

    /* Input Mode Toggle */
    .mode-toggle {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .mode-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0;
        margin-right: 0.5rem;
    }

    .mode-button {
        padding: 0.6rem 1rem;
        border: 2px solid var(--border);
        border-radius: 6px;
        background: var(--surface);
        color: var(--text-primary);
        cursor: pointer;
        font-weight: 500;
        font-size: 0.85rem;
        transition: var(--transition);
    }

    .mode-button.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .mode-button:hover:not(.active) {
        border-color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
    }

    .mode-input-hidden {
        display: none;
    }
    
    .component-not-found {
        background: rgba(59, 130, 246, 0.05);
        border: 1px solid var(--primary);
        color: #1e40af;
        padding: 1rem;
        border-radius: 6px;
        margin: 0px 1.5rem 1.5rem 1.5rem;
    }
    
    .component-not-found-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .component-not-found h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }
    
    .component-not-found p {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    :root {
        --primary: #3B82F6;
        --primary-dark: #1E40AF;
        --secondary: #10B981;
        --accent: #F59E0B;
        --danger: #EF4444;
        --success: #22C55E;
        --surface: #FFFFFF;
        --background: #F8FAFC;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border: #E2E8F0;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --radius: 12px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .main-content {
        padding: 0.7rem 2rem 2rem 2rem;
        flex: 1;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-header h2 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-900);
        margin-bottom: 0.5rem;
    }

    .page-header p {
        color: var(--text-600);
        font-size: 0.9rem;
    }

    .alert-card {
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        margin-bottom: 2rem;
        border: 1px solid;
        position: relative;
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    @keyframes slideIn {
        from { transform: translateY(-10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        border-color: var(--success);
        color: #166534;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border-color: var(--danger);
        color: #991b1b;
    }

    .period-selector {
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .period-selector label {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .period-selector form {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .period-selector select {
        padding: 0.65rem 1rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        font-size: 0.9rem;
        color: var(--text-primary);
        background: var(--surface);
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        transition: var(--transition);
    }

    .period-selector select:hover {
        border-color: var(--primary);
    }

    .period-selector select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .section-grid-container {
        margin-bottom: 2rem;
    }

    .section-grid-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .section-card {
        background: var(--surface);
        border: 2px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem;
        text-decoration: none;
        color: var(--text-primary);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        cursor: pointer;
        position: relative;
    }

    .section-card:hover {
        border-color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .section-card.active {
        border-color: var(--primary);
        background: rgba(59, 130, 246, 0.08);
        box-shadow: var(--shadow);
    }

    .section-card.active .section-card-icon i {
        color: var(--primary);
    }

    .section-card.active .section-card-label {
        color: var(--primary);
        font-weight: 700;
    }

    .section-card-icon {
        width: 40px;
        height: 40px;
        background: rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: var(--text-secondary);
    }

    .section-card-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        text-align: center;
    }

    .section-card-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(16, 185, 129, 0.1);
        color: var(--secondary);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.15rem 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .table-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-md);
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
        overflow: hidden;
        transition: var(--transition);
    }

    .table-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .table-wrap {
        overflow-x: auto;
    }

    .subject-title {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        flex-direction: column;
    }
    
    .subject-title > div:first-child {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        width: 100%;
    }
    
    .subject-title .title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .subject-title .subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-approved {
        background: #f0fdf4;
        color: #166634;
        border: 1px solid #22c55e;
    }

    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .grades-table-container {
        padding: 0;
    }

    .grades-table {
        width: 100%;
        border-collapse: collapse;
    }

    .grades-table thead {
        background: var(--bg);
        border-bottom: 1px solid var(--border);
    }

    .grades-table th {
        padding: 1rem 1.25rem;
        text-align: center;
        font-weight: 700;
        color: var(--text-400);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .grades-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: var(--transition);
    }

    .grades-table tbody tr:hover {
        background: #f8f9ff;
    }

    .grades-table td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        color: black;
        text-align: center;
    }

    .term-tabs {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: var(--background);
    }

    .term-tabs-group {
        display: flex;
        gap: 0.5rem;
    }

    .term-tab {
        padding: 0.6rem 1.5rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        background: var(--surface);
        color: var(--text-secondary);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .term-tab:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
    }

    .term-tab.active {
        border-color: var(--primary);
        background: var(--primary);
        color: white;
    }

    .term-tabs .btn-edit-components {
        padding: 0.6rem 1.2rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        white-space: nowrap;
    }

    .term-tabs .btn-edit-components:hover {
        background: var(--primary-dark);
        opacity: 0.9;
    }

    .term-panel {
        display: none;
    }

    .term-panel.active {
        display: block;
    }

    .term-submit-bar {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        background: var(--background);
    }

    .submit-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 100px;
        justify-content: center;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .submit-btn:active {
        transform: translateY(0);
    }

    .no-data {
        background: var(--surface);
        border: 2px solid var(--border);
        border-radius: var(--r-md);
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .no-data-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .no-data h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .no-data p {
        font-size: 1rem;
        margin: 0;
    }

    /* Score Input Styling */
    .score-inputs {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        align-items: center;
        width: 100%;
    }

    .score-input {
        width: 85px;
        padding: 0.6rem 0.7rem;
        border: 2px solid var(--border);
        border-radius: 4px;
        font-size: 0.9rem;
        text-align: center;
        background: #ffffff;
        color: var(--text-primary);
        transition: var(--transition);
        -moz-appearance: textfield;
        pointer-events: auto;
    }

    .score-input::-webkit-outer-spin-button,
    .score-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .score-input:hover {
        border-color: var(--primary);
        background: #fafbff;
    }

    .score-input:focus {
        outline: none;
        border-color: var(--primary);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .score-input:disabled {
        background: #f0f0f0;
        color: var(--text-secondary);
        cursor: not-allowed;
        border-color: var(--border);
    }

    .score-input.score-pct {
        width: 90px;
    }

    .score-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Semestral Grade Section Styles */
    .semestral-section {
        border-top: 2px solid var(--border);
        margin-top: 0;
    }

    .semestral-header {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(16, 185, 129, 0.05));
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .semestral-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .semestral-title i {
        color: var(--primary);
        font-size: 1.25rem;
    }

    .semestral-table th {
        background: rgba(59, 130, 246, 0.05);
    }

    .grade-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    .grade-missing {
        color: var(--text-secondary);
        font-style: italic;
    }

    .semestral-action-bar {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--background);
    }

    .btn-print {
        padding: 0.75rem 1.5rem;
        background: var(--surface);
        color: var(--text-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-print:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .section-grid { grid-template-columns: repeat(2, 1fr); }
        .modal-info-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .section-grid { grid-template-columns: 1fr; }
    }

    /* ══════════════════════════════════════════
       REMINDER SECTION
    ══════════════════════════════════════════ */
    .reminder-section {
        background: var(--surface);
        border: 1px solid rgba(59,130,246,.2);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-top: 2rem;
    }

    .reminder-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(59,130,246,.04));
        border-bottom: 1px solid rgba(59,130,246,.2);
        font-size: 1rem;
        font-weight: 700;
        color: #1E40AF;
    }
    .reminder-header i { font-size: 1.25rem; color: #2563EB; }

    .reminder-body { padding: 1.5rem; }

    .reminder-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .reminder-item {
        display: flex;
        gap: 0.875rem;
        padding: 1rem 1.125rem;
        border-radius: 10px;
        border: 1px solid rgba(59,130,246,.18);
        background: rgba(59,130,246,.04);
        align-items: flex-start;
        width: 100%;
        box-sizing: border-box;
    }

    .reminder-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
        margin-top: 0.1rem;
        background: rgba(59,130,246,.12);
        color: #2563EB;
    }

    .reminder-item-title {
        font-size: 0.875rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
        color: #1E40AF;
    }

    .reminder-item-text {
        font-size: 0.825rem;
        line-height: 1.6;
        color: var(--text-secondary);
    }

    .reminder-item-text strong { font-weight: 700; }
    .reminder-item-text em     { font-style: italic; }

    /* All reminder types unified to blue scheme */
    .reminder-policy,
    .reminder-deadline,
    .reminder-lock,
    .reminder-semestral,
    .reminder-warning,
    .reminder-info {
        background: rgba(59,130,246,.04);
        border-color: rgba(59,130,246,.18);
    }

    .reminder-policy .reminder-item-icon,
    .reminder-deadline .reminder-item-icon,
    .reminder-lock .reminder-item-icon,
    .reminder-semestral .reminder-item-icon,
    .reminder-warning .reminder-item-icon,
    .reminder-info .reminder-item-icon {
        background: rgba(59,130,246,.12);
        color: #2563EB;
    }

    .reminder-policy .reminder-item-title,
    .reminder-deadline .reminder-item-title,
    .reminder-lock .reminder-item-title,
    .reminder-semestral .reminder-item-title,
    .reminder-warning .reminder-item-title,
    .reminder-info .reminder-item-title {
        color: #1E40AF;
    }
</style>

<!-- ══════════════════════════════════════════════
     GRADE SUBMISSION CONFIRMATION MODAL
══════════════════════════════════════════════ -->
<div class="modal-backdrop" id="gradeSubmitModal">
    <div class="modal-box" role="dialog" aria-modal="true">

        <div class="modal-box-header">
            <div class="modal-box-icon" id="modalBoxIcon">
                <i class='bx bx-send'></i>
            </div>
            <div>
                <h3 id="modalBoxTitle">Confirm Grade Submission</h3>
                <p id="modalBoxSubtitle">Review the details before submitting.</p>
            </div>
        </div>

        <div class="modal-box-body">
            <div class="modal-info-grid">
                <div>
                    <span>Subject</span>
                    <strong id="modalInfoSubject">—</strong>
                </div>
                <div>
                    <span>Term / Type</span>
                    <strong id="modalInfoTerm">—</strong>
                </div>
                <div>
                    <span>Section</span>
                    <strong id="modalInfoSection">—</strong>
                </div>
                <div>
                    <span>Academic Period</span>
                    <strong id="modalInfoPeriod">—</strong>
                </div>
            </div>
            <div class="modal-warning-box">
                <i class='bx bx-info-circle'></i>
                <span id="modalWarningText">Once submitted, grades will be locked and forwarded to the Registrar for approval. You may request a correction afterwards if needed.</span>
            </div>
        </div>

        <div class="modal-box-footer">
            <button type="button" class="modal-btn-cancel" id="modalBoxCancel">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalBoxConfirm">
                <i class='bx bx-send'></i>
                <span id="modalBtnLabel">Submit Grades</span>
            </button>
        </div>

    </div>
</div>

<script>
    // ===== DYNAMIC COMPONENT MANAGER =====
    
    function toggleComponentManager(subjectId, button) {
        let manager = null;
        const form = document.getElementById(`component-form-${subjectId}`);
        if (form) {
            manager = form.closest('.component-manager');
        }
        if (!manager) {
            manager = document.querySelector(`.component-manager[data-subject='${subjectId}']`);
        }
        if (!manager) return;
        manager.classList.toggle('hidden');
        if (manager.classList.contains('hidden')) {
            button.innerHTML = '<i class="bx bx-edit"></i> Edit Components';
        } else {
            button.innerHTML = '<i class="bx bx-hide"></i> Hide Components';
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Hide all component managers by default
        document.querySelectorAll('.component-manager').forEach(manager => {
            manager.classList.add('hidden');
        });
        
        const componentFormsData = <?= json_encode(
            array_map(function($subj) use ($conn) {
                return [
                    'subject_id' => $subj['subject_id'],
                    'terms' => [
                        'Prelim' => getGradeCategories($conn, $subj['subject_id'], 'Prelim'),
                        'Midterm' => getGradeCategories($conn, $subj['subject_id'], 'Midterm'),
                        'Finals' => getGradeCategories($conn, $subj['subject_id'], 'Finals'),
                    ]
                ];
            }, $subjects)
        ) ?>;
        
        componentFormsData.forEach(data => {
            initializeComponentManager(data.subject_id, data.terms['Prelim'] || []);
        });
        
        document.querySelectorAll('.btn-add-category').forEach(btn => {
            btn.addEventListener('click', function() {
                addCategory(this.dataset.subject);
            });
        });
        
        document.querySelectorAll('.component-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                saveComponents(this);
            });
        });

        document.querySelectorAll('.score-input').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('.grade-row');
                if (row) calculateRowGrade(row);
            });
        });

        // ── MODAL LOGIC ──
        const modal        = document.getElementById('gradeSubmitModal');
        const modalCancel  = document.getElementById('modalBoxCancel');
        const modalConfirm = document.getElementById('modalBoxConfirm');

        let pendingForm = null;

        // Intercept all term-grade forms
        document.querySelectorAll('.term-grade-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                pendingForm = this;

                const subjectTitle = this.closest('.table-card').querySelector('.subject-title .title')?.textContent?.trim() || '—';
                const term         = this.querySelector('input[name="term"]')?.value || '—';
                const section      = this.querySelector('input[name="section"]')?.value || '—';
                const period       = this.querySelector('input[name="academic_period"]')?.value || '—';

                document.getElementById('modalInfoSubject').textContent = subjectTitle;
                document.getElementById('modalInfoTerm').textContent    = term + ' Grades';
                document.getElementById('modalInfoSection').textContent  = section;
                document.getElementById('modalInfoPeriod').textContent  = period;
                document.getElementById('modalBoxTitle').textContent    = 'Confirm ' + term + ' Grade Submission';
                document.getElementById('modalBoxSubtitle').textContent = 'You are about to submit ' + term.toLowerCase() + ' grades for the selected students.';
                document.getElementById('modalWarningText').textContent = 'Once submitted, grades will be locked and forwarded to the Registrar for approval. You may request a correction afterwards if needed.';
                document.getElementById('modalBtnLabel').textContent    = 'Submit ' + term + ' Grades';
                document.getElementById('modalBoxIcon').className       = 'modal-box-icon';
                document.getElementById('modalBoxConfirm').className    = 'modal-btn-confirm';

                modal.classList.add('open');
            });
        });

        // Intercept all semestral forms
        document.querySelectorAll('.semestral-submit-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                pendingForm = this;

                const subjectTitle = this.closest('.table-card').querySelector('.subject-title .title')?.textContent?.trim() || '—';
                const section      = this.querySelector('input[name="section"]')?.value || '—';
                const period       = this.querySelector('input[name="academic_period"]')?.value || '—';

                document.getElementById('modalInfoSubject').textContent = subjectTitle;
                document.getElementById('modalInfoTerm').textContent    = 'Semestral Grade';
                document.getElementById('modalInfoSection').textContent  = section;
                document.getElementById('modalInfoPeriod').textContent  = period;
                document.getElementById('modalBoxTitle').textContent    = 'Confirm Semestral Grade Submission';
                document.getElementById('modalBoxSubtitle').textContent = 'You are about to submit the final semestral grade computed from Prelim, Midterm, and Finals.';
                document.getElementById('modalWarningText').textContent = 'The semestral grade is computed as: Prelim (30%) + Midterm (30%) + Finals (40%). This will be submitted to the Registrar for final approval.';
                document.getElementById('modalBtnLabel').textContent    = 'Submit Semestral Grade';
                document.getElementById('modalBoxIcon').className       = 'modal-box-icon semestral';
                document.getElementById('modalBoxConfirm').className    = 'modal-btn-confirm semestral-confirm';

                modal.classList.add('open');
            });
        });

        modalCancel.addEventListener('click', function() {
            modal.classList.remove('open');
            pendingForm = null;
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('open');
                pendingForm = null;
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                modal.classList.remove('open');
                pendingForm = null;
            }
        });

        modalConfirm.addEventListener('click', function() {
            if (pendingForm) {
                modal.classList.remove('open');
                // Show loading state on the button that was originally clicked
                modalConfirm.disabled = true;
                modalConfirm.innerHTML = '<i class="bx bx-loader-alt" style="animation:spin 1s linear infinite;"></i> Submitting...';
                pendingForm.submit();
            }
        });
    });
    
    function initializeComponentManager(subjectId, categories) {
        const container = document.querySelector(
            `#component-form-${subjectId} .categories-container`
        );
        if (!container) return;
        
        container.innerHTML = '';
        
        if (categories && categories.length > 0) {
            categories.forEach((cat, idx) => {
                renderCategoryBlock(subjectId, idx, cat);
            });
        }
        
        updateWeightTotal(subjectId);
    }
    
    function switchComponentTerm(subjectId, term) {
        const data = componentFormsData.find(d => d.subject_id == subjectId);
        if (!data) return;

        const categories = data.terms[term] || [];
        initializeComponentManager(subjectId, categories);

        const termInput = document.querySelector(`#component-form-${subjectId} input[name="term"]`);
        if (termInput) termInput.value = term;

        const card = document.querySelector(`[data-subject="${subjectId}"]`).closest('.table-card');
        if (card) {
            card.querySelectorAll('.component-term-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.term === term);
            });
        }
    }
    
    function renderCategoryBlock(subjectId, index, categoryData = null) {
        const container = document.querySelector(
            `#component-form-${subjectId} .categories-container`
        );
        if (!container) return;
        
        const categoryName = categoryData ? categoryData.category_name : '';
        const weight = categoryData ? categoryData.weight : 0;
        const inputMode = categoryData ? (categoryData.input_mode || 'raw') : 'raw';
        const items = categoryData ? categoryData.items : [];
        
        const block = document.createElement('div');
        block.className = 'category-block';
        block.dataset.categoryIndex = index;
        
        let itemsHtml = items.map((item, itemIdx) => `
            <div class="item-row">
                <div class="item-input-wrapper">
                    <input type="text"
                           class="item-input"
                           name="categories[${index}][items][]"
                           value="${escapeHtml(item.item_label || item)}"
                           placeholder="e.g., Quiz #1">
                </div>
                <button type="button" class="btn-remove-item" onclick="removeItemRow(this)">
                    <i class='bx bx-trash'></i>
                </button>
            </div>
        `).join('');
        
        block.innerHTML = `
            <div class="category-header">
                <div class="category-input-group">
                    <label class="category-label">Category Name</label>
                    <input type="text" class="category-input category-name" 
                           name="categories[${index}][name]" 
                           value="${escapeHtml(categoryName)}" 
                           placeholder="e.g., Quizzes"
                           onchange="updateWeightTotal(${subjectId})">
                </div>
                <div class="category-input-group">
                    <label class="category-label">Weight (%)</label>
                    <input type="number" class="weight-input category-weight" 
                           name="categories[${index}][weight]" 
                           min="0" max="100" step="0.01"
                           value="${weight}" 
                           placeholder="0.00"
                           onchange="updateWeightTotal(${subjectId})">
                </div>
                <div class="category-input-group">
                    <label class="category-label">Input Mode</label>
                    <div class="mode-toggle">
                        <button type="button" class="mode-button ${inputMode === 'raw' ? 'active' : ''}" 
                                onclick="toggleMode(this, '${index}', 'raw')">
                            Raw / Max
                        </button>
                        <button type="button" class="mode-button ${inputMode === 'percentage' ? 'active' : ''}" 
                                onclick="toggleMode(this, '${index}', 'percentage')">
                            Direct %
                        </button>
                        <input type="hidden" class="category-mode" 
                               name="categories[${index}][mode]" 
                               value="${inputMode}">
                    </div>
                </div>
                <div class="category-actions">
                    <button type="button" class="btn-remove" onclick="removeCategory(this, ${subjectId})">
                        <i class='bx bx-trash'></i> Remove
                    </button>
                </div>
            </div>
            
            <div class="items-container">
                <div class="items-header">Items <span class="items-example">(e.g., Quiz #1, Quiz #2)</span></div>
                ${itemsHtml}
                <button type="button" class="btn-add-item" onclick="addItemRow(this)">
                    <i class='bx bx-plus'></i> Add Item
                </button>
            </div>
        `;
        
        container.appendChild(block);
    }
    
    function toggleMode(button, categoryIndex, mode) {
        const buttons = button.parentElement.querySelectorAll('.mode-button');
        buttons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        const hiddenInput = button.parentElement.querySelector('.category-mode');
        if (hiddenInput) hiddenInput.value = mode;
    }
    
    function addCategory(subjectId) {
        const form = document.getElementById(`component-form-${subjectId}`);
        const container = form.querySelector('.categories-container');
        const existingBlocks = container.querySelectorAll('.category-block');
        const newIndex = existingBlocks.length;
        renderCategoryBlock(subjectId, newIndex);
        updateWeightTotal(subjectId);
    }
    
    function addItemRow(button) {
        const itemsContainer = button.parentElement;
        const itemRow = document.createElement('div');
        itemRow.className = 'item-row';
        const categoryBlock = button.closest('.category-block');
        const categoryIndex = categoryBlock.dataset.categoryIndex;
        itemRow.innerHTML = `
            <div class="item-input-wrapper">
                <input type="text" class="item-input" 
                       name="categories[${categoryIndex}][items][]" 
                       placeholder="e.g., Quiz #1">
            </div>
            <button type="button" class="btn-remove-item" onclick="removeItemRow(this)">
                <i class='bx bx-trash'></i>
            </button>
        `;
        button.parentElement.insertBefore(itemRow, button);
    }
    
    function removeItemRow(button) {
        button.closest('.item-row').remove();
    }
    
    function removeCategory(button, subjectId) {
        button.closest('.category-block').remove();
        updateWeightTotal(subjectId);
    }
    
    function updateWeightTotal(subjectId) {
        const form = document.getElementById(`component-form-${subjectId}`);
        if (!form) return;
        const weights = Array.from(form.querySelectorAll('.category-weight'))
            .map(input => parseFloat(input.value) || 0)
            .reduce((a, b) => a + b, 0);
        const totalElement = document.querySelector(`.weight-total-value[data-subject="${subjectId}"]`);
        if (totalElement) {
            totalElement.textContent = weights.toFixed(2) + '%';
            totalElement.classList.toggle('invalid', Math.abs(weights - 100) > 0.01);
        }
    }
    
    function saveComponents(form) {
        const subjectId = form.dataset.subject;
        const categoryBlocks = form.querySelectorAll('.category-block');
        const categories = [];

        categoryBlocks.forEach(block => {
            const nameInput   = block.querySelector('.category-name');
            const weightInput = block.querySelector('.category-weight');
            const modeInput   = block.querySelector('.category-mode');
            const categoryName = nameInput ? nameInput.value.trim() : '';
            const weight       = weightInput ? parseFloat(weightInput.value) || 0 : 0;
            const mode         = modeInput ? modeInput.value || 'raw' : 'raw';
            if (!categoryName) return;
            const items = [];
            block.querySelectorAll('.item-row').forEach(row => {
                const inputField = row.querySelector('input.item-input[type="text"]');
                if (inputField) {
                    const val = inputField.value.trim();
                    if (val) items.push(val);
                }
            });
            categories.push({ name: categoryName, weight, mode, items });
        });

        const weightTotal = categories.reduce((sum, cat) => sum + cat.weight, 0);
        if (Math.abs(weightTotal - 100) > 0.01) {
            alert('Component weights must sum to 100%. Currently: ' + weightTotal.toFixed(2) + '%');
            return;
        }

        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (!csrfInput) { alert('Security token missing. Please refresh the page and try again.'); return; }

        const formData = new FormData();
        formData.append('action', 'save_components');
        formData.append('subject_id', subjectId);
        formData.append('csrf_token', csrfInput.value);
        const termInput = form.querySelector('input[name="term"]');
        const term = termInput ? termInput.value : 'Prelim';
        formData.append('term', term);

        categories.forEach((cat, catIdx) => {
            formData.append(`categories[${catIdx}][name]`, cat.name);
            formData.append(`categories[${catIdx}][weight]`, cat.weight);
            formData.append(`categories[${catIdx}][mode]`, cat.mode);
            cat.items.forEach(item => { formData.append(`categories[${catIdx}][items][]`, item); });
        });

        const submitBtn = form.querySelector('.btn-save-components');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bx bx-loader-alt" style="animation: spin 1s linear infinite;"></i> Saving...';

        fetch('?page=submit_grades', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert-card alert-success';
                alertDiv.innerHTML = '<i class="bx bx-check-circle"></i> ' + data.message;
                alertDiv.style.marginBottom = '1rem';
                form.parentElement.insertBefore(alertDiv, form);
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transition = 'opacity 0.3s ease-out';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 2000);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                alert('Error: ' + data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Save components error:', error);
            alert('Error saving components. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }
    
    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function switchTab(btn, panelId) {
        const tabGroup = btn.closest('.term-tabs');
        tabGroup.querySelectorAll('.term-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        const card = btn.closest('.table-card');
        card.querySelectorAll('.term-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(panelId).classList.add('active');
    }

    function syncMaxInputs(changedInput) {
        const itemId = changedInput.dataset.item;
        const value = changedInput.value;
        const termPanel = changedInput.closest('.term-panel');
        if (!termPanel) return;
        termPanel.querySelectorAll(`.score-max[data-item="${itemId}"]`).forEach(input => {
            if (input !== changedInput && !input.disabled) { input.value = value; }
        });
    }

    function computeNumericGrade(pct) {
        if (pct >= 98) return { grade: '1.00', remarks: 'Excellent' };
        if (pct >= 95) return { grade: '1.25', remarks: 'Excellent' };
        if (pct >= 92) return { grade: '1.50', remarks: 'Very Good' };
        if (pct >= 89) return { grade: '1.75', remarks: 'Very Good' };
        if (pct >= 86) return { grade: '2.00', remarks: 'Good' };
        if (pct >= 83) return { grade: '2.25', remarks: 'Good' };
        if (pct >= 80) return { grade: '2.50', remarks: 'Good' };
        if (pct >= 77) return { grade: '2.75', remarks: 'Satisfactory' };
        if (pct >= 75) return { grade: '3.00', remarks: 'Passed' };
        return { grade: '5.00', remarks: 'Failed' };
    }

    function calculateRowGrade(row) {
        const scores = {};
        let hasValidData = false;
        row.querySelectorAll('.score-input').forEach(input => {
            const cat  = parseInt(input.dataset.cat);
            const item = parseInt(input.dataset.item);
            const isRaw = input.classList.contains('score-raw');
            const value = parseFloat(input.value) || 0;
            if (!scores[cat]) scores[cat] = {};
            if (!scores[cat][item]) scores[cat][item] = {};
            if (isRaw) scores[cat][item].raw = value;
            else scores[cat][item].max = value;
        });
        for (const cat in scores) {
            for (const item in scores[cat]) {
                const raw = scores[cat][item].raw || 0;
                const max = scores[cat][item].max || 0;
                if (max > 0) { hasValidData = true; }
            }
        }
    }

    function printSemestralGrade(subjectId) {
        const section = document.querySelector('input[name="section"]')?.value ?? '';
        const period  = document.querySelector('input[name="academic_period"]')?.value ?? '';
        const table   = document.querySelector(`#semestral-form-${subjectId} .semestral-table`);
        if (!table) return;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html><html>
            <head><title>Semestral Grade Sheet</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 2rem; color: #000; }
                h2 { margin-bottom: 0.25rem; }
                p { margin-bottom: 1rem; color: #555; font-size: 0.9rem; }
                table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
                th, td { border: 1px solid #ccc; padding: 0.6rem 0.875rem; text-align: center; font-size: 0.875rem; }
                th { background: #f0f4ff; font-weight: 700; }
                td:first-child { text-align: left; }
                @media print { body { padding: 0; } }
            </style>
            </head>
            <body>
                <h2>Semestral Grade Sheet</h2>
                <p>Section: <strong>${section}</strong> &nbsp;|&nbsp; Period: <strong>${period}</strong></p>
                ${table.outerHTML}
                <script>window.onload = function() { window.print(); }<\/script>
            </body></html>
        `);
        printWindow.document.close();
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertCards = document.querySelectorAll('.alert-card');
        alertCards.forEach(card => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => card.remove(), 300);
            }, 3600);
        });
    });
</script>

<!-- Flash Messages -->
<?php if ($flash = getFlash()): ?>
    <div class="alert-card <?= $flash['type'] === 'error' ? 'alert-error' : 'alert-success' ?>">
        <i class='bx <?= $flash['type'] === 'error' ? 'bx-error-circle' : 'bx-check-circle' ?>'></i>
        <?= htmlspecialchars($flash['msg'], ENT_QUOTES) ?>
    </div>
<?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h2>Encode Grades</h2>
        <p>Encode and submit student grades for your assigned subjects</p>
    </div>

    <!-- Period Selector -->
    <div class="period-selector">
        <form method="GET" action="" id="periodForm">
            <input type="hidden" name="page" value="submit_grades">
            <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section, ENT_QUOTES) ?>">
            <label for="period">Academic Period:</label>
            <select id="period" name="period" onchange="document.getElementById('periodForm').submit()">
                <?php foreach ($periods as $period): ?>
                    <option value="<?= htmlspecialchars($period, ENT_QUOTES) ?>" 
                        <?= $period === $selected_period ? 'selected' : '' ?>>
                        <?= htmlspecialchars($period, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Section Grid -->
    <?php if (!empty($selected_period)): ?>
    <div class="section-grid-container">
        <h3 class="section-grid-title">
            <i class='bx bx-grid-alt'></i> Select a Section
        </h3>
        <div class="section-grid">
            <?php foreach ($all_sections as $sec): ?>
                <?php
                $is_active = $sec === $selected_section;
                $has_data  = $sec === 'BSIT-32011-IM';
                ?>
                <a href="?page=submit_grades&period=<?= urlencode($selected_period) ?>&section=<?= urlencode($sec) ?>"
                   class="section-card <?= $is_active ? 'active' : '' ?>">
                    <div class="section-card-icon"><i class='bx bx-group'></i></div>
                    <div class="section-card-label"><?= htmlspecialchars($sec, ENT_QUOTES) ?></div>
                    <?php if ($has_data): ?>
                        <div class="section-card-badge">Live Data</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Subject Tables -->
    <div class="content-section">

    <?php if (!empty($selected_section) && count($subjects) > 0): ?>
        <?php foreach ($subjects as $subject): ?>
            <?php
            $enrolled_students = [];
            $stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.program, u.year_level, u.section FROM enrollments e JOIN users u ON e.student_id = u.user_id WHERE e.subject_id = ? AND e.status = 'Active' AND u.section = ? ORDER BY u.full_name");
            $stmt->bind_param("is", $subject['subject_id'], $selected_section);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) { $enrolled_students[] = $row; }
            $stmt->close();
            ?>
            <div class="table-card">
                <!-- Subject Title & Component Manager Header -->
                <div class="subject-title">
                    <div style="display: flex; justify-content: space-between; align-items: start; width: 100%;">
                        <div>
                            <div class="title"><?= htmlspecialchars($subject['subject_code'], ENT_QUOTES) ?></div>
                            <div class="subtitle"><?= htmlspecialchars($subject['subject_name'], ENT_QUOTES) ?></div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 1.1rem; color: var(--text-primary); font-weight: 600;">
                            <i class='bx bx-layer' style="font-size:1.3rem;"></i>
                            <span>Grade Components</span>
                        </div>
                    </div>
                </div>
                
                <!-- Dynamic Component Manager -->
                <div class="component-manager" data-subject="<?= $subject['subject_id'] ?>">
                    <div class="component-term-switcher">
                        <span class="component-term-label">Editing components for:</span>
                        <button type="button" class="component-term-btn active" data-term="Prelim"
                            onclick="switchComponentTerm(<?= $subject['subject_id'] ?>, 'Prelim')">Prelim</button>
                        <button type="button" class="component-term-btn" data-term="Midterm"
                            onclick="switchComponentTerm(<?= $subject['subject_id'] ?>, 'Midterm')">Midterm</button>
                        <button type="button" class="component-term-btn" data-term="Finals"
                            onclick="switchComponentTerm(<?= $subject['subject_id'] ?>, 'Finals')">Finals</button>
                    </div>

                    <form id="component-form-<?= $subject['subject_id'] ?>" class="component-form" method="POST" data-subject="<?= $subject['subject_id'] ?>">
                        <input type="hidden" name="action" value="save_components">
                        <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                        <div class="categories-container"></div>
                        <button type="button" class="btn-add-category" data-subject="<?= $subject['subject_id'] ?>">
                            <i class='bx bx-plus'></i> Add Category
                        </button>
                        <div class="weight-total">
                            <span class="weight-total-label">Total Weight</span>
                            <div class="weight-total-value" data-subject="<?= $subject['subject_id'] ?>">0%</div>
                        </div>
                        <button type="submit" class="btn-save-components">
                            <i class='bx bx-save'></i> Save Components
                        </button>
                    </form>
                </div>
                
                <?php 
                $prelim_categories = getGradeCategories($conn, $subject['subject_id'], 'Prelim');
                $has_any_grades = hasGradesForSubject($conn, $subject['subject_id'], 'Prelim') || 
                                  hasGradesForSubject($conn, $subject['subject_id'], 'Midterm') || 
                                  hasGradesForSubject($conn, $subject['subject_id'], 'Finals');
                ?>
                <?php if (empty($prelim_categories) && !$has_any_grades): ?>
                    <div class="component-not-found">
                        <div class="component-not-found-icon"><i class='bx bx-info-circle'></i></div>
                        <h4>Define grade components above before encoding grades.</h4>
                        <p>Add categories and items using the component manager above.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Term Tabs -->
                <div class="term-tabs">
                    <div class="term-tabs-group">
                        <button class="term-tab active" onclick="switchTab(this, 'prelim-<?= $subject['subject_id'] ?>')">
                            <i class='bx bx-book-open'></i> Prelim
                        </button>
                        <button class="term-tab" onclick="switchTab(this, 'midterm-<?= $subject['subject_id'] ?>')">
                            <i class='bx bx-book-open'></i> Midterm
                        </button>
                        <button class="term-tab" onclick="switchTab(this, 'finals-<?= $subject['subject_id'] ?>')">
                            <i class='bx bx-book-open'></i> Finals
                        </button>
                    </div>
                    <button type="button" class="btn-edit-components" onclick="toggleComponentManager(<?= $subject['subject_id'] ?>, this)">
                        <i class='bx bx-edit'></i> Edit Components
                    </button>
                </div>

                <?php foreach (['Prelim', 'Midterm', 'Finals'] as $term_index => $term): ?>
                    <?php
                    $term_id   = strtolower($term) . '-' . $subject['subject_id'];
                    $is_first  = $term_index === 0;
                    $categories = getGradeCategories($conn, $subject['subject_id'], $term);
                    $has_grades = hasGradesForSubject($conn, $subject['subject_id'], $term);
                    ?>
                    <div class="term-panel <?= $is_first ? 'active' : '' ?>" id="<?= $term_id ?>">
                        <!-- NOTE: added class term-grade-form so the modal JS can intercept it -->
                        <form method="POST" class="term-grade-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                            <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                            <input type="hidden" name="academic_period" value="<?= htmlspecialchars($selected_period, ENT_QUOTES) ?>">
                            <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section, ENT_QUOTES) ?>">
                            <input type="hidden" name="term" value="<?= $term ?>">

                            <div class="table-wrap">
                                <?php if (empty($categories) && $has_grades): ?>
                                    <div class="no-data">
                                        <div class="no-data-icon"><i class='bx bx-error'></i></div>
                                        <h3>No grade components configured.</h3>
                                        <p>Components cannot be modified after grades have been submitted.</p>
                                    </div>
                                <?php elseif (empty($categories)): ?>
                                    <!-- Already shown above -->
                                <?php elseif (empty($enrolled_students)): ?>
                                    <div class="no-data">
                                        <div class="no-data-icon"><i class='bx bx-user-x'></i></div>
                                        <h3>No active students enrolled in this section.</h3>
                                    </div>
                                <?php else: ?>
                                <table class="grades-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width:180px;">Student</th>
                                            <?php foreach ($categories as $cat): ?>
                                                <th colspan="<?= count($cat['items']) ?: 1 ?>" style="text-align:center;">
                                                    <?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>
                                                    <br><span style="font-weight:400;font-size:0.75em;">(<?= $cat['weight'] ?>%)</span>
                                                </th>
                                            <?php endforeach; ?>
                                            <th>Weighted %</th>
                                            <th>Grade</th>
                                            <th>Remarks</th>
                                            <th>Status</th>
                                        </tr>
                                        <tr>
                                            <th></th>
                                            <?php foreach ($categories as $cat): ?>
                                                <?php if (!empty($cat['items'])): ?>
                                                    <?php foreach ($cat['items'] as $item): ?>
                                                        <th style="text-align:center;font-size:0.85em;font-weight:500;">
                                                            <?= htmlspecialchars($item['item_label'], ENT_QUOTES) ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <th></th>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <th></th><th></th><th></th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrolled_students as $student): ?>
                                            <?php
                                            $grade_status   = getGradeStatus($conn, $student['user_id'], $subject['subject_id'], $selected_period, $term);
                                            $is_locked      = $grade_status && $grade_status['status'] === 'Approved' && $grade_status['is_locked'];
                                            $existing_scores = getExistingScores($conn, $student['user_id'], $subject['subject_id'], $selected_period, $term);
                                            ?>
                                            <tr class="grade-row">
                                                <td style="text-align:left;">
                                                    <strong><?= htmlspecialchars($student['full_name'], ENT_QUOTES) ?></strong>
                                                </td>
                                                <?php foreach ($categories as $cat): ?>
                                                    <?php if (!empty($cat['items'])): ?>
                                                        <?php foreach ($cat['items'] as $item): ?>
                                                            <td style="text-align:center; min-width:120px; padding:0.75rem;">
                                                                <?php if ($is_locked): ?>
                                                                    <div style="color:var(--text-secondary);font-size:0.85em;">Locked</div>
                                                                <?php else: ?>
                                                                    <?php if ($cat['input_mode'] === 'percentage'): ?>
                                                                        <div class="score-inputs">
                                                                            <?php
                                                                            $pct_value = '';
                                                                            if (isset($existing_scores[$item['item_id']])) {
                                                                                $s = $existing_scores[$item['item_id']];
                                                                                $pct_value = ($s['max_score'] == 100) ? $s['raw_score'] : ($s['max_score'] > 0 ? ($s['raw_score'] / $s['max_score']) * 100 : '');
                                                                            }
                                                                            ?>
                                                                            <input type="number" class="score-input score-pct"
                                                                                name="scores[<?= $student['user_id'] ?>][<?= $cat['category_id'] ?>][<?= $item['item_id'] ?>][raw]"
                                                                                data-student="<?= $student['user_id'] ?>" data-cat="<?= $cat['category_id'] ?>" data-item="<?= $item['item_id'] ?>"
                                                                                min="0" max="100" step="0.01" placeholder="0"
                                                                                <?php if ($pct_value !== '' && $pct_value !== '0') echo 'value="' . floatval($pct_value) . '"'; ?>
                                                                                <?= $is_locked ? 'disabled' : '' ?>>
                                                                            <input type="hidden" name="scores[<?= $student['user_id'] ?>][<?= $cat['category_id'] ?>][<?= $item['item_id'] ?>][max]" value="100">
                                                                            <span class="score-label">%</span>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="score-inputs">
                                                                            <input type="number" class="score-input score-raw"
                                                                                name="scores[<?= $student['user_id'] ?>][<?= $cat['category_id'] ?>][<?= $item['item_id'] ?>][raw]"
                                                                                data-student="<?= $student['user_id'] ?>" data-cat="<?= $cat['category_id'] ?>" data-item="<?= $item['item_id'] ?>"
                                                                                min="0" step="0.01" placeholder="Raw"
                                                                                value="<?= isset($existing_scores[$item['item_id']]) ? htmlspecialchars($existing_scores[$item['item_id']]['raw_score']) : '' ?>"
                                                                                <?= $is_locked ? 'disabled' : '' ?>>
                                                                            <input type="number" class="score-input score-max"
                                                                                name="scores[<?= $student['user_id'] ?>][<?= $cat['category_id'] ?>][<?= $item['item_id'] ?>][max]"
                                                                                data-student="<?= $student['user_id'] ?>" data-cat="<?= $cat['category_id'] ?>" data-item="<?= $item['item_id'] ?>"
                                                                                min="0" step="0.01" placeholder="Max"
                                                                                value="<?= isset($existing_scores[$item['item_id']]) ? htmlspecialchars($existing_scores[$item['item_id']]['max_score']) : '' ?>"
                                                                                oninput="syncMaxInputs(this)"
                                                                                <?= $is_locked ? 'disabled' : '' ?>>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <td></td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <td style="text-align:center;font-weight:600;">
                                                    <?= $grade_status ? number_format($grade_status['percentage'], 2) . '%' : '—' ?>
                                                </td>
                                                <td style="text-align:center;font-weight:600;">
                                                    <?= $grade_status ? htmlspecialchars($grade_status['numeric_grade'], ENT_QUOTES) : '—' ?>
                                                </td>
                                                <td style="text-align:center;font-size:0.9em;">
                                                    <?= $grade_status ? htmlspecialchars($grade_status['remarks'], ENT_QUOTES) : '—' ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_locked): ?>
                                                        <span class="badge-status badge-approved"><i class='bx bx-check-circle'></i> Approved</span>
                                                    <?php elseif ($grade_status && $grade_status['status'] === 'Pending'): ?>
                                                        <span class="badge-status badge-pending"><i class='bx bx-time'></i> Pending</span>
                                                    <?php elseif ($grade_status && $grade_status['status'] === 'Returned'): ?>
                                                        <span class="badge-status badge-pending" style="background:#fef9c3;color:#92400e;"><i class='bx bx-undo'></i> Returned</span>
                                                    <?php else: ?>
                                                        <span class="badge-status badge-pending"><i class='bx bx-time'></i> Not Submitted</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($enrolled_students)): ?>
                                <div class="term-submit-bar">
                                    <!-- type="submit" triggers the modal interceptor -->
                                    <button type="submit" class="submit-btn">
                                        <i class='bx bx-send'></i>
                                        Submit <?= $term ?> Grades
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php $semestral_rows = getSemestralGrades($conn, $subject['subject_id'], $selected_period, $selected_section); ?>

                <div class="semestral-section">
                    <div class="semestral-header">
                        <div class="semestral-title">
                            <i class='bx bx-trophy'></i>
                            Semestral Grade
                        </div>
                    </div>

                    <?php if (empty($semestral_rows)): ?>
                        <div class="no-data">
                            <div class="no-data-icon"><i class='bx bx-trophy'></i></div>
                            <h3>No Semestral Grades Yet</h3>
                            <p>Submit Prelim, Midterm, and Finals grades first to generate the semestral grade.</p>
                        </div>
                    <?php else: ?>
                        <!-- NOTE: added class semestral-submit-form so the modal JS can intercept it -->
                        <form method="POST" id="semestral-form-<?= $subject['subject_id'] ?>" class="semestral-submit-form">
                            <input type="hidden" name="action" value="submit_semestral">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                            <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                            <input type="hidden" name="academic_period" value="<?= htmlspecialchars($selected_period, ENT_QUOTES) ?>">
                            <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section, ENT_QUOTES) ?>">

                            <div class="table-wrap">
                                <table class="grades-table semestral-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; min-width:180px;">Student</th>
                                            <th>Prelim (30%)</th>
                                            <th>Midterm (30%)</th>
                                            <th>Finals (40%)</th>
                                            <th>Final Grade</th>
                                            <th>Numeric</th>
                                            <th>Remarks</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($semestral_rows as $row): ?>
                                            <?php
                                            $computed_final = $row['computed_final'];
                                            $final_numeric = null;
                                            $final_remarks = null;
                                            if ($computed_final !== null) {
                                                list($final_numeric, $final_remarks) = convertGrade($computed_final);
                                            }
                                            $is_submitted = isset($row['status']) && in_array($row['status'], ['Submitted', 'Approved']);
                                            ?>
                                            <tr>
                                                <td style="text-align:left;">
                                                    <strong><?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?></strong>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_submitted): ?>
                                                        <?= $row['prelim_grade'] !== null ? number_format($row['prelim_grade'], 2) . '%' : '—' ?>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][prelim]" value="<?= $row['prelim_grade'] ?? 0 ?>">
                                                    <?php else: ?>
                                                        <span class="<?= $row['computed_prelim'] ? 'grade-value' : 'grade-missing' ?>">
                                                            <?= $row['computed_prelim'] !== null ? number_format($row['computed_prelim'], 2) . '%' : '—' ?>
                                                        </span>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][prelim]" value="<?= $row['computed_prelim'] ?? 0 ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_submitted): ?>
                                                        <?= $row['midterm_grade'] !== null ? number_format($row['midterm_grade'], 2) . '%' : '—' ?>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][midterm]" value="<?= $row['midterm_grade'] ?? 0 ?>">
                                                    <?php else: ?>
                                                        <span class="<?= $row['computed_midterm'] ? 'grade-value' : 'grade-missing' ?>">
                                                            <?= $row['computed_midterm'] !== null ? number_format($row['computed_midterm'], 2) . '%' : '—' ?>
                                                        </span>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][midterm]" value="<?= $row['computed_midterm'] ?? 0 ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_submitted): ?>
                                                        <?= $row['finals_grade'] !== null ? number_format($row['finals_grade'], 2) . '%' : '—' ?>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][finals]" value="<?= $row['finals_grade'] ?? 0 ?>">
                                                    <?php else: ?>
                                                        <span class="<?= $row['computed_finals'] ? 'grade-value' : 'grade-missing' ?>">
                                                            <?= $row['computed_finals'] !== null ? number_format($row['computed_finals'], 2) . '%' : '—' ?>
                                                        </span>
                                                        <input type="hidden" name="semestral[<?= $row['user_id'] ?>][finals]" value="<?= $row['computed_finals'] ?? 0 ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center; font-weight:700; color:var(--primary);">
                                                    <?php if ($is_submitted): ?>
                                                        <?= $row['final_grade'] !== null ? number_format($row['final_grade'], 2) . '%' : '—' ?>
                                                    <?php else: ?>
                                                        <?= $computed_final !== null ? number_format($computed_final, 2) . '%' : '—' ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center; font-weight:700;">
                                                    <?php if ($is_submitted): ?>
                                                        <?= $row['final_numeric'] !== null ? number_format($row['final_numeric'], 2) : '—' ?>
                                                    <?php else: ?>
                                                        <?= $final_numeric !== null ? number_format($final_numeric, 2) : '—' ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($is_submitted): ?>
                                                        <?= htmlspecialchars($row['final_remarks'] ?? '—', ENT_QUOTES) ?>
                                                    <?php else: ?>
                                                        <?= $final_remarks ?? '—' ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($row['status'] === 'Approved'): ?>
                                                        <span class="badge-status badge-approved"><i class='bx bx-check-circle'></i> Approved</span>
                                                    <?php elseif ($row['status'] === 'Submitted'): ?>
                                                        <span class="badge-status badge-pending"><i class='bx bx-time'></i> Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge-status badge-pending"><i class='bx bx-time'></i> Draft</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="semestral-action-bar">
                                <button type="button" class="btn-print" onclick="printSemestralGrade(<?= $subject['subject_id'] ?>)">
                                    <i class='bx bx-printer'></i>
                                    Print Semestral Grade
                                </button>
                                <!-- type="submit" triggers the modal interceptor -->
                                <button type="submit" class="submit-btn">
                                    <i class='bx bx-send'></i>
                                    Submit Semestral Grade
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <?php
                $weight_sum = 0;
                foreach ($categories as $cat) { $weight_sum += floatval($cat['weight']); }
                ?>
                <?php if (!empty($categories) && abs($weight_sum - 100) > 0.01): ?>
                    <div class="alert-card alert-error" style="margin:1rem;">
                        Warning: <?= $term ?> grade component weights do not sum to 100% (current: <?= number_format($weight_sum, 2) ?>%). Please update the components before encoding grades.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
        <?php if (empty($selected_section) && !empty($selected_period)): ?>
            <div class="no-data">
                <div class="no-data-icon"><i class='bx bx-info-circle'></i></div>
                <h3>Select a Section</h3>
                <p>Choose a section from the grid above to view grades.</p>
            </div>
        <?php else: ?>
            <div class="no-data">
                <div class="no-data-icon"><i class='bx bx-book-open'></i></div>
                <h3>No Data Available</h3>
                <p>No students enrolled in your subjects for this section.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════
     REMINDER SECTION
══════════════════════════════════════════ -->
<div class="reminder-section">
    <div class="reminder-header">
        <i class='bx bx-info-circle'></i>
        <span>Reminders on Grade Encoding & Submission</span>
    </div>
    <div class="reminder-body">
        <div class="reminder-grid">

            <div class="reminder-item reminder-policy">
                <div class="reminder-item-icon"><i class='bx bx-edit-alt'></i></div>
                <div>
                    <div class="reminder-item-title">Accuracy is Your Responsibility</div>
                    <div class="reminder-item-text">
                        Faculty members are solely accountable for the accuracy of grades they encode. Review all scores carefully before submitting. Once submitted, grades are forwarded to the Registrar for approval and <strong>cannot be edited</strong> without a formal correction request.
                    </div>
                </div>
            </div>

            <div class="reminder-item reminder-deadline">
                <div class="reminder-item-icon"><i class='bx bx-calendar-check'></i></div>
                <div>
                    <div class="reminder-item-title">Submission Deadlines</div>
                    <div class="reminder-item-text">
                        Submit grades within the deadline set by the Registrar's Office for each term (Prelim, Midterm, Finals). Late submissions may affect the official release of student grade reports and transcripts.
                    </div>
                </div>
            </div>

            <div class="reminder-item reminder-lock">
                <div class="reminder-item-icon"><i class='bx bx-lock-alt'></i></div>
                <div>
                    <div class="reminder-item-title">Grades Lock Upon Submission</div>
                    <div class="reminder-item-text">
                        Submitted grades are immediately <strong>locked</strong> and placed in <em>Pending</em> status awaiting Registrar approval. Once the Registrar approves them, grades become <strong>permanently locked</strong>. Changes after this point require a formal Grade Correction Request.
                    </div>
                </div>
            </div>

            <div class="reminder-item reminder-semestral">
                <div class="reminder-item-icon"><i class='bx bx-trophy'></i></div>
                <div>
                    <div class="reminder-item-title">Semestral Grade Formula</div>
                    <div class="reminder-item-text">
                        The semestral grade is computed as:
                        <strong>Prelim (30%) + Midterm (30%) + Finals (40%)</strong>.
                        All three term grades must be submitted and approved before the semestral grade can be finalized. Ensure term grades are complete and correct before submitting the semestral grade.
                    </div>
                </div>
            </div>

            <div class="reminder-item reminder-warning">
                <div class="reminder-item-icon"><i class='bx bx-error-alt'></i></div>
                <div>
                    <div class="reminder-item-title">Academic Integrity</div>
                    <div class="reminder-item-text">
                        Deliberately encoding incorrect grades, inflating or deflating scores, or submitting grades without a legitimate basis constitutes academic misconduct. All submissions are logged and subject to audit by the Registrar and academic administration.
                    </div>
                </div>
            </div>

            <div class="reminder-item reminder-info">
                <div class="reminder-item-icon"><i class='bx bx-support'></i></div>
                <div>
                    <div class="reminder-item-title">Need to Correct a Grade?</div>
                    <div class="reminder-item-text">
                        If you discover an error after submission, go to <strong>Grade Correction</strong> in the sidebar to file a formal correction request. Each term grade is allowed <strong>one correction cycle only</strong>. Keep your original class records and score sheets on file as supporting documents.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
ob_end_flush();
?>