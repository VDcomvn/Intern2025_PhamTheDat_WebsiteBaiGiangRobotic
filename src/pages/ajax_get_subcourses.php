<?php
session_start();
include '../database/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    exit;
}

if (!isset($_GET['program_id']) || !is_numeric($_GET['program_id'])) {
    echo "<p style='color:red;'>Chương trình không hợp lệ</p>";
    exit;
}

$program_id = intval($_GET['program_id']);
$user_id    = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

/* ===== LẤY SUBCOURSE + QUYỀN ĐÃ CẤP ===== */
$sql = "
    SELECT 
        s.subcourse_id,
        s.name,
        IF(p.subcourse_id IS NOT NULL, 1, 0) AS checked
    FROM subcourses s
    LEFT JOIN teacher_subcourse_permission p
        ON s.subcourse_id = p.subcourse_id
        AND p.user_id = ?
    WHERE s.program_id = ?
    ORDER BY s.subcourse_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $program_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color:#888;'>Chưa có khóa học</p>";
    exit;
}

/* ===== HIỂN THỊ ===== */
while ($row = $result->fetch_assoc()):
?>
    <div class="subcourse-item">
        <span><?= htmlspecialchars($row['name']) ?></span>
        <input
            type="checkbox"
            name="subcourses[<?= $row['subcourse_id'] ?>]"
            <?= $row['checked'] ? 'checked' : '' ?>
        >
    </div>
<?php endwhile; ?>
