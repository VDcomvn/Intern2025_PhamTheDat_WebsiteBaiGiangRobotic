<?php
session_start();
include '../database/connect.php';

// Chỉ cho ADMIN truy cập
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Kiểm tra id subcourse
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID subcourse không hợp lệ");
}
$subcourse_id = intval($_GET['id']);

$error = "";
$success = "";
$uploadDir = '../img/subcourse/';

// Lấy danh sách chương trình
$programsResult = $conn->query("SELECT program_id, name FROM programs ORDER BY name ASC");

// Lấy thông tin subcourse hiện tại
$stmt = $conn->prepare("SELECT * FROM subcourses WHERE subcourse_id = ?");
$stmt->bind_param("i", $subcourse_id);
$stmt->execute();
$result_subcourse = $stmt->get_result();
if ($result_subcourse->num_rows == 0) {
    die("Không tìm thấy subcourse.");
}
$subcourse = $result_subcourse->fetch_assoc();
$stmt->close();

// Xử lý lưu thay đổi
if (isset($_POST['subcourse_submit'])) {
    $program_id = intval($_POST['program_id']);
    $name = trim($_POST['name']);
    $age_group = trim($_POST['age_group']);
    $overall_goal = trim($_POST['overall_goal']);
    $short_description = trim($_POST['short_description']);
    $lesson_count = intval($_POST['lesson_count']);
    $blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks']) : null;

    if (empty($program_id) || empty($name)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        $media = $subcourse['media']; // giữ media cũ nếu không upload mới
        if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
            $allowedTypes = ['image/jpeg','image/png','image/gif','video/mp4','video/webm','video/ogg'];
            if (in_array($_FILES['media']['type'], $allowedTypes)) {
                $media = time() . '_' . basename($_FILES['media']['name']);
                if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $media)) {
                    $error = "Lỗi khi tải file lên server.";
                } else {
                    // xóa file cũ
                    if (!empty($subcourse['media']) && file_exists($uploadDir . $subcourse['media'])) {
                        unlink($uploadDir . $subcourse['media']);
                    }
                }
            } else {
                $error = "Chỉ cho phép tải lên ảnh hoặc video (jpg, png, gif, mp4, webm).";
            }
        }

        if (!$error) {
            $stmt_update = $conn->prepare("UPDATE subcourses SET program_id=?, name=?, media=?, age_group=?, overall_goal=?, short_description=?, lesson_count=?, blocks=?, updated_at=NOW() WHERE subcourse_id=?");
            $stmt_update->bind_param("isssssisi", $program_id, $name, $media, $age_group, $overall_goal, $short_description, $lesson_count, $blocks, $subcourse_id);
            if ($stmt_update->execute()) {
                $success = "Cập nhật subcourse thành công!";
                // Cập nhật lại dữ liệu subcourse để hiển thị trong form
                $subcourse['program_id'] = $program_id;
                $subcourse['name'] = $name;
                $subcourse['media'] = $media;
                $subcourse['age_group'] = $age_group;
                $subcourse['overall_goal'] = $overall_goal;
                $subcourse['short_description'] = $short_description;
                $subcourse['lesson_count'] = $lesson_count;
                $subcourse['blocks'] = $blocks;
            } else {
                $error = "Lỗi cập nhật: " . $conn->error;
            }
            $stmt_update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE - Sửa Subcourse</title>
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
            <?php if ($error): ?>
                <div class="message-box error"><?php echo $error; ?></div>
            <?php elseif ($success): ?>
                <div class="message-box success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <h2>Sửa khóa học</h2>

                <div class="input-group select-program">
                    <label>Chọn chương trình</label>
                    <select name="program_id" required>
                        <option value="">-- Chọn chương trình --</option>
                        <?php while ($rowP = $programsResult->fetch_assoc()): ?>
                            <option value="<?= $rowP['program_id'] ?>" <?= $rowP['program_id'] == $subcourse['program_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rowP['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Tên khóa học</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($subcourse['name']) ?>">
                </div>

                <div class="input-group">
                    <label>Media (ảnh/video giới thiệu)</label>
                    <input type="file" name="media" accept="image/*,video/*">
                    <?php if (!empty($subcourse['media'])): ?>
                        <?php 
                        $ext = pathinfo($subcourse['media'], PATHINFO_EXTENSION);
                        $filePath = '../img/subcourse/' . $subcourse['media'];
                        if (in_array(strtolower($ext), ['mp4','webm','mov','ogg'])) {
                            echo "<video width='150' controls><source src='{$filePath}'></video>";
                        } else {
                            echo "<img src='{$filePath}' width='150'>";
                        }
                        ?>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label>Nhóm tuổi</label>
                    <input type="text" name="age_group" value="<?= htmlspecialchars($subcourse['age_group']) ?>">
                </div>

                <div class="input-group">
                    <label>Mục tiêu tổng thể</label>
                    <textarea name="overall_goal" rows="4" style="width:100%; padding:8px; resize:vertical;"><?= htmlspecialchars($subcourse['overall_goal']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>Mô tả ngắn</label>
                    <textarea name="short_description" rows="4" style="width:100%; padding:8px; resize:vertical;"><?= htmlspecialchars($subcourse['short_description']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>Số bài học</label>
                    <input type="number" name="lesson_count" value="<?= $subcourse['lesson_count'] ?>" min="1">
                </div>

                <div class="input-group">
                    <label>Khối lệnh sử dụng</label>
                    <div class="blocks-options">
                        <?php
                        $blocksArr = json_decode($subcourse['blocks'], true) ?: [];
                        $allBlocks = ['icon' => 'Biểu tượng', 'text' => 'Chữ', 'python' => 'Python'];
                        foreach ($allBlocks as $value => $label):
                        ?>
                            <label class="block-item">
                                <input type="checkbox" name="blocks[]" value="<?= $value ?>" <?= in_array($value, $blocksArr) ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="subcourse_submit" class="submit-btn">LƯU THAY ĐỔI</button>
                <a href="create_subcourses.php" class="btn-back">
                    Quay lại
                </a>
            </form>
        </div>
    </div>
</div>

<?php include('../pages/footer.php'); ?>
</body>
</html>
