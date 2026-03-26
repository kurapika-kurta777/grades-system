<?php
/*
 * REGISTRAR PENDING ENROLLMENTS
 * This file is included by index.php (router)
 * Do not include HTML head/body tags - only content
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/flash.php';

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

requireRole([2]); // Registrar

// ──────────────────────────────────────────────
// POST HANDLER — Approve
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_enrollment'])) {
    if (empty($_POST['csrf_token'])) { http_response_code(400); die('Missing CSRF token'); }
    csrf_validate_or_die($_POST['csrf_token']);

    $request_id = intval($_POST['request_id'] ?? 0);

    $chk = $conn->prepare("SELECT student_id, subject_id, status FROM enrollment_requests WHERE request_id = ?");
    $chk->bind_param('i', $request_id);
    $chk->execute();
    $crow = $chk->get_result()->fetch_assoc();

    if (!$crow) {
        setFlash('Invalid enrollment request.', 'error');
    } elseif ($crow['status'] !== 'Pending') {
        setFlash('This request is no longer pending.', 'error');
    } else {
        $student_id = intval($crow['student_id']);
        $subject_id = intval($crow['subject_id']);

        $sem = $conn->prepare("SELECT semester_id FROM semesters ORDER BY semester_id DESC LIMIT 1");
        $sem->execute();
        $srow = $sem->get_result()->fetch_assoc();

        if (!$srow) {
            setFlash('No active semester found.', 'error');
        } else {
            $semester_id = intval($srow['semester_id']);

            $ins = $conn->prepare("INSERT INTO enrollments (student_id, subject_id, semester_id) VALUES (?, ?, ?)");
            $ins->bind_param('iii', $student_id, $subject_id, $semester_id);
            $ins->execute();

            $upd = $conn->prepare("UPDATE enrollment_requests SET status = 'Approved', registrar_id = ?, decision_date = NOW() WHERE request_id = ?");
            $upd->bind_param('ii', $_SESSION['user_id'], $request_id);
            $upd->execute();

            logAction($conn, $_SESSION['user_id'], "Approved enrollment request $request_id");
            addNotification($conn, $student_id, "Your enrollment request #$request_id was approved.");
            setFlash('Enrollment request approved successfully.', 'success');
        }
    }

    header('Location: ?page=pending_enrollments');
    exit;
}

// ──────────────────────────────────────────────
// POST HANDLER — Reject
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_enrollment'])) {
    if (empty($_POST['csrf_token'])) { http_response_code(400); die('Missing CSRF token'); }
    csrf_validate_or_die($_POST['csrf_token']);

    $request_id = intval($_POST['request_id'] ?? 0);

    $upd = $conn->prepare("UPDATE enrollment_requests SET status = 'Rejected', registrar_id = ?, decision_date = NOW() WHERE request_id = ?");
    $upd->bind_param('ii', $_SESSION['user_id'], $request_id);
    $upd->execute();

    logAction($conn, $_SESSION['user_id'], "Rejected enrollment request $request_id");
    setFlash('Enrollment request rejected.', 'warning');

    header('Location: ?page=pending_enrollments');
    exit;
}

// ──────────────────────────────────────────────
// FILTER PARAMETERS
// ──────────────────────────────────────────────
$filter_subject = (int)($_GET['filter_subject'] ?? 0);
$filter_faculty = (int)($_GET['filter_faculty'] ?? 0);
$filter_student = trim($_GET['filter_student']  ?? '');
$filters_applied = isset($_GET['filtered']);

// ──────────────────────────────────────────────
// POPULATE FILTER DROPDOWNS
// ──────────────────────────────────────────────

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

// All faculty
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

// ──────────────────────────────────────────────
// FETCH DATA (only when filters applied)
// ──────────────────────────────────────────────
$enrollment_rows = [];

if ($filters_applied) {
    $er_query = "
        SELECT
            er.request_id,
            er.student_id,
            er.subject_id,
            u.full_name   AS student_name,
            s.subject_code,
            s.subject_name,
            uf.full_name  AS teacher_name,
            er.request_date,
            er.status
        FROM enrollment_requests er
        JOIN   users    u  ON er.student_id  = u.user_id
        JOIN   subjects s  ON er.subject_id  = s.subject_id
        LEFT JOIN users uf ON s.faculty_id   = uf.user_id
        WHERE  er.status = 'Pending'
    ";

    $er_params = [];
    $er_types  = '';

    if ($filter_subject > 0) {
        $er_query    .= " AND er.subject_id = ?";
        $er_params[]  = $filter_subject;
        $er_types    .= 'i';
    }
    if ($filter_faculty > 0) {
        $er_query    .= " AND s.faculty_id = ?";
        $er_params[]  = $filter_faculty;
        $er_types    .= 'i';
    }
    if (!empty($filter_student)) {
        $er_query    .= " AND u.full_name LIKE ?";
        $er_params[]  = '%' . $filter_student . '%';
        $er_types    .= 's';
    }

    $er_query .= " ORDER BY er.request_date ASC";

    $er_stmt = $conn->prepare($er_query);
    if (!empty($er_params)) {
        $er_stmt->bind_param($er_types, ...$er_params);
    }
    $er_stmt->execute();
    $er_result = $er_stmt->get_result();
    while ($row = $er_result->fetch_assoc()) {
        $enrollment_rows[] = $row;
    }
    $er_stmt->close();
}

logAction($conn, $_SESSION['user_id'], "Viewed pending enrollment requests");

$csrf_token = csrf_token();

// Pull flash message
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
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .filter-actions {
        grid-column: 1 / -1;
        display: flex;
        gap: 0.5rem;
        justify-content: flex-start;
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

    .filter-group select,
    .filter-group input[type="text"] {
        padding: 0.65rem 0.875rem;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--surface);
        color: var(--text-primary);
        transition: var(--transition);
        font-family: inherit;
    }

    .filter-group select:focus,
    .filter-group input[type="text"]:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59,130,246,.1);
    }

    .btn-filter {
        padding: 0.65rem 1.25rem;
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

    .btn-filter:hover { background: var(--primary-dark); transform: translateY(-1px); }

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

    .btn-reset:hover { border-color: var(--text-secondary); background: var(--background); }

    /* ── Prompt card ── */
    .prompt-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 3rem 2rem;
        text-align: center;
        color: var(--text-secondary);
    }
    .prompt-card i   { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: .4; }
    .prompt-card h3  { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: .5rem; }
    .prompt-card p   { font-size: .9rem; margin: 0; }

    /* ── Content section ── */
    .content-section { margin-bottom: 0; }

    .section-label {
        font-size: 1.1rem; font-weight: 700; color: var(--text-primary);
        margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem;
    }
    .section-label i { font-size: 1.25rem; color: var(--primary); }

    /* ── Table card ── */
    .table-card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius); box-shadow: var(--shadow);
        margin-bottom: 2rem; overflow: hidden; transition: var(--transition);
    }
    .table-card:hover { box-shadow: var(--shadow-lg); border-color: var(--primary); }
    .table-wrap { overflow-x: auto; }

    .enrollments-table { width: 100%; border-collapse: collapse; }
    .enrollments-table thead { background: var(--background); border-bottom: 1px solid var(--border); }
    .enrollments-table th {
        padding: 1rem 1.25rem; text-align: center; font-weight: 700;
        color: var(--text-secondary); font-size: .75rem;
        text-transform: uppercase; letter-spacing: .05em;
    }
    .enrollments-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
    .enrollments-table tbody tr:hover { background: #f8f9ff; }
    .enrollments-table td { padding: 1rem 1.25rem; vertical-align: middle; color: var(--text-primary); text-align: center; }

    /* ── Badges ── */
    .status-badge {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .4rem .75rem; border-radius: 6px;
        font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
        background: rgba(245,158,11,.1); color: #92400e; border: 1px solid rgba(245,158,11,.3);
    }

    /* ── Action buttons ── */
    .action-buttons { display: flex; gap: .5rem; align-items: center; justify-content: center; }
    .action-btn {
        padding: .6rem 1.1rem; border: none; border-radius: 8px;
        font-size: .85rem; font-weight: 600; cursor: pointer;
        transition: var(--transition); display: flex; align-items: center; gap: .4rem;
    }
    .btn-approve { background: linear-gradient(135deg,var(--secondary),#059669); color: white; }
    .btn-approve:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); opacity: .9; }
    .btn-reject  { background: linear-gradient(135deg,var(--danger),#dc2626); color: white; }
    .btn-reject:hover  { transform: translateY(-2px); box-shadow: var(--shadow-lg); opacity: .9; }

    /* ── Empty ── */
    .empty-message { text-align: center; padding: 3rem 2rem; color: var(--text-secondary); }
    .empty-message i { font-size: 3rem; color: var(--border); margin-bottom: 1rem; display: block; }
    .empty-message p { font-size: 1rem; margin: 0; }

    .date-cell { font-size: .82rem; color: var(--text-secondary); font-family: 'DM Mono', monospace; }

    /* ══════════════════════════════════════════
       CONFIRMATION MODAL
    ══════════════════════════════════════════ */
    .modal-backdrop {
        display: none; position: fixed; inset: 0;
        background: rgba(15,23,42,.55); backdrop-filter: blur(4px);
        z-index: 2000; align-items: center; justify-content: center;
    }
    .modal-backdrop.open { display: flex; }

    .modal {
        background: var(--surface); border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);
        width: 90%; max-width: 420px;
        animation: modalIn .25s cubic-bezier(0.34,1.56,0.64,1);
        overflow: hidden;
    }
    @keyframes modalIn {
        from { opacity:0; transform:scale(.92) translateY(16px); }
        to   { opacity:1; transform:scale(1)   translateY(0); }
    }

    .modal-header {
        padding: 1.4rem 1.5rem 1rem;
        display: flex; align-items: center; gap: .75rem;
        border-bottom: 1px solid var(--border);
    }
    .modal-icon {
        width: 42px; height: 42px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; flex-shrink: 0;
    }
    .modal-icon.approve { background: rgba(16,185,129,.12); color: #059669; }
    .modal-icon.reject  { background: rgba(239,68,68,.12);  color: var(--danger); }

    .modal-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0; }
    .modal-header p  { font-size: .82rem; color: var(--text-secondary); margin: .2rem 0 0; }

    .modal-body { padding: 1.25rem 1.5rem; }

    .modal-info-row {
        background: var(--background); border: 1px solid var(--border);
        border-radius: 8px; padding: .9rem 1rem;
        display: grid; grid-template-columns: 1fr 1fr; gap: .5rem .75rem;
    }
    .modal-info-row span   { color: var(--text-secondary); font-size: .78rem; display: block; margin-bottom: .1rem; }
    .modal-info-row strong { font-size: .875rem; font-weight: 600; color: var(--text-primary); }

    .modal-footer {
        padding: 1rem 1.5rem; border-top: 1px solid var(--border);
        display: flex; justify-content: flex-end; gap: .6rem;
        background: var(--background);
    }
    .btn-cancel {
        padding: .65rem 1.3rem; background: var(--surface); color: var(--text-primary);
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--transition);
    }
    .btn-cancel:hover { background: var(--background); border-color: var(--text-secondary); }

    .btn-confirm-approve {
        padding: .65rem 1.3rem; background: linear-gradient(135deg,var(--secondary),#059669);
        color: white; border: none; border-radius: 8px;
        font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: .4rem;
    }
    .btn-confirm-approve:hover { transform: translateY(-1px); box-shadow: var(--shadow); opacity: .92; }

    .btn-confirm-reject {
        padding: .65rem 1.3rem; background: linear-gradient(135deg,var(--danger),#dc2626);
        color: white; border: none; border-radius: 8px;
        font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: .4rem;
    }
    .btn-confirm-reject:hover { transform: translateY(-1px); box-shadow: var(--shadow); opacity: .92; }

    @media (max-width:768px) {
        .filters-form { grid-template-columns: 1fr; }
        .enrollments-table { font-size: .85rem; }
        .enrollments-table th, .enrollments-table td { padding: .75rem .5rem; }
        .action-buttons { flex-direction: column; width: 100%; }
        .action-btn { width: 100%; justify-content: center; }
        .modal-info-row { grid-template-columns: 1fr; }
    }
</style>

<!-- ════════════════════════════════════════════════
     CONFIRMATION MODAL
════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

        <div class="modal-header">
            <div class="modal-icon" id="modalIcon"><i class='bx' id="modalIconInner"></i></div>
            <div>
                <h3 id="modalTitle">Confirm Action</h3>
                <p id="modalSubtitle">Please review the request before proceeding.</p>
            </div>
        </div>

        <form method="POST" id="modalForm">
            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
            <input type="hidden" name="request_id"  id="modalRequestId">
            <input type="hidden" name=""            id="modalActionField" value="1">

            <div class="modal-body">
                <div class="modal-info-row" id="modalInfoRow">
                    <div><span>Student</span><strong id="infoStudent">—</strong></div>
                    <div><span>Subject</span><strong id="infoSubject">—</strong></div>
                    <div><span>Faculty</span><strong id="infoFaculty">—</strong></div>
                    <div><span>Requested</span><strong id="infoDate">—</strong></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="modalCancel">Cancel</button>
                <button type="submit" class="btn-confirm-approve" id="btnConfirmApprove" style="display:none;">
                    <i class='bx bx-check'></i> Confirm Approve
                </button>
                <button type="submit" class="btn-confirm-reject" id="btnConfirmReject" style="display:none;">
                    <i class='bx bx-x'></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════
     PAGE
════════════════════════════════════════════════ -->
<div>
    <?php if (!empty($flash_msg)): ?>
        <div class="alert-card alert-<?= htmlspecialchars($flash_type, ENT_QUOTES) ?>" id="flashAlert">
            <i class='bx <?= $flash_type === 'success' ? 'bx-check-circle' : ($flash_type === 'warning' ? 'bx-error-circle' : 'bx-error-circle') ?>'></i>
            <?= htmlspecialchars($flash_msg, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2>Enrollment Requests</h2>
        <p>Review and approve student enrollment requests.</p>
    </div>

    <!-- ── Filters ── -->
    <div class="filters-section">
        <form method="GET" class="filters-form" id="filtersForm">
            <input type="hidden" name="page"     value="pending_enrollments">
            <input type="hidden" name="filtered" value="1">

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

            <div class="filter-group">
                <label for="filter_student">Student Name</label>
                <input type="text" name="filter_student" id="filter_student"
                       placeholder="Search student…"
                       value="<?= htmlspecialchars($filter_student, ENT_QUOTES) ?>">
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class='bx bx-filter-alt'></i> Apply
                </button>
                <a href="?page=pending_enrollments" class="btn-reset">
                    <i class='bx bx-reset'></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ── Results ── -->
    <div class="content-section">
        <?php if (!$filters_applied): ?>
            <div class="prompt-card">
                <i class='bx bx-filter-alt'></i>
                <h3>Apply Filters to View Requests</h3>
                <p>Use the filters above and click <strong>Apply</strong> to load pending enrollment requests.</p>
            </div>

        <?php else: ?>
            <div class="section-label">
                <i class='bx bx-user-plus'></i>
                Pending Requests
                <span style="font-size:.8rem; font-weight:500; color:var(--text-secondary); margin-left:.5rem;">
                    (<?= count($enrollment_rows) ?> record<?= count($enrollment_rows) !== 1 ? 's' : '' ?>)
                </span>
            </div>

            <div class="table-card">
                <div class="table-wrap">
                    <table class="enrollments-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Subject Teacher</th>
                                <th>Date Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($enrollment_rows)): ?>
                                <?php foreach ($enrollment_rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['student_name'],          ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars($row['subject_code'],           ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars($row['subject_name'],           ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars($row['teacher_name'] ?? '—',   ENT_QUOTES) ?></td>
                                        <td class="date-cell">
                                            <?= htmlspecialchars(date('M d, Y', strtotime($row['request_date'])), ENT_QUOTES) ?><br>
                                            <span style="font-size:.75rem;"><?= htmlspecialchars(date('h:i A', strtotime($row['request_date'])), ENT_QUOTES) ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="action-btn btn-approve"
                                                    onclick="openModal('approve',
                                                        <?= $row['request_id'] ?>,
                                                        <?= htmlspecialchars(json_encode($row['student_name']),  ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode($row['subject_code'] . ' – ' . $row['subject_name']), ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode($row['teacher_name'] ?? '—'), ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode(date('M d, Y h:i A', strtotime($row['request_date']))), ENT_QUOTES) ?>
                                                    )">
                                                    <i class='bx bx-check'></i> Approve
                                                </button>
                                                <button type="button" class="action-btn btn-reject"
                                                    onclick="openModal('reject',
                                                        <?= $row['request_id'] ?>,
                                                        <?= htmlspecialchars(json_encode($row['student_name']),  ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode($row['subject_code'] . ' – ' . $row['subject_name']), ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode($row['teacher_name'] ?? '—'), ENT_QUOTES) ?>,
                                                        <?= htmlspecialchars(json_encode(date('M d, Y h:i A', strtotime($row['request_date']))), ENT_QUOTES) ?>
                                                    )">
                                                    <i class='bx bx-x'></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="padding:0;">
                                        <div class="empty-message">
                                            <i class='bx bx-inbox'></i>
                                            <p>No pending enrollment requests match the selected filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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

// ── Modal logic ──
const backdrop       = document.getElementById('modalBackdrop');
const form           = document.getElementById('modalForm');
const reqIdField     = document.getElementById('modalRequestId');
const actionField    = document.getElementById('modalActionField');
const btnApprove     = document.getElementById('btnConfirmApprove');
const btnReject      = document.getElementById('btnConfirmReject');
const modalIcon      = document.getElementById('modalIcon');
const modalIconInner = document.getElementById('modalIconInner');
const modalTitle     = document.getElementById('modalTitle');
const modalSub       = document.getElementById('modalSubtitle');

function openModal(action, requestId, student, subject, faculty, date) {
    document.getElementById('infoStudent').textContent = student;
    document.getElementById('infoSubject').textContent = subject;
    document.getElementById('infoFaculty').textContent = faculty;
    document.getElementById('infoDate').textContent    = date;

    reqIdField.value = requestId;

    if (action === 'approve') {
        actionField.name             = 'approve_enrollment';
        modalTitle.textContent       = 'Approve Enrollment';
        modalSub.textContent         = 'You are about to approve this enrollment request.';
        modalIcon.className          = 'modal-icon approve';
        modalIconInner.className     = 'bx bx-check-circle';
        btnApprove.style.display     = 'flex';
        btnReject.style.display      = 'none';
    } else {
        actionField.name             = 'reject_enrollment';
        modalTitle.textContent       = 'Reject Enrollment';
        modalSub.textContent         = 'You are about to reject this enrollment request.';
        modalIcon.className          = 'modal-icon reject';
        modalIconInner.className     = 'bx bx-x-circle';
        btnApprove.style.display     = 'none';
        btnReject.style.display      = 'flex';
    }

    backdrop.classList.add('open');
}

function closeModal() { backdrop.classList.remove('open'); }

document.getElementById('modalCancel').addEventListener('click', closeModal);
backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>