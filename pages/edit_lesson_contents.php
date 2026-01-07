<?php
session_start();
include '../database/connect.php';

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

/* ===== KIỂM TRA ID ===== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Khối nội dung không hợp lệ");
}

$block_id  = intval($_GET['id']);
$uploadDir = "../img/lesson_detail/";
$error = "";
$success = "";

/* ===== LẤY DỮ LIỆU BLOCK HIỆN TẠI ===== */
$resBlock = $conn->query("
    SELECT b.*, l.title AS lesson_title
    FROM lesson_content_blocks b
    JOIN lessons l ON b.lesson_id = l.lesson_id
    WHERE b.block_id = $block_id
");
$block = $resBlock->fetch_assoc();
if (!$block) die("Khối nội dung không tồn tại");

/* ===== XỬ LÝ XÓA MEDIA (ẢNH/VIDEO) ===== */
if (isset($_GET['delete_media']) && is_numeric($_GET['delete_media'])) {
    $media_id = intval($_GET['delete_media']);

    $q = $conn->query("SELECT url FROM media WHERE media_id=$media_id");
    if ($q && $m = $q->fetch_assoc()) {
        $filePath = $uploadDir . $m['url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $conn->query("DELETE FROM lesson_content_block_media WHERE media_id=$media_id");
        $conn->query("DELETE FROM media WHERE media_id=$media_id");
    }
    header("Location: edit_lesson_contents.php?id=$block_id&msg=deleted");
    exit;
}

/* ===== XỬ LÝ XÓA TỆP ĐÍNH KÈM ===== */
if (isset($_GET['delete_attachment']) && is_numeric($_GET['delete_attachment'])) {
    $media_id = intval($_GET['delete_attachment']);

    $q = $conn->query("SELECT url FROM media WHERE media_id=$media_id");
    if ($q && $m = $q->fetch_assoc()) {
        $filePath = $uploadDir . $m['url'];
        if (file_exists($filePath)) unlink($filePath);

        $conn->query("DELETE FROM lesson_attachments WHERE media_id=$media_id");
        $conn->query("DELETE FROM media WHERE media_id=$media_id");
    }
    header("Location: edit_lesson_contents.php?id=$block_id&msg=att_deleted");
    exit;
}

/* ===== XỬ LÝ LƯU THAY ĐỔI ===== */
if (isset($_POST['save_block'])) {
    $title        = trim($_POST['title']);
    $subtitle     = trim($_POST['subtitle']);
    $description  = trim($_POST['description']);
    $usage_text   = trim($_POST['usage_text']);
    $example_text = trim($_POST['example_text']);

    if ($title === "") {
        $error = "Tiêu đề không được để trống.";
    } else {
        $conn->begin_transaction();
        try {
            /* 1. Cập nhật thông tin văn bản */
            $stmt = $conn->prepare("
                UPDATE lesson_content_blocks
                SET title=?, subtitle=?, description=?, usage_text=?, example_text=?
                WHERE block_id=?
            ");
            $stmt->bind_param("sssssi", $title, $subtitle, $description, $usage_text, $example_text, $block_id);
            $stmt->execute();

            /* 2. Upload Media mới (Ảnh/Video) */
            if (!empty($_FILES['media']['name'][0])) {
                foreach ($_FILES['media']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['media']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['media']['name'][$i], PATHINFO_EXTENSION);
                        $fileName = time().'_'.rand(100,999).'.'.$ext;
                        
                        if (move_uploaded_file($tmp, $uploadDir.$fileName)) {
                            $stmtM = $conn->prepare("INSERT INTO media (url, mime_type, file_size, uploaded_at) VALUES (?,?,?,NOW())");
                            $stmtM->bind_param("ssi", $fileName, $_FILES['media']['type'][$i], $_FILES['media']['size'][$i]);
                            $stmtM->execute();
                            $media_id = $stmtM->insert_id;

                            $conn->query("INSERT INTO lesson_content_block_media (block_id, media_id) VALUES ($block_id, $media_id)");
                        }
                    }
                }
            }

            /* 3. Upload Tệp đính kèm mới */
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['attachments']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
                        $fileName = time().'_att_'.rand(100,999).'.'.$ext;

                        if (move_uploaded_file($tmp, $uploadDir.$fileName)) {
                            $stmtA = $conn->prepare("INSERT INTO media (url, mime_type, file_size, uploaded_at) VALUES (?,?,?,NOW())");
                            $stmtA->bind_param("ssi", $fileName, $_FILES['attachments']['type'][$i], $_FILES['attachments']['size'][$i]);
                            $stmtA->execute();
                            $media_id = $stmtA->insert_id;

                            $stmtLink = $conn->prepare("INSERT INTO lesson_attachments (lesson_id, media_id) VALUES (?,?)");
                            $stmtLink->bind_param("ii", $block['lesson_id'], $media_id);
                            $stmtLink->execute();
                        }
                    }
                }
            }

            $conn->commit();
            $success = "Cập nhật khối nội dung thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}

/* Lấy danh sách Media hiện tại */
$resMedia = $conn->query("SELECT m.* FROM lesson_content_block_media lbm JOIN media m ON lbm.media_id = m.media_id WHERE lbm.block_id = $block_id");

/* Lấy danh sách Tệp đính kèm hiện tại */
$resAttach = $conn->query("SELECT m.* FROM lesson_attachments la JOIN media m ON la.media_id = m.media_id WHERE la.lesson_id = ".$block['lesson_id']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa nội dung bài học - LET'S CODE</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/edit_lesson_detail.css">
    <style>
        .media-preview { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
        .media-item { border: 1px solid #ddd; padding: 8px; border-radius: 8px; text-align: center; background: #f9f9f9; }
        .media-item video, .media-item img { border-radius: 5px; background: #000; object-fit: cover; }
        .btn-delete-media { display: block; color: #ff4d4d; text-decoration: none; font-size: 13px; margin-top: 8px; font-weight: bold; }
        .attachment-list { display: flex; flex-direction: column; gap: 8px; }
        .attachment-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #fff; border: 1px solid #eee; border-radius: 6px; }
    </style>
</head>
<body>
<?php include('../pages/header.php'); ?>

<div class="user-page">
    <div class="container">
        <div class="form-container">
            <h2>Sửa khối nội dung bài học</h2>

            <?php if ($error): ?> <div class="message-box error"><?= $error ?></div> <?php endif; ?>
            <?php if ($success): ?> <div class="message-box success"><?= $success ?></div> <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <label>Bài học</label>
                    <input type="text" value="<?= htmlspecialchars($block['lesson_title']) ?>" disabled>
                </div>

                <div class="input-group">
                    <label>Tiêu đề chính</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($block['title']) ?>">
                </div>

                <div class="input-group">
                    <label>Tiêu đề phụ (Subtitle)</label>
                    <input type="text" name="subtitle" value="<?= htmlspecialchars($block['subtitle']) ?>">
                </div>

                <div class="input-group">
                    <label>Mô tả nội dung</label>
                    <textarea name="description" rows="5" style="width:100%;"><?= htmlspecialchars($block['description']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>Hướng dẫn sử dụng</label>
                    <textarea name="usage_text" rows="4" style="width:100%;"><?= htmlspecialchars($block['usage_text']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>Ví dụ minh họa</label>
                    <textarea name="example_text" rows="4" style="width:100%;"><?= htmlspecialchars($block['example_text']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>Media hiện tại (Video/Ảnh minh họa)</label>
                    <div class="media-preview">
                        <?php while ($m = $resMedia->fetch_assoc()): ?>
                            <div class="media-item">
                                <?php if (strpos($m['mime_type'], 'video/') !== false): ?>
                                    <video width="150" height="100" muted>
                                        <source src="<?= $uploadDir.$m['url'] ?>" type="<?= $m['mime_type'] ?>">
                                    </video>    
                                <?php else: ?>
                                    <img src="<?= $uploadDir.$m['url'] ?>" width="150" height="100">
                                <?php endif; ?>

                                <a class="btn-delete-media" href="?id=<?= $block_id ?>&delete_media=<?= $m['media_id'] ?>" onclick="return confirm('Xóa tệp này?')">Xóa</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="input-group">
                    <label>Thêm Media mới</label>
                    <input type="file" name="media[]" multiple accept="image/*,video/*">
                </div>

                <div class="input-group">
                    <label>Tệp đính kèm hiện tại (PDF, tài liệu...)</label>
                    <div class="attachment-list">
                        <?php while ($a = $resAttach->fetch_assoc()): ?>
                            <div class="attachment-item">
                                <a href="<?= $uploadDir.$a['url'] ?>" target="_blank" style="color:#007bff; text-decoration:none;">
                                    📎 <?= htmlspecialchars($a['url']) ?>
                                </a>
                                <a href="?id=<?= $block_id ?>&delete_attachment=<?= $a['media_id'] ?>" style="color:red; font-size:12px;" onclick="return confirm('Xóa tệp đính kèm này?')">Xóa</a>
                            </div>
                        <?php endwhile; ?>
                        <?php if ($resAttach->num_rows === 0): ?> <p style="color:#999; font-style:italic;">Chưa có tệp đính kèm.</p> <?php endif; ?>
                    </div>
                </div>

                <div class="input-group">
                    <label>Thêm Tệp đính kèm mới</label>
                    <input type="file" name="attachments[]" multiple>
                </div>

                <button type="submit" name="save_block" class="submit-btn">LƯU TẤT CẢ THAY ĐỔI</button>
                <a href="create_lesson_contents.php" class="btn-back">Quay lại</a>
            </form>
        </div>
    </div>
</div>

<?php include('../pages/footer.php'); ?>
</body>
</html>