<?php
session_start();
include '../database/connect.php';

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";
$uploadDir = "../img/lesson_detail/";

/* Đảm bảo thư mục upload tồn tại */
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* ===== LẤY DANH SÁCH BÀI HỌC ĐỂ CHỌN ===== */
$lessonResult = $conn->query("SELECT lesson_id, title FROM lessons ORDER BY created_at ASC");

/* ===== XỬ LÝ THÊM BLOCK NỘI DUNG MỚI ===== */
if (isset($_POST['block_submit'])) {
    $lesson_id    = intval($_POST['lesson_id']);
    $title         = trim($_POST['title']);
    $subtitle      = trim($_POST['subtitle']);
    $description   = trim($_POST['description']);
    $usage_text    = trim($_POST['usage_text']);
    $example_text  = trim($_POST['example_text']);

    if (!$lesson_id || !$title) {
        $error = "Vui lòng chọn bài học và nhập tiêu đề cho khối nội dung.";
    } else {
        $conn->begin_transaction();
        try {
            /* 1. Thêm dữ liệu vào bảng lesson_content_blocks */
            $stmt = $conn->prepare("
                INSERT INTO lesson_content_blocks 
                (lesson_id, title, subtitle, description, usage_text, example_text) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss", $lesson_id, $title, $subtitle, $description, $usage_text, $example_text);
            $stmt->execute();
            $block_id = $stmt->insert_id;

            /* 2. Xử lý Upload Media (nếu có) */
            if (!empty($_FILES['media']['name'][0])) {
                foreach ($_FILES['media']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['media']['error'][$i] === 0) {
                        $fileExtension = pathinfo($_FILES['media']['name'][$i], PATHINFO_EXTENSION);
                        $fileName = time() . '_' . rand(100, 999) . '.' . $fileExtension;
                        
                        if (move_uploaded_file($tmp, $uploadDir . $fileName)) {
                            /* Lưu vào bảng media */
                            $stmtM = $conn->prepare("
                                INSERT INTO media (url, mime_type, file_size, uploaded_at) 
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmtM->bind_param("ssi", $fileName, $_FILES['media']['type'][$i], $_FILES['media']['size'][$i]);
                            $stmtM->execute();
                            $media_id = $stmtM->insert_id;

                            /* Tạo liên kết trong bảng trung gian */
                            $conn->query("
                                INSERT INTO lesson_content_block_media (block_id, media_id) 
                                VALUES ($block_id, $media_id)
                            ");
                        }
                    }
                }
            }
            /* 3. Upload TỆP ĐÍNH KÈM (lesson_attachments) */
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['attachments']['error'][$i] === 0) {

                        $ext = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
                        $fileName = time() . '_att_' . rand(100,999) . '.' . $ext;

                        if (move_uploaded_file($tmp, $uploadDir . $fileName)) {

                            /* Lưu media */
                            $stmtA = $conn->prepare("
                                INSERT INTO media (url, mime_type, file_size, uploaded_at)
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmtA->bind_param(
                                "ssi",
                                $fileName,
                                $_FILES['attachments']['type'][$i],
                                $_FILES['attachments']['size'][$i]
                            );
                            $stmtA->execute();
                            $media_id = $stmtA->insert_id;

                            /* Liên kết lesson_attachments */
                            $stmtLink = $conn->prepare("
                                INSERT INTO lesson_attachments (lesson_id, media_id)
                                VALUES (?, ?)
                            ");
                            $stmtLink->bind_param("ii", $lesson_id, $media_id);
                            $stmtLink->execute();
                        }
                    }
                }
            }

            $conn->commit();
            $success = "Thêm khối nội dung bài học thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}

/* ===== XỬ LÝ XÓA BLOCK NỘI DUNG ===== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $block_id = intval($_GET['delete']);

    /* Lấy danh sách media để xóa file vật lý */
    $res = $conn->query("
        SELECT m.media_id, m.url 
        FROM lesson_content_block_media lbm 
        JOIN media m ON lbm.media_id = m.media_id 
        WHERE lbm.block_id = $block_id
    ");

    while ($m = $res->fetch_assoc()) {
        $filePath = $uploadDir . $m['url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        /* Xóa media trong database */
        $conn->query("DELETE FROM media WHERE media_id = " . $m['media_id']);
    }

    /* Xóa liên kết và xóa block */
    $conn->query("DELETE FROM lesson_content_block_media WHERE block_id = $block_id");
    $conn->query("DELETE FROM lesson_content_blocks WHERE block_id = $block_id");

    header("Location: create_lesson_contents.php?success_deleted=1");
    exit;
}

/* Thông báo xóa thành công sau khi redirect */
if (isset($_GET['success_deleted'])) $success = "Đã xóa nội dung thành công!";

/* ===== TRUY VẤN DANH SÁCH BLOCK ĐỂ HIỂN THỊ ===== */
$blocks = $conn->query("
    SELECT b.*, l.title AS lesson_title 
    FROM lesson_content_blocks b 
    JOIN lessons l ON b.lesson_id = l.lesson_id 
    ORDER BY b.block_id ASC
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý nội dung bài học - LET'S CODE</title>
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
                <h2>Tạo nội dung bài học</h2>

                <?php if ($error): ?>
                    <div class="message-box error"><?= $error ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="message-box success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="input-group select-program">
                        <label>Bài học thuộc về</label>
                        <select name="lesson_id" required>
                            <option value="">-- Chọn bài học --</option>
                            <?php 
                            $lessonResult->data_seek(0);
                            while($l = $lessonResult->fetch_assoc()): 
                            ?>
                                <option value="<?= $l['lesson_id'] ?>"><?= htmlspecialchars($l['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tiêu đề khối nội dung</label>
                        <input type="text" name="title" required >
                    </div>

                    <div class="input-group">
                        <label>Phụ đề (Subtitle)</label>
                        <input type="text" name="subtitle" >
                    </div>

                    <div class="input-group">
                        <label>Mô tả chi tiết nội dung</label>
                        <textarea name="description" rows="6" style="width:98%" ></textarea>
                    </div>

                    <div class="input-group">
                        <label>Cách sử dụng / Hướng dẫn</label>
                        <textarea name="usage_text" rows="6" style="width:98%"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Ví dụ minh họa</label>
                        <textarea name="example_text" rows="6" style="width:98%"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Hình ảnh / Video minh họa</label>
                        <input type="file" name="media[]" multiple accept="image/*,video/*">
                    </div>

                    <div class="input-group">
                        <label>Tệp đính kèm</label>
                        <input type="file" name="attachments[]" multiple>
                    </div>

                    <button type="submit" name="block_submit" class="submit-btn">TẠO KHỐI NỘI DUNG</button>

                    <a href="lesson.php" class="btn-back">
                        Quay lại
                    </a>

                </form>
            </div>
        </div>

        <div class="container-table">
            <h2>Danh sách các khối nội dung</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Bài học</th>
                        <th>Tiêu đề khối</th>
                        <th>Media</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($b = $blocks->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['lesson_title']) ?></td>
                            <td><?= htmlspecialchars($b['title']) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <?php
                                    $mediaQuery = $conn->query("
                                        SELECT m.url, m.mime_type 
                                        FROM lesson_content_block_media lbm 
                                        JOIN media m ON lbm.media_id = m.media_id 
                                        WHERE lbm.block_id = " . $b['block_id']
                                    );
                                    while($m = $mediaQuery->fetch_assoc()):
                                    ?>
                                        <?php if(strpos($m['mime_type'], 'video') !== false): ?>
                                            <div style="width: 80px; height: 60px; background: #000; color: #fff; font-size: 10px; display: flex; align-items:center; justify-content:center;">VIDEO</div>
                                        <?php else: ?>
                                            <img src="<?= $uploadDir . $m['url'] ?>" width="80" style="border: 1px solid #ddd; border-radius: 4px;">
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_lesson_contents.php?id=<?= $b['block_id'] ?>" class="btn-edit">Sửa</a>
                                    <a class="btn-delete" 
                                       href="?delete=<?= $b['block_id'] ?>" 
                                       onclick="return confirm('Bạn có chắc chắn muốn xóa khối nội dung này và các media liên quan?')">
                                       Xóa
                                    </a>
                                </div>
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