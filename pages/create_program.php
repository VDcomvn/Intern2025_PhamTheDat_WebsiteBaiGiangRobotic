<?php
session_start();
include '../database/connect.php';

// Chỉ cho ADMIN truy cập
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

// Thư mục lưu file upload
$uploadDir = '../img/program/';

// XỬ LÝ THÊM CHƯƠNG TRÌNH
if (isset($_POST['program_submit'])) {

    $name = trim($_POST['name']);
    $short_description = trim($_POST['short_description']);
    $blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks']) : null;

    // Kiểm tra thông tin bắt buộc
    if (empty($name) || empty($blocks)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        // Xử lý file media
        $media = null;
        if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
            $allowedTypes = ['image/jpeg','image/png','image/gif','video/mp4','video/webm','video/ogg'];
            if (in_array($_FILES['media']['type'], $allowedTypes)) {
                $media = time() . '_' . basename($_FILES['media']['name']);
                if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $media)) {
                    $error = "Lỗi khi tải file lên server.";
                }
            } else {
                $error = "Chỉ cho phép tải lên ảnh hoặc video (jpg, png, gif, mp4, webm).";
            }
        }

        // Nếu không lỗi thì insert vào database
        if (!$error) {
            $stmt = $conn->prepare("INSERT INTO programs (name, short_description, media, blocks, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $short_description, $media, $blocks);
            if ($stmt->execute()) {
                $success = "Thêm chương trình học thành công!";
            } else {
                $error = "Lỗi thêm dữ liệu: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// XÓA CHƯƠNG TRÌNH
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Lấy file media để xóa
    $stmt = $conn->prepare("SELECT media FROM programs WHERE program_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($mediaFile);
    $stmt->fetch();
    $stmt->close();

    if (!empty($mediaFile) && file_exists($uploadDir . $mediaFile)) {
        unlink($uploadDir . $mediaFile);
    }

    $stmt = $conn->prepare("DELETE FROM programs WHERE program_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: create_program.php");
    exit;
}

// LẤY DANH SÁCH CHƯƠNG TRÌNH
$sql = "SELECT * FROM programs ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE - Quản lý chương trình học</title>
    <link rel="stylesheet" href="../css/user.css"> 
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
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

                <h2>Tạo chương trình học</h2>

                <div class="input-group">
                    <label>Tên chương trình</label>
                    <input type="text" name="name" required>
                </div>

                <div class="input-group">
                    <label>Mô tả</label>
                    <textarea name="short_description" rows="6" style="width:100%; resize:vertical;"></textarea>
                </div>

                <div class="input-group">
                    <label>Media (ảnh/video giới thiệu)</label>
                    <input type="file" name="media" accept="image/*,video/*">
                </div>

                <div class="input-group">
                    <label>Khối lệnh sử dụng</label>
                    <div class="blocks-options">
                        <label class="block-item">
                            <input type="checkbox" name="blocks[]" value="icon">
                            <span>Biểu tượng</span>
                        </label>
                        <label class="block-item">
                            <input type="checkbox" name="blocks[]" value="text">
                            <span>Chữ</span>
                        </label>
                        <label class="block-item">
                            <input type="checkbox" name="blocks[]" value="python">
                            <span>Python</span>
                        </label>
                    </div>
                </div>

                <button type="submit" name="program_submit" class="submit-btn">TẠO CHƯƠNG TRÌNH</button>

            </form>

        </div>
    </div>

    <div class="container-table">

        <h2>Danh sách chương trình</h2>

        <table class="user-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Mô tả ngắn</th>
                    <th>Media</th>
                    <th>Blocks</th>
                    <th>Ngày tạo</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td style="text-align:left; max-width:300px;"><?php echo nl2br(htmlspecialchars($row['short_description'])); ?></td>
                        <td>
                            <?php 
                            if (!empty($row['media'])) {
                                $ext = pathinfo($row['media'], PATHINFO_EXTENSION);
                                $filePath = '../img/program/' . $row['media'];
                                if (in_array(strtolower($ext), ['mp4','webm','mov','ogg'])) {
                                    echo "<video width='150' controls><source src='{$filePath}'></video>";
                                } else {
                                    echo "<img src='{$filePath}' width='150'>";
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                                $blocksArr = json_decode($row['blocks'], true);
                                if ($blocksArr) {
                                    echo implode(', ', $blocksArr);
                                }
                            ?>
                        </td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="edit_program.php?id=<?php echo $row['program_id']; ?>" class="btn-edit">Sửa</a>
                                <a href="create_program.php?delete=<?php echo $row['program_id']; ?>" class="btn-delete" onclick="return confirm('Xóa chương trình này?');">Xóa</a>
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
