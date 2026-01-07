<?php
session_start();
include '../database/connect.php';

// Chỉ cho ADMIN
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Kiểm tra ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID bài học không hợp lệ");
}

$lesson_id = intval($_GET['id']);
$error = "";
$success = "";
$uploadDir = '../img/lessons/';

// Lấy dữ liệu bài học
$stmt = $conn->prepare("
    SELECT 
        subcourse_id, 
        title, 
        subtitle,
        overview,
        media,
        blocks
    FROM lessons
    WHERE lesson_id = ?
");

if (!$stmt) {
    die("Lỗi SQL: " . $conn->error);
}

$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Bài học không tồn tại");
}

$stmt->bind_result($subcourse_id, $title, $subtitle, $overview, $media, $blocks_json);
$stmt->fetch();
$stmt->close();

$blocksArr = $blocks_json ? json_decode($blocks_json, true) : [];

// Lấy danh sách subcourse
$subList = $conn->query("SELECT subcourse_id, name FROM subcourses ORDER BY subcourse_id DESC");

// Lưu thay đổi
if (isset($_POST['save_lesson'])) {

    $new_title = trim($_POST['title']);
    $new_subtitle = trim($_POST['subtitle']);
    $new_overview = trim($_POST['overview']);
    $new_subcourse = intval($_POST['subcourse_id']);
    $new_blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks']) : null;

    // Upload media
    if (!empty($_FILES['media']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];

        if (in_array($_FILES['media']['type'], $allowed)) {
            $new_media = time() . '_' . basename($_FILES['media']['name']);

            if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $new_media)) {
                if (!empty($media) && file_exists($uploadDir . $media)) {
                    unlink($uploadDir . $media);
                }
                $media = $new_media;
            } else {
                $error = "Không thể upload file lên server.";
            }
        } else {
            $error = "Chỉ cho phép upload ảnh hoặc video.";
        }
    }

    if (!$error) {
        $update = $conn->prepare("
            UPDATE lessons 
            SET title=?, subtitle=?, overview=?, subcourse_id=?, media=?, blocks=?, updated_at=NOW()
            WHERE lesson_id=?
        ");

        if (!$update) {
            die("Lỗi SQL UPDATE: " . $conn->error);
        }

        $update->bind_param(
            "sssissi",
            $new_title,
            $new_subtitle,
            $new_overview,
            $new_subcourse,
            $media,
            $new_blocks,
            $lesson_id
        );

        if ($update->execute()) {
            $success = "Cập nhật bài học thành công!";

            // Cập nhật biến hiển thị
            $title = $new_title;
            $subtitle = $new_subtitle;
            $overview = $new_overview;
            $subcourse_id = $new_subcourse;
            $blocksArr = $new_blocks ? json_decode($new_blocks, true) : [];
        } else {
            $error = "Lỗi cập nhật: " . $conn->error;
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa bài học</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">
</head>
<body>

    <?php include('../pages/header.php'); ?>

    <div class="user-page">
        <div class="container">
            <div class="form-container">
                <h2>Sửa bài học</h2>

                <?php if ($error): ?>
                    <div class="message-box error"><?= $error ?></div>
                <?php elseif ($success): ?>
                    <div class="message-box success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="input-group select-program">
                        <label>Thuộc khóa học</label>
                        <select name="subcourse_id" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php while ($row = $subList->fetch_assoc()): ?>
                                <option value="<?= $row['subcourse_id'] ?>" 
                                    <?= ($subcourse_id == $row['subcourse_id']) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tiêu đề bài học</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($title) ?>">
                    </div>

                    <div class="input-group">
                        <label>Phụ đề</label>
                        <input type="text" name="subtitle" value="<?= htmlspecialchars($subtitle) ?>">
                    </div>

                    <div class="input-group">
                        <label>Tổng quan</label>
                        <textarea name="overview" rows="6" style="width:100%;"><?= htmlspecialchars($overview) ?></textarea>
                    </div>

                    <div class="input-group">
                        <label>Khối lệnh sử dụng</label>
                        <div class="blocks-options">
                            <?php 
                            $blockList = ['icon' => 'Biểu tượng', 'text' => 'Chữ', 'python' => 'Python'];
                            foreach ($blockList as $value => $label): ?>
                                <label class="block-item">
                                    <input type="checkbox" name="blocks[]" value="<?= $value ?>"
                                      <?= in_array($value, $blocksArr) ? "checked" : "" ?>>
                                    <span><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Media hiện tại</label>
                        <div class="media-preview">
                            <?php if ($media): 
                                $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                $path = $uploadDir . $media;
                            ?>
                                <?php if (in_array($ext, ['mp4', 'webm', 'mov', 'ogg'])): ?>
                                    <video width="160" controls><source src="<?= $path ?>"></video>
                                <?php else: ?>
                                    <img src="<?= $path ?>" width="160">
                                <?php endif; ?>
                            <?php else: ?>
                                Chưa có media
                            <?php endif; ?>
                        </div>
                        <input type="file" name="media" accept="image/*,video/*">
                    </div>

                    <button type="submit" name="save_lesson" class="submit-btn">LƯU THAY ĐỔI</button>
                    <a href="create_lessons.php" class="btn-back">Quay lại</a>
                </form>
            </div>
        </div>
    </div>

    <?php include('../pages/footer.php'); ?>

</body>
</html>