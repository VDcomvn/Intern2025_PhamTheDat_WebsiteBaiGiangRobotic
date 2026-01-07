<?php
session_start();
include '../database/connect.php';

/* ================== CHỈ ADMIN ================== */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

/* ================== KIỂM TRA ID ================== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID người dùng không hợp lệ");
}

$user_id = intval($_GET['id']);
$error = "";
$success = "";

/* ================== LẤY USER ================== */
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Không tìm thấy người dùng.");
}

$stmt->bind_result($username, $email, $role);
$stmt->fetch();
$stmt->close();

/* ================== SUBMIT ================== */
if (isset($_POST['save_user'])) {

    $new_username = trim($_POST['username']);
    $new_email    = trim($_POST['email']);
    $new_role     = $_POST['role'];

    if (empty($new_username) || empty($new_email)) {
        $error = "Không được để trống các trường bắt buộc.";
    } else {

        // Check trùng
        $stmt_check = $conn->prepare("
            SELECT user_id FROM users 
            WHERE (username=? OR email=?) AND user_id!=?
        ");
        $stmt_check->bind_param("ssi", $new_username, $new_email, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Tên người dùng hoặc Email đã tồn tại!";
        } else {

            // Đổi mật khẩu (nếu có)
            if (!empty($_POST['password'])) {
                if ($_POST['password'] !== $_POST['confirm']) {
                    $error = "Xác nhận mật khẩu không khớp!";
                } else {
                    $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);

                    $stmt_update = $conn->prepare("
                        UPDATE users 
                        SET username=?, email=?, role=?, hashed_password=? 
                        WHERE user_id=?
                    ");
                    $stmt_update->bind_param(
                        "ssssi",
                        $new_username,
                        $new_email,
                        $new_role,
                        $hashed,
                        $user_id
                    );
                }
            } else {
                $stmt_update = $conn->prepare("
                    UPDATE users 
                    SET username=?, email=?, role=? 
                    WHERE user_id=?
                ");
                $stmt_update->bind_param(
                    "sssi",
                    $new_username,
                    $new_email,
                    $new_role,
                    $user_id
                );
            }

            if (empty($error) && $stmt_update->execute()) {

                /* ================== LƯU QUYỀN SUBCOURSE ================== */
                if ($new_role === 'teacher' && !empty($_POST['current_program_id'])) {

                    $current_program_id = intval($_POST['current_program_id']);

                    /* ===== XÓA QUYỀN CHỈ TRONG CHƯƠNG TRÌNH ĐANG CHỌN ===== */
                    $stmt_del = $conn->prepare("
                        DELETE p FROM teacher_subcourse_permission p
                        INNER JOIN subcourses s ON p.subcourse_id = s.subcourse_id
                        WHERE p.user_id = ? AND s.program_id = ?
                    ");
                    $stmt_del->bind_param("ii", $user_id, $current_program_id);
                    $stmt_del->execute();
                    $stmt_del->close();

                    /* ===== THÊM QUYỀN MỚI ===== */
                    if (!empty($_POST['subcourses'])) {
                        foreach ($_POST['subcourses'] as $subcourse_id => $val) {
                            $stmt_ins = $conn->prepare("
                                INSERT INTO teacher_subcourse_permission
                                (user_id, subcourse_id, is_allowed)
                                VALUES (?, ?, 1)
                            ");
                            $stmt_ins->bind_param("ii", $user_id, $subcourse_id);
                            $stmt_ins->execute();
                            $stmt_ins->close();
                        }
                    }
                }

                $success = "Cập nhật tài khoản thành công!";
            } else if (empty($error)) {
                $error = "Lỗi cập nhật dữ liệu!";
            }

            if (isset($stmt_update)) $stmt_update->close();
        }

        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa tài khoản</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">

    <style>
        .course-permission {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .course-item {
            background: #f7f7f7;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        .course-item input {
            margin-right: 6px;
        }
        .subcourse-list {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            max-height: 200px;
            overflow-y: auto;
        }

        .subcourse-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 6px;
            border-bottom: 1px dashed #eee;
        }

        .subcourse-item:last-child {
            border-bottom: none;
        }

        .subcourse-item span {
            font-size: 25px;
        }

        /* ===== Checkbox gọn hơn ===== */
        .subcourse-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-left: 10px;
            flex-shrink: 0;
            cursor: pointer;
        }


    </style>
</head>

<body>

<?php include('../pages/header.php'); ?>

<div class="user-page">
    <div class="container">

        <div class="form-container">
            <h2>Sửa tài khoản</h2>

            <?php if ($error): ?>
                <div class="message-box error"><?= $error ?></div>
            <?php elseif ($success): ?>
                <div class="message-box success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="input-group">
                    <label>Tên người dùng</label>
                    <input type="text" name="username" required
                        value="<?= htmlspecialchars($username) ?>">
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" required
                        value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="input-group">
                    <label>Phân quyền</label>
                    <div class="role-options">
                        <label class="role-item">
                            <input type="radio" name="role" value="admin"
                                <?= $role == 'admin' ? 'checked' : '' ?>>
                            <span>Admin</span>
                        </label>

                        <label class="role-item">
                            <input type="radio" name="role" value="teacher"
                                <?= $role == 'teacher' ? 'checked' : '' ?>>
                            <span>Giáo viên</span>
                        </label>
                    </div>
                </div>

                <?php if ($role === 'teacher'): ?>
                <div class="input-group select-program">
                    <label>Chọn chương trình</label>
                    <select id="programSelect">
                        <option value="">-- Chọn chương trình --</option>
                        <?php
                        $programs = $conn->query("SELECT program_id, name FROM programs");
                        while ($p = $programs->fetch_assoc()):
                        ?>
                            <option value="<?= $p['program_id'] ?>">
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <input type="hidden" name="current_program_id" id="currentProgramInput">
                </div>

                <div class="input-group">
                    <label>Khóa học trong chương trình</label>

                    <div id="subcourseList" class="subcourse-list">
                        <p style="color:#888;">Vui lòng chọn chương trình</p>
                    </div>
                </div>

                <?php endif; ?>

                <div class="input-group">
                    <label>Mật khẩu mới (không bắt buộc)</label>
                    <input type="password" name="password">
                </div>

                <div class="input-group">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="confirm">
                </div>

                <button type="submit" name="save_user" class="submit-btn">
                    LƯU THAY ĐỔI
                </button>

                <a href="user.php" class="btn-back">Quay lại</a>

            </form>
        </div>

    </div>
</div>

<script>
const programSelect = document.getElementById('programSelect');
const programInput  = document.getElementById('currentProgramInput');
const subcourseList = document.getElementById('subcourseList');

if (programSelect) {
    programSelect.addEventListener('change', function () {
        const programId = this.value;

        // gán program_id để submit
        programInput.value = programId;

        if (!programId) {
            subcourseList.innerHTML =
                '<p style="color:#888;">Vui lòng chọn chương trình</p>';
            return;
        }

        fetch(
            `ajax_get_subcourses.php?program_id=${programId}&user_id=<?= $user_id ?>`
        )
            .then(res => res.text())
            .then(html => {
                subcourseList.innerHTML = html;
            })
            .catch(() => {
                subcourseList.innerHTML =
                    '<p style="color:red;">Lỗi tải khóa học</p>';
            });
    });
}
</script>

<?php include('../pages/footer.php'); ?>


</body>
</html>
