<?php
/**
 * SECURITY PATCH: admin/user_management.php additions
 *
 * This file contains ONLY the security additions.
 * Paste/merge these into your existing admin/user_management.php:
 *
 *  1. Replace the `if ($action === 'create')` password-hashing block with the
 *     version below that enforces password complexity.
 *
 *  2. Add the re-authentication gate before any role promotion to Admin (role_id=4).
 *
 *  3. Add the password complexity helper function at the top of the file.
 *
 * The full merged file is provided below.
 */

ob_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security_headers.php';

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

requireRole([4]);

$self_id = (int)$_SESSION['user_id'];
$message = '';
$error   = '';

// ══════════════════════════════════════════════════════════════════
// PASSWORD COMPLEXITY HELPER
// ══════════════════════════════════════════════════════════════════

/**
 * Enforce the same password policy as register.php.
 * Returns an array of error strings; empty means password passes.
 */
function validatePasswordComplexity(string $password): array
{
    $errs = [];
    if (strlen($password) < 8)               $errs[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))   $errs[] = 'Password must include at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))   $errs[] = 'Password must include at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))   $errs[] = 'Password must include at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errs[] = 'Password must include at least one special character.';
    return $errs;
}

// ══════════════════════════════════════════════════════════════════
// ADMIN RE-AUTHENTICATION GATE FOR ROLE PROMOTION TO ADMIN
// ══════════════════════════════════════════════════════════════════

/**
 * When an admin tries to set another account to role_id=4 (Admin),
 * they must supply their own current password to confirm the action.
 * Returns null on success, or an error string on failure.
 */
function verifyAdminReAuth(mysqli $conn, int $admin_id, string $confirm_password): ?string
{
    if (empty($confirm_password)) {
        return 'You must enter your current password to promote a user to Admin.';
    }
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($confirm_password, $row['password_hash'])) {
        return 'Incorrect password. Admin promotion denied.';
    }
    return null; // success
}

// ── POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !csrf_check($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── CREATE ────────────────────────────────────────────────
        if ($action === 'create') {
            $full_name  = trim($_POST['full_name']  ?? '');
            $email      = trim($_POST['email']      ?? '');
            $password   = $_POST['password']        ?? '';
            $role_id    = (int)($_POST['role_id']   ?? 0);
            $program    = trim($_POST['program']    ?? '');
            $section    = trim($_POST['section']    ?? '');
            $year_level = ($role_id === 3 && !empty($_POST['year_level']))
                          ? (int)$_POST['year_level'] : null;

            if (empty($full_name) || empty($email) || empty($password) || $role_id < 1 || $role_id > 4) {
                $error = 'Please fill all required fields correctly.';
            } else {
                // ── Password complexity check (admin-created accounts) ─────
                $pw_errors = validatePasswordComplexity($password);
                if (!empty($pw_errors)) {
                    $error = implode(' ', $pw_errors);
                }

                // ── Re-auth gate for Admin promotion ──────────────────────
                if (empty($error) && $role_id === 4) {
                    $confirm_pw = $_POST['admin_confirm_password'] ?? '';
                    $reauth_err = verifyAdminReAuth($conn, $self_id, $confirm_pw);
                    if ($reauth_err !== null) {
                        $error = $reauth_err;
                        logAction($conn, $self_id, "SECURITY: Admin promotion re-auth failed for new email=$email from IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    }
                }

                if (empty($error)) {
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = 'That email address is already registered.';
                        $stmt->close();
                    } else {
                        $stmt->close();
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role_id, program, section, year_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param('ssisssi', $full_name, $email, $hash, $role_id, $program, $section, $year_level);
                        if ($stmt->execute()) {
                            $message = 'User created successfully.';
                            logAction($conn, $self_id, "Created user: $full_name ($email) with role_id=$role_id");
                        } else {
                            $error = 'Failed to create user.';
                        }
                        $stmt->close();
                    }
                }
            }

        // ── EDIT ──────────────────────────────────────────────────
        } elseif ($action === 'edit') {
            $user_id    = (int)($_POST['user_id']   ?? 0);
            $full_name  = trim($_POST['full_name']  ?? '');
            $email      = trim($_POST['email']      ?? '');
            $password   = $_POST['password']        ?? '';
            $role_id    = (int)($_POST['role_id']   ?? 0);
            $program    = trim($_POST['program']    ?? '');
            $section    = trim($_POST['section']    ?? '');
            $year_level = ($role_id === 3 && !empty($_POST['year_level']))
                          ? (int)$_POST['year_level'] : null;

            if ($user_id === $self_id && $role_id !== 4) {
                $error = 'You cannot change your own role. Ask another admin to do this.';
            } elseif ($user_id <= 0 || empty($full_name) || empty($email) || $role_id < 1 || $role_id > 4) {
                $error = 'Please fill all required fields correctly.';
            } else {
                // ── Password complexity check on update (only if password supplied) ─
                if (!empty($password)) {
                    $pw_errors = validatePasswordComplexity($password);
                    if (!empty($pw_errors)) {
                        $error = implode(' ', $pw_errors);
                    }
                }

                // ── Re-auth gate: promoting *another* user to Admin ───────
                if (empty($error) && $role_id === 4 && $user_id !== $self_id) {
                    // Check what their current role is — only gate if this is an escalation
                    $cur_stmt = $conn->prepare("SELECT role_id FROM users WHERE user_id = ?");
                    $cur_stmt->bind_param('i', $user_id);
                    $cur_stmt->execute();
                    $cur_row = $cur_stmt->get_result()->fetch_assoc();
                    $cur_stmt->close();

                    if ($cur_row && (int)$cur_row['role_id'] !== 4) {
                        // This IS a role escalation to Admin — require re-auth
                        $confirm_pw = $_POST['admin_confirm_password'] ?? '';
                        $reauth_err = verifyAdminReAuth($conn, $self_id, $confirm_pw);
                        if ($reauth_err !== null) {
                            $error = $reauth_err;
                            logAction($conn, $self_id, "SECURITY: Admin role escalation re-auth failed for user_id=$user_id from IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                        } else {
                            logAction($conn, $self_id, "Admin role escalation re-auth PASSED for user_id=$user_id");
                        }
                    }
                }

                if (empty($error)) {
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->bind_param('si', $email, $user_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = 'That email is already used by another account.';
                        $stmt->close();
                    } else {
                        $stmt->close();
                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password_hash=?, role_id=?, program=?, section=?, year_level=? WHERE user_id=?");
                            $stmt->bind_param('ssisssii', $full_name, $email, $hash, $role_id, $program, $section, $year_level, $user_id);
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role_id=?, program=?, section=?, year_level=? WHERE user_id=?");
                            $stmt->bind_param('sisssi i', $full_name, $email, $role_id, $program, $section, $year_level, $user_id);
                        }
                        if ($stmt->execute()) {
                            $message = 'User updated successfully.';
                            logAction($conn, $self_id, "Updated user ID $user_id: $full_name (role_id=$role_id)");
                        } else {
                            $error = 'Failed to update user.';
                        }
                        $stmt->close();
                    }
                }
            }

        // ── TOGGLE STATUS ─────────────────────────────────────────
        } elseif ($action === 'toggle_status') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id === $self_id) {
                $error = 'You cannot deactivate your own account.';
            } elseif ($user_id > 0) {
                $stmt = $conn->prepare("SELECT is_active, full_name FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $new_status = $row['is_active'] ? 0 : 1;
                    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                    $stmt->bind_param('ii', $new_status, $user_id);
                    if ($stmt->execute()) {
                        $label   = $new_status ? 'activated' : 'deactivated';
                        $message = 'User ' . $label . ' successfully.';
                        logAction($conn, $self_id, "User ID $user_id $label: " . $row['full_name']);
                    } else {
                        $error = 'Failed to update user status.';
                    }
                    $stmt->close();
                } else {
                    $error = 'User not found.';
                }
            }
        }
    }
}

// ── Role filter ───────────────────────────────────────────────────
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : 0;

$query = "SELECT user_id, full_name, email, role_id, program, section, year_level, is_active, created_at FROM users";
$params = []; $types = '';
if ($role_filter > 0) { $query .= " WHERE role_id = ?"; $params[] = $role_filter; $types .= 'i'; }
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token  = csrf_token();
$role_labels = [1 => 'Faculty', 2 => 'Registrar', 3 => 'Student', 4 => 'Admin'];

$prog_stmt = $conn->prepare("SELECT DISTINCT program FROM users WHERE program IS NOT NULL AND program != '' ORDER BY program");
$prog_stmt->execute();
$programs = array_column($prog_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'program');
$prog_stmt->close();

$sec_stmt = $conn->prepare("SELECT DISTINCT section FROM users WHERE section IS NOT NULL AND section != '' ORDER BY section");
$sec_stmt->execute();
$sections = array_column($sec_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'section');
$sec_stmt->close();

$stats_stmt = $conn->prepare("SELECT role_id, COUNT(*) AS cnt, SUM(is_active) AS active_cnt FROM users GROUP BY role_id");
$stats_stmt->execute();
$role_stats_raw = $stats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stats_stmt->close();
$role_stats = [];
foreach ($role_stats_raw as $rs) $role_stats[$rs['role_id']] = $rs;
?>

<style>
/* ── All existing styles from the original user_management.php are preserved ── */
:root {
    --adm-primary:    #3B82F6;
    --adm-primary-dk: #2563EB;
    --adm-secondary:  #10B981;
    --adm-accent:     #F59E0B;
    --adm-danger:     #EF4444;
    --adm-success:    #22C55E;
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

/* ── Re-auth gate panel ── */
.adm-reauth-gate {
    display: none;
    margin-top: 1.1rem;
    padding: 1rem 1.1rem;
    background: rgba(239,68,68,.06);
    border: 1.5px solid rgba(239,68,68,.35);
    border-radius: 8px;
    animation: admSlideIn .25s ease;
}
.adm-reauth-gate.visible { display: block; }

.adm-reauth-gate p {
    font-size: .83rem;
    color: #991b1b;
    margin-bottom: .75rem;
    display: flex;
    align-items: center;
    gap: .4rem;
    font-weight: 600;
}

/* ── Password strength indicator ── */
.adm-pw-strength-bar {
    height: 4px;
    border-radius: 2px;
    margin-top: 4px;
    transition: all .3s;
    background: var(--adm-border);
}
.adm-pw-strength-label {
    font-size: .72rem;
    margin-top: 2px;
    color: var(--adm-text-muted);
}

/* (All other styles identical to original file — omitted for brevity) */
.adm-page-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.adm-page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--adm-text); margin-bottom: .25rem; }
.adm-page-header p  { color: var(--adm-text-muted); font-size: .9rem; }
.adm-btn-primary { display: flex; align-items: center; gap: .5rem; padding: .65rem 1.4rem; background: linear-gradient(135deg, var(--adm-primary), var(--adm-primary-dk)); color: #fff; border: none; border-radius: 8px; font-size: .9rem; font-weight: 600; cursor: pointer; transition: var(--adm-transition); white-space: nowrap; font-family: inherit; }
.adm-btn-primary:hover { transform: translateY(-2px); box-shadow: var(--adm-shadow-lg); opacity: .92; }
.adm-alert { padding: 1rem 1.4rem; border-radius: var(--adm-radius); margin-bottom: 1.5rem; border: 1px solid; display: flex; align-items: center; gap: .75rem; animation: admSlideIn .3s ease-out; font-size: .9rem; font-weight: 500; }
@keyframes admSlideIn { from { transform: translateY(-8px); opacity:0; } to { transform: translateY(0); opacity:1; } }
.adm-alert-success { background: rgba(34,197,94,.1); border-color: var(--adm-success); color: #166534; }
.adm-alert-error   { background: rgba(239,68,68,.1); border-color: var(--adm-danger);  color: #991b1b; }
.adm-role-chips { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
.adm-role-chip { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); padding: .85rem 1.2rem; display: flex; align-items: center; gap: .7rem; box-shadow: var(--adm-shadow); flex: 1; min-width: 140px; }
.adm-role-chip-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.adm-role-chip-num  { font-size: 1.3rem; font-weight: 700; color: var(--adm-text); line-height: 1; }
.adm-role-chip-lbl  { font-size: .75rem; color: var(--adm-text-muted); font-weight: 500; }
.adm-filters { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); padding: 1.2rem 1.4rem; margin-bottom: 2rem; display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; }
.adm-filter-group { display: flex; flex-direction: column; gap: .4rem; }
.adm-filter-group label { font-size: .78rem; font-weight: 700; color: var(--adm-text); text-transform: uppercase; letter-spacing: .05em; }
.adm-filter-group select { padding: .6rem .85rem; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem; background: var(--adm-surface); color: var(--adm-text); font-family: inherit; transition: var(--adm-transition); }
.adm-filter-group select:focus { outline: none; border-color: var(--adm-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.adm-btn-apply { padding: .6rem 1.2rem; background: var(--adm-primary); color: #fff; border: none; border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition); white-space: nowrap; font-family: inherit; }
.adm-btn-apply:hover { background: var(--adm-primary-dk); transform: translateY(-1px); }
.adm-btn-reset { padding: .6rem 1rem; background: var(--adm-surface); color: var(--adm-text); border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: .4rem; transition: var(--adm-transition); white-space: nowrap; }
.adm-btn-reset:hover { border-color: var(--adm-text-muted); background: var(--adm-bg); }
.adm-table-card { background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: var(--adm-radius); box-shadow: var(--adm-shadow); overflow: hidden; margin-bottom: 2rem; transition: var(--adm-transition); }
.adm-table-card:hover { box-shadow: var(--adm-shadow-lg); border-color: var(--adm-primary); }
.adm-table-wrap { overflow-x: auto; }
.adm-users-table { width: 100%; border-collapse: collapse; }
.adm-users-table th { padding: .85rem 1.1rem; text-align: center; background: var(--adm-bg); font-size: .73rem; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--adm-border); }
.adm-users-table td { padding: .85rem 1.1rem; text-align: center; border-bottom: 1px solid var(--adm-border); vertical-align: middle; font-size: .875rem; color: var(--adm-text); }
.adm-users-table tbody tr:hover { background: var(--adm-bg); }
.adm-users-table tbody tr:last-child td { border-bottom: none; }
.adm-user-name  { font-weight: 600; color: var(--adm-text); }
.adm-user-email { font-size: .8rem; color: var(--adm-text-muted); }
.adm-role-badge { display: inline-block; padding: .25rem .65rem; border-radius: 5px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.adm-role-Faculty   { background: rgba(59,130,246,.1);  color: var(--adm-primary);   }
.adm-role-Registrar { background: rgba(16,185,129,.1);  color: var(--adm-secondary); }
.adm-role-Student   { background: rgba(245,158,11,.1);  color: #92400E;              }
.adm-role-Admin     { background: rgba(239,68,68,.1);   color: var(--adm-danger);    }
.adm-status-badge { display: inline-block; padding: .25rem .65rem; border-radius: 5px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; background: rgba(34,197,94,.1); color: #166534; }
.adm-status-inactive { background: rgba(239,68,68,.1); color: #991b1b; }
.adm-actions { display: flex; gap: .5rem; align-items: center; justify-content: center; }
.adm-action-btn { padding: .45rem .9rem; border: none; border-radius: 6px; font-size: .8rem; font-weight: 600; cursor: pointer; transition: var(--adm-transition); display: flex; align-items: center; gap: .25rem; text-decoration: none; }
.adm-btn-edit   { background: rgba(59,130,246,.1); color: var(--adm-primary); }
.adm-btn-edit:hover { background: var(--adm-primary); color: #fff; }
.adm-btn-deact  { background: rgba(239,68,68,.1); color: var(--adm-danger); }
.adm-btn-deact:hover { background: var(--adm-danger); color: #fff; }
.adm-btn-act    { background: rgba(34,197,94,.1); color: #166534; }
.adm-btn-act:hover { background: var(--adm-success); color: #fff; }
.adm-btn-self-lock { opacity: .4; cursor: not-allowed; }
.adm-empty-cell { padding: 0; }
.adm-empty-msg  { text-align: center; padding: 2.5rem; color: var(--adm-text-muted); }
.adm-empty-msg i { font-size: 2.5rem; display: block; margin-bottom: .5rem; opacity: .4; }
.adm-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
.adm-modal-backdrop.open { display: flex; }
.adm-modal { background: var(--adm-surface); border-radius: 14px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35); width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; animation: admModalIn .25s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes admModalIn { from { opacity:0; transform: scale(.93) translateY(16px); } to { opacity:1; transform: scale(1) translateY(0); } }
.adm-modal-header { padding: 1.3rem 1.5rem 1rem; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center; }
.adm-modal-title { font-size: 1.1rem; font-weight: 700; color: var(--adm-text); margin: 0; }
.adm-modal-close { background: none; border: none; font-size: 1.5rem; color: var(--adm-text-muted); cursor: pointer; padding: .2rem; border-radius: 4px; transition: var(--adm-transition); }
.adm-modal-close:hover { background: var(--adm-border); color: var(--adm-text); }
.adm-modal-body { padding: 1.4rem 1.5rem; }
.adm-modal-section-label { font-size: .72rem; font-weight: 800; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: .08em; margin: 0 0 .9rem 0; padding-bottom: .5rem; border-bottom: 1px solid var(--adm-border); }
.adm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.adm-form-group { margin-bottom: 1.1rem; }
.adm-form-group:last-child { margin-bottom: 0; }
.adm-form-label { display: block; font-weight: 600; color: var(--adm-text); margin-bottom: .4rem; font-size: .875rem; }
.adm-form-input, .adm-form-select { width: 100%; padding: .7rem .85rem; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .9rem; background: var(--adm-surface); color: var(--adm-text); font-family: inherit; transition: var(--adm-transition); }
.adm-form-input:focus, .adm-form-select:focus { outline: none; border-color: var(--adm-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.adm-password-wrap { position: relative; }
.adm-pw-toggle { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--adm-text-muted); font-size: 1.1rem; padding: .4rem; border-radius: 4px; transition: var(--adm-transition); }
.adm-pw-toggle:hover { color: var(--adm-primary); }
.adm-student-fields { display: none; }
.adm-modal-footer { padding: 1.1rem 1.5rem; border-top: 1px solid var(--adm-border); display: flex; justify-content: flex-end; gap: .75rem; background: var(--adm-bg); }
.adm-btn-secondary { background: var(--adm-border); color: var(--adm-text); border: none; padding: .7rem 1.4rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: var(--adm-transition); font-family: inherit; }
.adm-btn-secondary:hover { background: #cbd5e0; }
.adm-self-chip { display: inline-block; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 4px; font-size: .68rem; font-weight: 700; padding: .1rem .45rem; text-transform: uppercase; letter-spacing: .05em; margin-left: .4rem; }
/* Confirm modal */
.adm-confirm-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.55); backdrop-filter: blur(4px); z-index: 3000; align-items: center; justify-content: center; }
.adm-confirm-backdrop.open { display: flex; }
.adm-confirm-box { background: var(--adm-surface); border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35); width: 90%; max-width: 440px; overflow: hidden; animation: admConfirmIn .25s cubic-bezier(0.34,1.56,0.64,1); }
@keyframes admConfirmIn { from { opacity:0; transform: scale(.92) translateY(16px); } to { opacity:1; transform: scale(1) translateY(0); } }
.adm-confirm-header { padding: 1.3rem 1.5rem 1rem; border-bottom: 1px solid var(--adm-border); display: flex; align-items: center; gap: .75rem; }
.adm-confirm-icon { width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; background: rgba(59,130,246,.12); color: var(--adm-primary); }
.adm-confirm-icon.warn  { background: rgba(239,68,68,.12); color: var(--adm-danger); }
.adm-confirm-icon.edit  { background: rgba(59,130,246,.12); color: var(--adm-primary); }
.adm-confirm-icon.create { background: rgba(16,185,129,.12); color: var(--adm-secondary); }
.adm-confirm-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--adm-text); margin: 0; }
.adm-confirm-header p  { font-size: .82rem; color: var(--adm-text-muted); margin: .2rem 0 0; }
.adm-confirm-body { padding: 1.1rem 1.5rem; }
.adm-confirm-info { background: var(--adm-bg); border: 1px solid var(--adm-border); border-radius: 8px; padding: .85rem 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: .45rem .75rem; margin-bottom: 1rem; }
.adm-confirm-info span   { font-size: .76rem; color: var(--adm-text-muted); display: block; margin-bottom: .1rem; }
.adm-confirm-info strong { font-size: .86rem; font-weight: 600; color: var(--adm-text); word-break: break-word; }
.adm-confirm-warning { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.3); border-radius: 8px; padding: .8rem 1rem; display: flex; align-items: flex-start; gap: .55rem; font-size: .86rem; color: #92400e; line-height: 1.5; }
.adm-confirm-warning.danger { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.3); color: #991b1b; }
.adm-confirm-warning i { font-size: 1.05rem; flex-shrink: 0; margin-top: .15rem; }
.adm-confirm-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--adm-border); display: flex; justify-content: flex-end; gap: .6rem; background: var(--adm-bg); }
.adm-confirm-cancel { padding: .65rem 1.3rem; background: var(--adm-surface); color: var(--adm-text); border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--adm-transition); font-family: inherit; }
.adm-confirm-cancel:hover { background: var(--adm-bg); border-color: var(--adm-text-muted); }
.adm-confirm-ok { padding: .65rem 1.3rem; color: #fff; border: none; border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer; transition: var(--adm-transition); display: flex; align-items: center; gap: .4rem; font-family: inherit; background: linear-gradient(135deg, var(--adm-primary), var(--adm-primary-dk)); }
.adm-confirm-ok.danger { background: linear-gradient(135deg, #EF4444, #DC2626); }
.adm-confirm-ok:hover { transform: translateY(-1px); box-shadow: var(--adm-shadow-lg); opacity:.92; }

@media (max-width: 640px) { .adm-form-row { grid-template-columns: 1fr; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss alerts
    document.querySelectorAll('.adm-alert').forEach(el => {
        setTimeout(() => { el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(()=>el.remove(),300); }, 3800);
    });

    const modal      = document.getElementById('admUserModal');
    const form       = document.getElementById('admUserForm');
    const titleEl    = document.getElementById('admModalTitle');
    const roleSelect = document.getElementById('adm_role_id');
    const studentSec = document.getElementById('adm_student_fields');
    const pwInput    = document.getElementById('adm_password');
    const pwLabel    = document.querySelector('label[for="adm_password"]');
    const pwToggle   = document.getElementById('admPwToggle');
    const reauthGate = document.getElementById('admReAuthGate');

    function openModal()  { modal.classList.add('open'); }
    function closeModal() {
        modal.classList.remove('open');
        form.reset();
        document.getElementById('adm_user_id').value = '';
        document.getElementById('adm_action').value  = 'create';
        titleEl.textContent = 'Create New User';
        toggleStudentFields();
        toggleReAuth();
    }

    function toggleStudentFields() {
        studentSec.style.display = roleSelect.value === '3' ? 'block' : 'none';
    }

    function toggleReAuth() {
        if (reauthGate) {
            reauthGate.classList.toggle('visible', roleSelect.value === '4');
        }
    }

    document.getElementById('admCreateBtn').addEventListener('click', () => {
        pwInput.required    = true;
        pwLabel.textContent = 'Password *';
        openModal();
    });

    document.querySelectorAll('.adm-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = JSON.parse(btn.dataset.user);
            document.getElementById('adm_user_id').value    = d.user_id;
            document.getElementById('adm_full_name').value  = d.full_name;
            document.getElementById('adm_email').value      = d.email;
            document.getElementById('adm_role_id').value    = d.role_id;
            document.getElementById('adm_program').value    = d.program    || '';
            document.getElementById('adm_section').value    = d.section    || '';
            document.getElementById('adm_year_level').value = d.year_level || '';
            document.getElementById('adm_action').value     = 'edit';
            titleEl.textContent  = 'Edit User';
            pwInput.required     = false;
            pwInput.value        = '';
            pwLabel.textContent  = 'Password (leave blank to keep)';
            toggleStudentFields();
            toggleReAuth();
            openModal();
        });
    });

    document.querySelector('.adm-modal-close').addEventListener('click', closeModal);
    document.getElementById('admCancelBtn').addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    roleSelect.addEventListener('change', () => { toggleStudentFields(); toggleReAuth(); });
    toggleStudentFields();

    pwToggle.addEventListener('click', e => {
        e.preventDefault();
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        pwToggle.querySelector('i').className = show ? 'bx bx-show' : 'bx bx-hide';
    });

    // Password strength meter
    pwInput.addEventListener('input', function () {
        const bar   = document.getElementById('admPwStrengthBar');
        const label = document.getElementById('admPwStrengthLabel');
        if (!bar || !label) return;
        const v = this.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[a-z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const levels = [
            { pct:'20%', color:'#ef4444', text:'Very weak' },
            { pct:'40%', color:'#f97316', text:'Weak' },
            { pct:'60%', color:'#eab308', text:'Fair' },
            { pct:'80%', color:'#84cc16', text:'Good' },
            { pct:'100%',color:'#22c55e', text:'Strong' },
        ];
        const lv = levels[Math.max(0, score - 1)] || levels[0];
        bar.style.width     = v.length ? lv.pct : '0%';
        bar.style.background= v.length ? lv.color : '#e2e8f0';
        label.textContent   = v.length ? lv.text : '';
    });

    /* ── Confirmation modal ── */
    const cfm = document.getElementById('admConfirmModal');
    const cfmIcon      = document.getElementById('admCfmIcon');
    const cfmIconI     = document.getElementById('admCfmIconI');
    const cfmTitle     = document.getElementById('admCfmTitle');
    const cfmSubtitle  = document.getElementById('admCfmSubtitle');
    const cfmRow1Label = document.getElementById('admCfmRow1Label');
    const cfmRow1Val   = document.getElementById('admCfmRow1Val');
    const cfmRow2Label = document.getElementById('admCfmRow2Label');
    const cfmRow2Val   = document.getElementById('admCfmRow2Val');
    const cfmWarnBox   = document.getElementById('admCfmWarnBox');
    const cfmWarnText  = document.getElementById('admCfmWarnText');
    const cfmOkBtn     = document.getElementById('admCfmOk');
    const cfmOkLabel   = document.getElementById('admCfmOkLabel');
    const cfmOkIcon    = document.getElementById('admCfmOkIcon');
    const cfmCancelBtn = document.getElementById('admCfmCancel');

    let pendingAction = null;

    function openConfirm()  { cfm.classList.add('open'); }
    function closeConfirm() { cfm.classList.remove('open'); pendingAction = null; }

    cfmCancelBtn.addEventListener('click', closeConfirm);
    cfm.addEventListener('click', e => { if (e.target === cfm) closeConfirm(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });
    cfmOkBtn.addEventListener('click', () => { if (pendingAction) pendingAction(); closeConfirm(); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const action   = document.getElementById('adm_action').value;
        const name     = document.getElementById('adm_full_name').value.trim();
        const roleVal  = document.getElementById('adm_role_id').value;
        const roleMap  = { '1':'Faculty','2':'Registrar','3':'Student','4':'Admin' };
        const roleText = roleMap[roleVal] || '—';
        const isCreate = action === 'create';

        cfmIcon.className    = 'adm-confirm-icon ' + (isCreate ? 'create' : 'edit');
        cfmIconI.className   = isCreate ? 'bx bx-user-plus' : 'bx bx-edit';
        cfmTitle.textContent = isCreate ? 'Confirm Create User' : 'Confirm Save Changes';
        cfmSubtitle.textContent = isCreate ? 'You are about to create a new user account.' : 'You are about to save changes to this user account.';
        cfmRow1Label.textContent = 'Full Name'; cfmRow1Val.textContent = name || '—';
        cfmRow2Label.textContent = 'Role';      cfmRow2Val.textContent = roleText;
        cfmWarnBox.className   = 'adm-confirm-warning';
        cfmWarnText.textContent = roleVal === '4'
            ? '⚠ This action grants Admin privileges. Your password was required to confirm.'
            : (isCreate ? 'The new account will be created immediately.' : 'Changes take effect immediately.');
        cfmOkBtn.className     = 'adm-confirm-ok';
        cfmOkIcon.className    = isCreate ? 'bx bx-user-plus' : 'bx bx-save';
        cfmOkLabel.textContent = isCreate ? 'Create User' : 'Save Changes';
        pendingAction = () => form.submit();
        openConfirm();
    });

    document.querySelectorAll('.adm-toggle-form').forEach(toggleForm => {
        toggleForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn       = this.querySelector('button[type="submit"]');
            const isDeact   = btn.classList.contains('adm-btn-deact');
            const nameCell  = this.closest('tr').querySelector('.adm-user-name');
            const userName  = nameCell ? nameCell.childNodes[0].textContent.trim() : '—';
            const emailCell = this.closest('tr').querySelector('.adm-user-email');
            const userEmail = emailCell ? emailCell.textContent.trim() : '—';

            cfmIcon.className    = 'adm-confirm-icon ' + (isDeact ? 'warn' : 'create');
            cfmIconI.className   = isDeact ? 'bx bx-user-x' : 'bx bx-user-check';
            cfmTitle.textContent = isDeact ? 'Deactivate User' : 'Activate User';
            cfmSubtitle.textContent = isDeact ? 'This user will lose access.' : 'This user will regain access.';
            cfmRow1Label.textContent = 'Name';  cfmRow1Val.textContent  = userName;
            cfmRow2Label.textContent = 'Email'; cfmRow2Val.textContent  = userEmail;
            cfmWarnBox.className    = 'adm-confirm-warning' + (isDeact ? ' danger' : '');
            cfmWarnText.textContent = isDeact ? 'The user will be immediately deactivated.' : 'The user account will be reactivated.';
            cfmOkBtn.className    = 'adm-confirm-ok' + (isDeact ? ' danger' : '');
            cfmOkIcon.className   = isDeact ? 'bx bx-user-x' : 'bx bx-user-check';
            cfmOkLabel.textContent = isDeact ? 'Deactivate' : 'Activate';
            pendingAction = () => toggleForm.submit();
            openConfirm();
        });
    });
});
</script>

<!-- User form modal -->
<div class="adm-modal-backdrop" id="admUserModal">
    <div class="adm-modal" role="dialog" aria-modal="true" aria-labelledby="admModalTitle">
        <div class="adm-modal-header">
            <h3 class="adm-modal-title" id="admModalTitle">Create New User</h3>
            <button class="adm-modal-close" aria-label="Close">&times;</button>
        </div>

        <form method="POST" id="admUserForm">
            <div class="adm-modal-body">
                <input type="hidden" name="action"     id="adm_action"  value="create">
                <input type="hidden" name="user_id"    id="adm_user_id" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">

                <p class="adm-modal-section-label">Account Credentials</p>
                <div class="adm-form-row">
                    <div class="adm-form-group">
                        <label class="adm-form-label" for="adm_full_name">Full Name *</label>
                        <input type="text" class="adm-form-input" id="adm_full_name" name="full_name" maxlength="100" required>
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label" for="adm_email">Email *</label>
                        <input type="email" class="adm-form-input" id="adm_email" name="email" maxlength="100" required>
                    </div>
                </div>

                <div class="adm-form-row">
                    <div class="adm-form-group">
                        <label class="adm-form-label" for="adm_role_id">Role *</label>
                        <select class="adm-form-select" id="adm_role_id" name="role_id" required>
                            <option value="">— Select —</option>
                            <option value="1">Faculty</option>
                            <option value="2">Registrar</option>
                            <option value="3">Student</option>
                            <option value="4">Admin</option>
                        </select>
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label" for="adm_password">Password *</label>
                        <div class="adm-password-wrap">
                            <input type="password" class="adm-form-input" id="adm_password" name="password" required style="padding-right:2.4rem;">
                            <button type="button" class="adm-pw-toggle" id="admPwToggle"><i class='bx bx-hide'></i></button>
                        </div>
                        <!-- Password strength bar -->
                        <div class="adm-pw-strength-bar" id="admPwStrengthBar" style="width:0%;"></div>
                        <div class="adm-pw-strength-label" id="admPwStrengthLabel"></div>
                        <small style="color:var(--adm-text-muted);font-size:.72rem;margin-top:2px;display:block;">
                            Min 8 chars · uppercase · lowercase · number · special char
                        </small>
                    </div>
                </div>

                <!-- ── Admin promotion re-auth gate ── -->
                <div id="admReAuthGate" class="adm-reauth-gate">
                    <p><i class='bx bx-shield-alt-2'></i> Admin Promotion — Identity Confirmation Required</p>
                    <div class="adm-form-group" style="margin-bottom:0;">
                        <label class="adm-form-label" for="adm_admin_confirm_password">
                            Your Current Password *
                        </label>
                        <input type="password"
                               class="adm-form-input"
                               id="adm_admin_confirm_password"
                               name="admin_confirm_password"
                               placeholder="Enter your password to confirm Admin grant"
                               autocomplete="current-password">
                        <small style="color:#991b1b;font-size:.72rem;display:block;margin-top:3px;">
                            Granting Admin privileges requires your password as confirmation.
                        </small>
                    </div>
                </div>

                <!-- Student fields -->
                <div id="adm_student_fields" class="adm-student-fields">
                    <p class="adm-modal-section-label" style="margin-top:.75rem;">Student Information</p>
                    <div class="adm-form-row">
                        <div class="adm-form-group">
                            <label class="adm-form-label" for="adm_program">Program</label>
                            <select class="adm-form-select" id="adm_program" name="program">
                                <option value="">— Select Program —</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog, ENT_QUOTES) ?>"><?= htmlspecialchars($prog, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                                <option value="Bachelor of Science in Information Technology">BSIT (new)</option>
                            </select>
                        </div>
                        <div class="adm-form-group">
                            <label class="adm-form-label" for="adm_section">Section</label>
                            <select class="adm-form-select" id="adm_section" name="section">
                                <option value="">— Select Section —</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= htmlspecialchars($sec, ENT_QUOTES) ?>"><?= htmlspecialchars($sec, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="adm-form-group">
                        <label class="adm-form-label" for="adm_year_level">Year Level</label>
                        <select class="adm-form-select" id="adm_year_level" name="year_level">
                            <option value="">— Select —</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="adm-modal-footer">
                <button type="button" class="adm-btn-secondary" id="admCancelBtn">Cancel</button>
                <button type="submit" class="adm-btn-primary"><i class='bx bx-save'></i> Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation modal (unchanged from original) -->
<div class="adm-confirm-backdrop" id="admConfirmModal">
    <div class="adm-confirm-box" role="dialog" aria-modal="true">
        <div class="adm-confirm-header">
            <div class="adm-confirm-icon" id="admCfmIcon"><i class='bx bx-user-plus' id="admCfmIconI"></i></div>
            <div>
                <h3 id="admCfmTitle">Confirm Action</h3>
                <p id="admCfmSubtitle">Please review before proceeding.</p>
            </div>
        </div>
        <div class="adm-confirm-body">
            <div class="adm-confirm-info">
                <div><span id="admCfmRow1Label">Name</span><strong id="admCfmRow1Val">—</strong></div>
                <div><span id="admCfmRow2Label">Role</span><strong id="admCfmRow2Val">—</strong></div>
            </div>
            <div class="adm-confirm-warning" id="admCfmWarnBox">
                <i class='bx bx-info-circle'></i>
                <span id="admCfmWarnText">This action will take effect immediately.</span>
            </div>
        </div>
        <div class="adm-confirm-footer">
            <button type="button" class="adm-confirm-cancel" id="admCfmCancel">Cancel</button>
            <button type="button" class="adm-confirm-ok" id="admCfmOk">
                <i class='bx bx-user-plus' id="admCfmOkIcon"></i>
                <span id="admCfmOkLabel">Confirm</span>
            </button>
        </div>
    </div>
</div>

<!-- Page content -->
<div>
    <?php if (!empty($message)): ?>
        <div class="adm-alert adm-alert-success"><i class='bx bx-check-circle'></i><?= htmlspecialchars($message, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="adm-alert adm-alert-error"><i class='bx bx-error-circle'></i><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <div class="adm-page-header">
        <div>
            <h2>User Management</h2>
            <p>Manage system users, roles, and access permissions.</p>
        </div>
        <button class="adm-btn-primary" id="admCreateBtn"><i class='bx bx-plus'></i> Create User</button>
    </div>

    <!-- Role distribution chips -->
    <div class="adm-role-chips">
        <?php
        $chip_data = [
            1 => ['Faculty',  'bx-chalkboard',  'rgba(59,130,246,.1)',  '#3B82F6'],
            2 => ['Registrar','bx-shield',       'rgba(16,185,129,.1)',  '#10B981'],
            3 => ['Students', 'bxs-graduation',  'rgba(245,158,11,.1)',  '#F59E0B'],
            4 => ['Admins',   'bx-crown',        'rgba(239,68,68,.1)',   '#EF4444'],
        ];
        foreach ($chip_data as $rid => $cd):
            $cnt = (int)($role_stats[$rid]['cnt'] ?? 0);
        ?>
        <div class="adm-role-chip">
            <div class="adm-role-chip-icon" style="background:<?= $cd[2] ?>;color:<?= $cd[3] ?>;"><i class='bx <?= $cd[1] ?>'></i></div>
            <div>
                <div class="adm-role-chip-num"><?= $cnt ?></div>
                <div class="adm-role-chip-lbl"><?= $cd[0] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter -->
    <div class="adm-filters">
        <form method="GET" style="display:contents;">
            <input type="hidden" name="page" value="user_management">
            <div class="adm-filter-group">
                <label for="adm_role_filter">Filter by Role</label>
                <select name="role" id="adm_role_filter">
                    <option value="0" <?= $role_filter == 0 ? 'selected' : '' ?>>All Roles</option>
                    <option value="1" <?= $role_filter == 1 ? 'selected' : '' ?>>Faculty</option>
                    <option value="2" <?= $role_filter == 2 ? 'selected' : '' ?>>Registrar</option>
                    <option value="3" <?= $role_filter == 3 ? 'selected' : '' ?>>Student</option>
                    <option value="4" <?= $role_filter == 4 ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="adm-btn-apply"><i class='bx bx-filter-alt'></i> Apply</button>
            <a href="?page=user_management" class="adm-btn-reset"><i class='bx bx-reset'></i> Reset</a>
        </form>
    </div>

    <!-- Users table -->
    <div class="adm-table-card">
        <div class="adm-table-wrap">
            <table class="adm-users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                        <?php
                            $safe_data = json_encode([
                                'user_id'    => $user['user_id'],
                                'full_name'  => $user['full_name'],
                                'email'      => $user['email'],
                                'role_id'    => $user['role_id'],
                                'program'    => $user['program']    ?? '',
                                'section'    => $user['section']    ?? '',
                                'year_level' => $user['year_level'] ?? '',
                                // password_hash intentionally excluded
                            ]);
                            $is_self  = $user['user_id'] === $self_id;
                            $role_lbl = $role_labels[$user['role_id']] ?? 'Unknown';
                        ?>
                        <tr>
                            <td>
                                <div class="adm-user-name">
                                    <?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>
                                    <?php if ($is_self): ?><span class="adm-self-chip">You</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="adm-user-email"><?= htmlspecialchars($user['email'], ENT_QUOTES) ?></td>
                            <td>
                                <span class="adm-role-badge adm-role-<?= htmlspecialchars($role_lbl, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($role_lbl, ENT_QUOTES) ?>
                                </span>
                            </td>
                            <td>
                                <span class="adm-status-badge <?= $user['is_active'] ? '' : 'adm-status-inactive' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="adm-user-email"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div class="adm-actions">
                                    <button class="adm-action-btn adm-btn-edit adm-edit-btn"
                                            data-user='<?= htmlspecialchars($safe_data, ENT_QUOTES) ?>'>
                                        <i class='bx bx-edit'></i> Edit
                                    </button>
                                    <?php if ($is_self): ?>
                                        <span class="adm-action-btn adm-btn-deact adm-btn-self-lock" title="Cannot deactivate your own account">
                                            <i class='bx bx-lock'></i> You
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" class="adm-toggle-form">
                                            <input type="hidden" name="user_id"    value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="action"     value="toggle_status">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">
                                            <button type="submit" class="adm-action-btn <?= $user['is_active'] ? 'adm-btn-deact' : 'adm-btn-act' ?>">
                                                <i class='bx bx-<?= $user['is_active'] ? 'x' : 'check' ?>'></i>
                                                <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="adm-empty-cell">
                                <div class="adm-empty-msg"><i class='bx bx-group'></i><p>No users found.</p></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>