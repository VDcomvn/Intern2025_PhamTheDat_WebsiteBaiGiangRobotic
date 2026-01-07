<?php
session_start();
include '../database/connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";
$uploadDir = "../img/lesson_challenge/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* ===== ID ===== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID không hợp lệ");
}
$challenge_id = intval($_GET['id']);

/* ===== LẤY THỬ THÁCH ===== */
$challenge = $conn->query("
    SELECT * FROM lesson_challenges WHERE challenge_id=$challenge_id
")->fetch_assoc();
if (!$challenge) {
    die("Không tìm thấy thử thách");
}

/* ===== MEDIA HIỆN TẠI ===== */
$resMedia = $conn->query("
    SELECT m.media_id, m.url, m.mime_type
    FROM media m
    JOIN lesson_challenge_media lcm ON m.media_id = lcm.media_id
    WHERE lcm.challenge_id = $challenge_id
");

/* ===== BÀI HỌC ===== */
$lessonResult = $conn->query("SELECT lesson_id, title FROM lessons ORDER BY created_at ASC");

/* ===== QUIZ ===== */
$quizRes = $conn->query("
    SELECT * FROM lesson_quizzes WHERE lesson_id={$challenge['lesson_id']}
");

/* ===== UPLOAD MEDIA ===== */
function uploadMedia($files, $conn, $uploadDir, $challenge_id) {
    foreach ($files['tmp_name'] as $k => $tmp) {
        if ($files['error'][$k] !== 0) {
            continue;
        }
        $ext = pathinfo($files['name'][$k], PATHINFO_EXTENSION);
        $name = uniqid('challenge_') . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDir . $name)) {
            $stmt = $conn->prepare("
                INSERT INTO media (url, mime_type, file_size, uploaded_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssi", $name, $files['type'][$k], $files['size'][$k]);
            $stmt->execute();
            $mid = $stmt->insert_id;
            $stmt->close();

            $conn->query("
                INSERT INTO lesson_challenge_media (challenge_id, media_id)
                VALUES ($challenge_id, $mid)
            ");
        }
    }
}

/* ===== XÓA MEDIA ===== */
if (isset($_GET['delete_media']) && is_numeric($_GET['delete_media'])) {
    $mid = intval($_GET['delete_media']);

    $file = $conn->query("SELECT url FROM media WHERE media_id=$mid")->fetch_assoc();
    if ($file) {
        $path = $uploadDir . $file['url'];
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $conn->query("DELETE FROM lesson_challenge_media WHERE media_id=$mid");
    $conn->query("DELETE FROM media WHERE media_id=$mid");

    header("Location: edit_lesson_challenge.php?id=" . $challenge_id);
    exit;
}

/* ===== UPDATE ===== */
if (isset($_POST['challenge_submit'])) {

    $lesson_id = intval($_POST['lesson_id']);
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);

    $quiz_texts = $_POST['quiz_text'] ?? [];
    $quiz_types = $_POST['quiz_type'] ?? [];
    $answers_all = $_POST['answers'] ?? [];
    $corrects_all = $_POST['correct_answers'] ?? [];
    $explanations_all = $_POST['explanations'] ?? [];

    if (!$title) {
        $error = "Vui lòng nhập tiêu đề";
    } else {
        $conn->begin_transaction();
        try {
            /* ===== UPDATE CHALLENGE ===== */
            $stmt = $conn->prepare("
                UPDATE lesson_challenges
                SET lesson_id=?, title=?, subtitle=?, description=?, instructions=?
                WHERE challenge_id=?
            ");
            $stmt->bind_param(
                "issssi",
                $lesson_id,
                $title,
                $subtitle,
                $description,
                $instructions,
                $challenge_id
            );
            $stmt->execute();
            $stmt->close();

            /* ===== XÓA QUIZ CŨ ===== */
            $qOld = $conn->query("
                SELECT quiz_id FROM lesson_quizzes WHERE lesson_id={$challenge['lesson_id']}
            ");
            while ($q = $qOld->fetch_assoc()) {
                $conn->query("DELETE FROM quiz_answers WHERE quiz_id=" . $q['quiz_id']);
            }
            $conn->query("DELETE FROM lesson_quizzes WHERE lesson_id={$challenge['lesson_id']}");

            /* ===== THÊM QUIZ MỚI ===== */
            foreach ($quiz_texts as $i => $q_text) {
                $q_text = trim($q_text);
                $q_type = $quiz_types[$i];
                if ($q_text === '') {
                    continue;
                }

                $stmtQ = $conn->prepare("
                    INSERT INTO lesson_quizzes (lesson_id, question_text, quiz_type)
                    VALUES (?, ?, ?)
                ");
                $stmtQ->bind_param("iss", $lesson_id, $q_text, $q_type);
                $stmtQ->execute();
                $quiz_id = $stmtQ->insert_id;
                $stmtQ->close();

                if ($q_type !== 'open') {
                    foreach ($answers_all[$i] as $a => $ans) {
                        if (trim($ans) === '') {
                            continue;
                        }
                        $isCorrect = in_array($a, $corrects_all[$i] ?? []) ? 1 : 0;
                        $exp = $explanations_all[$i][$a] ?? '';
                        $stmtA = $conn->prepare("
                            INSERT INTO quiz_answers (quiz_id, answer_text, is_correct, explanation)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmtA->bind_param("isis", $quiz_id, $ans, $isCorrect, $exp);
                        $stmtA->execute();
                        $stmtA->close();
                    }
                } else {
                    $ans = $answers_all[$i][0] ?? '';
                    $exp = $explanations_all[$i][0] ?? '';
                    if ($ans !== '') {
                        $stmtA = $conn->prepare("
                            INSERT INTO quiz_answers (quiz_id, answer_text, is_correct, explanation)
                            VALUES (?, ?, 1, ?)
                        ");
                        $stmtA->bind_param("iss", $quiz_id, $ans, $exp);
                        $stmtA->execute();
                        $stmtA->close();
                    }
                }
            }

            if (!empty($_FILES['challenge_media']['name'][0])) {
                uploadMedia($_FILES['challenge_media'], $conn, $uploadDir, $challenge_id);
            }

            $conn->commit();
            /* LOAD LẠI CHALLENGE */
            $challenge = $conn->query("
                SELECT * FROM lesson_challenges WHERE challenge_id=$challenge_id
            ")->fetch_assoc();

            /* LOAD LẠI QUIZ */
            $quizRes = $conn->query("
                SELECT * FROM lesson_quizzes WHERE lesson_id={$challenge['lesson_id']}
            ");
            $success = "Cập nhật thử thách thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa thử thách</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">
    <link rel="stylesheet" href="../css/edit_lesson_detail.css">
    <style>
        .quiz-block { border: 1px solid #ccc; padding: 12px; margin-bottom: 12px; border-radius: 8px }
        .answers-row { display: flex; gap: 8px; margin-bottom: 6px; align-items: center }
        .btn-delete-quiz { background: #ffdddd; border: 1px solid #ff5c5c; padding: 6px 10px; border-radius: 6px; cursor: pointer }
        .btn-delete-answer { background: #ffe5e5; border: 1px solid #ff5c5c; color: #c00; padding: 4px 8px; border-radius: 6px; cursor: pointer }
        .media-preview { display: flex; gap: 16px; flex-wrap: wrap; }
        .media-item { display: flex; flex-direction: column; align-items: center; }
        .media-item img, .media-item video { width: 150px; height: 100px; object-fit: cover; border-radius: 6px; }
        .btn-delete-media { margin-top: 6px; color: #c00; font-size: 13px; text-decoration: none; }
        .btn-delete-media:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <?php include('../pages/header.php'); ?>

    <div class="user-page">
        <div class="container">
            <div class="form-container">

                <?php if ($error): ?>
                    <div class="message-box error"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message-box success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <h2>Thử thách</h2>

                    <div class="input-group select-program">
                        <label>Chọn bài học</label>
                        <select name="lesson_id" required>
                            <?php while ($l = $lessonResult->fetch_assoc()): ?>
                                <option value="<?= $l['lesson_id'] ?>" <?= $l['lesson_id'] == $challenge['lesson_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['title']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tiêu đề</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($challenge['title']) ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Phụ đề</label>
                        <input type="text" name="subtitle" value="<?= htmlspecialchars($challenge['subtitle']) ?>">
                    </div>

                    <div class="input-group">
                        <label>Mô tả</label>
                        <textarea name="description" rows="5" style="width:100%;"><?= htmlspecialchars($challenge['description']) ?></textarea>
                    </div>

                    <div class="input-group">
                        <label>Hướng dẫn</label>
                        <textarea name="instructions" rows="5" style="width:100%;"><?= htmlspecialchars($challenge['instructions']) ?></textarea>
                    </div>

                    <div class="input-group">
                        <label>Media hiện tại (Video / Ảnh minh họa)</label>
                        <div class="media-preview">
                            <?php while ($m = $resMedia->fetch_assoc()): ?>
                                <div class="media-item">
                                    <?php if (strpos($m['mime_type'], 'video/') !== false): ?>
                                        <video controls muted>
                                            <source src="<?= $uploadDir . $m['url'] ?>" type="<?= $m['mime_type'] ?>">
                                        </video>
                                    <?php else: ?>
                                        <img src="<?= $uploadDir . $m['url'] ?>">
                                    <?php endif; ?>

                                    <a class="btn-delete-media" 
                                       href="?id=<?= $challenge_id ?>&delete_media=<?= $m['media_id'] ?>" 
                                       onclick="return confirm('Xóa tệp này?')">Xóa</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Thêm media mới</label>
                        <input type="file" name="challenge_media[]" multiple>
                    </div>

                    <h2>Tổng kết</h2>
                    <div id="quizzes-container">
                        <?php
                        $i = 0;
                        while ($q = $quizRes->fetch_assoc()):
                            $ans = $conn->query("SELECT * FROM quiz_answers WHERE quiz_id=" . $q['quiz_id']);
                        ?>
                            <div class="quiz-block select-program">
                                <h3>Câu hỏi <?= $i + 1 ?></h3>
                                <button type="button" class="btn-delete-quiz">Xóa câu hỏi</button><br><br>

                                <textarea name="quiz_text[]" rows="5" style="width:100%;"><?= htmlspecialchars($q['question_text']) ?></textarea><br><br>

                                <select name="quiz_type[]">
                                    <option value="single" <?= $q['quiz_type'] == 'single' ? 'selected' : '' ?>>1 đáp án</option>
                                    <option value="multiple" <?= $q['quiz_type'] == 'multiple' ? 'selected' : '' ?>>Nhiều đáp án</option>
                                    <option value="open" <?= $q['quiz_type'] == 'open' ? 'selected' : '' ?>>Tự luận</option>
                                </select><br><br>

                                <div class="multiple-answers" style="<?= $q['quiz_type'] == 'open' ? 'display:none;' : '' ?>">
                                    <?php $a = 0; while ($row = $ans->fetch_assoc()): ?>
                                        <div class="answers-row">
                                            <input type="text" name="answers[<?= $i ?>][]" style="width:100%;" value="<?= htmlspecialchars($row['answer_text']) ?>">
                                            <input type="checkbox" name="correct_answers[<?= $i ?>][]" value="<?= $a ?>" <?= $row['is_correct'] ? 'checked' : '' ?>>
                                            <input type="text" name="explanations[<?= $i ?>][]" style="width:100%;" value="<?= htmlspecialchars($row['explanation']) ?>">
                                            <button type="button" class="btn-delete-answer">❌</button>
                                        </div>
                                    <?php $a++; endwhile; ?>
                                </div>

                                <button type="button" class="btn-add-answer">Thêm đáp án</button>
                            </div>
                        <?php $i++; endwhile; ?>
                    </div>

                    <button type="button" id="add-quiz">Thêm câu hỏi</button>
                    <br><br>
                    <button type="submit" name="challenge_submit" class="submit-btn">LƯU</button>
                    <a href="create_lesson_challenge.php" class="btn-back">Quay lại</a>

                </form>
            </div>
        </div>
    </div>

    <script>
        // ================== THÊM CÂU HỎI ==================
        document.getElementById('add-quiz').addEventListener('click', () => {
            const container = document.getElementById('quizzes-container');
            const count = container.querySelectorAll('.quiz-block').length;

            const firstBlock = container.querySelector('.quiz-block');
            if (!firstBlock) return;

            const q = firstBlock.cloneNode(true);
            q.querySelectorAll('input[type=text], textarea').forEach(e => e.value = '');
            q.querySelectorAll('input[type=checkbox]').forEach(e => e.checked = false);
            q.querySelectorAll('input, textarea, select').forEach(e => {
                if (e.name) e.name = e.name.replace(/\[\d+\]/, `[${count}]`);
            });

            q.querySelector('h3').textContent = 'Câu hỏi ' + (count + 1);
            container.appendChild(q);
            reIndex();
        });

        // ================== XỬ LÝ CLICK ==================
        document.getElementById('quizzes-container').addEventListener('click', e => {
            // XÓA CÂU HỎI
            if (e.target.classList.contains('btn-delete-quiz')) {
                const blocks = document.querySelectorAll('.quiz-block');
                if (blocks.length <= 1) return alert('Phải có ít nhất 1 câu hỏi');
                e.target.closest('.quiz-block').remove();
                reIndex();
            }

            // XÓA ĐÁP ÁN
            if (e.target.classList.contains('btn-delete-answer')) {
                const quiz = e.target.closest('.quiz-block');
                const answers = quiz.querySelector('.multiple-answers');
                if (answers.children.length <= 1) return alert('Mỗi câu hỏi phải có ít nhất 1 đáp án');
                e.target.closest('.answers-row').remove();
                reIndexAnswers(quiz);
            }

            // CHỈ 1 ĐÁP ÁN ĐÚNG KHI SINGLE
            if (e.target.type === 'checkbox') {
                const quiz = e.target.closest('.quiz-block');
                const type = quiz.querySelector('select[name^="quiz_type"]').value;
                if (type === 'single') {
                    quiz.querySelectorAll('input[type=checkbox]').forEach(cb => {
                        if (cb !== e.target) cb.checked = false;
                    });
                }
            }
        });

        // ================== THAY ĐỔI LOẠI CÂU HỎI ==================
        document.getElementById('quizzes-container').addEventListener('change', e => {
            if (e.target.tagName === 'SELECT') {
                const quiz = e.target.closest('.quiz-block');
                const answers = quiz.querySelector('.multiple-answers');
                if (e.target.value === 'open') {
                    answers.style.display = 'none';
                } else {
                    answers.style.display = 'block';
                }

                if (e.target.value === 'single') {
                    let found = false;
                    quiz.querySelectorAll('input[type=checkbox]').forEach(cb => {
                        if (cb.checked) {
                            if (!found) found = true;
                            else cb.checked = false;
                        }
                    });
                }
            }
        });

        // ================== THÊM ĐÁP ÁN ==================
        document.getElementById('quizzes-container').addEventListener('click', e => {
            if (e.target.classList.contains('btn-add-answer')) {
                const quiz = e.target.closest('.quiz-block');
                const answersBox = quiz.querySelector('.multiple-answers');
                const index = [...document.querySelectorAll('.quiz-block')].indexOf(quiz);
                const count = answersBox.querySelectorAll('.answers-row').length;

                const row = document.createElement('div');
                row.className = 'answers-row';
                row.innerHTML = `
                    <input type="text" name="answers[${index}][]" placeholder="Đáp án" style="width:100%;">
                    <input type="checkbox" name="correct_answers[${index}][]" value="${count}">
                    <input type="text" name="explanations[${index}][]" placeholder="Giải thích" style="width:100%;">
                    <button type="button" class="btn-delete-answer">❌</button>
                `;
                answersBox.appendChild(row);
            }
        });

        // ================== RE-INDEX ==================
        function reIndex() {
            document.querySelectorAll('.quiz-block').forEach((q, i) => {
                q.querySelector('h3').textContent = 'Câu hỏi ' + (i + 1);
                q.querySelectorAll('input, textarea, select').forEach(e => {
                    if (e.name) e.name = e.name.replace(/\[\d+\]/, `[${i}]`);
                });
                reIndexAnswers(q);
            });
        }

        function reIndexAnswers(quiz) {
            quiz.querySelectorAll('.answers-row').forEach((row, a) => {
                const checkbox = row.querySelector('input[type=checkbox]');
                if (checkbox) checkbox.value = a;
            });
        }
    </script>

    <?php include('../pages/footer.php'); ?>
</body>
</html>