<?php
session_start();
include '../database/connect.php';

// Chỉ cho ADMIN truy cập
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Kiểm tra ID chương trình
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID chương trình không hợp lệ");
}

$program_id = intval($_GET['id']);
$error = "";
$success = "";
$uploadDir = '../img/program/';

// Lấy thông tin chương trình
$stmt = $conn->prepare("SELECT name, short_description, media, blocks FROM programs WHERE program_id = ?");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Chương trình không tồn tại.");
}

$stmt->bind_result($name, $short_description, $media, $blocks_json);
$stmt->fetch();
$stmt->close();

$blocksArr = $blocks_json ? json_decode($blocks_json, true) : [];

// Xử lý lưu thay đổi
if (isset($_POST['save_program'])) {
    $new_name = trim($_POST['name']);
    $new_short_description = trim($_POST['short_description']);
    $new_blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks']) : null;

    // Xử lý media mới
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
        if (in_array($_FILES['media']['type'], $allowedTypes)) {
            $new_media = time() . '_' . basename($_FILES['media']['name']);
            if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $new_media)) {
                $error = "Lỗi khi tải file lên server.";
            } else {
                // Xóa file cũ nếu có
                if (!empty($media) && file_exists($uploadDir . $media)) {
                    unlink($uploadDir . $media);
                }
                $media = $new_media;
            }
        } else {
            $error = "Chỉ cho phép tải lên ảnh hoặc video (jpg, png, gif, mp4, webm).";
        }
    }

    if (!$error) {
        $stmt_update = $conn->prepare("
            UPDATE programs 
            SET name=?, short_description=?, media=?, blocks=?, updated_at=NOW() 
            WHERE program_id=?
        ");
        $stmt_update->bind_param("ssssi", $new_name, $new_short_description, $media, $new_blocks, $program_id);
        
        if ($stmt_update->execute()) {
            $success = "Cập nhật chương trình thành công!";
            // Cập nhật lại biến hiển thị
            $name = $new_name;
            $short_description = $new_short_description;
            $blocksArr = $new_blocks ? json_decode($new_blocks, true) : [];
        } else {
            $error = "Lỗi cập nhật: " . $conn->error;
        }
        $stmt_update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa chương trình học</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
</head>
<body>

    <?php include('../pages/header.php'); ?>

    <div class="user-page">
        <div class="container">
            <div class="form-container">
                <h2>Sửa chương trình học</h2>

                <?php if ($error): ?>
                    <div class="message-box error"><?php echo $error; ?></div>
                <?php elseif ($success): ?>
                    <div class="message-box success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="input-group">
                        <label>Tên chương trình</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                    </div>

                    <div class="input-group">
                        <label>Mô tả</label>
                        <textarea name="short_description" rows="6" style="width:100%; padding:8px; resize:vertical;"><?php echo htmlspecialchars($short_description); ?></textarea>
                    </div>

                    <div class="input-group">
                        <label>Media (ảnh/video giới thiệu)</label>
                        <?php if(!empty($media)): ?>
                            <?php 
                                $ext = pathinfo($media, PATHINFO_EXTENSION); 
                                $filePath = $uploadDir . $media; 
                            ?>
                            <div style="margin-bottom: 10px;">
                                <?php if(in_array(strtolower($ext), ['mp4','webm','mov','ogg'])): ?>
                                    <video width="150" controls><source src="<?php echo $filePath; ?>"></video>
                                <?php else: ?>
                                    <img src="<?php echo $filePath; ?>" width="150">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="media" accept="image/*,video/*">
                    </div>

                    <div class="input-group">
                        <label>Khối lệnh sử dụng</label>
                        <div class="blocks-options">
                            <?php 
                            $allBlocks = ['icon' => 'Biểu tượng', 'text' => 'Chữ', 'python' => 'Python'];
                            foreach($allBlocks as $val => $labelBlock): ?>
                                <label class="block-item">
                                    <input type="checkbox" name="blocks[]" value="<?php echo $val; ?>" 
                                    <?php echo in_array($val, $blocksArr) ? "checked" : ""; ?>>
                                    <span><?php echo $labelBlock; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" name="save_program" class="submit-btn">LƯU THAY ĐỔI</button>
                    <a href="create_program.php" class="btn-back">Quay lại</a>
                </form>
            </div>
        </div>
    </div>

    <?php include('../pages/footer.php'); ?>

</body>
</html>