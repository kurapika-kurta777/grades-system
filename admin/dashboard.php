<?php
/*
 * ADMIN DASHBOARD CONTENT
 * This file is included by index.php (router)
 */

ob_start();
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

requireRole([4]);

$admin_id   = $_SESSION["user_id"];
$admin_name = $_SESSION["user_name"] ?? "Admin";

try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM users");
    $stmt->execute();
    $total_users_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM users WHERE is_active = 1");
    $stmt->execute();
    $active_users_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Count approved semestral grades (the academically meaningful unit)
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM semestral_grades WHERE status = 'Approved'");
    $stmt->execute();
    $approved_semestral_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Pending items needing admin attention
    $stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM grades          WHERE status = 'Pending') +
            (SELECT COUNT(*) FROM semestral_grades WHERE status = 'Submitted') AS pending_total
    ");
    $stmt->execute();
    $pending_count = $stmt->get_result()->fetch_assoc()['pending_total'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count FROM audit_logs
        WHERE action_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $recent_logs_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $stats = [
        'total_users'        => $total_users_count,
        'active_users'       => $active_users_count,
        'approved_semestral' => $approved_semestral_count,
        'pending_grades'     => $pending_count,
        'recent_logs'        => $recent_logs_count,
    ];
} catch (Exception $e) {
    $stats = [
        'total_users'        => 0,
        'active_users'       => 0,
        'approved_semestral' => 0,
        'pending_grades'     => 0,
        'recent_logs'        => 0,
    ];
    error_log("Database error in admin dashboard: " . $e->getMessage());
}

$recent_activities = [];
try {
    $stmt = $conn->prepare("
        SELECT al.action_time, u.full_name, al.action
        FROM audit_logs al
        JOIN users u ON al.user_id = u.user_id
        ORDER BY al.action_time DESC
        LIMIT 7
    ");
    $stmt->execute();
    $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($activities as $activity) {
        $diff = time() - strtotime($activity['action_time']);
        if      ($diff < 60)    $time = 'just now';
        elseif  ($diff < 3600)  $time = floor($diff / 60)   . 'm ago';
        elseif  ($diff < 86400) $time = floor($diff / 3600)  . 'h ago';
        else                    $time = floor($diff / 86400) . 'd ago';

        $recent_activities[] = [
            'actor'  => $activity['full_name'],
            'action' => $activity['action'],
            'time'   => $time,
        ];
    }
} catch (Exception $e) {
    error_log("Dashboard activities error: " . $e->getMessage());
}

// Role distribution for the mini chart
$role_dist = [];
try {
    $stmt = $conn->prepare("
        SELECT r.role_name, COUNT(u.user_id) AS cnt
        FROM roles r
        LEFT JOIN users u ON r.role_id = u.role_id AND u.is_active = 1
        GROUP BY r.role_id, r.role_name
        ORDER BY r.role_id
    ");
    $stmt->execute();
    $role_dist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) { /* non-critical */ }
?>

<style>
/* ── Shared admin design tokens ── */
:root {
    --adm-primary:      #3B82F6;
    --adm-primary-dk:   #2563EB;
    --adm-secondary:    #10B981;
    --adm-accent:       #F59E0B;
    --adm-danger:       #EF4444;
    --adm-surface:      #FFFFFF;
    --adm-bg:           #F8FAFC;
    --adm-border:       #E2E8F0;
    --adm-text:         #1E293B;
    --adm-text-muted:   #64748B;
    --adm-shadow:       0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
    --adm-shadow-lg:    0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05);
    --adm-radius:       12px;
    --adm-transition:   all 0.3s cubic-bezier(0.4,0,0.2,1);
}

/* ── Page header ── */
.adm-page-header { margin-bottom: 2rem; }
.adm-page-header h2 {
    font-size: 1.6rem; font-weight: 700;
    color: var(--adm-text); margin-bottom: .25rem;
}
.adm-page-header p { color: var(--adm-text-muted); font-size: .9rem; }

/* ── Stats grid ── */
.adm-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.adm-stat-card {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius);
    padding: 1.4rem 1.5rem;
    box-shadow: var(--adm-shadow);
    transition: var(--adm-transition);
    position: relative;
    overflow: hidden;
    min-width: 0; /* prevent grid blowout */
}

/* ── Sidebar-open: shrink text content only, card size stays the same ── */
body.sidebar-open .adm-stats-grid {
    gap: .75rem;
}
body.sidebar-open .adm-stat-icon {
    font-size: 1.1rem;
    margin-bottom: .6rem;
}
body.sidebar-open .adm-stat-value {
    font-size: 1.45rem;
}
body.sidebar-open .adm-stat-label {
    font-size: .68rem;
}
body.sidebar-open .adm-stat-note {
    font-size: .62rem;
}
.adm-stat-card::after {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 100%; height: 3px;
}
.adm-stat-card:nth-child(1)::after { background: var(--adm-primary); }
.adm-stat-card:nth-child(2)::after { background: var(--adm-secondary); }
.adm-stat-card:nth-child(3)::after { background: var(--adm-accent); }
.adm-stat-card:nth-child(4)::after { background: var(--adm-danger); }
.adm-stat-card:nth-child(5)::after { background: #8B5CF6; }

.adm-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--adm-shadow-lg);
}

.adm-stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; margin-bottom: .9rem;
}
.adm-stat-card:nth-child(1) .adm-stat-icon { background: rgba(59,130,246,.1);  color: var(--adm-primary);   }
.adm-stat-card:nth-child(2) .adm-stat-icon { background: rgba(16,185,129,.1);  color: var(--adm-secondary); }
.adm-stat-card:nth-child(3) .adm-stat-icon { background: rgba(245,158,11,.1);  color: var(--adm-accent);    }
.adm-stat-card:nth-child(4) .adm-stat-icon { background: rgba(239,68,68,.1);   color: var(--adm-danger);    }
.adm-stat-card:nth-child(5) .adm-stat-icon { background: rgba(139,92,246,.1);  color: #8B5CF6;              }

.adm-stat-value {
    font-size: 1.9rem; font-weight: 700;
    color: var(--adm-text); line-height: 1;
    margin-bottom: .3rem;
}
.adm-stat-label {
    font-size: .78rem; font-weight: 600;
    color: var(--adm-text-muted);
    text-transform: uppercase; letter-spacing: .05em;
}
.adm-stat-note {
    font-size: .72rem; color: var(--adm-text-muted);
    margin-top: .4rem;
}

/* ── Content grid ── */
.adm-content-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* ── Cards ── */
.adm-card {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius);
    box-shadow: var(--adm-shadow);
    overflow: hidden;
}
.adm-card-header {
    padding: 1.1rem 1.4rem;
    border-bottom: 1px solid var(--adm-border);
    display: flex; align-items: center; justify-content: space-between;
}
.adm-card-title {
    font-size: 1rem; font-weight: 700;
    color: var(--adm-text);
    display: flex; align-items: center; gap: .5rem;
}
.adm-card-title i { color: var(--adm-primary); font-size: 1.15rem; }
.adm-card-body   { padding: 0; }

/* ── Activity list ── */
.adm-activity-list { list-style: none; }
.adm-activity-item {
    display: flex; align-items: flex-start;
    gap: .85rem; padding: .9rem 1.4rem;
    border-bottom: 1px solid var(--adm-border);
    transition: var(--adm-transition);
}
.adm-activity-item:last-child { border-bottom: none; }
.adm-activity-item:hover      { background: var(--adm-bg); }

.adm-activity-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--adm-primary);
    flex-shrink: 0; margin-top: 6px;
}
.adm-activity-text { flex: 1; min-width: 0; }
.adm-activity-actor {
    font-size: .85rem; font-weight: 600;
    color: var(--adm-text);
}
.adm-activity-action {
    font-size: .8rem; color: var(--adm-text-muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 320px;
}
.adm-activity-time {
    font-size: .75rem; color: var(--adm-text-muted);
    flex-shrink: 0; white-space: nowrap;
}
.adm-empty { padding: 2.5rem 1.4rem; text-align: center; color: var(--adm-text-muted); font-size: .9rem; }

/* ── Quick actions ── */
.adm-quick-actions { padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .6rem; }
.adm-qa-btn {
    display: flex; align-items: center; gap: .75rem;
    padding: .85rem 1rem;
    background: var(--adm-bg);
    border: 1px solid var(--adm-border);
    border-radius: 8px;
    text-decoration: none;
    color: var(--adm-text);
    font-weight: 500; font-size: .88rem;
    transition: var(--adm-transition);
}
.adm-qa-btn:hover {
    background: #EFF6FF;
    border-color: var(--adm-primary);
    color: var(--adm-primary-dk);
    transform: translateX(3px);
}
.adm-qa-btn i { font-size: 1.2rem; color: var(--adm-primary); flex-shrink: 0; }
.adm-qa-btn-desc { font-size: .73rem; color: var(--adm-text-muted); font-weight: 400; }

/* ── Role distribution ── */
.adm-role-dist { padding: 1.2rem 1.4rem; }
.adm-role-row  { display: flex; align-items: center; gap: .75rem; margin-bottom: .7rem; }
.adm-role-row:last-child { margin-bottom: 0; }
.adm-role-name { font-size: .82rem; font-weight: 600; color: var(--adm-text); width: 80px; flex-shrink: 0; }
.adm-role-bar-wrap { flex: 1; height: 8px; background: var(--adm-border); border-radius: 99px; overflow: hidden; }
.adm-role-bar  { height: 100%; border-radius: 99px; }
.adm-role-count { font-size: .8rem; font-weight: 700; color: var(--adm-text-muted); width: 24px; text-align: right; flex-shrink: 0; }

/* ── System health strip ── */
.adm-health-strip {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--adm-radius);
    box-shadow: var(--adm-shadow);
    padding: 1rem 1.5rem;
    display: flex; align-items: center; gap: 2rem;
    flex-wrap: wrap;
}
.adm-health-item { display: flex; align-items: center; gap: .5rem; font-size: .83rem; }
.adm-health-dot  { width: 9px; height: 9px; border-radius: 50%; background: var(--adm-secondary); }
.adm-health-label { color: var(--adm-text-muted); }
.adm-health-val   { font-weight: 600; color: var(--adm-text); }

@media (max-width: 900px) {
    .adm-content-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .adm-stats-grid { grid-template-columns: 1fr 1fr !important; }
}
</style>

<div class="adm-page-header">
    <h2>Dashboard</h2>
    <p>System overview — users, grades, and recent activity at a glance.</p>
</div>

<!-- Stats -->
<div class="adm-stats-grid">
    <div class="adm-stat-card">
        <div class="adm-stat-icon"><i class='bx bx-group'></i></div>
        <div class="adm-stat-value"><?= $stats['total_users'] ?></div>
        <div class="adm-stat-label">Total Users</div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon"><i class='bx bx-user-check'></i></div>
        <div class="adm-stat-value"><?= $stats['active_users'] ?></div>
        <div class="adm-stat-label">Active Users</div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon"><i class='bx bx-trophy'></i></div>
        <div class="adm-stat-value"><?= $stats['approved_semestral'] ?></div>
        <div class="adm-stat-label">Approved Final Grades</div>
        <div class="adm-stat-note">Semestral — official records</div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon"><i class='bx bx-time-five'></i></div>
        <div class="adm-stat-value"><?= $stats['pending_grades'] ?></div>
        <div class="adm-stat-label">Pending Grade Items</div>
        <div class="adm-stat-note">Term + semestral awaiting approval</div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon"><i class='bx bx-pulse'></i></div>
        <div class="adm-stat-value"><?= $stats['recent_logs'] ?></div>
        <div class="adm-stat-label">Log Events (24h)</div>
    </div>
</div>

<!-- Content Grid -->
<div class="adm-content-grid">

    <!-- Recent Activity -->
    <div class="adm-card">
        <div class="adm-card-header">
            <span class="adm-card-title"><i class='bx bx-history'></i> Recent Activity</span>
        </div>
        <div class="adm-card-body">
            <?php if (!empty($recent_activities)): ?>
            <ul class="adm-activity-list">
                <?php foreach ($recent_activities as $act): ?>
                <li class="adm-activity-item">
                    <div class="adm-activity-dot"></div>
                    <div class="adm-activity-text">
                        <div class="adm-activity-actor"><?= htmlspecialchars($act['actor'], ENT_QUOTES) ?></div>
                        <div class="adm-activity-action"><?= htmlspecialchars($act['action'], ENT_QUOTES) ?></div>
                    </div>
                    <div class="adm-activity-time"><?= htmlspecialchars($act['time'], ENT_QUOTES) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="adm-empty"><i class='bx bx-inbox' style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No recent activity.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Quick Actions -->
        <div class="adm-card">
            <div class="adm-card-header">
                <span class="adm-card-title"><i class='bx bx-zap'></i> Quick Actions</span>
            </div>
            <div class="adm-card-body">
                <div class="adm-quick-actions">
                    <a href="?page=user_management" class="adm-qa-btn">
                        <i class='bx bx-group'></i>
                        <span>
                            <div>User Management</div>
                            <div class="adm-qa-btn-desc">Create, edit & deactivate accounts</div>
                        </span>
                    </a>
                    <a href="?page=grade_reports" class="adm-qa-btn">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <span>
                            <div>Grade Reports</div>
                            <div class="adm-qa-btn-desc">View academic performance summaries</div>
                        </span>
                    </a>
                    <a href="?page=audit_logs" class="adm-qa-btn">
                        <i class='bx bx-file-find'></i>
                        <span>
                            <div>Audit Logs</div>
                            <div class="adm-qa-btn-desc">Monitor all system actions</div>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <!-- User Role Distribution -->
        <?php if (!empty($role_dist)): ?>
        <div class="adm-card">
            <div class="adm-card-header">
                <span class="adm-card-title"><i class='bx bx-pie-chart-alt'></i> Active Users by Role</span>
            </div>
            <div class="adm-card-body">
                <div class="adm-role-dist">
                    <?php
                    $total_active = max(1, array_sum(array_column($role_dist, 'cnt')));
                    $bar_colors   = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'];
                    foreach ($role_dist as $i => $rd):
                        $pct = round(($rd['cnt'] / $total_active) * 100);
                        $col = $bar_colors[$i % count($bar_colors)];
                    ?>
                    <div class="adm-role-row">
                        <span class="adm-role-name"><?= htmlspecialchars($rd['role_name'], ENT_QUOTES) ?></span>
                        <div class="adm-role-bar-wrap">
                            <div class="adm-role-bar" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                        </div>
                        <span class="adm-role-count"><?= (int)$rd['cnt'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- System Health Strip -->
<div class="adm-health-strip">
    <div class="adm-health-item">
        <span class="adm-health-dot"></span>
        <span class="adm-health-label">Database</span>
        <span class="adm-health-val">Online</span>
    </div>
    <div class="adm-health-item">
        <span class="adm-health-dot" style="background:var(--adm-secondary);"></span>
        <span class="adm-health-label">Session Security</span>
        <span class="adm-health-val">Active</span>
    </div>
    <div class="adm-health-item">
        <span class="adm-health-dot" style="background:var(--adm-primary);"></span>
        <span class="adm-health-label">CSRF Protection</span>
        <span class="adm-health-val">Enabled</span>
    </div>
    <div class="adm-health-item" style="margin-left:auto;">
        <span class="adm-health-label">Logged in as</span>
        <span class="adm-health-val">&nbsp;<?= htmlspecialchars($admin_name, ENT_QUOTES) ?></span>
    </div>
</div>