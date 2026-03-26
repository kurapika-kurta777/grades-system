<?php
/* 
 * FACULTY GRADE SHEET
 * This file is included by index.php (router)
 * Do not include HTML head/body tags - only content
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/audit.php';

// Faculty access control
requireRole([1]);

$faculty_id = $_SESSION['user_id'];

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

// Section list
$all_sections = [
    'BSIT-32001-IM', 'BSIT-32002-IM', 'BSIT-32003-IM',
    'BSIT-32004-IM', 'BSIT-32005-IM', 'BSIT-32006-IM',
    'BSIT-32007-IM', 'BSIT-32008-IM', 'BSIT-32009-IM',
    'BSIT-32010-IM', 'BSIT-32011-IM', 'BSIT-32012-IM',
    'BSIT-32013-IM', 'BSIT-32014-IM', 'BSIT-32015-IM'
];

// Get selected section from GET, default to empty (no section selected)
$selected_section = $_GET['section'] ?? '';
if ($selected_section && !in_array($selected_section, $all_sections)) {
    $selected_section = '';
}

// Query grades for this faculty's subjects filtered by period and section, grouped by subject
$stmt = $conn->prepare("
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        u.full_name AS student_name,
        g.academic_period,
        g.percentage,
        g.numeric_grade,
        g.remarks,
        g.status
    FROM grades g
    JOIN subjects s ON g.subject_id = s.subject_id
    JOIN users u ON g.student_id = u.user_id
    WHERE s.faculty_id = ? AND g.academic_period = ? AND u.section = ?
    ORDER BY s.subject_code, u.full_name
");
$stmt->bind_param("iss", $faculty_id, $selected_period, $selected_section);

$grades_all = [];
if (!empty($selected_section)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $grades_all[] = $row;
    }
    $stmt->close();
}

// Group grades by subject
$grades_by_subject = [];
foreach ($grades_all as $grade) {
    $subject_id = $grade['subject_id'];
    if (!isset($grades_by_subject[$subject_id])) {
        $grades_by_subject[$subject_id] = [
            'subject_code' => $grade['subject_code'],
            'subject_name' => $grade['subject_name'],
            'grades' => []
        ];
    }
    $grades_by_subject[$subject_id]['grades'][] = $grade;
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
    
    /* Generic variables for section grid */
    --primary: #2563EB;
    --secondary: #10B981;
    --text-primary: #0F1E4A;
    --text-secondary: #64748B;
    --radius: 12px;
    --shadow: 0 6px 20px rgba(15, 36, 108, 0.12);
}

body {
    background: var(--bg);
}

.grade-sheet-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
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

.period-selector {
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.period-selector label {
    font-weight: 600;
    color: var(--text-900);
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
    border-radius: var(--r-md);
    font-size: 0.9rem;
    color: var(--text-900);
    background: var(--surface);
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    transition: var(--transition);
}

.period-selector select:hover {
    border-color: var(--blue-600);
}

.period-selector select:focus {
    outline: none;
    border-color: var(--blue-600);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
    border-color: var(--blue-600);
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

.title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
}

.subtitle {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
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
    text-align: center; /* header names centered */
    font-weight: 700;
    color: var(--text-400);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
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

.badge {
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

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-approved {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #22c55e;
}

.badge-submitted {
    background: #ecfdf5;
    color: #065f46;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-600);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-data {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--r-md);    
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-600);
}

.no-data-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-data h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-900);
    margin-bottom: 0.5rem;
}

.no-data p {
    font-size: 1rem;
    margin: 0;
    color: var(--text-600);
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

@media (max-width: 768px) {
    .section-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .grade-sheet-container {
        padding: 1rem;
    }

    .page-header h2 {
        font-size: 1.4rem;
    }

    .period-selector {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .period-selector select {
        width: 100%;
    }

    table {
        font-size: 0.8rem;
    }

    th, td {
        padding: 0.75rem;
    }
}

@media (max-width: 480px) {
    .section-grid {
        grid-template-columns: 1fr;
    }
}
</style>

    <div class="page-header">
        <h2>Submitted Grades</h2>
        <p>Review all grades submitted for your subjects by academic period.</p>
    </div>

    <div class="period-selector">
        <form method="GET" action="" id="periodForm">
            <input type="hidden" name="page" value="view_grades">
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
    <div class="section-grid-container">
        <h3 class="section-grid-title">
            <i class='bx bx-grid-alt'></i> Select a Section
        </h3>
        <div class="section-grid">
            <?php foreach ($all_sections as $sec): ?>
                <?php
                $is_active = $sec === $selected_section;
                $has_data = $sec === 'BSIT-32011-IM';
                ?>
                <a href="?page=view_grades&period=<?= urlencode($selected_period) ?>&section=<?= urlencode($sec) ?>"
                   class="section-card <?= $is_active ? 'active' : '' ?> <?= $has_data ? 'has-data' : '' ?>">
                    <div class="section-card-icon">
                        <i class='bx bx-group'></i>
                    </div>
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
                    <div class="no-data-icon">
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <h3>Select a Section</h3>
                    <p>Choose a section from the grid above to view grades.</p>
                </div>
            </div>
        <?php elseif (count($grades_by_subject) > 0): ?>
            <?php foreach ($grades_by_subject as $subject): ?>
                <div class="table-card">
                    <div class="subject-title">
                        <div class="title"><?= htmlspecialchars($subject['subject_code'], ENT_QUOTES) ?></div>
                        <div class="subtitle"><?= htmlspecialchars($subject['subject_name'], ENT_QUOTES) ?></div>
                    </div>

                    <div class="table-wrap">
                        <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Percentage</th>
                                        <th>Numeric Grade</th>
                                        <th>Remarks</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject['grades'] as $grade): ?>
                                        <tr>
                                            <td style="text-align: left;"><?= htmlspecialchars($grade['student_name'], ENT_QUOTES) ?></td>
                                            <td><?= number_format($grade['percentage'], 2) ?>%</td>
                                            <td><?= number_format($grade['numeric_grade'], 2) ?></td>
                                            <td><?= htmlspecialchars($grade['remarks'], ENT_QUOTES) ?></td>
                                            <td>
                                                <?php
                                                $status = strtolower($grade['status']);
                                                $icon = '';
                                                if ($status === 'approved') $icon = '<i class="bx bx-check-circle"></i>';
                                                elseif ($status === 'pending') $icon = '<i class="bx bx-time"></i>';
                                                elseif ($status === 'submitted') $icon = '<i class="bx bx-send"></i>';
                                                ?>
                                                <span class="badge badge-<?= $status ?>">
                                                    <?= $icon ?> <?= htmlspecialchars($grade['status'], ENT_QUOTES) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php if (!empty($selected_section) && count($grades_by_subject) === 0): ?>
                <div class="table-card">
                    <div class="no-data">
                        <div class="no-data-icon">
                            <i class='bx bx-book-open'></i>
                        </div>
                        <h3>No Data Available</h3>
                        <p>No submitted grades found for this section and period.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
