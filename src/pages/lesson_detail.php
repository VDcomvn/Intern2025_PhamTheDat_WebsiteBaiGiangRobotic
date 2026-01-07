<?php 
session_start();
include '../database/connect.php';

// LẤY ID BÀI HỌC TỪ URL
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lesson_id === 0) {
    die("Bài học không hợp lệ hoặc không tìm thấy!");
}

// Thông tin bài học & Mục tiêu
$stmt = $conn->prepare("
    SELECT l.title, l.subtitle, lo.knowledge_objective, lo.thinking_objective, lo.skills_objective, lo.attitude_objective 
    FROM lessons l
    LEFT JOIN lesson_objectives lo ON l.lesson_id = lo.lesson_id
    WHERE l.lesson_id = ?
");
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$lessonData = $stmt->get_result()->fetch_assoc();

// Mô hình & Media mô hình
$modelQ = $conn->query("SELECT * FROM lesson_models WHERE lesson_id = $lesson_id LIMIT 1");
$model = $modelQ->fetch_assoc();
$model_media = [];
if ($model) {
    $resM = $conn->query("SELECT m.url, m.mime_type FROM lesson_model_media lmm JOIN media m ON lmm.media_id = m.media_id WHERE lmm.model_id = {$model['model_id']}");
    while($m = $resM->fetch_assoc()) $model_media[] = $m;
}

// Chuẩn bị (Media linh kiện)
$prep_media = [];
$resP = $conn->query("
    SELECT m.url, m.mime_type FROM lesson_preparation_media lpm 
    JOIN lesson_preparations lp ON lpm.preparation_id = lp.preparation_id
    JOIN media m ON lpm.media_id = m.media_id 
    WHERE lp.lesson_id = $lesson_id
");
while($m = $resP->fetch_assoc()) $prep_media[] = $m;

// Xây dựng (Slider Media)
$build_media = [];
$resB = $conn->query("
    SELECT m.url, m.mime_type FROM lesson_build_media lbm 
    JOIN lesson_builds lb ON lbm.build_id = lb.build_id
    JOIN media m ON lbm.media_id = m.media_id 
    WHERE lb.lesson_id = $lesson_id
");
while($m = $resB->fetch_assoc()) $build_media[] = $m;

// Khối nội dung bài học & Tệp đính kèm
$blocksQ = $conn->query("SELECT * FROM lesson_content_blocks WHERE lesson_id = $lesson_id ORDER BY block_id ASC");
$contentBlocks = [];
while($b = $blocksQ->fetch_assoc()) {
    $bmQ = $conn->query("SELECT m.url, m.mime_type FROM lesson_content_block_media lbm JOIN media m ON lbm.media_id = m.media_id WHERE lbm.block_id = {$b['block_id']}");
    $b['media_list'] = [];
    while($m = $bmQ->fetch_assoc()) $b['media_list'][] = $m;
    
    $attQ = $conn->query("SELECT m.url FROM lesson_attachments la JOIN media m ON la.media_id = m.media_id WHERE la.lesson_id = $lesson_id");
    $b['attachments'] = [];
    while($a = $attQ->fetch_assoc()) $b['attachments'][] = $a;

    $contentBlocks[] = $b;
}

// Thử thách
$challenge = $conn->query("SELECT * FROM lesson_challenges WHERE lesson_id = $lesson_id LIMIT 1")->fetch_assoc();
$challenge_media = [];
if ($challenge) {
    $cmQ = $conn->query("SELECT m.url, m.mime_type FROM lesson_challenge_media lcm JOIN media m ON lcm.media_id = m.media_id WHERE lcm.challenge_id = {$challenge['challenge_id']}");
    while($m = $cmQ->fetch_assoc()) $challenge_media[] = $m;
}

// Câu hỏi Tổng kết
$quizzes = [];
$resQuiz = $conn->query("SELECT * FROM lesson_quizzes WHERE lesson_id = $lesson_id ORDER BY quiz_id ASC");
while ($q = $resQuiz->fetch_assoc()) {
    $resAns = $conn->query("SELECT * FROM quiz_answers WHERE quiz_id = {$q['quiz_id']} ORDER BY answer_id ASC");
    $q['answers'] = [];
    while ($a = $resAns->fetch_assoc()) $q['answers'][] = $a;
    $quizzes[] = $q;
}
$total_questions = count($quizzes);

include('../pages/header.php');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($lessonData['title'] ?? 'Chi tiết bài học') ?></title>
    <link rel="stylesheet" href="../css/lesson_details.css">
</head>
<body>

    <section id="buoi">
        <h1><?= htmlspecialchars($lessonData['title'] ?? '') ?></h1>
        <h2><?= htmlspecialchars($lessonData['subtitle'] ?? '') ?></h2>
    </section>

    <div class="lesson-wrapper">
        <aside class="lesson-sidebar">
    <ul class="lesson-nav">
        <li class="muctieu"><a href="#muctieu">Mục tiêu</a></li>
        <li class="mohinh"><a href="#mohinh">Mô hình</a></li>
        <li class="chuanbi"><a href="#chuanbi">Chuẩn bị</a></li>
        <li class="xaydung"><a href="#xaydung">Xây dựng</a></li>
        <li class="noidung"><a href="#noidung">Nội dung</a></li>
        <li class="thuthach"><a href="#thuthach">Thử thách</a></li>
        <li class="tongket"><a href="#tongket">Tổng kết</a></li>
    </ul>
</aside>


        <main class="lesson-content">
            <section id="muctieu" class="lesson-box">
                <h2>Mục tiêu</h2>
                <div class="objective-tabs">
                    <button class="tab-btn active" data-target="m1">Kiến thức</button>
                    <button class="tab-btn" data-target="m2">Kỹ năng</button>
                    <button class="tab-btn" data-target="m3">Tư duy</button>
                    <button class="tab-btn" data-target="m4">Thái độ</button>
                </div>
                <div class="objective-content">
                    <div class="content-item-target" id="m1"><?= nl2br(htmlspecialchars($lessonData['knowledge_objective'] ?? '')) ?></div>
                    <div class="content-item-target" id="m2" style="display:none;"><?= nl2br(htmlspecialchars($lessonData['skills_objective'] ?? '')) ?></div>
                    <div class="content-item-target" id="m3" style="display:none;"><?= nl2br(htmlspecialchars($lessonData['thinking_objective'] ?? '')) ?></div>
                    <div class="content-item-target" id="m4" style="display:none;"><?= nl2br(htmlspecialchars($lessonData['attitude_objective'] ?? '')) ?></div>
                </div>
            </section>

            <section id="mohinh" class="lesson-box">
                <h2>Mô hình tiêu biểu</h2>
                <?php foreach($model_media as $m): ?>
                    <?php if(strpos($m['mime_type'], 'video/') !== false): ?>
                        <video controls style="width:80%; border-radius:10px; display: block; margin: 20px auto;""><source src="../img/lesson_detail/<?= $m['url'] ?>" type="<?= $m['mime_type'] ?>"></video>
                    <?php else: ?>
                        <img src="../img/lesson_detail/<?= $m['url'] ?>" style="width:80%; border-radius:16px; display: block; margin: 20px auto;">
                    <?php endif; ?>
                <?php endforeach; ?>
                <p><?= nl2br(htmlspecialchars($model['description'] ?? '')) ?></p>
            </section>

            <section id="chuanbi" class="lesson-box">
                <h2>Chuẩn bị linh kiện</h2>
                <div class="grid-prep">
                    <?php foreach($prep_media as $m): ?>
                        <?php if(strpos($m['mime_type'], 'video/') !== false): ?>
                            <video muted autoplay loop><source src="../img/lesson_detail/<?= $m['url'] ?>" type="<?= $m['mime_type'] ?>"></video>
                        <?php else: ?>
                            <img src="../img/lesson_detail/<?= $m['url'] ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="xaydung" class="lesson-box">
                <h2>Các bước xây dựng</h2>
                <div class="slider">
                    <button class="slider-btn prev" style="left:10px;">&#10094;</button>
                    <div class="slider-track">
                        <?php foreach($build_media as $m): ?>
                            <?php if(strpos($m['mime_type'], 'video/') !== false): ?>
                                <video controls><source src="../img/lesson_detail/<?= $m['url'] ?>" type="<?= $m['mime_type'] ?>"></video>
                            <?php else: ?>
                                <img src="../img/lesson_detail/<?= $m['url'] ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button class="slider-btn next" style="right:10px;">&#10095;</button>
                </div>
                <input type="range" class="slider-progress" value="0" min="0" step="1">
            </section>

            <section id="noidung" class="lesson-box">   
                <h2>Nội dung bài học</h2>
                <div class="objective-tabs">
                    <?php foreach($contentBlocks as $i => $block): ?>
                        <button class="tab-btn <?= $i==0?'active':'' ?>" data-target="n<?= $block['block_id'] ?>"><?= htmlspecialchars($block['title']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="objective-content">
                    <?php foreach($contentBlocks as $i => $block): ?>
                        <div class="content-item-lesson" id="n<?= $block['block_id'] ?>" style="<?= $i!=0?'display:none':'' ?>">
                            <h3 style="font-size: 1.9rem;"><?= htmlspecialchars($block['subtitle']) ?></h3>
                            <p style="font-size: 1.5rem;"><?= nl2br(htmlspecialchars($block['description'])) ?></p>
                            <?php
                            $hasImage = false;
                            $hasVideo = false;

                            foreach ($block['media_list'] as $m) {
                                if (strpos($m['mime_type'], 'video/') !== false) {
                                    $hasVideo = true;
                                } else {
                                    $hasImage = true;
                                }
                            }
                            ?>

                            <?php if ($hasImage): ?>
                                <!-- ===== ẢNH ===== -->
                                <div class="lesson-media-text">
                                    <div class="lesson-media">
                                        <?php foreach($block['media_list'] as $media): ?>
                                            <?php if (strpos($media['mime_type'], 'video/') === false): ?>
                                                <img src="../img/lesson_detail/<?= $media['url'] ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="lesson-text">
                                        <p><?= nl2br(htmlspecialchars($block['usage_text'])) ?></p>
                                    </div>
                                </div>

                            <?php elseif ($hasVideo): ?>
                                <!-- ===== VIDEO ===== -->
                                <?php foreach($block['media_list'] as $media): ?>
                                    <?php if (strpos($media['mime_type'], 'video/') !== false): ?>
                                        <div style="margin: 20px auto; text-align:center;">
                                            <video controls style="width:100%; max-width:800px;">
                                                <source src="../img/lesson_detail/<?= $media['url'] ?>" type="<?= $media['mime_type'] ?>">
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <p><?= nl2br(htmlspecialchars($block['usage_text'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($block['attachments'])): ?>
                                <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ddd;">
                                    <strong>Tệp đính kèm:</strong>
                                    <?php foreach($block['attachments'] as $f): ?>
                                        <a href="../img/lesson_detail/<?= $f['url'] ?>" target="_blank" style="display:block; color:#007bff;">📎 <?= $f['url'] ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="thuthach" class="lesson-box">
                <h2>Thử thách sáng tạo</h2>
                <?php foreach($challenge_media as $m): ?>
                    <div style="margin-bottom:15px; text-align:center;">
                        <?php if(strpos($m['mime_type'], 'video/') !== false): ?>
                            <video controls style="width:100%; border-radius:16px;"><source src="../img/lesson_challenge/<?= $m['url'] ?>" type="<?= $m['mime_type'] ?>"></video>
                        <?php else: ?>
                            <img src="../img/lesson_challenge/<?= $m['url'] ?>" style="width:100%; border-radius:16px;">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <p><?= nl2br(htmlspecialchars($challenge['description'] ?? '')) ?></p>
            </section>
            
            <section id="tongket" class="lesson-box">
                <h2>Tổng kết & Trắc nghiệm</h2>
                <div id="quiz-container">
                    <?php foreach ($quizzes as $idx => $q): ?>
                        <div class="quiz-item">
                            <div class="quiz-question">Câu <?= $idx+1 ?>: <?= htmlspecialchars($q['question_text']) ?></div>
                            <?php foreach ($q['answers'] as $a): ?>
                                <div class="answer-option" data-correct="<?= $a['is_correct'] ?>" data-explanation="<?= htmlspecialchars($a['explanation']) ?>" onclick="checkAnswer(this)">
                                    <?= htmlspecialchars($a['answer_text']) ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="explanation-box"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="final-score" style="display:none; text-align:center; font-size:1.5rem; font-weight:bold; color:#9c00e5; margin-top:20px;"></div>
            </section>
        </main>
    </div>

    <script>
    /* ===== TAB MỤC TIÊU ===== */
    document.querySelectorAll('#muctieu .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {

            document.querySelectorAll('#muctieu .tab-btn')
                .forEach(b => b.classList.remove('active'));

            document.querySelectorAll('#muctieu .content-item-target')
                .forEach(c => c.style.display = 'none');

            btn.classList.add('active');

            const target = document.getElementById(btn.dataset.target);
            if (target) target.style.display = 'block';
        });
    });
    /* ===== TAB NỘI DUNG BÀI HỌC ===== */
    document.querySelectorAll('#noidung .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {

            document.querySelectorAll('#noidung .tab-btn')
                .forEach(b => b.classList.remove('active'));

            document.querySelectorAll('#noidung .content-item-lesson')
                .forEach(c => c.style.display = 'none');

            btn.classList.add('active');

            const target = document.getElementById(btn.dataset.target);
            if (target) target.style.display = 'block';
        });
    });
    </script>

    <script>
    let currentScore = 0;
    let currentIndex = 0;
    const totalQuestions = <?= $total_questions ?>;
    const quizItems = document.querySelectorAll('.quiz-item');

    // ===== KHỞI TẠO =====
    quizItems.forEach((q, i) => {
        q.style.display = i === 0 ? 'block' : 'none';

        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Câu kế tiếp';
        nextBtn.className = 'submit-btn next-btn';
        nextBtn.style.display = 'none';
        nextBtn.style.marginTop = '15px';

        nextBtn.onclick = () => goNext();
        q.appendChild(nextBtn);
    });

    // ===== KIỂM TRA ĐÁP ÁN =====
    function checkAnswer(el) {
        const parent = el.closest('.quiz-item');
        if (parent.classList.contains('answered')) return;

        const isCorrect = el.dataset.correct === "1";
        const explanation = el.dataset.explanation;
        const expBox = parent.querySelector('.explanation-box');

        if (isCorrect) {
            el.classList.add('correct');
            currentScore++;
        } else {
            el.classList.add('wrong');
            parent.querySelectorAll('.answer-option').forEach(opt => {
                if (opt.dataset.correct === "1") opt.classList.add('correct');
            });
        }

        expBox.innerHTML = `<strong>Giải thích:</strong> ${explanation || 'Chính xác!'}`;
        expBox.style.display = 'block';

        parent.classList.add('answered');
        parent.querySelector('.next-btn').style.display = 'inline-block';
    }

    // ===== SANG CÂU TIẾP =====
    function goNext() {
        quizItems[currentIndex].style.display = 'none';
        currentIndex++;

        if (currentIndex < totalQuestions) {
            quizItems[currentIndex].style.display = 'block';
        } else {
            showResult();
        }
    }

    // ===== HIỂN THỊ KẾT QUẢ + NÚT LÀM LẠI =====
    function showResult() {
        const resultBox = document.getElementById('final-score');

        resultBox.innerHTML = `
            <p>Kết quả:${currentScore}</strong> / ${totalQuestions} điểm</p>
            <button class="submit-btn" onclick="restartQuiz()">Làm lại</button>
        `;
        resultBox.style.display = 'block';
    }

    // ===== LÀM LẠI QUIZ =====
    function restartQuiz() {
        currentScore = 0;
        currentIndex = 0;

        document.getElementById('final-score').style.display = 'none';

        quizItems.forEach((q, i) => {
            q.style.display = i === 0 ? 'block' : 'none';
            q.classList.remove('answered');

            // Reset đáp án
            q.querySelectorAll('.answer-option').forEach(opt => {
                opt.classList.remove('correct', 'wrong');
            });

            // Ẩn giải thích
            const exp = q.querySelector('.explanation-box');
            if (exp) exp.style.display = 'none';

            // Ẩn nút next
            const btn = q.querySelector('.next-btn');
            if (btn) btn.style.display = 'none';
        });
    }
    </script>
    <script>
        const sections = document.querySelectorAll("main section");
        const navItems = document.querySelectorAll(".lesson-nav li");

        window.addEventListener("scroll", () => {
            let current = "";

            sections.forEach(section => {
                const sectionTop = section.offsetTop - 120;
                if (window.scrollY >= sectionTop) {
                    current = section.getAttribute("id");
                }
            });

            navItems.forEach(li => {
                li.classList.remove("active");
                const link = li.querySelector("a");
                if (link.getAttribute("href") === "#" + current) {
                    li.classList.add("active");
                }
            });
        });
    </script>

    <script>
        document.querySelectorAll('.slider').forEach(slider => {

            const track = slider.querySelector('.slider-track');
            const slides = track.children;
            const prevBtn = slider.querySelector('.prev');
            const nextBtn = slider.querySelector('.next');
            const progress = slider.parentElement.querySelector('.slider-progress');

            let index = 0;
            const total = slides.length;

            if (total <= 1) return;

            progress.max = total - 1;
            progress.value = 0;

            function updateSlider() {
                track.style.transform = `translateX(-${index * 100}%)`;
                progress.value = index;

                // pause video khi trượt
                track.querySelectorAll('video').forEach(v => v.pause());
            }

            nextBtn.addEventListener('click', () => {
                index = (index + 1) % total;
                updateSlider();
            });

            prevBtn.addEventListener('click', () => {
                index = (index - 1 + total) % total;
                updateSlider();
            });

            progress.addEventListener('input', () => {
                index = parseInt(progress.value);
                updateSlider();
            });

        });
    </script>

    <?php include('../pages/footer.php'); ?>
</body>
</html>