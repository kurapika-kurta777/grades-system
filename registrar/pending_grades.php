<?php
/*
 * REGISTRAR PENDING GRADES APPROVAL
 * This file is included by index.php (router)
 * Do not include HTML head/body tags - only content
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

requireRole([2]);

$registrar_id = $_SESSION['user_id'];

// ──────────────────────────────────────────────
// POST HANDLER
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !csrf_check($_POST['csrf_token'])) {
        setFlash('Invalid security token. Please try again.', 'error');
        header('Location: ?page=pending_grades');
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $type   = trim($_POST['type']   ?? 'term');

    if (!in_array($action, ['approve', 'return'])) {
        setFlash('Invalid action.', 'error');
        header('Location: ?page=pending_grades');
        exit;
    }

    // ── Semestral ──
    if ($type === 'semestral') {
        $semestral_grade_id = (int)($_POST['semestral_grade_id'] ?? 0);

        if ($semestral_grade_id <= 0) {
            setFlash('Invalid semestral grade record.', 'error');
            header('Location: ?page=pending_grades');
            exit;
        }

        $new_status = $action === 'approve' ? 'Approved' : 'Draft';
        $stmt = $conn->prepare("UPDATE semestral_grades SET status = ? WHERE semestral_grade_id = ?");
        $stmt->bind_param("si", $new_status, $semestral_grade_id);

        if ($stmt->execute()) {
            $label = $action === 'approve' ? 'approved' : 'returned';
            setFlash('Semestral grade ' . $label . ' successfully.', $action === 'approve' ? 'success' : 'warning');
            logAction($conn, $registrar_id, "Semestral grade ID $semestral_grade_id $label by registrar.");
        } else {
            setFlash('Failed to update semestral grade: ' . $stmt->error, 'error');
        }
        $stmt->close();

    // ── Term ──
    } else {
        $grade_id = (int)($_POST['grade_id'] ?? 0);

        if ($grade_id <= 0) {
            setFlash('Invalid grade record.', 'error');
            header('Location: ?page=pending_grades');
            exit;
        }

        $status    = $action === 'approve' ? 'Approved' : 'Returned';
        $is_locked = $action === 'approve' ? 1 : 0;

        $stmt = $conn->prepare("UPDATE grades SET status = ?, is_locked = ? WHERE grade_id = ?");
        $stmt->bind_param("sii", $status, $is_locked, $grade_id);

        if ($stmt->execute()) {
            $label = $action === 'approve' ? 'approved' : 'returned';
            setFlash('Grade ' . $label . ' successfully.', $action === 'approve' ? 'success' : 'warning');
            logAction($conn, $registrar_id, "Grade ID $grade_id $label by registrar.");
        } else {
            setFlash('Failed to update grade: ' . $stmt->error, 'error');
        }
        $stmt->close();
    }

    // Preserve filters on redirect
    $qs = http_build_query(array_filter([
        'page'            => 'pending_grades',
        'filtered'        => '1',
        'filter_period'   => $_POST['filter_period']   ?? '',
        'filter_subject'  => $_POST['filter_subject']  ?? '',
        'filter_term'     => $_POST['filter_term']     ?? '',
        'filter_faculty'  => $_POST['filter_faculty']  ?? '',
    ]));
    header('Location: ?' . $qs);
    exit;
}

// ──────────────────────────────────────────────
// FILTER PARAMETERS
// ──────────────────────────────────────────────
$filter_period   = trim($_GET['filter_period']  ?? '');
$filter_subject  = (int)($_GET['filter_subject'] ?? 0);
$filter_term     = trim($_GET['filter_term']    ?? '');
$filter_faculty  = (int)($_GET['filter_faculty'] ?? 0);
$filters_applied = isset($_GET['filtered']);

// ──────────────────────────────────────────────
// POPULATE FILTER DROPDOWNS
// ──────────────────────────────────────────────

// Academic periods (static list matching the rest of the system)
$periods = [
    '1st Year - 1st Semester',
    '1st Year - 2nd Semester',
    '2nd Year - 1st Semester',
    '2nd Year - 2nd Semester',
    '3rd Year - 1st Semester',
    '3rd Year - 2nd Semester',
];

// All subjects
$subjects_stmt = $conn->prepare("
    SELECT subject_id, subject_code, subject_name
    FROM subjects
    ORDER BY subject_code
");
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();
$filter_subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $filter_subjects[] = $row;
}
$subjects_stmt->close();

// All faculty members
$faculty_stmt = $conn->prepare("
    SELECT user_id, full_name
    FROM users
    WHERE role_id = 1
    ORDER BY full_name
");
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$filter_faculty_list = [];
while ($row = $faculty_result->fetch_assoc()) {
    $filter_faculty_list[] = $row;
}
$faculty_stmt->close();

// Terms (static)
$terms = ['Prelim', 'Midterm', 'Finals'];

// ──────────────────────────────────────────────
// FETCH PENDING TERM GRADES (only when filters applied)
// ──────────────────────────────────────────────
$pending_grades    = [];
$pending_semestral = [];

if ($filters_applied) {
    $term_query = "
        SELECT
            g.grade_id,
            u.full_name      AS student_name,
            s.subject_code,
            s.subject_name,
            f.full_name      AS faculty_name,
            f.user_id        AS faculty_id,
            g.academic_period,
            g.term,
            g.percentage,
            g.numeric_grade,
            g.status
        FROM grades g
        JOIN subjects s ON g.subject_id  = s.subject_id
        JOIN users    u ON g.student_id  = u.user_id
        JOIN users    f ON s.faculty_id  = f.user_id
        WHERE g.status = 'Pending'
    ";

    $term_params = [];
    $term_types  = '';

    if (!empty($filter_period)) {
        $term_query   .= " AND g.academic_period = ?";
        $term_params[] = $filter_period;
        $term_types   .= 's';
    }
    if ($filter_subject > 0) {
        $term_query   .= " AND g.subject_id = ?";
        $term_params[] = $filter_subject;
        $term_types   .= 'i';
    }
    if (!empty($filter_term)) {
        $term_query   .= " AND g.term = ?";
        $term_params[] = $filter_term;
        $term_types   .= 's';
    }
    if ($filter_faculty > 0) {
        $term_query   .= " AND s.faculty_id = ?";
        $term_params[] = $filter_faculty;
        $term_types   .= 'i';
    }

    $term_query .= " ORDER BY g.academic_period, u.full_name, g.term";

    $stmt = $conn->prepare($term_query);
    if (!empty($term_params)) {
        $stmt->bind_param($term_types, ...$term_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_grades[] = $row;
    }
    $stmt->close();

    // ──────────────────────────────────────────────
    // FETCH PENDING SEMESTRAL GRADES (with filters)
    // ──────────────────────────────────────────────
    $sem_query = "
        SELECT
            sg.semestral_grade_id,
            u.full_name  AS student_name,
            s.subject_code,
            s.subject_name,
            f.full_name  AS faculty_name,
            f.user_id    AS faculty_id,
            sg.academic_period,
            sg.prelim_grade,
            sg.midterm_grade,
            sg.finals_grade,
            sg.final_grade,
            sg.final_numeric,
            sg.final_remarks,
            sg.status
        FROM semestral_grades sg
        JOIN subjects s ON sg.subject_id = s.subject_id
        JOIN users    u ON sg.student_id = u.user_id
        JOIN users    f ON s.faculty_id  = f.user_id
        WHERE sg.status = 'Submitted'
    ";

    $sem_params = [];
    $sem_types  = '';

    if (!empty($filter_period)) {
        $sem_query    .= " AND sg.academic_period = ?";
        $sem_params[]  = $filter_period;
        $sem_types    .= 's';
    }
    if ($filter_subject > 0) {
        $sem_query    .= " AND sg.subject_id = ?";
        $sem_params[]  = $filter_subject;
        $sem_types    .= 'i';
    }
    if ($filter_faculty > 0) {
        $sem_query    .= " AND s.faculty_id = ?";
        $sem_params[]  = $filter_faculty;
        $sem_types    .= 'i';
    }

    $sem_query .= " ORDER BY sg.academic_period, u.full_name";

    $stmt = $conn->prepare($sem_query);
    if (!empty($sem_params)) {
        $stmt->bind_param($sem_types, ...$sem_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_semestral[] = $row;
    }
    $stmt->close();
}

$csrf_token = csrf_token();

// Pull flash
$flash      = getFlash();
$flash_msg  = $flash['msg']  ?? '';
$flash_type = $flash['type'] ?? 'success';
?>

<style>
    :root {
        --primary:       #3B82F6;
        --primary-dark:  #1E40AF;
        --secondary:     #10B981;
        --accent:        #F59E0B;
        --danger:        #EF4444;
        --success:       #22C55E;
        --surface:       #FFFFFF;
        --background:    #F8FAFC;
        --text-primary:  #1E293B;
        --text-secondary:#64748B;
        --border:        #E2E8F0;
        --shadow:        0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
        --shadow-lg:     0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
        --radius:        12px;
        --transition:    all 0.3s cubic-bezier(0.4,0,0.2,1);
    }

    /* ── Page header ── */
    .page-header { margin-bottom:2rem; }
    .page-header h2 { font-size:1.6rem; font-weight:700; color:var(--text-primary); margin-bottom:.5rem; margin-top:0; }
    .page-header p  { color:var(--text-secondary); font-size:.9rem; margin:0; }

    /* ── Alerts ── */
    .alert-card {
        padding:1rem 1.5rem; border-radius:var(--radius);
        margin-bottom:2rem; border:1px solid;
        animation:slideIn .3s ease-out;
        display:flex; align-items:center; gap:.75rem;
    }
    @keyframes slideIn {
        from { transform:translateY(-10px); opacity:0; }
        to   { transform:translateY(0);     opacity:1; }
    }
    .alert-success { background:rgba(34,197,94,.1);  border-color:var(--success); color:#166534; }
    .alert-error   { background:rgba(239,68,68,.1);  border-color:var(--danger);  color:#991b1b; }
    .alert-warning { background:rgba(245,158,11,.1); border-color:var(--accent);  color:#92400e; }

    /* ── Filters ── */
    .filters-section {
        background: var(--surface);
        padding: 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        border: 1px solid var(--border);
    }

    .filters-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .filter-group select {
        padding: 0.65rem 0.875rem;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--surface);
        color: var(--text-primary);
        transition: var(--transition);
        font-family: inherit;
    }

    .filter-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59,130,246,.1);
    }

    .filter-actions {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
    }

    .btn-filter {
        padding: 0.80rem 1.25rem;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
    }

    .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .btn-reset {
        padding: 0.65rem 1rem;
        background: var(--surface);
        color: var(--text-primary);
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
    }

    .btn-reset:hover {
        border-color: var(--text-secondary);
        background: var(--background);
    }

    /* ── Section label ── */
    .content-section { margin-bottom:3rem; }
    .section-label {
        font-size:1.1rem; font-weight:700; color:var(--text-primary);
        margin-bottom:1rem; display:flex; align-items:center; gap:.5rem;
    }
    .section-label i { font-size:1.25rem; color:var(--primary); }

    /* ── Table card ── */
    .table-card {
        background:var(--surface); border:1px solid var(--border);
        border-radius:var(--radius); box-shadow:var(--shadow);
        margin-bottom:2rem; overflow:hidden; transition:var(--transition);
    }
    .table-card:hover { box-shadow:var(--shadow-lg); border-color:var(--primary); }
    .table-wrap { overflow-x:auto; }

    .pending-grades-table { width:100%; border-collapse:collapse; }
    .pending-grades-table thead { background:var(--background); border-bottom:1px solid var(--border); }
    .pending-grades-table th {
        padding:1rem 1.25rem; text-align:center; font-weight:700;
        color:var(--text-secondary); font-size:.75rem;
        text-transform:uppercase; letter-spacing:.05em;
    }
    .pending-grades-table tbody tr { border-bottom:1px solid var(--border); transition:var(--transition); }
    .pending-grades-table tbody tr:hover { background:#f8f9ff; }
    .pending-grades-table td { padding:1rem 1.25rem; vertical-align:middle; color:var(--text-primary); text-align:center; }

    .student-cell { font-weight:600; }
    .subject-cell { color:var(--text-secondary); }
    .faculty-cell { color:var(--text-secondary); font-size:.875rem; }

    /* ── Badges ── */
    .status-badge {
        display:inline-flex; align-items:center; gap:.5rem;
        padding:.4rem .75rem; border-radius:6px;
        font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
        background:rgba(245,158,11,.1); color:#92400e; border:1px solid rgba(245,158,11,.3);
    }
    .status-submitted {
        background:rgba(59,130,246,.1); color:var(--primary); border:1px solid rgba(59,130,246,.3);
    }
    .term-badge {
        display:inline-flex; align-items:center;
        padding:.25rem .75rem; border-radius:20px;
        font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    }
    .term-prelim  { background:rgba(59,130,246,.1);  color:var(--primary);  border:1px solid rgba(59,130,246,.3); }
    .term-midterm { background:rgba(245,158,11,.1);  color:#92400e;         border:1px solid rgba(245,158,11,.3); }
    .term-finals  { background:rgba(16,185,129,.1);  color:#065f46;         border:1px solid rgba(16,185,129,.3); }

    /* ── Action buttons ── */
    .action-buttons { display:flex; gap:.5rem; align-items:center; justify-content:center; }
    .action-btn {
        padding:.6rem 1.1rem; border:none; border-radius:8px;
        font-size:.85rem; font-weight:600; cursor:pointer;
        transition:var(--transition); display:flex; align-items:center; gap:.4rem;
        white-space:nowrap;
    }
    .btn-approve { background:linear-gradient(135deg,var(--secondary),#059669); color:white; }
    .btn-approve:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); opacity:.9; }
    .btn-return  { background:linear-gradient(135deg,var(--accent),#d97706); color:white; }
    .btn-return:hover  { transform:translateY(-2px); box-shadow:var(--shadow-lg); opacity:.9; }

    /* ── Empty ── */
    .empty-message { text-align:center; padding:3rem 2rem; color:var(--text-secondary); }
    .empty-message i { font-size:3rem; color:var(--border); margin-bottom:1rem; display:block; }
    .empty-message p { font-size:1rem; margin:0; }

    /* ══════════════════════════════════════════
       CONFIRMATION MODAL
    ══════════════════════════════════════════ */
    .modal-backdrop {
        display:none; position:fixed; inset:0;
        background:rgba(15,23,42,.55); backdrop-filter:blur(4px);
        z-index:2000; align-items:center; justify-content:center;
    }
    .modal-backdrop.open { display:flex; }

    .modal {
        background:var(--surface); border-radius:16px;
        box-shadow:0 25px 50px -12px rgba(0,0,0,.35);
        width:90%; max-width:440px;
        animation:modalIn .25s cubic-bezier(0.34,1.56,0.64,1);
        overflow:hidden;
    }
    @keyframes modalIn {
        from { opacity:0; transform:scale(.92) translateY(16px); }
        to   { opacity:1; transform:scale(1)   translateY(0); }
    }

    .modal-header {
        padding:1.4rem 1.5rem 1rem;
        display:flex; align-items:center; gap:.75rem;
        border-bottom:1px solid var(--border);
    }
    .modal-icon {
        width:42px; height:42px; border-radius:10px;
        display:flex; align-items:center; justify-content:center;
        font-size:1.3rem; flex-shrink:0;
    }
    .modal-icon.approve { background:rgba(16,185,129,.12); color:#059669; }
    .modal-icon.return  { background:rgba(245,158,11,.12);  color:#d97706; }

    .modal-header h3 { font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; }
    .modal-header p  { font-size:.82rem; color:var(--text-secondary); margin:.2rem 0 0; }

    .modal-body { padding:1.25rem 1.5rem; }

    .modal-info-row {
        background:var(--background); border:1px solid var(--border);
        border-radius:8px; padding:.9rem 1rem;
        display:grid; grid-template-columns:1fr 1fr; gap:.5rem .75rem;
    }
    .modal-info-row span   { color:var(--text-secondary); font-size:.78rem; display:block; margin-bottom:.1rem; }
    .modal-info-row strong { font-size:.875rem; font-weight:600; color:var(--text-primary); }

    .modal-footer {
        padding:1rem 1.5rem; border-top:1px solid var(--border);
        display:flex; justify-content:flex-end; gap:.6rem;
        background:var(--background);
    }
    .btn-cancel {
        padding:.65rem 1.3rem; background:var(--surface); color:var(--text-primary);
        border:1.5px solid var(--border); border-radius:8px;
        font-size:.875rem; font-weight:600; cursor:pointer; transition:var(--transition);
    }
    .btn-cancel:hover { background:var(--background); border-color:var(--text-secondary); }

    .btn-confirm-approve {
        padding:.65rem 1.3rem;
        background:linear-gradient(135deg,var(--secondary),#059669);
        color:white; border:none; border-radius:8px;
        font-size:.875rem; font-weight:600; cursor:pointer; transition:var(--transition);
        display:flex; align-items:center; gap:.4rem;
    }
    .btn-confirm-approve:hover { transform:translateY(-1px); box-shadow:var(--shadow); opacity:.92; }

    .btn-confirm-return {
        padding:.65rem 1.3rem;
        background:linear-gradient(135deg,var(--accent),#d97706);
        color:white; border:none; border-radius:8px;
        font-size:.875rem; font-weight:600; cursor:pointer; transition:var(--transition);
        display:flex; align-items:center; gap:.4rem;
    }
    .btn-confirm-return:hover { transform:translateY(-1px); box-shadow:var(--shadow); opacity:.92; }

    @media (max-width:768px) {
        .filters-form { grid-template-columns: 1fr; }
        .pending-grades-table { font-size:.85rem; }
        .pending-grades-table th,
        .pending-grades-table td { padding:.75rem .5rem; }
        .action-buttons { flex-direction:column; width:100%; }
        .action-btn { width:100%; justify-content:center; }
        .modal-info-row { grid-template-columns:1fr; }
    }

    /* ── Prompt card (shown before filter is applied) ── */
    .prompt-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 3rem 2rem;
        text-align: center;
        color: var(--text-secondary);
    }
    .prompt-card i  { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: .4; }
    .prompt-card h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: .5rem; }
    .prompt-card p  { font-size: .9rem; margin: 0; }
</style>

<!-- ════════════════════════════════════════════════
     CONFIRMATION MODAL
════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

        <div class="modal-header">
            <div class="modal-icon" id="modalIcon">
                <i class='bx' id="modalIconInner"></i>
            </div>
            <div>
                <h3 id="modalTitle">Confirm Action</h3>
                <p  id="modalSubtitle">Please review the details before proceeding.</p>
            </div>
        </div>

        <form method="POST" id="modalForm">
            <input type="hidden" name="csrf_token"         value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="action"             id="modalAction"      value="">
            <input type="hidden" name="type"               id="modalType"        value="">
            <input type="hidden" name="grade_id"           id="modalGradeId"     value="">
            <input type="hidden" name="semestral_grade_id" id="modalSemestralId" value="">
            <!-- Preserve active filters so redirect returns to same view -->
            <input type="hidden" name="filter_period"      value="<?= htmlspecialchars($filter_period,  ENT_QUOTES) ?>">
            <input type="hidden" name="filter_subject"     value="<?= htmlspecialchars($filter_subject, ENT_QUOTES) ?>">
            <input type="hidden" name="filter_term"        value="<?= htmlspecialchars($filter_term,    ENT_QUOTES) ?>">
            <input type="hidden" name="filter_faculty"     value="<?= htmlspecialchars($filter_faculty, ENT_QUOTES) ?>">

            <div class="modal-body">
                <div class="modal-info-row">
                    <div>
                        <span>Student</span>
                        <strong id="infoStudent">—</strong>
                    </div>
                    <div>
                        <span>Subject</span>
                        <strong id="infoSubject">—</strong>
                    </div>
                    <div>
                        <span>Faculty</span>
                        <strong id="infoFaculty">—</strong>
                    </div>
                    <div>
                        <span>Detail</span>
                        <strong id="infoDetail">—</strong>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="modalCancel">Cancel</button>
                <button type="submit" class="btn-confirm-approve" id="btnConfirmApprove" style="display:none;">
                    <i class='bx bx-check'></i> Confirm Approve
                </button>
                <button type="submit" class="btn-confirm-return" id="btnConfirmReturn" style="display:none;">
                    <i class='bx bx-undo'></i> Confirm Return
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ════════════════════════════════════════════════
     PAGE CONTENT
════════════════════════════════════════════════ -->
<div>
    <?php if (!empty($flash_msg)): ?>
        <div class="alert-card alert-<?= htmlspecialchars($flash_type, ENT_QUOTES) ?>" id="flashAlert">
            <i class='bx <?= $flash_type === 'success' ? 'bx-check-circle' : ($flash_type === 'warning' ? 'bx-error-circle' : 'bx-error-circle') ?>'></i>
            <?= htmlspecialchars($flash_msg, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2>Grade Submissions</h2>
        <p>Review and approve grades submitted by faculty.</p>
    </div>

    <!-- ── Filters ── -->
    <div class="filters-section">
        <form method="GET" class="filters-form" id="filtersForm">
            <input type="hidden" name="page"     value="pending_grades">
            <input type="hidden" name="filtered" value="1">

            <div class="filter-group">
                <label for="filter_period">Academic Period</label>
                <select name="filter_period" id="filter_period">
                    <option value="">All Periods</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= htmlspecialchars($period, ENT_QUOTES) ?>"
                                <?= $filter_period === $period ? 'selected' : '' ?>>
                            <?= htmlspecialchars($period, ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_subject">Subject</label>
                <select name="filter_subject" id="filter_subject">
                    <option value="0">All Subjects</option>
                    <?php foreach ($filter_subjects as $subj): ?>
                        <option value="<?= (int)$subj['subject_id'] ?>"
                                <?= $filter_subject === (int)$subj['subject_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code'] . ' – ' . $subj['subject_name'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_term">Term</label>
                <select name="filter_term" id="filter_term">
                    <option value="">All Terms</option>
                    <?php foreach ($terms as $term): ?>
                        <option value="<?= htmlspecialchars($term, ENT_QUOTES) ?>"
                                <?= $filter_term === $term ? 'selected' : '' ?>>
                            <?= htmlspecialchars($term, ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter_faculty">Faculty</label>
                <select name="filter_faculty" id="filter_faculty">
                    <option value="0">All Faculty</option>
                    <?php foreach ($filter_faculty_list as $fac): ?>
                        <option value="<?= (int)$fac['user_id'] ?>"
                                <?= $filter_faculty === (int)$fac['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fac['full_name'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class='bx bx-filter-alt'></i> Apply
                </button>
                <a href="?page=pending_grades" class="btn-reset">
                    <i class='bx bx-reset'></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ── Term Grades ── -->
    <?php if (!$filters_applied): ?>
        <div class="prompt-card">
            <i class='bx bx-filter-alt'></i>
            <h3>Apply Filters to View Submissions</h3>
            <p>Use the filters above and click <strong>Apply</strong> to load pending grade submissions.</p>
        </div>

    <?php else: ?>
    <div class="content-section">
        <div class="section-label">
            <i class='bx bx-book-open'></i>
            Term Grades
            <span style="font-size:.8rem; font-weight:500; color:var(--text-secondary); margin-left:.5rem;">
                (<?= count($pending_grades) ?> record<?= count($pending_grades) !== 1 ? 's' : '' ?>)
            </span>
        </div>
        <div class="table-card">
            <div class="table-wrap">
                <table class="pending-grades-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th>Period</th>
                            <th>Term</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pending_grades) > 0): ?>
                            <?php foreach ($pending_grades as $grade): ?>
                                <tr>
                                    <td class="student-cell"><?= htmlspecialchars($grade['student_name'],  ENT_QUOTES) ?></td>
                                    <td class="subject-cell">
                                        <strong><?= htmlspecialchars($grade['subject_code'], ENT_QUOTES) ?></strong><br>
                                        <small style="color:var(--text-secondary);"><?= htmlspecialchars($grade['subject_name'], ENT_QUOTES) ?></small>
                                    </td>
                                    <td class="faculty-cell"><?= htmlspecialchars($grade['faculty_name'], ENT_QUOTES) ?></td>
                                    <td class="subject-cell"><?= htmlspecialchars($grade['academic_period'], ENT_QUOTES) ?></td>
                                    <td>
                                        <span class="term-badge term-<?= strtolower($grade['term']) ?>">
                                            <?= htmlspecialchars($grade['term'], ENT_QUOTES) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= number_format($grade['percentage'], 2) ?>%</strong></td>
                                    <td><strong><?= number_format($grade['numeric_grade'], 2) ?></strong></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="action-btn btn-approve"
                                                onclick="openModal('approve','term',
                                                    <?= $grade['grade_id'] ?>, 0,
                                                    <?= htmlspecialchars(json_encode($grade['student_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['subject_code'] . ' – ' . $grade['subject_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['faculty_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['term'] . ' · ' . $grade['academic_period']), ENT_QUOTES) ?>
                                                )">
                                                <i class='bx bx-check'></i> Approve
                                            </button>
                                            <button type="button" class="action-btn btn-return"
                                                onclick="openModal('return','term',
                                                    <?= $grade['grade_id'] ?>, 0,
                                                    <?= htmlspecialchars(json_encode($grade['student_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['subject_code'] . ' – ' . $grade['subject_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['faculty_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($grade['term'] . ' · ' . $grade['academic_period']), ENT_QUOTES) ?>
                                                )">
                                                <i class='bx bx-undo'></i> Return
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding:0;">
                                    <div class="empty-message">
                                        <i class='bx bx-inbox'></i>
                                        <p>No pending term grade submissions matching the selected filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Semestral Grades ── -->
    <div class="content-section">
        <div class="section-label">
            <i class='bx bx-trophy'></i>
            Semestral Grades
            <span style="font-size:.8rem; font-weight:500; color:var(--text-secondary); margin-left:.5rem;">
                (<?= count($pending_semestral) ?> record<?= count($pending_semestral) !== 1 ? 's' : '' ?>)
            </span>
        </div>
        <div class="table-card">
            <div class="table-wrap">
                <table class="pending-grades-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Faculty</th>
                            <th>Period</th>
                            <th>Prelim (30%)</th>
                            <th>Midterm (30%)</th>
                            <th>Finals (40%)</th>
                            <th>Final Grade</th>
                            <th>Numeric</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pending_semestral) > 0): ?>
                            <?php foreach ($pending_semestral as $sg): ?>
                                <tr>
                                    <td class="student-cell"><?= htmlspecialchars($sg['student_name'], ENT_QUOTES) ?></td>
                                    <td class="subject-cell">
                                        <strong><?= htmlspecialchars($sg['subject_code'], ENT_QUOTES) ?></strong><br>
                                        <small style="color:var(--text-secondary);"><?= htmlspecialchars($sg['subject_name'], ENT_QUOTES) ?></small>
                                    </td>
                                    <td class="faculty-cell"><?= htmlspecialchars($sg['faculty_name'], ENT_QUOTES) ?></td>
                                    <td class="subject-cell"><?= htmlspecialchars($sg['academic_period'], ENT_QUOTES) ?></td>
                                    <td><?= $sg['prelim_grade']   !== null ? number_format($sg['prelim_grade'],   2) . '%' : '—' ?></td>
                                    <td><?= $sg['midterm_grade']  !== null ? number_format($sg['midterm_grade'],  2) . '%' : '—' ?></td>
                                    <td><?= $sg['finals_grade']   !== null ? number_format($sg['finals_grade'],   2) . '%' : '—' ?></td>
                                    <td style="font-weight:700; color:var(--primary);">
                                        <?= $sg['final_grade'] !== null ? number_format($sg['final_grade'], 2) . '%' : '—' ?>
                                    </td>
                                    <td style="font-weight:700;">
                                        <?= $sg['final_numeric'] !== null ? number_format($sg['final_numeric'], 2) : '—' ?>
                                    </td>
                                    <td><?= htmlspecialchars($sg['final_remarks'] ?? '—', ENT_QUOTES) ?></td>
                                    <td>
                                        <span class="status-badge status-submitted">
                                            <i class='bx bx-send'></i>
                                            <?= htmlspecialchars($sg['status'], ENT_QUOTES) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="action-btn btn-approve"
                                                onclick="openModal('approve','semestral',
                                                    0, <?= $sg['semestral_grade_id'] ?>,
                                                    <?= htmlspecialchars(json_encode($sg['student_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($sg['subject_code'] . ' – ' . $sg['subject_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($sg['faculty_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode('Semestral · ' . $sg['academic_period']), ENT_QUOTES) ?>
                                                )">
                                                <i class='bx bx-check'></i> Approve
                                            </button>
                                            <button type="button" class="action-btn btn-return"
                                                onclick="openModal('return','semestral',
                                                    0, <?= $sg['semestral_grade_id'] ?>,
                                                    <?= htmlspecialchars(json_encode($sg['student_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($sg['subject_code'] . ' – ' . $sg['subject_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode($sg['faculty_name']), ENT_QUOTES) ?>,
                                                    <?= htmlspecialchars(json_encode('Semestral · ' . $sg['academic_period']), ENT_QUOTES) ?>
                                                )">
                                                <i class='bx bx-undo'></i> Return
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" style="padding:0;">
                                    <div class="empty-message">
                                        <i class='bx bx-inbox'></i>
                                        <p>No pending semestral grade submissions matching the selected filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ── Auto-dismiss flash alert ──
document.addEventListener('DOMContentLoaded', function () {
    const alert = document.getElementById('flashAlert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity .3s ease-out';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 300);
        }, 3600);
    }
});

// ── Modal elements ──
const backdrop        = document.getElementById('modalBackdrop');
const form            = document.getElementById('modalForm');
const modalIcon       = document.getElementById('modalIcon');
const modalIconInner  = document.getElementById('modalIconInner');
const modalTitle      = document.getElementById('modalTitle');
const modalSub        = document.getElementById('modalSubtitle');
const btnApprove      = document.getElementById('btnConfirmApprove');
const btnReturn       = document.getElementById('btnConfirmReturn');

function openModal(action, type, gradeId, semestralId, student, subject, faculty, detail) {
    document.getElementById('infoStudent').textContent = student;
    document.getElementById('infoSubject').textContent = subject;
    document.getElementById('infoFaculty').textContent = faculty;
    document.getElementById('infoDetail').textContent  = detail;

    document.getElementById('modalAction').value       = action;
    document.getElementById('modalType').value         = type;
    document.getElementById('modalGradeId').value      = gradeId;
    document.getElementById('modalSemestralId').value  = semestralId;

    if (action === 'approve') {
        modalTitle.textContent   = 'Approve Grade';
        modalSub.textContent     = 'You are about to approve this grade submission.';
        modalIcon.className      = 'modal-icon approve';
        modalIconInner.className = 'bx bx-check-circle';
        btnApprove.style.display = 'flex';
        btnReturn.style.display  = 'none';
    } else {
        modalTitle.textContent   = 'Return Grade';
        modalSub.textContent     = 'This grade will be returned to the faculty for correction.';
        modalIcon.className      = 'modal-icon return';
        modalIconInner.className = 'bx bx-undo';
        btnApprove.style.display = 'none';
        btnReturn.style.display  = 'flex';
    }

    backdrop.classList.add('open');
}

function closeModal() {
    backdrop.classList.remove('open');
}

document.getElementById('modalCancel').addEventListener('click', closeModal);
backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>