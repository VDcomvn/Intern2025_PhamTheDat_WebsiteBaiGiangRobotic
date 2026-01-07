<?php
session_start();
include '../database/connect.php';

$error = "";
$success = "";

// Kiểm tra login
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

if (isset($_POST['register_submit'])) {

    $username = trim($_POST['reg-username']);
    $email    = trim($_POST['reg-email']);
    $password = $_POST['reg-password'];
    $confirm  = $_POST['reg-confirm-password'];
    $role     = isset($_POST['reg-role']) ? $_POST['reg-role'] : null; // KHÔNG BAO GIỜ LỖI

    // Kiểm tra dữ liệu
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = "Vui lòng điền đầy đủ tất cả các trường.";
    } elseif ($password !== $confirm) {
        $error = "Xác nhận mật khẩu không khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif ($role === null) {
        $error = "Vui lòng chọn quyền tài khoản.";
    } else {

        // Kiểm tra Email hoặc Username đã tồn tại
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Tên người dùng hoặc Email đã tồn tại.";
        } else {

            // Tạo tài khoản mới
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt_insert = $conn->prepare("
                INSERT INTO users (username, email, hashed_password, role) 
                VALUES (?, ?, ?, ?)
            ");

            $stmt_insert->bind_param("ssss", $username, $email, $hashed, $role);

            if ($stmt_insert->execute()) {
                $success = "Đăng ký thành công!";
            } else {
                $error = "Lỗi đăng ký: " . $conn->error;
            }

            $stmt_insert->close();
        }

        $stmt_check->close();
    }
}

// XÓA TÀI KHOẢN
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt_del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt_del->bind_param("i", $id);
    $stmt_del->execute();
    $stmt_del->close();
    header("Location: user.php");
    exit;
}

// LẤY DANH SÁCH USER
$sql = "SELECT user_id, username, email, role, created_at FROM users ORDER BY user_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE - Đăng ký tài khoản</title>
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

                <form method="POST" action="">
                    <h2>Tạo tài khoản</h2>

                    <div class="input-group">
                        <label for="reg-username">Tên người dùng</label>
                        <input type="text" id="reg-username" name="reg-username" 
                            required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label for="reg-email">Email</label>
                        <input type="email" id="reg-email" name="reg-email" 
                            required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                    </div>

                    <div class="input-group">
                        <label for="reg-password">Mật khẩu</label>
                        <input type="password" id="reg-password" name="reg-password" required>
                    </div>

                    <div class="input-group">
                        <label for="reg-confirm-password">Xác nhận mật khẩu</label>
                        <input type="password" id="reg-confirm-password" name="reg-confirm-password" required>
                    </div>

                    <div class="input-group">
                        <label>Phân quyền</label>

                        <div class="role-options">
                            <label class="role-item">
                                <input type="radio" name="reg-role" value="admin" required>
                                <span>Admin</span>
                            </label>

                            <label class="role-item">
                                <input type="radio" name="reg-role" value="teacher">
                                <span>Giáo viên</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="register_submit" class="submit-btn">TẠO TÀI KHOẢN</button>
                </form>

            </div>
        </div>

        <div class="container-table">

            <h2>Danh sách tài khoản</h2>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>Tên người dùng</th>
                        <th>Email</th>
                        <th>Quyền</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <?php 
                                    echo $row['role'] === "admin" 
                                        ? "<span class='role-admin'>Admin</span>"
                                        : "<span class='role-teacher'>Giáo viên</span>";
                                ?>
                            </td>
                            <td><?php echo $row['created_at']; ?></td>

                            <td>
                                <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn-edit">Sửa</a>

                                <a href="user.php?delete=<?php echo $row['user_id']; ?>" 
                                class="btn-delete"
                                onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?');">
                                Xóa
                                </a>
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
