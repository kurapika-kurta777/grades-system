<?php
/*
 * ADMIN AUDIT LOGS — with HMAC integrity verification
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/audit_hmac.php';
require_once __DIR__ . '/../includes/security_headers.php';

requireRole([4]);

// ── Filter parameters ──
$selected_user      = isset($_GET['user'])          ? (int)$_GET['user']          : 0;
$selected_date_from = isset($_GET['date_from'])     ? trim($_GET['date_from'])     : '';
$selected_date_to   = isset($_GET['date_to'])       ? trim($_GET['date_to'])       : '';
$search_action      = isset($_GET['action_search']) ? trim($_GET['action_search']) : '';

// ── User list for filter dropdown ──
$users_stmt = $conn->prepare("
    SELECT u.user_id, u.full_name, u.email, r.role_name
    FROM users u JOIN roles r ON u.role_id = r.role_id
    ORDER BY r.role_id, u.full_name
");
$users_stmt->execute();
$users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$users_stmt->close();

// ── Build filtered query (now includes row_hmac) ──
$query = "
    SELECT al.log_id, al.user_id, al.action, al.action_time, al.row_hmac,
           u.full_name, u.email, r.role_name
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id
    JOIN roles r ON u.role_id  = r.role_id
";

$params = []; $types = ''; $where = [];

if ($selected_user > 0)          { $where[] = "al.user_id = ?";          $params[] = $selected_user;      $types .= 'i'; }
if (!empty($selected_date_from))  { $where[] = "DATE(al.action_time) >= ?"; $params[] = $selected_date_from; $types .= 's'; }
if (!empty($selected_date_to))    { $where[] = "DATE(al.action_time) <= ?"; $params[] = $selected_date_to;   $types .= 's'; }
if (!empty($search_action))       { $where[] = "al.action LIKE ?";          $params[] = '%' . $search_action . '%'; $types .= 's'; }
if (!empty($where)) $query .= " WHERE " . implode(" AND ", $where);
$query .= " ORDER BY al.action_time DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$audit_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Summary stats ──
$total_logs   = count($audit_logs);
$unique_users = count(array_unique(array_column($audit_logs, 'user_id')));
$today_logs   = count(array_filter($audit_logs, function($l) {
    return date('Y-m-d', strtotime($l['action_time'])) === date('Y-m-d');
}));

$last_stmt = $conn->prepare("SELECT action_time FROM audit_logs ORDER BY action_time DESC LIMIT 1");
$last_stmt->execute();
$last_row      = $last_stmt->get_result()->fetch_assoc();
$last_activity = $last_row ? date('H:i', strtotime($last_row['action_time'])) : '--:--';
$last_stmt->close();

// ── HMAC integrity — count tampered rows within current filter result ──
$tampered_ids = [];
foreach ($audit_logs as $log) {
    $ok = AuditHMAC::verify(
        (int)$log['log_id'],
        (int)$log['user_id'],
        $log['action'],
        $log['action_time'],
        $log['row_hmac'] ?? null
    );
    if (!$ok) $tampered_ids[] = (int)$log['log_id'];
}
$tampered_count = count($tampered_ids);

// ── Bulk integrity check for the FULL table (admin-triggered) ──
// Only run when explicitly requested to avoid slowing every page load
$full_tampered = [];
if (isset($_GET['run_integrity_check']) && $_GET['run_integrity_check'] === '1') {
    $full_tampered = AuditHMAC::bulkVerify($conn);
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

/* ── Integrity banner ── */
.adm-integrity-banner {
    display: flex; align-items: center; gap: .75rem;
    padding: .9rem 1.25rem; border-radius: var(--adm-radius);
    margin-bottom: 1.5rem; border: 1px solid; font-size: .875rem;
}
.adm-integrity-ok      { background: rgba(16,185,129,.08); border-color: var(--adm-secondary); color: #065F46; }
.adm-integrity-warn    { background: rgba(239,68,68,.08); border-color: var(--adm-danger);    color: #991b1b; }
.adm-integrity-unknown { background: rgba(245,158,11,.08); border-color: var(--adm-accent);   color: #92400e; }
.adm-integrity-banner i { font-size: 1.2rem; flex-shrink: 0; }
.adm-integrity-banner a { color: inherit; font-weight: 700; }

/* ── Filter card ── */
.adm-filters { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); padding: 1.4rem; margin-bottom: 2rem; }
.adm-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; align-items: end; }
.adm-filter-group { display: flex; flex-direction: column; gap: .4rem; }
.adm-filter-group label { font-size: .8rem; font-weight: 600; color: var(--adm-text); text-transform: uppercase; letter-spacing: .04em; }
.adm-filter-group input, .adm-filter-group select { padding: .6rem .85rem; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem; background: var(--adm-surface); color: var(--adm-text); font-family: inherit; transition: var(--adm-transition); }
.adm-filter-group input:focus, .adm-filter-group select:focus { outline: none; border-color: var(--adm-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.adm-filter-actions { display: flex; gap: .5rem; align-items: flex-end; }
.adm-btn-apply { padding: .6rem 1.2rem; background: var(--adm-primary); color: #fff; border: none; border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition); white-space: nowrap; }
.adm-btn-apply:hover { background: var(--adm-primary-dk); transform: translateY(-1px); }
.adm-btn-reset { padding: .6rem 1rem; background: var(--adm-surface); color: var(--adm-text); border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition); white-space: nowrap; }
.adm-btn-reset:hover { border-color: var(--adm-text-muted); background: var(--adm-bg); }

/* ── Summary cards ── */
.adm-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.adm-summary-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); padding: 1.3rem 1.5rem; }
.adm-summary-card h3 { font-size: .85rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
.adm-summary-card h3 i { color: var(--adm-primary); font-size: 1rem; }
.adm-summary-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.adm-stat-box { text-align: center; padding: .9rem .75rem; background: var(--adm-bg); border: 1px solid var(--adm-border); border-radius: 8px; }
.adm-stat-box-num { font-size: 1.8rem; font-weight: 700; color: var(--adm-primary); display: block; margin-bottom: .2rem; line-height: 1; }
.adm-stat-box-num.tampered { color: var(--adm-danger); }
.adm-stat-box-label { font-size: .75rem; color: var(--adm-text-muted); font-weight: 500; }

/* ── Table card ── */
.adm-table-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); overflow: hidden; margin-bottom: 2rem; }
.adm-table-card-header { padding: .9rem 1.25rem; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center; }
.adm-table-card-title  { font-size: 1rem; font-weight: 700; color: var(--adm-text); }
.adm-table-card-subtitle { font-size: .82rem; color: var(--adm-text-muted); }
.adm-table-wrap { overflow-x: auto; }
.adm-audit-table { width: 100%; border-collapse: collapse; }
.adm-audit-table th { padding: .85rem 1.1rem; text-align: center; background: var(--adm-bg); font-size: .73rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--adm-border); }
.adm-audit-table td { padding: .85rem 1.1rem; border-bottom: 1px solid var(--adm-border); vertical-align: middle; text-align: center; font-size: .875rem; color: var(--adm-text); }
.adm-audit-table tbody tr:hover { background: var(--adm-bg); }
.adm-audit-table tbody tr:last-child td { border-bottom: none; }
.adm-audit-table tbody tr.adm-row-tampered { background: rgba(239,68,68,.06); }
.adm-audit-table tbody tr.adm-row-tampered:hover { background: rgba(239,68,68,.10); }

.adm-user-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; }
.adm-user-name  { font-weight: 600; color: var(--adm-text); }
.adm-user-email { font-size: .78rem; color: var(--adm-text-muted); }

.adm-role-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .25rem .65rem; border-radius: 5px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.adm-role-Admin     { background: #FEF3C7; color: #92400E; }
.adm-role-Faculty   { background: #D1FAE5; color: #065F46; }
.adm-role-Registrar { background: #DBEAFE; color: #1E40AF; }
.adm-role-Student   { background: #F3E8FF; color: #6B21A8; }

.adm-action-text { font-weight: 500; text-align: left !important; max-width: 360px; }
.adm-timestamp { font-size: .82rem; color: var(--adm-text-muted); font-family: 'DM Mono', monospace; }

/* ── HMAC badge ── */
.adm-hmac-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .22rem .6rem; border-radius: 5px;
    font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
}
.adm-hmac-ok      { background: rgba(16,185,129,.1); color: #065F46; border: 1px solid rgba(16,185,129,.3); }
.adm-hmac-fail    { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
.adm-hmac-missing { background: rgba(245,158,11,.1); color: #92400e; border: 1px solid rgba(245,158,11,.3); }

.adm-no-data { text-align: center; padding: 3rem 2rem; background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); }
.adm-no-data h3 { color: var(--adm-text); font-size: 1.1rem; margin-bottom: .4rem; }
.adm-no-data p  { color: var(--adm-text-muted); font-size: .9rem; }

.adm-export-strip { display: flex; justify-content: flex-end; margin-bottom: .75rem; }
.adm-btn-export { padding: .55rem 1.1rem; background: var(--adm-secondary); color: #fff; border: none; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition); }
.adm-btn-export:hover { background: #059669; transform: translateY(-1px); }

@media (max-width: 700px) {
    .adm-filter-grid { grid-template-columns: 1fr; }
    .adm-summary-grid { grid-template-columns: 1fr; }
    .adm-audit-table th, .adm-audit-table td { padding: .65rem .75rem; }
}
</style>

<div class="adm-page-header">
    <h2>Audit Logs</h2>
    <p>Monitor all system actions and user activity. HMAC integrity verification detects any direct database tampering.</p>
</div>

<!-- ── Integrity status banner ── -->
<?php if (!empty($full_tampered)): ?>
    <div class="adm-integrity-banner adm-integrity-warn">
        <i class='bx bx-error-circle'></i>
        <span>
            <strong>⚠ Integrity Violation Detected:</strong>
            <?= count($full_tampered) ?> audit log row<?= count($full_tampered) !== 1 ? 's' : '' ?> failed HMAC verification
            (log_id: <?= implode(', ', array_slice($full_tampered, 0, 10)) . (count($full_tampered) > 10 ? '…' : '') ?>).
            These rows may have been tampered with directly in the database.
        </span>
    </div>
<?php elseif (isset($_GET['run_integrity_check'])): ?>
    <div class="adm-integrity-banner adm-integrity-ok">
        <i class='bx bx-shield-check'></i>
        <strong>Full Integrity Check Passed:</strong>&nbsp;All audit log rows verified — no tampering detected.
    </div>
<?php else: ?>
    <div class="adm-integrity-banner adm-integrity-unknown">
        <i class='bx bx-info-circle'></i>
        <span>
            HMAC integrity is verified per-row in the table below. To run a full database-wide check,
            <a href="?page=audit_logs&run_integrity_check=1">click here</a>.
        </span>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="adm-filters">
    <form method="GET" class="adm-filter-grid">
        <input type="hidden" name="page" value="audit_logs">

        <div class="adm-filter-group">
            <label for="al_user">User</label>
            <select name="user" id="al_user">
                <option value="0">All Users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= $selected_user == $u['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name'] . ' (' . $u['role_name'] . ')', ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="adm-filter-group">
            <label for="al_from">Date From</label>
            <input type="date" name="date_from" id="al_from" value="<?= htmlspecialchars($selected_date_from, ENT_QUOTES) ?>">
        </div>

        <div class="adm-filter-group">
            <label for="al_to">Date To</label>
            <input type="date" name="date_to" id="al_to" value="<?= htmlspecialchars($selected_date_to, ENT_QUOTES) ?>">
        </div>

        <div class="adm-filter-group">
            <label for="al_action">Search Action</label>
            <input type="text" name="action_search" id="al_action" placeholder="e.g. logged in, approved…" value="<?= htmlspecialchars($search_action, ENT_QUOTES) ?>">
        </div>

        <div class="adm-filter-actions">
            <button type="submit" class="adm-btn-apply"><i class='bx bx-filter-alt'></i> Apply</button>
            <a href="?page=audit_logs" class="adm-btn-reset"><i class='bx bx-reset'></i> Reset</a>
        </div>
    </form>
</div>

<!-- Summary cards -->
<div class="adm-summary-grid">
    <div class="adm-summary-card">
        <h3><i class='bx bx-bar-chart'></i> Activity Summary</h3>
        <div class="adm-summary-stats">
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $total_logs ?></span>
                <span class="adm-stat-box-label">Matching Logs</span>
            </div>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $unique_users ?></span>
                <span class="adm-stat-box-label">Unique Users</span>
            </div>
        </div>
    </div>

    <div class="adm-summary-card">
        <h3><i class='bx bx-time-five'></i> System Activity</h3>
        <div class="adm-summary-stats">
            <div class="adm-stat-box">
                <span class="adm-stat-box-num"><?= $today_logs ?></span>
                <span class="adm-stat-box-label">Today (in filter)</span>
            </div>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num" style="font-size:1.3rem;"><?= $last_activity ?></span>
                <span class="adm-stat-box-label">Last System Event</span>
            </div>
        </div>
    </div>

    <div class="adm-summary-card">
        <h3><i class='bx bx-shield-alt-2'></i> HMAC Integrity (filter)</h3>
        <div class="adm-summary-stats">
            <div class="adm-stat-box">
                <span class="adm-stat-box-num" style="color:var(--adm-secondary);"><?= $total_logs - $tampered_count ?></span>
                <span class="adm-stat-box-label">✓ Intact Rows</span>
            </div>
            <div class="adm-stat-box">
                <span class="adm-stat-box-num <?= $tampered_count > 0 ? 'tampered' : '' ?>"><?= $tampered_count ?></span>
                <span class="adm-stat-box-label"><?= $tampered_count > 0 ? '⚠ Tampered Rows' : '✓ Tampered Rows' ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Export + Table -->
<?php if (!empty($audit_logs)): ?>
    <div class="adm-export-strip">
        <button class="adm-btn-export" onclick="exportAuditCSV()">
            <i class='bx bx-download'></i> Export CSV
        </button>
    </div>

    <div class="adm-table-card">
        <div class="adm-table-card-header">
            <span class="adm-table-card-title">System Audit Logs</span>
            <span class="adm-table-card-subtitle"><?= count($audit_logs) ?> record<?= count($audit_logs) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-audit-table" id="auditTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Timestamp</th>
                        <th title="HMAC-SHA256 integrity check — verifies this row has not been altered in the database">Integrity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_logs as $log):
                        $is_tampered = in_array((int)$log['log_id'], $tampered_ids, true);
                        $has_hmac    = !empty($log['row_hmac']);
                    ?>
                    <tr class="<?= $is_tampered ? 'adm-row-tampered' : '' ?>">
                        <td>
                            <div class="adm-user-cell">
                                <span class="adm-user-name"><?= htmlspecialchars($log['full_name'], ENT_QUOTES) ?></span>
                                <span class="adm-user-email"><?= htmlspecialchars($log['email'], ENT_QUOTES) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="adm-role-badge adm-role-<?= htmlspecialchars($log['role_name'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($log['role_name'], ENT_QUOTES) ?>
                            </span>
                        </td>
                        <td class="adm-action-text"><?= htmlspecialchars($log['action'], ENT_QUOTES) ?></td>
                        <td>
                            <div class="adm-timestamp">
                                <?= date('M d, Y', strtotime($log['action_time'])) ?><br>
                                <?= date('H:i:s', strtotime($log['action_time'])) ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!$has_hmac): ?>
                                <span class="adm-hmac-badge adm-hmac-missing" title="No HMAC stored — row predates signing or column not yet added">
                                    <i class='bx bx-question-mark'></i> No Signature
                                </span>
                            <?php elseif ($is_tampered): ?>
                                <span class="adm-hmac-badge adm-hmac-fail" title="HMAC mismatch — this row may have been altered directly in the database">
                                    <i class='bx bx-error-circle'></i> TAMPERED
                                </span>
                            <?php else: ?>
                                <span class="adm-hmac-badge adm-hmac-ok" title="HMAC verified — row is intact">
                                    <i class='bx bx-check-shield'></i> Verified
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="adm-no-data">
        <h3><i class='bx bx-search-alt'></i> No Logs Found</h3>
        <p>No audit logs match the selected filters. Try adjusting your criteria.</p>
    </div>
<?php endif; ?>

<script>
function exportAuditCSV() {
    const rows  = [['User', 'Email', 'Role', 'Action', 'Timestamp', 'Integrity']];
    const tbody = document.querySelectorAll('#auditTable tbody tr');
    tbody.forEach(tr => {
        const cells = tr.querySelectorAll('td');
        const name  = cells[0].querySelector('.adm-user-name')?.textContent.trim()  ?? '';
        const email = cells[0].querySelector('.adm-user-email')?.textContent.trim() ?? '';
        const role  = cells[1].textContent.trim();
        const act   = cells[2].textContent.trim();
        const ts    = cells[3].textContent.trim().replace(/\s+/g, ' ');
        const intg  = cells[4].textContent.trim();
        rows.push([name, email, role, act, ts, intg]);
    });
    const csv = rows.map(r => r.map(v => '"' + v.replace(/"/g, '""') + '"').join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'audit_logs_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}
</script>