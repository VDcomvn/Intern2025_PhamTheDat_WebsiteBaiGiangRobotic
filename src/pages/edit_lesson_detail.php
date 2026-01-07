<?php
session_start();
include '../database/connect.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("ID không hợp lệ");

$model_id = intval($_GET['id']);
$uploadDir = "../img/lesson_detail/";
$error = ""; $success = "";

// Lấy dữ liệu cơ bản
$resModel = $conn->query("SELECT lm.*, l.title AS lesson_title FROM lesson_models lm JOIN lessons l ON lm.lesson_id = l.lesson_id WHERE lm.model_id = $model_id");
$modelData = $resModel->fetch_assoc();
if (!$modelData) die("Không tồn tại");
$lesson_id = $modelData['lesson_id'];

$resPrep = $conn->query("SELECT * FROM lesson_preparations WHERE lesson_id = $lesson_id LIMIT 1");
$prepData = $resPrep->fetch_assoc();
$prep_id = $prepData['preparation_id'] ?? 0;

$resBuild = $conn->query("SELECT * FROM lesson_builds WHERE lesson_id = $lesson_id LIMIT 1");
$buildData = $resBuild->fetch_assoc();
$build_id = $buildData['build_id'] ?? 0;

// Xử lý Xóa Media
if (isset($_GET['delete_media']) && is_numeric($_GET['delete_media'])) {
    $media_id = intval($_GET['delete_media']);
    $type = $_GET['type'];
    $linkTable = ($type === 'model') ? "lesson_model_media" : (($type === 'prep') ? "lesson_preparation_media" : "lesson_build_media");
    
    $q = $conn->query("SELECT url FROM media WHERE media_id = $media_id");
    if ($m = $q->fetch_assoc()) {
        if (file_exists($uploadDir . $m['url'])) unlink($uploadDir . $m['url']);
        $conn->query("DELETE FROM $linkTable WHERE media_id = $media_id");
        $conn->query("DELETE FROM media WHERE media_id = $media_id");
    }
    header("Location: edit_lesson_detail.php?id=$model_id"); exit;
}

// Xử lý Lưu
if (isset($_POST['save_all'])) {
    $conn->begin_transaction();
    try {
        // Cập nhật text nội dung
        $uModel = $conn->prepare("UPDATE lesson_models SET title=?, description=? WHERE model_id=?");
        $uModel->bind_param("ssi", $_POST['title'], $_POST['description'], $model_id);
        $uModel->execute();

        $prep_notes = trim($_POST['prep_notes']);
        if ($prep_id) {
            $uP = $conn->prepare("UPDATE lesson_preparations SET notes=? WHERE preparation_id=?");
            $uP->bind_param("si", $prep_notes, $prep_id); $uP->execute();
        } elseif ($prep_notes) {
            $iP = $conn->prepare("INSERT INTO lesson_preparations (lesson_id, notes) VALUES (?,?)");
            $iP->bind_param("is", $lesson_id, $prep_notes); $iP->execute();
            $prep_id = $iP->insert_id;
        }

        $build_title = trim($_POST['build_title']);
        $build_type = $_POST['build_type'];
        if ($build_id) {
            $uB = $conn->prepare("UPDATE lesson_builds SET title=?, type=? WHERE build_id=?");
            $uB->bind_param("ssi", $build_title, $build_type, $build_id); $uB->execute();
        } elseif ($build_title) {
            $iB = $conn->prepare("INSERT INTO lesson_builds (lesson_id, title, type) VALUES (?,?,?)");
            $iB->bind_param("iss", $lesson_id, $build_title, $build_type); $iB->execute();
            $build_id = $iB->insert_id;
        }

        // Hàm Upload nội bộ (FIXED INSERT)
        $fnUpload = function($key) use ($conn, $uploadDir) {
            $ids = [];
            if (!empty($_FILES[$key]['name'][0])) {
                foreach ($_FILES[$key]['tmp_name'] as $i => $tmp) {
                    if ($_FILES[$key]['error'][$i] === 0) {
                        $name = time().'_'.rand(100,999).'_'.$_FILES[$key]['name'][$i];
                        if (move_uploaded_file($tmp, $uploadDir.$name)) {
                            $stmt = $conn->prepare("INSERT INTO media (url, mime_type, file_size, uploaded_at) VALUES (?,?,?,NOW())");
                            $stmt->bind_param("ssi", $name, $_FILES[$key]['type'][$i], $_FILES[$key]['size'][$i]);
                            $stmt->execute();
                            $ids[] = $stmt->insert_id;
                        }
                    }
                }
            }
            return $ids;
        };

        // Lưu liên kết media (FIXED: Đã thêm tên cột)
        foreach ($fnUpload('model_media') as $mid) 
            $conn->query("INSERT INTO lesson_model_media (model_id, media_id) VALUES ($model_id, $mid)");
        
        if ($prep_id) foreach ($fnUpload('prep_media') as $mid) 
            $conn->query("INSERT INTO lesson_preparation_media (preparation_id, media_id) VALUES ($prep_id, $mid)");
        
        if ($build_id) foreach ($fnUpload('build_media') as $mid) 
            $conn->query("INSERT INTO lesson_build_media (build_id, media_id) VALUES ($build_id, $mid)");

        $conn->commit();
        header("Location: edit_lesson_detail.php?id=$model_id&success=1"); exit;
    } catch (Exception $e) { $conn->rollback(); $error = $e->getMessage(); }
}
if (isset($_GET['success'])) $success = "Cập nhật thành công!";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa chi tiết bài học</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">
    <link rel="stylesheet" href="../css/edit_lesson_detail.css">
</head>
<body>
<?php include('../pages/header.php'); ?>

<div class="user-page">
    <div class="container">
        <div class="form-container">
            <h2>Sửa nội dung bài học</h2>

            <?php if ($error): ?> <div class="message-box error"><?= $error ?></div> <?php endif; ?>
            <?php if ($success): ?> <div class="message-box success"><?= $success ?></div> <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <label>Bài học</label>
                    <input type="text" value="<?= htmlspecialchars($modelData['lesson_title']) ?>" disabled>
                </div>

                <h2 style="margin-top:30px;;">Mô hình</h2>
                <div class="input-group">
                    <label>Tiêu đề mô hình</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($modelData['title']) ?>">
                </div>
                <div class="input-group">
                    <label>Mô tả mô hình</label>
                    <textarea name="description" rows="6" style="width:100%;"><?= htmlspecialchars($modelData['description']) ?></textarea>
                </div>
                <div class="input-group">
                    <label>Media mô hình hiện tại</label>
                    <div class="media-preview">
                        <?php 
                        $resM = $conn->query("
                            SELECT m.* 
                            FROM lesson_model_media lmm 
                            JOIN media m ON lmm.media_id = m.media_id 
                            WHERE lmm.model_id = $model_id
                        ");

                        while ($m = $resM->fetch_assoc()):
                            $filePath = $uploadDir . $m['url'];
                        ?>
                            <div class="media-item">
                                <?php if (strpos($m['mime_type'], 'video') !== false): ?>
                                    <video width="160" controls>
                                        <source src="<?= $filePath ?>" type="<?= $m['mime_type'] ?>">
                                        Trình duyệt không hỗ trợ video
                                    </video>
                                <?php else: ?>
                                    <img src="<?= $filePath ?>" width="160">
                                <?php endif; ?>

                                <a class="btn-delete-media"
                                href="?id=<?= $model_id ?>&delete_media=<?= $m['media_id'] ?>&type=model"
                                onclick="return confirm('Xóa media này?')">
                                Xóa
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <label>Thêm media mô hình</label>
                    <input type="file" name="model_media[]" multiple accept="image/*,video/*">
                </div>

                <h2 style="margin-top:30px; ">Chuẩn bị</h2>
                <div class="input-group">
                    <label>Ghi chú (Vật liệu/Dụng cụ)</label>
                    <textarea name="prep_notes" rows="6" style="width:100%;"><?= htmlspecialchars($prepData['notes'] ?? '') ?></textarea>
                </div>
                <div class="input-group">
                    <label>Media chuẩn bị hiện tại</label>
                    <div class="media-preview">
                        <?php if($prep_id):
                        $resP = $conn->query("SELECT m.* FROM lesson_preparation_media lpm JOIN media m ON lpm.media_id=m.media_id WHERE lpm.preparation_id=$prep_id");
                        while($m = $resP->fetch_assoc()): ?>
                            <div class="media-item">
                                <img src="<?= $uploadDir.$m['url'] ?>" width="120">
                                <a class="btn-delete-media" href="?id=<?= $model_id ?>&delete_media=<?= $m['media_id'] ?>&type=prep" onclick="return confirm('Xóa?')">Xóa</a>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                    <label>Thêm media chuẩn bị</label>
                    <input type="file" name="prep_media[]" multiple accept="image/*">
                </div>

                <h2 style="margin-top:30px;">Xây dựng</h2>
                <div class="input-group">
                    <label>Tiêu đề xây dựng</label>
                    <input type="text" name="build_title" value="<?= htmlspecialchars($buildData['title'] ?? '') ?>">
                </div>
                <div class="input-group select-program">
                    <label>Loại nội dung</label>
                    <select name="build_type">
                        <option value="slide" <?= ($buildData['type'] ?? '') == 'slide' ? 'selected' : '' ?>>Ảnh</option>
                        <option value="pdf" <?= ($buildData['type'] ?? '') == 'pdf' ? 'selected' : '' ?>>Tài liệu PDF</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Media xây dựng hiện tại</label>
                    <div class="media-preview">
                        <?php if($build_id):
                        $resB = $conn->query("SELECT m.* FROM lesson_build_media lbm JOIN media m ON lbm.media_id=m.media_id WHERE lbm.build_id=$build_id");
                        while($m = $resB->fetch_assoc()): ?>
                            <div class="media-item">
                                <?php if(strpos($m['mime_type'], 'pdf') !== false): ?>
                                    <div style="width:120px; height:80px; background:#eee; display:flex; align-items:center; justify-content:center;">PDF</div>
                                <?php else: ?>
                                    <img src="<?= $uploadDir.$m['url'] ?>" width="120">
                                <?php endif; ?>
                                <a class="btn-delete-media" href="?id=<?= $model_id ?>&delete_media=<?= $m['media_id'] ?>&type=build" onclick="return confirm('Xóa?')">Xóa</a>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                    <label>Thêm media xây dựng</label>
                    <input type="file" name="build_media[]" multiple>
                </div>

                <button type="submit" name="save_all" class="submit-btn" style="margin-top:30px;">LƯU TẤT CẢ THAY ĐỔI</button>
                <a href="create_lesson_MPB.php" class="btn-back" style="display:inline-block; margin-top:10px; text-decoration:none; color:#666;">Quay lại</a>
            </form>
        </div>
    </div>
</div>

<?php include('../pages/footer.php'); ?>
</body>
</html>