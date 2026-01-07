<?php
session_start();
include '../database/connect.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết bài học</title>
    <link rel="stylesheet" href="../css/program.css">
    <link rel="stylesheet" href="../css/lesson.css">
</head>

<body class="course-page">
<?php include('../pages/header.php'); ?>

<div class="lesson-detail-wrapper">
    <h1>Chi tiết bài học</h1>

    <div class="lesson-detail-grid">

        <a href="../pages/create_lesson_objectives.php" class="lesson-card">
            <h3>Mục tiêu</h3>
            <p>Xác định kiến thức, kỹ năng, tư duy và thái độ</p>
        </a>

        <a href="../pages/create_lesson_MPB.php" class="lesson-card">
            <h3>Mô hình</h3>
            <p>Hình ảnh và mô tả mô hình học tập</p>
        </a>

        <a href="../pages/create_lesson_contents.php" class="lesson-card">
            <h3>Nội dung</h3>
            <p>Chi tiết các hoạt động trong bài học</p>
        </a>

        <a href="../pages/create_lesson_challenge.php" class="lesson-card">
            <h3>Thử thách</h3>
            <p>Nhiệm vụ nâng cao cho học sinh</p>
        </a>

    </div>
</div>

<?php include('../pages/footer.php'); ?>
</body>
</html>
