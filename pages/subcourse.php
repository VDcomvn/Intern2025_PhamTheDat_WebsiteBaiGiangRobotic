<?php
session_start();
include '../database/connect.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Subcourse không hợp lệ");
}

$subcourse_id = intval($_GET['id']);

// LẤY THÔNG TIN SUBCOURSE
$stmt = $conn->prepare("
    SELECT name, overall_goal, media 
    FROM subcourses 
    WHERE subcourse_id = ?
");
$stmt->bind_param("i", $subcourse_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Subcourse không tồn tại");
}

$stmt->bind_result($sub_name, $overall_goal, $sub_media);
$stmt->fetch();
$stmt->close();

// LẤY DANH SÁCH BÀI HỌC
$lessonStmt = $conn->prepare("
    SELECT lesson_id, title, subtitle, overview, media 
    FROM lessons 
    WHERE subcourse_id = ?
    ORDER BY created_at ASC
");
$lessonStmt->bind_param("i", $subcourse_id);
$lessonStmt->execute();
$lessons = $lessonStmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($sub_name); ?></title>
    <link rel="stylesheet" href="../css/subcourse.css">
</head>
<body>

<?php include('../pages/header.php'); ?>

<!-- ===== THÔNG TIN SUBCOURSE ===== -->
<div class="course-detail">
    <div class="course-grid">

        <div class="left-image">
            <img class="course-img"
                 src="../img/subcourse/<?php echo $sub_media ?: 'default-course.jpg'; ?>">
        </div>

        <div class="right-text">
            <h1><?php echo htmlspecialchars($sub_name); ?></h1>
            <h2>Mục tiêu khóa học</h2>
            <p><?php echo nl2br(htmlspecialchars($overall_goal)); ?></p>
        </div>

    </div>
</div>

<!-- ===== DANH SÁCH BÀI HỌC ===== -->
<?php while ($lesson = $lessons->fetch_assoc()): ?>
    <a href="lesson_detail.php?id=<?php echo $lesson['lesson_id']; ?>" class="card-link">

        <div class="course-detail">
            <div class="course-grid">

                <div class="right-text">
                    <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <h2><?php echo htmlspecialchars($lesson['subtitle']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($lesson['overview'])); ?></p>
                </div>

                <div class="left-image">
                    <?php if (!empty($lesson['media'])): ?>
                        <?php
                            $ext = pathinfo($lesson['media'], PATHINFO_EXTENSION);
                            $file = "../img/lessons/" . $lesson['media'];
                        ?>
                        <?php if (in_array(strtolower($ext), ['mp4','webm','ogg'])): ?>
                            <video class="course-img" controls>
                                <source src="<?php echo $file; ?>">
                            </video>
                        <?php else: ?>
                            <img class="course-img" src="<?php echo $file; ?>">
                        <?php endif; ?>
                    <?php else: ?>
                        <img class="course-img" src="../img/default-course.jpg">
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </a>
<?php endwhile; ?>

<?php include('../pages/footer.php'); ?>

</body>
</html>
