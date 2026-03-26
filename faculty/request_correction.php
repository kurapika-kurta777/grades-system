<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

requireRole([1]); // Faculty

require_once __DIR__ . '/../includes/flash.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // GET: Show locked/approved grades that can be corrected
    $faculty_id = $_SESSION['user_id'];
    $action_msg = '';
    $msg_type = 'success';
    if ($flash = getFlash()) {
        $action_msg = $flash['msg'];
        $msg_type   = $flash['type'];
    }
    
    $periods = [
        '1st Year - 1st Semester',
        '1st Year - 2nd Semester',
        '2nd Year - 1st Semester',
        '2nd Year - 2nd Semester',
        '3rd Year - 1st Semester',
        '3rd Year - 2nd Semester'
    ];
    
    $all_sections = [
        'BSIT-32001-IM', 'BSIT-32002-IM', 'BSIT-32003-IM',
        'BSIT-32004-IM', 'BSIT-32005-IM', 'BSIT-32006-IM',
        'BSIT-32007-IM', 'BSIT-32008-IM', 'BSIT-32009-IM',
        'BSIT-32010-IM', 'BSIT-32011-IM', 'BSIT-32012-IM',
        'BSIT-32013-IM', 'BSIT-32014-IM', 'BSIT-32015-IM'
    ];
    
    $selected_period = isset($_GET['period']) ? $_GET['period'] : '3rd Year - 2nd Semester';
    if (!in_array($selected_period, $periods)) {
        $selected_period = '3rd Year - 2nd Semester';
    }
    
    $selected_section = isset($_GET['section']) ? $_GET['section'] : '';
    
    // Fetch all locked grades for this faculty's subjects, with the latest correction request status
    $locked_grades = [];
    if (!empty($selected_section)) {
        /*
         * We LEFT JOIN to the most-recent correction request for each grade.
         * This lets us show the correct action state in every scenario:
         *   - No request ever filed           → show Request button
         *   - Latest request is Pending       → Under Review
         *   - Latest request is Approved      → Grade was unlocked; faculty should go re-encode
         *   - Latest request is Rejected      → show Request button again (new attempt allowed)
         */
        $grades_query = "
            SELECT
                g.grade_id,
                u.full_name    AS student_name,
                s.subject_code,
                s.subject_name,
                g.academic_period,
                g.term,
                g.percentage,
                g.numeric_grade,
                g.status       AS grade_status,
                g.is_locked,
                gc_latest.status          AS correction_status,
                gc_latest.request_date    AS correction_date,
                gc_latest.decision_notes  AS correction_notes
            FROM grades g
            JOIN users    u  ON g.student_id  = u.user_id
            JOIN subjects s  ON g.subject_id  = s.subject_id
            LEFT JOIN (
                SELECT grade_id, status, request_date, decision_notes
                FROM grade_corrections gc_inner
                WHERE gc_inner.request_id = (
                    SELECT MAX(request_id)
                    FROM grade_corrections
                    WHERE grade_id = gc_inner.grade_id
                )
            ) gc_latest ON gc_latest.grade_id = g.grade_id
            WHERE s.faculty_id = ?
              AND g.is_locked  = 1
              AND g.academic_period = ?
              AND u.section    = ?
            ORDER BY s.subject_code, u.full_name, g.term
        ";

        $stmt = $conn->prepare($grades_query);
        $stmt->bind_param("iss", $faculty_id, $selected_period, $selected_section);
        $stmt->execute();
        $grades_result = $stmt->get_result();

        if ($grades_result) {
            while ($row = $grades_result->fetch_assoc()) {
                $locked_grades[] = $row;
            }
        }
        $stmt->close();
    }
    
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
    --accent: #F59E0B;
    --danger: #EF4444;
    --success: #22C55E;
    --secondary: #10B981;
    --primary: #3B82F6;
    --primary-dark: #1E40AF;
    --text-secondary: #64748B;
    --text-primary: #1E293B;
    --radius: 12px;
    --shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
}

.page-header { margin-bottom: 2rem; }
.page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-900); margin-bottom: 0.5rem; }
.page-header p  { color: var(--text-600); font-size: 0.9rem; }

.alert-card {
    padding: 1rem 1.5rem; border-radius: var(--radius);
    margin-bottom: 2rem; border: 1px solid;
    animation: slideIn 0.3s ease-out;
    display: flex; align-items: center; gap: 0.75rem;
}
@keyframes slideIn {
    from { transform: translateY(-10px); opacity: 0; }
    to   { transform: translateY(0);     opacity: 1; }
}
.alert-success { background: rgba(34,197,94,.1); border-color: var(--success); color: #166534; }
.alert-error   { background: rgba(239,68,68,.1); border-color: var(--danger);  color: #991b1b; }
.alert-warning { background: rgba(245,158,11,.1); border-color: var(--accent); color: #92400e; }

.content-section { margin-bottom: 0; }

.table-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r-md); box-shadow: var(--shadow-sm);
    margin-bottom: 2rem; overflow: hidden; transition: var(--transition);
}
.table-card:hover { box-shadow: var(--shadow-md); border-color: var(--blue-600); }
.table-wrap { overflow-x: auto; }

.grades-table { width: 100%; border-collapse: collapse; }
.grades-table thead { background: var(--bg); border-bottom: 1px solid var(--border); }
.grades-table th {
    padding: 1rem 1.25rem; text-align: center; font-weight: 700;
    color: var(--text-400); font-size: 0.75rem;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.grades-table tbody tr { border-bottom: 1px solid var(--border); transition: var(--transition); }
.grades-table tbody tr:hover { background: #f8f9ff; }
.grades-table td { padding: 1rem 1.25rem; vertical-align: middle; color: black; text-align: center; }

.reason-textarea {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    font-size: 0.9rem;
    background: var(--surface);
    color: var(--text-900);
    font-family: 'DM Sans', sans-serif;
    min-height: 80px;
    width: 100%;
    transition: var(--transition);
    resize: vertical;
}
.reason-textarea:focus {
    outline: none; border-color: var(--blue-600);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.action-btn {
    padding: 0.65rem 1.25rem;
    border: none; border-radius: 8px;
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    transition: var(--transition); white-space: nowrap;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.btn-request {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.btn-request:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); opacity: .92; }
.btn-request:disabled {
    background: rgba(100,116,139,.2); color: var(--text-secondary);
    cursor: not-allowed; transform: none; box-shadow: none; opacity: 1;
}

.badge-status {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0.75rem; border-radius: 6px;
    font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-pending  { background: #fef3c7; color: #92400e; }
.badge-approved { background: #f0fdf4; color: #166534; border: 1px solid #22c55e; }

.period-selector {
    margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;
}
.period-selector label { font-weight: 600; color: var(--text-900); font-size: 0.95rem; }
.period-selector form  { display: flex; align-items: center; gap: 1rem; }
.period-selector select {
    padding: 0.65rem 1rem; border: 1px solid var(--border);
    border-radius: var(--r-md); font-size: 0.9rem; color: var(--text-900);
    background: var(--surface); cursor: pointer;
    font-family: 'DM Sans', sans-serif; transition: var(--transition);
}
.period-selector select:hover  { border-color: var(--blue-600); }
.period-selector select:focus  { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }

.section-grid-container { margin-bottom: 2rem; }
.section-grid-title {
    font-size: 1rem; font-weight: 600; color: var(--text-900);
    margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
}
.section-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
}
.section-card {
    background: var(--surface); border: 2px solid var(--border);
    border-radius: var(--r-md); padding: 1.25rem; text-decoration: none;
    color: var(--text-900); display: flex; flex-direction: column;
    align-items: center; gap: 0.5rem; transition: var(--transition);
    cursor: pointer; position: relative;
}
.section-card:hover { border-color: var(--blue-600); background: rgba(59,130,246,.05); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.section-card.active { border-color: var(--blue-600); background: rgba(59,130,246,.08); box-shadow: var(--shadow-md); }
.section-card.active .section-card-icon i { color: var(--blue-600); }
.section-card.active .section-card-label { color: var(--blue-600); font-weight: 700; }
.section-card-icon { width: 40px; height: 40px; background: rgba(59,130,246,.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--text-secondary); }
.section-card-label { font-size: 0.85rem; font-weight: 600; color: var(--text-900); text-align: center; }
.section-card-badge { position: absolute; top: 0.5rem; right: 0.5rem; background: rgba(16,185,129,.1); color: var(--secondary); border: 1px solid rgba(16,185,129,.3); border-radius: 20px; font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }

.no-data { text-align: center; background: var(--surface); border: 2px solid var(--border); border-radius: var(--r-md);padding: 3rem 2rem; color: var(--text-600); }
.no-data-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
.no-data h3 { font-size: 1.25rem; font-weight: 600; color: var(--text-900); margin-bottom: 0.5rem; }
.no-data p  { font-size: 1rem; margin: 0; color: var(--text-600); }

/* ══════════════════════════════════════════
   CORRECTION REQUEST CONFIRMATION MODAL
══════════════════════════════════════════ */
.modal-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); backdrop-filter: blur(4px);
    z-index: 2000; align-items: center; justify-content: center;
}
.modal-backdrop.open { display: flex; }

.modal-box {
    background: var(--surface); border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);
    width: 90%; max-width: 480px;
    animation: modalIn .25s cubic-bezier(0.34,1.56,0.64,1);
    overflow: hidden;
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(.92) translateY(16px); }
    to   { opacity: 1; transform: scale(1)   translateY(0); }
}

.modal-box-header {
    padding: 1.4rem 1.5rem 1rem;
    display: flex; align-items: center; gap: .75rem;
    border-bottom: 1px solid rgba(59,130,246,.14);
}
.modal-box-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
    background: rgba(245,158,11,.12); color: #d97706;
}
.modal-box-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.modal-box-header p  { font-size: .82rem; color: var(--text-secondary); margin: .2rem 0 0; }

.modal-box-body { padding: 1.25rem 1.5rem; }

.modal-info-grid {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; padding: .9rem 1rem;
    display: grid; grid-template-columns: 1fr 1fr; gap: .5rem .75rem;
    margin-bottom: 1rem;
}
.modal-info-grid span   { color: var(--text-secondary); font-size: .78rem; display: block; margin-bottom: .1rem; }
.modal-info-grid strong { font-size: .875rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }

.modal-reason-preview {
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 8px; padding: .85rem 1rem;
    font-size: .875rem; color: #78350f; line-height: 1.5;
}
.modal-reason-preview .reason-label {
    font-size: .75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: #92400e; margin-bottom: .35rem; display: block;
}

.modal-warning-box {
    background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.25);
    border-radius: 8px; padding: .75rem 1rem; margin-top: .75rem;
    display: flex; align-items: flex-start; gap: .5rem;
    font-size: .82rem; color: #991b1b; line-height: 1.5;
}
.modal-warning-box i { flex-shrink: 0; margin-top: .1rem; }

.modal-box-footer {
    padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0;
    display: flex; justify-content: flex-end; gap: .6rem; background: #f8fafc;
}
.modal-btn-cancel {
    padding: .65rem 1.3rem; background: var(--surface); color: var(--text-primary);
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--transition);
}
.modal-btn-cancel:hover { background: #f8fafc; border-color: var(--text-secondary); }
.modal-btn-confirm-request {
    padding: .65rem 1.3rem;
    background: linear-gradient(135deg, var(--accent), #d97706);
    color: white; border: none; border-radius: 8px;
    font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; gap: .4rem;
}
.modal-btn-confirm-request:hover { transform: translateY(-1px); box-shadow: var(--shadow); opacity: .92; }

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
    border-radius: var(--r-md);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-top: 2rem;
    margin-bottom: 0;
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
    color: var(--blue-700);
}
.reminder-header i { font-size: 1.25rem; color: var(--blue-600); }

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
    color: var(--blue-600);
}

.reminder-item-title {
    font-size: 0.875rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
    color: var(--blue-700);
}

.reminder-item-text {
    font-size: 0.825rem;
    line-height: 1.6;
    color: var(--text-600);
}

.reminder-item-text strong { font-weight: 700; }

/* All reminder types use the same blue scheme */
.reminder-policy,
.reminder-deadline,
.reminder-limit,
.reminder-rejection,
.reminder-warning,
.reminder-info {
    background: rgba(59,130,246,.04);
    border-color: rgba(59,130,246,.18);
}

.reminder-policy .reminder-item-icon,
.reminder-deadline .reminder-item-icon,
.reminder-limit .reminder-item-icon,
.reminder-rejection .reminder-item-icon,
.reminder-warning .reminder-item-icon,
.reminder-info .reminder-item-icon {
    background: rgba(59,130,246,.12);
    color: var(--blue-600);
}

.reminder-policy .reminder-item-title,
.reminder-deadline .reminder-item-title,
.reminder-limit .reminder-item-title,
.reminder-rejection .reminder-item-title,
.reminder-warning .reminder-item-title,
.reminder-info .reminder-item-title {
    color: var(--blue-700);
}
</style>

<!-- ══════════════════════════════════════════════
     CORRECTION REQUEST CONFIRMATION MODAL
══════════════════════════════════════════════ -->
<div class="modal-backdrop" id="correctionModal">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="correctionModalTitle">

        <div class="modal-box-header">
            <div class="modal-box-icon">
                <i class='bx bx-edit-alt'></i>
            </div>
            <div>
                <h3 id="correctionModalTitle">Confirm Correction Request</h3>
                <p>Please review the details before submitting your request.</p>
            </div>
        </div>

        <form method="post" id="correctionModalForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="grade_id" id="modalGradeId" value="">
            <input type="hidden" name="reason"   id="modalReason"  value="">

            <div class="modal-box-body">
                <div class="modal-info-grid">
                    <div>
                        <span>Student</span>
                        <strong id="modalStudent">—</strong>
                    </div>
                    <div>
                        <span>Subject</span>
                        <strong id="modalSubject">—</strong>
                    </div>
                    <div>
                        <span>Term</span>
                        <strong id="modalTerm">—</strong>
                    </div>
                    <div>
                        <span>Current Grade</span>
                        <strong id="modalGrade">—</strong>
                    </div>
                </div>

                <div class="modal-reason-preview">
                    <span class="reason-label"><i class='bx bx-comment-detail'></i> Reason for Correction</span>
                    <span id="modalReasonPreview">—</span>
                </div>

                <div class="modal-warning-box">
                    <i class='bx bx-info-circle'></i>
                    <span>This request will be forwarded to the Registrar for review. The grade will remain locked until your request is approved.</span>
                </div>
            </div>

            <div class="modal-box-footer">
                <button type="button" class="modal-btn-cancel" id="correctionModalCancel">Cancel</button>
                <button type="submit" class="modal-btn-confirm-request">
                    <i class='bx bx-send'></i>
                    Submit Request
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss alerts
        document.querySelectorAll('.alert-card').forEach(card => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transition = 'opacity 0.3s ease-out';
                setTimeout(() => card.remove(), 300);
            }, 3600);
        });

        // Modal elements
        const modal        = document.getElementById('correctionModal');
        const modalCancel  = document.getElementById('correctionModalCancel');

        // Open modal when "Request" button is clicked
        document.querySelectorAll('.btn-open-correction-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                const row     = this.closest('tr');
                const gradeId = this.dataset.gradeId;
                const reason  = row.querySelector('.reason-textarea')?.value?.trim() || '';

                if (!reason) {
                    // Highlight the textarea if reason is empty
                    const ta = row.querySelector('.reason-textarea');
                    if (ta) {
                        ta.style.borderColor = '#ef4444';
                        ta.focus();
                        ta.addEventListener('input', function() { ta.style.borderColor = ''; }, { once: true });
                    }
                    return;
                }

                document.getElementById('modalGradeId').value   = gradeId;
                document.getElementById('modalReason').value    = reason;
                document.getElementById('modalStudent').textContent  = this.dataset.student;
                document.getElementById('modalSubject').textContent  = this.dataset.subject;
                document.getElementById('modalTerm').textContent     = this.dataset.term;
                document.getElementById('modalGrade').textContent    = this.dataset.grade;
                document.getElementById('modalReasonPreview').textContent = reason;

                modal.classList.add('open');
            });
        });

        modalCancel.addEventListener('click', () => modal.classList.remove('open'));
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.remove('open'); });
    });
</script>

    <div>
        <?php if (!empty($action_msg)): ?>
            <div class="alert-card alert-<?= $msg_type !== 'success' ? $msg_type : 'success' ?>">
                <i class='bx <?= $msg_type !== 'success' ? 'bx-error-circle' : 'bx-check-circle' ?>'></i>
                <?= htmlspecialchars($action_msg, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Grade Correction Requests</h2>
            <p>Submit correction requests for grades that need to be updated.</p>
        </div>
        
        <div class="period-selector">
            <form method="GET" action="" id="periodForm">
                <input type="hidden" name="page"    value="request_correction">
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
                    <a href="?page=request_correction&period=<?= urlencode($selected_period) ?>&section=<?= urlencode($sec) ?>"
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

        <div class="content-section">
            <?php if (empty($selected_section)): ?>
                <div class="no-data">
                    <div class="no-data-icon"><i class='bx bx-info-circle'></i></div>
                    <h3>Select a Section</h3>
                    <p>Choose a section from the grid above to view correction requests.</p>
                </div>
            <?php elseif (count($locked_grades) > 0): ?>
                <div class="table-card">
                    <div class="table-wrap">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Period</th>
                                    <th>Term</th>
                                    <th>Grade</th>
                                    <th>Grade Status</th>
                                    <th>Correction Status</th>
                                    <th>Reason for Correction</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locked_grades as $grade): ?>
                                <?php
                                    /*
                                     * Determine action state from correction_status + grade_status:
                                     *
                                     * correction_status = NULL      → no request ever filed → show Request button
                                     * correction_status = 'Pending' → waiting on registrar  → show "Under Review"
                                     * correction_status = 'Approved'→ registrar approved,
                                     *                                  grade was unlocked & faculty re-encoded,
                                     *                                  now grade is locked again (re-approved) → show "Resolved"
                                     * correction_status = 'Rejected'→ registrar rejected    → show Request button again
                                     */
                                    $corr_status  = $grade['correction_status'] ?? null;
                                    $grade_status = $grade['grade_status'];

                                    /*
                                     * Finalized = correction was Approved AND the grade itself
                                     * is now Approved again (full cycle complete). No more requests.
                                     *
                                     * Can request if:
                                     *   - No correction ever filed, OR
                                     *   - Last correction was Rejected
                                     *   AND the grade is NOT finalized
                                     */
                                    $is_finalized = ($corr_status === 'Approved' && strtolower($grade_status) === 'approved');
                                    $can_request  = !$is_finalized && ($corr_status === null || $corr_status === 'Rejected');
                                ?>
                                <tr>
                                    <td style="text-align:left; font-weight:600;">
                                        <?= htmlspecialchars($grade['student_name'], ENT_QUOTES) ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($grade['subject_code'], ENT_QUOTES) ?></strong><br>
                                        <small style="color:var(--text-600);"><?= htmlspecialchars($grade['subject_name'], ENT_QUOTES) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($grade['academic_period'], ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($grade['term'], ENT_QUOTES) ?></td>
                                    <td>
                                        <strong><?= number_format($grade['numeric_grade'], 2) ?></strong>
                                        <br><small style="color:var(--text-600);"><?= number_format($grade['percentage'], 2) ?>%</small>
                                    </td>

                                    <!-- GRADE STATUS column -->
                                    <td>
                                        <?php if (strtolower($grade_status) === 'approved'): ?>
                                            <span class="badge-status badge-approved">
                                                <i class='bx bx-check-circle'></i> Approved
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status badge-pending">
                                                <i class='bx bx-lock'></i> <?= htmlspecialchars($grade_status, ENT_QUOTES) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- CORRECTION STATUS column -->
                                    <td>
                                        <?php if ($corr_status === null): ?>
                                            <span style="color:var(--text-400); font-size:0.85rem;">—</span>

                                        <?php elseif ($corr_status === 'Pending'): ?>
                                            <span class="badge-status" style="background:rgba(245,158,11,.1);color:#92400e;border:1px solid rgba(245,158,11,.3);">
                                                <i class='bx bx-time'></i> Under Review
                                            </span>

                                        <?php elseif ($corr_status === 'Approved'): ?>
                                            <span class="badge-status badge-approved">
                                                <i class='bx bx-check-double'></i> Resolved
                                            </span>

                                        <?php elseif ($corr_status === 'Rejected'): ?>
                                            <span class="badge-status" style="background:rgba(239,68,68,.1);color:#991b1b;border:1px solid rgba(239,68,68,.3);">
                                                <i class='bx bx-x-circle'></i> Rejected
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- REASON textarea — only enabled when faculty can file a new request -->
                                    <td>
                                        <textarea
                                            class="reason-textarea"
                                            placeholder="Explain why this grade needs correction…"
                                            <?= !$can_request ? 'disabled' : '' ?>
                                        ></textarea>
                                    </td>

                                    <!-- ACTION column -->
                                    <td>
                                        <?php if ($corr_status === 'Pending'): ?>
                                            <!-- Request is with the registrar — nothing to do -->
                                            <span class="badge-status" style="background:rgba(59,130,246,.08);color:var(--primary);border:1px solid rgba(59,130,246,.25);white-space:nowrap;">
                                                <i class='bx bx-hourglass'></i> Awaiting Registrar
                                            </span>

                                        <?php elseif ($is_finalized): ?>
                                            <!-- Full cycle complete: correction approved → re-encoded → re-approved → permanently locked -->
                                            <span class="badge-status" style="background:rgba(100,116,139,.1);color:#334155;border:1px solid rgba(100,116,139,.3);white-space:nowrap;">
                                                <i class='bx bx-lock-alt'></i> Finalized
                                            </span>

                                        <?php elseif ($can_request): ?>
                                            <!-- No request yet, OR previous was rejected → allow new request -->
                                            <button
                                                type="button"
                                                class="action-btn btn-request btn-open-correction-modal"
                                                data-grade-id="<?= (int)$grade['grade_id'] ?>"
                                                data-student="<?= htmlspecialchars($grade['student_name'], ENT_QUOTES) ?>"
                                                data-subject="<?= htmlspecialchars($grade['subject_code'] . ' – ' . $grade['subject_name'], ENT_QUOTES) ?>"
                                                data-term="<?= htmlspecialchars($grade['term'], ENT_QUOTES) ?>"
                                                data-grade="<?= number_format($grade['numeric_grade'], 2) ?> (<?= number_format($grade['percentage'], 2) ?>%)"
                                            >
                                                <i class='bx bx-send'></i>
                                                <?= $corr_status === 'Rejected' ? 'Re-Request' : 'Request' ?>
                                            </button>

                                        <?php else: ?>
                                            <!-- Fallback — should not normally be reached -->
                                            <span style="color:var(--text-400); font-size:0.85rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="no-data">
                        <div class="no-data-icon"><i class='bx bx-book-open'></i></div>
                        <h3>No Data Available</h3>
                        <p>No locked grades found for this section and period.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════
                 REMINDER SECTION
            ══════════════════════════════════════════ -->
            <div class="reminder-section">
                <div class="reminder-header">
                    <i class='bx bx-info-circle'></i>
                    <span>Reminders on Grade Correction Requests</span>
                </div>
                <div class="reminder-body">
                    <div class="reminder-grid">

                        <div class="reminder-item reminder-policy">
                            <div class="reminder-item-icon"><i class='bx bx-file-blank'></i></div>
                            <div>
                                <div class="reminder-item-title">Formal & Exceptional Process</div>
                                <div class="reminder-item-text">
                                    Grade correction is a formal academic procedure reserved for genuine encoding errors (e.g., computational mistakes, missed scores). It is <strong>not</strong> a mechanism for reconsidering or negotiating a student's earned grade.
                                </div>
                            </div>
                        </div>

                        <div class="reminder-item reminder-deadline">
                            <div class="reminder-item-icon"><i class='bx bx-calendar-x'></i></div>
                            <div>
                                <div class="reminder-item-title">Filing Deadline</div>
                                <div class="reminder-item-text">
                                    Correction requests must be filed within the prescribed period set by the Registrar's Office after grades are officially submitted. Requests filed outside this window may be denied at the Registrar's discretion.
                                </div>
                            </div>
                        </div>

                        <div class="reminder-item reminder-limit">
                            <div class="reminder-item-icon"><i class='bx bx-revision'></i></div>
                            <div>
                                <div class="reminder-item-title">One Correction Per Grade</div>
                                <div class="reminder-item-text">
                                    Each term grade is allowed <strong>one correction cycle only</strong>. Once a correction request is approved by the Registrar, the faculty re-encodes the grade, and the Registrar gives final approval — that grade is <strong>permanently finalized</strong> and no further correction requests will be accepted.
                                </div>
                            </div>
                        </div>

                        <div class="reminder-item reminder-rejection">
                            <div class="reminder-item-icon"><i class='bx bx-undo'></i></div>
                            <div>
                                <div class="reminder-item-title">Rejected Requests</div>
                                <div class="reminder-item-text">
                                    If the Registrar rejects your correction request, you may file <strong>one additional request</strong> with a more detailed justification. If this second request is also rejected, the original grade stands as final.
                                </div>
                            </div>
                        </div>

                        <div class="reminder-item reminder-warning">
                            <div class="reminder-item-icon"><i class='bx bx-error-alt'></i></div>
                            <div>
                                <div class="reminder-item-title">Academic Integrity Warning</div>
                                <div class="reminder-item-text">
                                    All correction requests are logged and subject to audit. Submitting a correction request with false, misleading, or unsubstantiated justification constitutes a violation of academic integrity and may result in disciplinary action in accordance with the institution's Code of Conduct.
                                </div>
                            </div>
                        </div>

                        <div class="reminder-item reminder-info">
                            <div class="reminder-item-icon"><i class='bx bx-support'></i></div>
                            <div>
                                <div class="reminder-item-title">Questions & Appeals</div>
                                <div class="reminder-item-text">
                                    For questions regarding the correction process or to formally appeal a rejected request, contact the Registrar's Office directly. Appeals must be submitted in writing and accompanied by supporting documentation (e.g., original class records, score sheets).
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit;
}

// ══════════════════════════════════════════════
// POST handler (actual form submission via modal)
// ══════════════════════════════════════════════
if (empty($_POST['csrf_token'])) { http_response_code(400); die('Missing CSRF token'); }
csrf_validate_or_die($_POST['csrf_token']);

$grade_id = intval($_POST["grade_id"] ?? 0);
$reason   = trim($_POST["reason"] ?? '');

if (empty($reason)) {
    setFlash('Reason for correction is required.', 'error');
    header("Location: ?page=request_correction");
    exit;
}

// Validate that the grade belongs to this faculty and is locked
$check = $conn->prepare(
    "SELECT g.grade_id
     FROM grades g
     JOIN subjects s ON g.subject_id = s.subject_id
     WHERE g.grade_id = ? AND s.faculty_id = ? AND g.is_locked = 1"
);
$check->bind_param("ii", $grade_id, $_SESSION["user_id"]);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    http_response_code(403);
    logAction($conn, $_SESSION["user_id"], "Unauthorized correction request attempt for grade ID $grade_id");
    setFlash('You are not authorized to request this correction.', 'error');
    header("Location: ?page=request_correction");
    exit;
}

$conn->begin_transaction();
try {
    // Block if a Pending request already exists
    $dup = $conn->prepare(
        "SELECT request_id FROM grade_corrections WHERE grade_id = ? AND status = 'Pending' LIMIT 1 FOR UPDATE"
    );
    $dup->bind_param("i", $grade_id);
    $dup->execute();
    $dupRes = $dup->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $conn->rollback();
        setFlash('A correction request for this grade is already under review by the Registrar.', 'error');
        header("Location: ?page=request_correction");
        exit;
    }

    // Block if the correction cycle is already finalized:
    // i.e. there exists an Approved correction AND the grade itself is now Approved again
    $fin = $conn->prepare(
        "SELECT gc.request_id
         FROM grade_corrections gc
         JOIN grades g ON gc.grade_id = g.grade_id
         WHERE gc.grade_id = ?
           AND gc.status   = 'Approved'
           AND g.status    = 'Approved'
         LIMIT 1"
    );
    $fin->bind_param("i", $grade_id);
    $fin->execute();
    $finRes = $fin->get_result();
    if ($finRes && $finRes->num_rows > 0) {
        $conn->rollback();
        setFlash('This grade has already completed the correction cycle and is permanently finalized. No further corrections are allowed.', 'error');
        header("Location: ?page=request_correction");
        exit;
    }

    $stmt   = $conn->prepare(
        "INSERT INTO grade_corrections (grade_id, faculty_id, reason, status) VALUES (?, ?, ?, ?)"
    );
    $status = 'Pending';
    $stmt->bind_param("iiss", $grade_id, $_SESSION["user_id"], $reason, $status);
    $stmt->execute();

    logAction($conn, $_SESSION["user_id"], "Requested correction for grade ID $grade_id");
    $conn->commit();

    setFlash('Correction request submitted successfully.', 'success');
    header("Location: ?page=request_correction");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    setFlash('Server error. Please try again.', 'error');
    header("Location: ?page=request_correction");
    exit;
}
?>