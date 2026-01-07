<?php
session_start();
include '../database/connect.php';

// Chỉ ADMIN sử dụng
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";
$uploadDir = '../img/lessons/';

// Lấy danh sách subcourse
$subcourseResult = $conn->query("SELECT subcourse_id, name FROM subcourses ORDER BY name ASC");

// THÊM BÀI HỌC
if (isset($_POST['lesson_submit'])) {
    $subcourse_id = intval($_POST['subcourse_id']);
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $overview = trim($_POST['overview']);
    $blocks = isset($_POST['blocks']) ? json_encode($_POST['blocks'], JSON_UNESCAPED_UNICODE) : "[]";

    if (empty($subcourse_id) || empty($title)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        $media = null;

        if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
            if (in_array($_FILES['media']['type'], $allowedTypes)) {
                $media = time() . '_' . basename($_FILES['media']['name']);
                if (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $media)) {
                    $error = "Không thể upload tập tin.";
                }
            } else {
                $error = "Chỉ được upload ảnh hoặc video.";
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("
                INSERT INTO lessons (subcourse_id, title, subtitle, overview, media, blocks, created_at)
                VALUES (?, ?, ?, ?, ?, CAST(? AS JSON), NOW())
            ");
            $stmt->bind_param("isssss", $subcourse_id, $title, $subtitle, $overview, $media, $blocks);

            if ($stmt->execute()) {
                $success = "Thêm bài học thành công!";
            } else {
                $error = "Lỗi SQL: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// XÓA BÀI HỌC
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("SELECT media FROM lessons WHERE lesson_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($mediaFile);
    $stmt->fetch();
    $stmt->close();

    if (!empty($mediaFile) && file_exists($uploadDir . $mediaFile)) {
        unlink($uploadDir . $mediaFile);
    }

    $conn->query("DELETE FROM lessons WHERE lesson_id = $id");

    header("Location: create_lessons.php");
    exit;
}

// LẤY DANH SÁCH BÀI HỌC
$sql = "SELECT l.*, s.name AS subcourse_name 
        FROM lessons l 
        LEFT JOIN subcourses s ON l.subcourse_id = s.subcourse_id
        ORDER BY l.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>LET'S CODE - Quản lý bài học</title>
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
                    <div class="message-box error"><?= $error ?></div>
                <?php elseif ($success): ?>
                    <div class="message-box success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <h2>Tạo bài học</h2>

                    <div class="input-group select-program">
                        <label>Chọn khóa học</label>
                        <select name="subcourse_id" required>
                            <option value="">-- Chọn khóa học --</option>
                            <?php while ($rowS = $subcourseResult->fetch_assoc()): ?>
                                <option value="<?= $rowS['subcourse_id'] ?>">
                                    <?= htmlspecialchars($rowS['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tiêu đề bài học</label>
                        <input type="text" name="title" required>
                    </div>

                    <div class="input-group">
                        <label>Phụ đề</label>
                        <input type="text" name="subtitle">
                    </div>

                    <div class="input-group">
                        <label>Mô tả bài học</label>
                        <textarea name="overview" rows="6" style="width:100%; resize:vertical;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Media (ảnh/video)</label>
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

                    <button type="submit" name="lesson_submit" class="submit-btn">TẠO BÀI HỌC</button>
                </form>
            </div>
        </div>

        <div class="container-table">
            <h2>Danh sách bài học</h2>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Subcourse</th>
                        <th>Tiêu đề</th>
                        <th>Media</th>
                        <th>Blocks</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['subcourse_name']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>

                            <td>
                                <?php 
                                    if (!empty($row['media'])) {
                                        $ext = pathinfo($row['media'], PATHINFO_EXTENSION);
                                        $file = '../img/lessons/' . $row['media'];
                                        if (in_array(strtolower($ext), ['mp4', 'webm', 'ogg'])) {
                                            echo "<video width='120' controls><source src='$file'></video>";
                                        } else {
                                            echo "<img src='$file' width='120'>";
                                        }
                                    }
                                ?>
                            </td>

                            <td>
                                <?php 
                                    $blocksArr = json_decode($row['blocks'], true);
                                    if ($blocksArr) echo implode(", ", $blocksArr);
                                ?>
                            </td>

                            <td><?= $row['created_at'] ?></td>

                            <td>
                                <div class="action-buttons">
                                    <a href="edit_lessons.php?id=<?= $row['lesson_id'] ?>" class="btn-edit">Sửa</a>
                                    <a href="create_lessons.php?delete=<?= $row['lesson_id'] ?>" class="btn-delete"
                                       onclick="return confirm('Xóa bài học này?');">Xóa</a>
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