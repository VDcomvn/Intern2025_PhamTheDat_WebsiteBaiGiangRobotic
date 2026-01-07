<?php
session_start();
include '../database/connect.php';

/* ===== USER INFO ===== */
$user_id   = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'guest';

/* ===== KIỂM TRA PROGRAM ID ===== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$program_id = intval($_GET['id']);

/* ===== LẤY THÔNG TIN CHƯƠNG TRÌNH ===== */
$stmt = $conn->prepare("SELECT * FROM programs WHERE program_id = ?");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$programResult = $stmt->get_result();
$program = $programResult->fetch_assoc();
$stmt->close();

if (!$program) {
    echo "Chương trình không tồn tại!";
    exit;
}

/* ===== LẤY SUBCOURSES ===== */
$stmt = $conn->prepare("
    SELECT * FROM subcourses 
    WHERE program_id = ?
    ORDER BY created_at ASC
");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$subcoursesResult = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($program['name']) ?></title>
    <link rel="stylesheet" href="../css/program.css">

    <style>
        .card-link.disabled {
            pointer-events: none;
        }

        .card-link.disabled .course-card {
            filter: grayscale(100%);
            opacity: 0.5;
            position: relative;
        }

        .locked-label {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.75);
            color: #fff;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 6px;
            z-index: 10;
        }
    </style>
</head>

<body class="course-page">

<?php include('../pages/header.php'); ?>

<h1>Khóa học <?= htmlspecialchars($program['name']) ?></h1>

<div class="course-section">
    <div class="course-row">

        <?php while ($sub = $subcoursesResult->fetch_assoc()): ?>

        <?php
            /* ===== KIỂM TRA QUYỀN GIÁO VIÊN ===== */
            $canAccess = true;

            if ($user_role === 'teacher') {
                $stmtPerm = $conn->prepare("
                    SELECT is_allowed 
                    FROM teacher_subcourse_permission
                    WHERE user_id = ? AND subcourse_id = ?
                ");
                $stmtPerm->bind_param("ii", $user_id, $sub['subcourse_id']);
                $stmtPerm->execute();
                $resPerm = $stmtPerm->get_result();

                if ($resPerm->num_rows == 0) {
                    $canAccess = false;
                } else {
                    $perm = $resPerm->fetch_assoc();
                    if ($perm['is_allowed'] != 1) {
                        $canAccess = false;
                    }
                }
                $stmtPerm->close();
            }
        ?>

        <?php if ($canAccess): ?>
            <a href="../pages/subcourse.php?id=<?= $sub['subcourse_id'] ?>" class="card-link">
        <?php else: ?>
            <div class="card-link disabled">
        <?php endif; ?>

            <div class="course-card">

                <?php if (!$canAccess): ?>
                    <div class="locked-label">Không thể truy cập</div>
                <?php endif; ?>

                <?php
                    $mediaFile = !empty($sub['media'])
                        ? '../img/subcourse/' . $sub['media']
                        : '../img/default-course.jpg';

                    $ext = pathinfo($mediaFile, PATHINFO_EXTENSION);
                ?>

                <?php if (in_array(strtolower($ext), ['mp4','webm','mov','ogg'])): ?>
                    <video width="250" controls>
                        <source src="<?= $mediaFile ?>">
                    </video>
                <?php else: ?>
                    <img src="<?= $mediaFile ?>" alt="<?= htmlspecialchars($sub['name']) ?>" width="250">
                <?php endif; ?>

                <div class="course-content">
                    <h3><?= htmlspecialchars($sub['name']) ?></h3>

                    <p><?= nl2br(htmlspecialchars($sub['short_description'])) ?></p>

                    <div class="course-info">
                        <div class="info-item">
                            <p class="info-title">Nhóm tuổi</p>
                            <p class="info-value"><?= htmlspecialchars($sub['age_group']) ?></p>
                        </div>

                        <div class="info-item">
                            <p class="info-title">Khối lệnh</p>
                            <p class="info-value">
                                <?php
                                if (!empty($sub['blocks'])) {
                                    $blocksArr = json_decode($sub['blocks'], true);
                                    echo implode(', ', $blocksArr);
                                }
                                ?>
                            </p>
                        </div>

                        <div class="info-item">
                            <p class="info-title">Số buổi</p>
                            <p class="info-value"><?= $sub['lesson_count'] ?> buổi</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php if ($canAccess): ?>
            </a>
        <?php else: ?>
            </div>
        <?php endif; ?>

        <?php endwhile; ?>

    </div>
</div>

<?php include('../pages/footer.php'); ?>

</body>
</html>
