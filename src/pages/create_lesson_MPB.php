<?php
session_start();
include '../database/connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";
$uploadDir = "../img/lesson_detail/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function uploadMedia($files, $conn, $uploadDir) {
    $media_ids = [];
    $allow = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg', 'application/pdf'];

    if (!isset($files['tmp_name']) || !is_array($files['tmp_name'])) return $media_ids;

    foreach ($files['tmp_name'] as $key => $tmp) {
        if ($files['error'][$key] !== 0) continue;

        $type = $files['type'][$key];
        if (!in_array($type, $allow)) continue;

        $ext  = pathinfo($files['name'][$key], PATHINFO_EXTENSION);
        $name = uniqid('media_') . '.' . $ext;
        $path = $uploadDir . $name;

        if (move_uploaded_file($tmp, $path)) {
            $stmt = $conn->prepare("INSERT INTO media (url, mime_type, file_size, uploaded_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $name, $type, $files['size'][$key]);
            $stmt->execute();
            $media_ids[] = $stmt->insert_id;
            $stmt->close();
        }
    }
    return $media_ids;
}

// Xử lý Xóa (Giữ nguyên logic của bạn)
if (isset($_GET['delete'])) {
    $model_id = intval($_GET['delete']);
    $conn->begin_transaction();
    try {
        $mediaResult = $conn->query("SELECT m.media_id, m.url FROM lesson_model_media lmm JOIN media m ON lmm.media_id = m.media_id WHERE lmm.model_id = $model_id");
        $mediaIds = [];
        while ($media = $mediaResult->fetch_assoc()) {
            $mediaIds[] = $media['media_id'];
            if (file_exists($uploadDir . $media['url'])) unlink($uploadDir . $media['url']);
        }
        $conn->query("DELETE FROM lesson_model_media WHERE model_id = $model_id");
        if (!empty($mediaIds)) {
            $idList = implode(',', $mediaIds);
            $conn->query("DELETE FROM media WHERE media_id IN ($idList)");
        }
        $conn->query("DELETE FROM lesson_models WHERE model_id = $model_id");
        $conn->commit();
        $success = "Đã xóa mô hình thành công!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Lỗi xóa: " . $e->getMessage();
    }
}

// Xử lý Submit Form
if (isset($_POST['model_submit'])) {
    $lesson_id   = intval($_POST['lesson_id']);
    $model_title = trim($_POST['title']);
    $model_desc  = trim($_POST['description']);
    $prep_notes  = trim($_POST['prep_notes']);
    $build_title = trim($_POST['build_title']);
    $build_type  = $_POST['build_type'];

    if (!$lesson_id || !$model_title) {
        $error = "Vui lòng chọn bài học và nhập tiêu đề mô hình.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Lưu Mô hình
            $stmt = $conn->prepare("INSERT INTO lesson_models (lesson_id, title, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $lesson_id, $model_title, $model_desc);
            $stmt->execute();
            $model_id = $stmt->insert_id;

            if (!empty($_FILES['model_media']['name'][0])) {
                foreach (uploadMedia($_FILES['model_media'], $conn, $uploadDir) as $mid) {
                    $conn->query("INSERT INTO lesson_model_media (model_id, media_id) VALUES ($model_id, $mid)");
                }
            }

            // 2. Lưu Chuẩn bị
            if (!empty($prep_notes)) {
                $stmtP = $conn->prepare("INSERT INTO lesson_preparations (lesson_id, notes) VALUES (?, ?)");
                $stmtP->bind_param("is", $lesson_id, $prep_notes);
                $stmtP->execute();
                $prep_id = $stmtP->insert_id;
                if (!empty($_FILES['prep_media']['name'][0])) {
                    foreach (uploadMedia($_FILES['prep_media'], $conn, $uploadDir) as $mid) {
                        $conn->query("INSERT INTO lesson_preparation_media (preparation_id, media_id) VALUES ($prep_id, $mid)");
                    }
                }
            }

            // 3. Lưu Xây dựng (FIXED)
            $hasBuildMedia = !empty($_FILES['build_media']['name'][0]);
            if (!empty($build_title) || $hasBuildMedia) {
                $build_title = empty($build_title) ? 'Nội dung xây dựng' : $build_title;
                $stmtB = $conn->prepare("INSERT INTO lesson_builds (lesson_id, title, `type`) VALUES (?, ?, ?)");
                $stmtB->bind_param("iss", $lesson_id, $build_title, $build_type);
                $stmtB->execute();
                $build_id = $stmtB->insert_id;

                if ($hasBuildMedia) {
                    foreach (uploadMedia($_FILES['build_media'], $conn, $uploadDir) as $mid) {
                        // Sửa câu lệnh INSERT chỉ định rõ cột
                        $conn->query("INSERT INTO lesson_build_media (build_id, media_id) VALUES ($build_id, $mid)");
                    }
                }
            }

            $conn->commit();
            $success = "Lưu chi tiết bài học thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}

$lessonResult = $conn->query("SELECT lesson_id, title FROM lessons ORDER BY created_at ASC");
$modelResult  = $conn->query("SELECT lm.*, l.title AS lesson_title FROM lesson_models lm LEFT JOIN lessons l ON lm.lesson_id = l.lesson_id ORDER BY lm.model_id DESC");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý mô hình bài học</title>
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

                <?php if ($error): ?><div class="message-box error"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="message-box success"><?= $success ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <h2>Chi tiết bài học</h2>

                    <div class="input-group select-program">
                        <label>Chọn bài học</label>
                        <select name="lesson_id" required>
                            <option value="">-- Chọn bài học --</option>
                            <?php while ($l = $lessonResult->fetch_assoc()): ?>
                                <option value="<?= $l['lesson_id'] ?>"><?= htmlspecialchars($l['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <h2>Mô hình</h2>
                    <div class="input-group">
                        <label>Tiêu đề mô hình</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="input-group">
                        <label>Mô tả mô hình</label>
                        <textarea name="description" rows="6" style="width:100%;"></textarea>
                    </div>
                    <div class="input-group">
                        <label>Media mô hình</label>
                        <input type="file" name="model_media[]" multiple>
                    </div>

                    <h2>Chuẩn bị</h2>
                    <div class="input-group">
                        <label>Ghi chú chuẩn bị</label>
                        <textarea name="prep_notes" rows="6" style="width:100%;"></textarea>
                    </div>
                    <div class="input-group">
                        <label>Media chuẩn bị</label>
                        <input type="file" name="prep_media[]" multiple>
                    </div>

                    <h2>Xây dựng</h2>
                    <div class="input-group">
                        <label>Tiêu đề xây dựng</label>
                        <input type="text" name="build_title">
                    </div>
                    <div class="input-group select-program">
                        <label>Loại xây dựng</label>
                        <select name="build_type">
                            <option value="slide">Ảnh</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Media xây dựng</label>
                        <input type="file" name="build_media[]" multiple>
                    </div>

                    <button type="submit" name="model_submit" class="submit-btn">THÊM</button>

                    <a href="lesson.php" class="btn-back">
                        Quay lại
                    </a>
                    
                </form>
            </div>
        </div>

        <div class="container-table">
            <h2>Danh sách</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Bài học</th>
                        <th>Tiêu đề</th>
                        <th>Media</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $modelResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['lesson_title']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td>
                                <?php
                                $mediaQ = $conn->query("
                                    SELECT m.url, m.mime_type
                                    FROM lesson_model_media lmm
                                    JOIN media m ON lmm.media_id = m.media_id
                                    WHERE lmm.model_id = {$row['model_id']}
                                ");
                                while ($m = $mediaQ->fetch_assoc()):
                                    $file = $uploadDir . $m['url'];
                                ?>
                                    <?php if (strpos($m['mime_type'], 'video/') !== false): ?>
                                        <video width="90" controls src="<?= $file ?>"></video>
                                    <?php else: ?>
                                        <img src="<?= $file ?>" width="90">
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </td>
                            <td>
                                <a href="edit_lesson_detail.php?id=<?= $row['model_id'] ?>" class="btn-edit">Sửa</a>
                                <a href="?delete=<?= $row['model_id'] ?>" class="btn-delete" onclick="return confirm('Xóa?')">Xóa</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include('../pages/footer.php'); ?>
</body>
</html>