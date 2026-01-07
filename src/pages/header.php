<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LET'S CODE</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css">
</head>

<body>
    <header>
        <a href="../pages/homepage.php">
            <img src="../img/logo/z4731633710147_b8c7aee20afa54bd5d8be2decf7dd3d4-removebg-preview.png" class="logo">
        </a>

        <i class="fa-solid fa-bars hamburger" onclick="toggleMenu()"></i>

        <nav class="menu" id="menu">
            <a href="../pages/program.php?id=18">Essential</a>
            <a href="../pages/program.php?id=19">Prime</a>

            <?php if(isset($_SESSION['username'])): ?>
                
                <div class="user-menu">
                    <span class="username" onclick="toggleUserMenu()">
                        <?php echo htmlspecialchars($_SESSION['username']); ?> 
                    </span>
                    
                    <div class="user-dropdown" id="userDropdown">
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="../pages/create_program.php">Chương trình</a>
                            <a href="../pages/create_subcourses.php">Khóa học</a>
                            <a href="../pages/create_lessons.php">Bài học</a>
                            <a href="../pages/lesson.php">Chi tiết</a>
                            <a href="../pages/user.php">Người dùng</a>
                        <?php endif; ?>

                        <a href="../pages/logout.php">Đăng xuất</a>
                    </div>
                </div>

            <?php else: ?>
                <a href="../pages/login.php">Đăng nhập</a>
            <?php endif; ?>

        </nav>
    </header>

    <script>
        function toggleMenu() {
            document.getElementById("menu").classList.toggle("show");
        }

        function toggleUserMenu() {
            document.getElementById("userDropdown").classList.toggle("show");
        }

        // Tự tắt dropdown khi click ra ngoài
        document.addEventListener("click", function(event) {
            let dropdown = document.getElementById("userDropdown");
            let username = document.querySelector(".username");

            if (dropdown && !dropdown.contains(event.target) && !username.contains(event.target)) {
                dropdown.classList.remove("show");
            }
        });
    </script>
</body>
</html>
