<?php
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

$action_msg = '';
$msg_type = 'success';
if ($flash = getFlash()) {
    $action_msg = $flash['msg'];
    $msg_type   = $flash['type'];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token'])) { http_response_code(400); die('Missing CSRF token'); }
    csrf_validate_or_die($_POST['csrf_token']);

    $action     = trim($_POST['modal_action'] ?? '');
    $request_id = intval($_POST['request_id']  ?? 0);
    $grade_id   = intval($_POST['grade_id']    ?? 0);
    $faculty_id = intval($_POST['faculty_id']  ?? 0);

    if ($action === 'approve') {
        $conn->begin_transaction();
        try {
            // Row-level lock: verify still pending
            $chk = $conn->prepare("SELECT status FROM grade_corrections WHERE request_id = ? FOR UPDATE");
            $chk->bind_param('i', $request_id);
            $chk->execute();
            $crow = $chk->get_result()->fetch_assoc();

            if (!$crow || $crow['status'] !== 'Pending') {
                $conn->rollback();
                setFlash('This request has already been processed.', 'error');
                header('Location: ?page=pending_corrections');
                exit;
            }

            // Verify grade is still locked
            $gchk = $conn->prepare("SELECT is_locked FROM grades WHERE grade_id = ? FOR UPDATE");
            $gchk->bind_param('i', $grade_id);
            $gchk->execute();
            $grow = $gchk->get_result()->fetch_assoc();

            if (!$grow || intval($grow['is_locked']) !== 1) {
                $conn->rollback();
                setFlash('Cannot approve: grade is not locked.', 'error');
                header('Location: ?page=pending_corrections');
                exit;
            }

            // Ensure no other active request for this grade
            $dup = $conn->prepare(
                "SELECT COUNT(*) AS c FROM grade_corrections
                 WHERE grade_id = ? AND status IN ('Pending','Approved') AND request_id != ?
                 FOR UPDATE"
            );
            $dup->bind_param('ii', $grade_id, $request_id);
            $dup->execute();
            $drow = $dup->get_result()->fetch_assoc();

            if ($drow && intval($drow['c']) > 0) {
                $conn->rollback();
                setFlash('Cannot approve: another active correction request exists for this grade.', 'error');
                header('Location: ?page=pending_corrections');
                exit;
            }

            // Unlock grade for resubmission
            $u1 = $conn->prepare("UPDATE grades SET is_locked = 0, status = 'Returned' WHERE grade_id = ?");
            $u1->bind_param('i', $grade_id);
            $u1->execute();

            // Mark correction Approved
            $u2 = $conn->prepare(
                "UPDATE grade_corrections
                 SET status = 'Approved', registrar_id = ?, decision_date = NOW()
                 WHERE request_id = ?"
            );
            $u2->bind_param('ii', $_SESSION['user_id'], $request_id);
            $u2->execute();

            logAction($conn, $_SESSION['user_id'], "Approved correction request ID $request_id for grade ID $grade_id");
            addNotification($conn, $faculty_id, "Your correction request #$request_id was approved; grade unlocked for resubmission.");

            $conn->commit();
            setFlash('Correction request approved — grade unlocked and returned to faculty.', 'success');
            header('Location: ?page=pending_corrections');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            setFlash('Server error processing request.', 'error');
            header('Location: ?page=pending_corrections');
            exit;
        }

    } elseif ($action === 'reject') {
        $conn->begin_transaction();
        try {
            // Row-level lock: verify still pending
            $chk = $conn->prepare("SELECT status FROM grade_corrections WHERE request_id = ? FOR UPDATE");
            $chk->bind_param('i', $request_id);
            $chk->execute();
            $crow = $chk->get_result()->fetch_assoc();

            if (!$crow || $crow['status'] !== 'Pending') {
                $conn->rollback();
                setFlash('This request has already been processed.', 'error');
                header('Location: ?page=pending_corrections');
                exit;
            }

            $u = $conn->prepare(
                "UPDATE grade_corrections
                 SET status = 'Rejected', registrar_id = ?, decision_date = NOW()
                 WHERE request_id = ?"
            );
            $u->bind_param('ii', $_SESSION['user_id'], $request_id);
            $u->execute();

            logAction($conn, $_SESSION['user_id'], "Rejected correction request ID $request_id");
            addNotification($conn, $faculty_id, "Your correction request #$request_id was rejected.");

            $conn->commit();
            setFlash('Correction request rejected.', 'warning');
            header('Location: ?page=pending_corrections');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            setFlash('Server error processing request.', 'error');
            header('Location: ?page=pending_corrections');
            exit;
        }

    } else {
        setFlash('Invalid action.', 'error');
        header('Location: ?page=pending_corrections');
        exit;
    }
}

// Fetch pending correction requests
$stmt = $conn->prepare(
    "SELECT
        gc.request_id,
        gc.grade_id,
        gc.faculty_id,
        gc.reason,
        u.full_name  AS student,
        s.subject_code,
        s.subject_name,
        g.academic_period,
        g.percentage,
        r.full_name  AS requester
     FROM grade_corrections gc
     JOIN grades   g  ON gc.grade_id  = g.grade_id
     JOIN users    u  ON g.student_id = u.user_id
     JOIN subjects s  ON g.subject_id = s.subject_id
     JOIN users    r  ON gc.faculty_id = r.user_id
     WHERE gc.status = 'Pending'
     ORDER BY gc.request_id ASC"
);
$stmt->execute();
$res = $stmt->get_result();

// Double-check each row is still pending (guards against stale reads)
$pending_rows = [];
while ($row = $res->fetch_assoc()) {
    $statusChk = $conn->prepare("SELECT status FROM grade_corrections WHERE request_id = ?");
    $statusChk->bind_param('i', $row['request_id']);
    $statusChk->execute();
    $srow = $statusChk->get_result()->fetch_assoc();
    if (!$srow || trim($srow['status']) !== 'Pending') continue;
    $pending_rows[] = $row;
}

logAction($conn, $_SESSION['user_id'], "Viewed pending correction requests");
?>

<style>
    :root {
        --primary:        #3B82F6;
        --primary-dark:   #1E40AF;
        --secondary:      #10B981;
        --accent:         #F59E0B;
        --danger:         #EF4444;
        --success:        #22C55E;
        --surface:        #FFFFFF;
        --background:     #F8FAFC;
        --text-primary:   #1E293B;
        --text-secondary: #64748B;
        --border:         #E2E8F0;
        --shadow:         0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
        --shadow-lg:      0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
        --radius:         12px;
        --transition:     all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body { background-color: #F0F4FF; }
    * { margin: 0; padding: 0; }

    /* ── Page header ── */
    .page-header { margin-bottom:2rem; }
    .page-header h2 { font-size:1.6rem; font-weight:700; color:var(--text-primary); margin-bottom:.5rem; margin-top:0; }
    .page-header p  { color:var(--text-secondary); font-size:.9rem; margin:0; }

    /* ── Alert ── */
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

    /* ── Table card ── */
    .content-section { margin-bottom:3rem; }
    .table-card {
        background:var(--surface); border:1px solid var(--border);
        border-radius:var(--radius); box-shadow:var(--shadow);
        margin-bottom:2rem; overflow:hidden; transition:var(--transition);
    }
    .table-card:hover { box-shadow:var(--shadow-lg); border-color:var(--primary); }
    .table-wrap { overflow-x:auto; }

    .corrections-table { width:100%; border-collapse:collapse; }
    .corrections-table thead { background:var(--background); border-bottom:1px solid var(--border); }
    .corrections-table th {
        padding:1rem 1.25rem; text-align:center; font-weight:700;
        color:var(--text-secondary); font-size:.75rem;
        text-transform:uppercase; letter-spacing:.05em;
    }
    .corrections-table tbody tr { border-bottom:1px solid var(--border); transition:var(--transition); }
    .corrections-table tbody tr:hover { background:#f8f9ff; }
    .corrections-table td { padding:1rem 1.25rem; vertical-align:middle; color:black; text-align:center; }
    .reason-cell { text-align:justify; text-align-last:left; max-width:220px; word-break:break-word; }

    /* ── Action buttons ── */
    .action-buttons { display:flex; gap:.5rem; align-items:center; justify-content:center; }
    .action-btn {
        padding:.6rem 1.1rem; border:none; border-radius:8px;
        font-size:.875rem; font-weight:600; cursor:pointer;
        transition:var(--transition); display:flex; align-items:center; gap:.4rem;
        white-space:nowrap;
    }
    .btn-approve { background:linear-gradient(135deg,var(--secondary),#059669); color:white; }
    .btn-approve:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); opacity:.9; }
    .btn-reject  { background:linear-gradient(135deg,var(--danger),#dc2626); color:white; }
    .btn-reject:hover  { transform:translateY(-2px); box-shadow:var(--shadow-lg); opacity:.9; }

    /* ── Empty ── */
    .empty-table-cell { padding:0; }
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
        width:90%; max-width:480px;
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
    .modal-icon.reject  { background:rgba(239,68,68,.12);  color:var(--danger); }
    .modal-header h3 { font-size:1.05rem; font-weight:700; color:var(--text-primary); margin:0; }
    .modal-header p  { font-size:.82rem; color:var(--text-secondary); margin:.2rem 0 0; }

    .modal-body { padding:1.25rem 1.5rem; }

    /* Summary info box */
    .modal-info-row {
        background:var(--background); border:1px solid var(--border);
        border-radius:8px; padding:.9rem 1rem; margin-bottom:1.1rem;
        display:grid; grid-template-columns:1fr 1fr; gap:.5rem .75rem;
        font-size:.875rem;
    }
    .modal-info-row span   { color:var(--text-secondary); font-size:.78rem; display:block; margin-bottom:.1rem; }
    .modal-info-row strong { font-weight:600; color:var(--text-primary); word-break:break-word; }

    /* Reason preview */
    .modal-reason-box {
        background:#fffbeb; border:1px solid #fde68a;
        border-radius:8px; padding:.85rem 1rem;
        font-size:.875rem; color:#78350f; line-height:1.5;
    }
    .modal-reason-box .reason-label {
        font-size:.75rem; font-weight:700; text-transform:uppercase;
        letter-spacing:.05em; color:#92400e; margin-bottom:.35rem; display:block;
    }

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

    .btn-confirm-reject {
        padding:.65rem 1.3rem;
        background:linear-gradient(135deg,var(--danger),#dc2626);
        color:white; border:none; border-radius:8px;
        font-size:.875rem; font-weight:600; cursor:pointer; transition:var(--transition);
        display:flex; align-items:center; gap:.4rem;
    }
    .btn-confirm-reject:hover { transform:translateY(-1px); box-shadow:var(--shadow); opacity:.92; }

    @media (max-width:768px) {
        .corrections-table { font-size:.85rem; }
        .corrections-table th, .corrections-table td { padding:.75rem .5rem; }
        .action-buttons { flex-direction:column; width:100%; }
        .action-btn { width:100%; justify-content:center; }
        .modal-info-row { grid-template-columns:1fr; }
    }
</style>

<!-- ══════════════════════════════════════════════
     CONFIRMATION MODAL
══════════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

        <div class="modal-header">
            <div class="modal-icon" id="modalIcon">
                <i class='bx' id="modalIconInner"></i>
            </div>
            <div>
                <h3 id="modalTitle">Confirm Action</h3>
                <p id="modalSubtitle">Please review the request before proceeding.</p>
            </div>
        </div>

        <form method="POST" id="modalForm">
            <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="modal_action"  id="modalAction"    value="">
            <input type="hidden" name="request_id"    id="modalRequestId" value="">
            <input type="hidden" name="grade_id"      id="modalGradeId"   value="">
            <input type="hidden" name="faculty_id"    id="modalFacultyId" value="">

            <div class="modal-body">

                <!-- Request summary -->
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
                        <span>Requested by</span>
                        <strong id="infoRequester">—</strong>
                    </div>
                    <div>
                        <span>Current Grade</span>
                        <strong id="infoGrade">—</strong>
                    </div>
                </div>

                <!-- Faculty's reason (read-only preview) -->
                <div class="modal-reason-box">
                    <span class="reason-label"><i class='bx bx-comment-detail'></i> Faculty's Reason for Correction</span>
                    <span id="infoReason">—</span>
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

<!-- ══════════════════════════════════════════════
     PAGE CONTENT
══════════════════════════════════════════════ -->
<div>
    <?php if (!empty($action_msg)): ?>
        <div class="alert-card alert-<?= htmlspecialchars($msg_type, ENT_QUOTES) ?>" id="flashAlert">
            <i class='bx <?= $msg_type === 'success' ? 'bx-check-circle' : ($msg_type === 'warning' ? 'bx-error-circle' : 'bx-error-circle') ?>'></i>
            <?= htmlspecialchars($action_msg, ENT_QUOTES) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2>Correction Requests</h2>
        <p>Review faculty requests to correct locked or approved grades.</p>
    </div>

    <div class="content-section">
        <div class="table-card">
            <div class="table-wrap">
                <table class="corrections-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject Code</th>
                            <th>Subject</th>
                            <th>Current Grade %</th>
                            <th>Requested By</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pending_rows)): ?>
                            <?php foreach ($pending_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['student'],      ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($row['subject_code'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($row['subject_name'], ENT_QUOTES) ?></td>
                                <td><?= number_format($row['percentage'], 2) ?>%</td>
                                <td><?= htmlspecialchars($row['requester'],    ENT_QUOTES) ?></td>
                                <td class="reason-cell"><?= htmlspecialchars($row['reason'], ENT_QUOTES) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button
                                            type="button"
                                            class="action-btn btn-approve"
                                            onclick="openModal('approve',
                                                <?= $row['request_id'] ?>,
                                                <?= $row['grade_id']   ?>,
                                                <?= $row['faculty_id'] ?>,
                                                <?= htmlspecialchars(json_encode($row['student']),      ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['subject_code'] . ' – ' . $row['subject_name']), ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['requester']),    ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode(number_format($row['percentage'], 2) . '%'), ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['reason']),       ENT_QUOTES) ?>
                                            )">
                                            <i class='bx bx-check'></i> Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="action-btn btn-reject"
                                            onclick="openModal('reject',
                                                <?= $row['request_id'] ?>,
                                                <?= $row['grade_id']   ?>,
                                                <?= $row['faculty_id'] ?>,
                                                <?= htmlspecialchars(json_encode($row['student']),      ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['subject_code'] . ' – ' . $row['subject_name']), ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['requester']),    ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode(number_format($row['percentage'], 2) . '%'), ENT_QUOTES) ?>,
                                                <?= htmlspecialchars(json_encode($row['reason']),       ENT_QUOTES) ?>
                                            )">
                                            <i class='bx bx-x'></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-table-cell">
                                    <div class="empty-message">
                                        <i class='bx bx-inbox'></i>
                                        <p>No pending correction requests at this time.</p>
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
const modalIcon       = document.getElementById('modalIcon');
const modalIconInner  = document.getElementById('modalIconInner');
const modalTitle      = document.getElementById('modalTitle');
const modalSub        = document.getElementById('modalSubtitle');
const btnApprove      = document.getElementById('btnConfirmApprove');
const btnReject       = document.getElementById('btnConfirmReject');

function openModal(action, requestId, gradeId, facultyId, student, subject, requester, grade, reason) {
    document.getElementById('modalAction').value    = action;
    document.getElementById('modalRequestId').value = requestId;
    document.getElementById('modalGradeId').value   = gradeId;
    document.getElementById('modalFacultyId').value = facultyId;

    document.getElementById('infoStudent').textContent   = student;
    document.getElementById('infoSubject').textContent   = subject;
    document.getElementById('infoRequester').textContent = requester;
    document.getElementById('infoGrade').textContent     = grade;
    document.getElementById('infoReason').textContent    = reason;

    if (action === 'approve') {
        modalTitle.textContent    = 'Approve Correction Request';
        modalSub.textContent      = 'The grade will be unlocked and returned to the faculty for resubmission.';
        modalIcon.className       = 'modal-icon approve';
        modalIconInner.className  = 'bx bx-check-circle';
        btnApprove.style.display  = 'flex';
        btnReject.style.display   = 'none';
    } else {
        modalTitle.textContent    = 'Reject Correction Request';
        modalSub.textContent      = 'The grade will remain locked. The faculty will be notified.';
        modalIcon.className       = 'modal-icon reject';
        modalIconInner.className  = 'bx bx-x-circle';
        btnApprove.style.display  = 'none';
        btnReject.style.display   = 'flex';
    }

    backdrop.classList.add('open');
}

function closeModal() {
    backdrop.classList.remove('open');
}

document.getElementById('modalCancel').addEventListener('click', closeModal);
backdrop.addEventListener('click', function (e) {
    if (e.target === backdrop) closeModal();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});
</script>