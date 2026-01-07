<?php
session_start();

include '../database/connect.php'; 

$error = "";
$success = "";

// Nếu đã đăng nhập → chuyển trang
if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$active_tab = 'login';

if (isset($_POST['register_submit'])) {
    $active_tab = 'register';

    $username = trim($_POST['reg-username']);
    $email    = trim($_POST['reg-email']);
    $password = $_POST['reg-password'];
    $confirm  = $_POST['reg-confirm-password'];

    // Kiểm tra dữ liệu
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = "Vui lòng điền đầy đủ tất cả các trường.";
    } elseif ($password !== $confirm) {
        $error = "Xác nhận mật khẩu không khớp.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } else {
        // Kiểm tra Email hoặc Username đã tồn tại
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Tên người dùng hoặc Email đã tồn tại.";
        } else {
            // Tạo tài khoản
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role = 2; // Mặc định = USER (thay đổi nếu cần)

            $stmt_insert = $conn->prepare("INSERT INTO users (username, email, hashed_password, role) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $username, $email, $hashed, $role);

            if ($stmt_insert->execute()) {
                $success = "Đăng ký thành công! Hãy đăng nhập.";
                $active_tab = 'login';
            } else {
                $error = "Lỗi đăng ký: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

if (isset($_POST['login_submit'])) {
    $active_tab = 'login';

    $identifier = trim($_POST['login-identifier']); 
    $password   = $_POST['login-password'];

    if (empty($identifier) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    } else {
        // Tìm user
        $stmt = $conn->prepare("SELECT user_id, username, hashed_password, role FROM users 
                                WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['hashed_password'])) {
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                header("Location: homepage.php");
                exit();
            } else {
                $error = "Mật khẩu không chính xác.";
            }
        } else {
            $error = "Tên người dùng hoặc Email không tồn tại.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE - Đăng ký & Đăng nhập</title>
    <link rel="stylesheet" href="../css/login.css"> 

    <style>
        /* CSS cho Thông báo */
        .message-box { 
            padding: 12px; 
            margin: 10px 0; 
            border-radius: 6px; 
            font-weight: bold; 
            text-align: center;
        }
        .success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .error { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        /* Đảm bảo tab được chọn vẫn sáng sau khi submit */
        .form-content { display: none; }
        .form-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-area">
            <img src="../img/logo/z4731633667117_454f1a250e4c2b0b1c5abfa4b5c264ba-removebg-preview.png" alt="Let's Code Logo" class="logo">
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="message-box error"><?php echo $error; ?></div>
            <?php elseif ($success): ?>
                <div class="message-box success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-button <?php echo $active_tab == 'login' ? 'active' : ''; ?>" onclick="showTab('login')">Đăng nhập</button>
                <button class="tab-button <?php echo $active_tab == 'register' ? 'active' : ''; ?>" onclick="showTab('register')">Đăng ký</button>
            </div>

            <form id="login" class="form-content <?php echo $active_tab == 'login' ? 'active' : ''; ?>" method="POST" action="">
                <h2>Đăng nhập</h2>
                <div class="input-group">
                    <label for="login-identifier">Email hoặc Tên người dùng</label>
                    <input type="text" id="login-identifier" name="login-identifier" required>
                </div>
                <div class="input-group">
                    <label for="login-password">Mật khẩu</label>
                    <input type="password" id="login-password" name="login-password" required>
                </div>
                <button type="submit" name="login_submit" class="submit-btn">ĐĂNG NHẬP</button>
            </form>

            <form id="register" class="form-content <?php echo $active_tab == 'register' ? 'active' : ''; ?>" method="POST" action="">
                <h2>Đăng ký tài khoản mới</h2>
                <div class="input-group">
                    <label for="reg-username">Tên người dùng</label>
                    <input type="text" id="reg-username" name="reg-username" required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
                <div class="input-group">
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="reg-email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                </div>
                <div class="input-group">
                    <label for="reg-password">Mật khẩu</label>
                    <input type="password" id="reg-password" name="reg-password" required>
                </div>
                <div class="input-group">
                    <label for="reg-confirm-password">Xác nhận Mật khẩu</label>
                    <input type="password" id="reg-confirm-password" name="reg-confirm-password" required>
                </div>
                <button type="submit" name="register_submit" class="submit-btn">TẠO TÀI KHOẢN</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // Cập nhật trạng thái của các nút
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelector(`.tab-button[onclick*="'${tabId}'"]`).classList.add('active');

            // Cập nhật hiển thị của các form
            document.querySelectorAll('.form-content').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        }
        
        // Đảm bảo tab đúng được hiển thị khi trang tải lần đầu hoặc sau khi submit form
        showTab('<?php echo $active_tab; ?>');
    </script>
</body>
</html>