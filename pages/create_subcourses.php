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
$uploadDir = '../img/subcourse/';

// Lấy danh sách chương trình để chọn
$programsResult = $conn->query("SELECT program_id, name FROM programs ORDER BY name ASC");

// XỬ LÝ THÊM SUBCOURSE
if (isset($_POST['subcourse_submit'])) {
    $program_id = intval($_POST['program_id']);
    $name = trim($_POST['name']);
    $age_group = trim($_POST['age_group']);
    $overall_goal = trim($_POST['overall_goal']);
    $short_description = trim($_POST['short_description']);
    $lesson_count = intval($_POST['lesson_count']);
    $blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks'], JSON_UNESCAPED_UNICODE) : '[]';

    // Kiểm tra thông tin bắt buộc
    if (empty($program_id) || empty($name)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        $media = null;
        if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
            if (in_array($_FILES['media']['type'], $allowedTypes)) {
                $media = time() . '_' . basename($_FILES['media']['name']);
                if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $media)) {
                    $error = "Lỗi khi tải file lên server.";
                }
            } else {
                $error = "Chỉ cho phép tải lên ảnh hoặc video (jpg, png, gif, mp4, webm).";
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("
                INSERT INTO subcourses 
                (program_id, name, media, age_group, overall_goal, short_description, lesson_count, blocks, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CAST(? AS JSON), NOW())
            ");
            $stmt->bind_param("isssssis", $program_id, $name, $media, $age_group, $overall_goal, $short_description, $lesson_count, $blocks);
            
            if ($stmt->execute()) {
                $success = "Thêm subcourse thành công!";
            } else {
                $error = "Lỗi thêm dữ liệu: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// XÓA SUBCOURSE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("SELECT media FROM subcourses WHERE subcourse_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($mediaFile);
    $stmt->fetch();
    $stmt->close();

    if (!empty($mediaFile) && file_exists($uploadDir . $mediaFile)) {
        unlink($uploadDir . $mediaFile);
    }

    $stmt = $conn->prepare("DELETE FROM subcourses WHERE subcourse_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: create_subcourses.php");
    exit;
}

// LẤY DANH SÁCH SUBCOURSE
$sql = "SELECT s.*, p.name AS program_name 
        FROM subcourses s 
        LEFT JOIN programs p ON s.program_id = p.program_id 
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE - Quản lý Subcourse</title>
    <link rel="stylesheet" href="../css/user.css"> 
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">
    <style>
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>
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
                    <h2>Tạo khóa học</h2>

                    <div class="input-group select-program">
                        <label>Chọn chương trình</label>
                        <select name="program_id" required>
                            <option value="">-- Chọn chương trình --</option>
                            <?php while ($rowP = $programsResult->fetch_assoc()): ?>
                                <option value="<?= $rowP['program_id'] ?>"><?= htmlspecialchars($rowP['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tên khóa học</label>
                        <input type="text" name="name" required>
                    </div>

                    <div class="input-group">
                        <label>Media (ảnh/video giới thiệu)</label>
                        <input type="file" name="media" accept="image/*,video/*">
                    </div>

                    <div class="input-group">
                        <label>Nhóm tuổi</label>
                        <input type="text" name="age_group">
                    </div>

                    <div class="input-group">
                        <label>Mục tiêu tổng thể</label>
                        <textarea name="overall_goal" rows="4" style="width:100%; resize:vertical;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Mô tả ngắn</label>
                        <textarea name="short_description" rows="4" style="width:100%; resize:vertical;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Số bài học</label>
                        <input type="number" name="lesson_count" value="1" min="1">
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

                    <button type="submit" name="subcourse_submit" class="submit-btn">TẠO khóa học</button>
                </form>
            </div>
        </div>

        <div class="container-table">
            <h2>Danh sách khóa học</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Chương trình</th>
                        <th>Tên</th>
                        <th>Media</th>
                        <th>Nhóm tuổi</th>
                        <th>Bài học</th>
                        <th>Blocks</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['program_name']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <?php 
                                if (!empty($row['media'])) {
                                    $ext = pathinfo($row['media'], PATHINFO_EXTENSION);
                                    $filePath = '../img/subcourse/' . $row['media'];
                                    if (in_array(strtolower($ext), ['mp4', 'webm', 'mov', 'ogg'])) {
                                        echo "<video width='150' controls><source src='{$filePath}'></video>";
                                    } else {
                                        echo "<img src='{$filePath}' width='150'>";
                                    }
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['age_group']) ?></td>
                            <td><?= $row['lesson_count'] ?></td>
                            <td>
                                <?php
                                $blocksArr = json_decode($row['blocks'], true);
                                if ($blocksArr) {
                                    echo implode(', ', $blocksArr);
                                }
                                ?>
                            </td>
                            <td><?= $row['created_at'] ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_subcourses.php?id=<?= $row['subcourse_id'] ?>" class="btn-edit">Sửa</a>
                                    <a href="create_subcourses.php?delete=<?= $row['subcourse_id'] ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Xóa subcourse này?');">Xóa</a>
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