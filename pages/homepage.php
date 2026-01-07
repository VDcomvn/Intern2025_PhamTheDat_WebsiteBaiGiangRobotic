<?php
session_start();
include '../database/connect.php';

// Lấy danh sách chương trình
$sql = "SELECT * FROM programs ORDER BY created_at ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slideshow</title>
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/homepage.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="../css/header.css">
</head>

<body>

<?php include('../pages/header.php'); ?>

    <div class="slider">
        <div class="slide active" style="background-image: url('../img/LEGO Education/lego-education-spike-prime.jpg');"></div>
        <div class="slide" style="background-image: url('../img/LEGO Education/45680_prod_spike_prime_expansion_competition_ready_timeforanupgrade_01-6cfb5e8f165295e2beaeb7df9e69ab82.png');"></div>
        <div class="slide" style="background-image: url('../img/LEGO Education/spike-essential.jpg');"></div>
        <div class="slide" style="background-image: url('../img/LEGO Education/spike-essential (1).jpg');"></div>

        <div class="slider-text">
            CHÀO MỪNG ĐẾN VỚI THẾ GIỚI ROBOTICS CÙNG<br>
            <img src="../img/logo/z4731633710147_b8c7aee20afa54bd5d8be2decf7dd3d4-removebg-preview.png" alt="">
        </div>
    </div>

    <div class="course-section">
        <h2>Những mô hình nổi bật</h2>

        <div class="course-row">
            <div class="course-card">
                <img src="../img/LEGO Education/lesson-header.webp" alt="">
                <div class="course-content">
                    <h3>Cánh tay Robot</h3>
                    <p>SPKIE Prime</p>
                </div>
            </div>

            <div class="course-card">
                <img src="../img/LEGO Education/OIP.jfif" alt="">
                <div class="course-content">
                    <h3>Robot</h3>
                    <p>SPKIE Prime</p>
                </div>
            </div>
            
            <div class="course-card">
                <img src="../img/LEGO Education/45680_prod_spike_prime_expansion_competition_ready_timeforanupgrade_01-6cfb5e8f165295e2beaeb7df9e69ab82.png" alt="">
                <div class="course-content">
                    <h3>Xe lu</h3>
                    <p>SPKIE Prime</p>
                </div>
            </div>
        </div>
    </div>

    <div class="course-section">
        <h2>Mục tiêu khóa học</h2>
    </div>

    <div class="course-intro">
        <div class="intro-box">
            <i class="fa-solid fa-robot"></i>
            <h3>Học lập trình qua Robot</h3>
            <p>Giúp học sinh tư duy logic và hiểu các nguyên tắc lập trình thông qua dự án thực tế.</p>
        </div>

        <div class="intro-box">
            <i class="fa-solid fa-gear"></i>
            <h3>Kỹ năng lắp ráp</h3>
            <p>Rèn luyện khả năng sáng tạo, thiết kế và lắp ghép mô hình từ bộ LEGO SPIKE.</p>
        </div>

        <div class="intro-box">
            <i class="fa-solid fa-lightbulb"></i>
            <h3>Kích thích sáng tạo</h3>
            <p>Khơi gợi đam mê STEM, khám phá khoa học – công nghệ – kỹ thuật – toán học.</p>
        </div>

        <div class="intro-box">
            <i class="fa-solid fa-people-group"></i>
            <h3>Làm việc nhóm</h3>
            <p>Phát triển kỹ năng giao tiếp, hợp tác và giải quyết vấn đề trong nhóm.</p>
        </div>
    </div>

    <div class="course-section">
        <h2>Chương trình học</h2>
        <div class="course-row">
            <?php while($row = $result->fetch_assoc()): ?>
                <a href="program.php?id=<?php echo $row['program_id']; ?>" class="card-link">
                    <div class="course-card">
                        <?php if(!empty($row['media'])): ?>
                            <?php 
                            $ext = pathinfo($row['media'], PATHINFO_EXTENSION);
                            $filePath = '../img/program/' . $row['media'];
                            if(in_array(strtolower($ext), ['mp4','webm','mov','ogg'])): ?>
                                <video width="250" controls>
                                    <source src="<?php echo $filePath; ?>">
                                </video>
                            <?php else: ?>
                                <img src="<?php echo $filePath; ?>" alt="" width="250">
                            <?php endif; ?>
                        <?php else: ?>
                            <img src="../img/default-course.jpg" alt="" width="250">
                        <?php endif; ?>
                        <div class="course-content">
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p><?php echo htmlspecialchars($row['short_description']); ?></p>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

<?php include('../pages/footer.php'); ?>

<script>
    let index = 0;
    const slides = document.querySelectorAll('.slide');

    function changeSlide() {
        slides[index].classList.remove('active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('active');
    }

    setInterval(changeSlide, 3000); 
</script>

</body>
</html>
