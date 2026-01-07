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

/* ===== HÀM UPLOAD MEDIA ===== */
function uploadMedia($files, $conn, $uploadDir) {
    $ids = [];
    if (!isset($files['tmp_name'])) {
        return $ids;
    }
    foreach ($files['tmp_name'] as $k => $tmp) {
        if ($files['error'][$k] !== 0) {
            continue;
        }
        $ext = pathinfo($files['name'][$k], PATHINFO_EXTENSION);
        $name = uniqid('challenge_') . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDir . $name)) {
            $stmt = $conn->prepare("INSERT INTO media (url, mime_type, file_size, uploaded_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $name, $files['type'][$k], $files['size'][$k]);
            $stmt->execute();
            $ids[] = $stmt->insert_id;
            $stmt->close();
        }
    }
    return $ids;
}

/* ===== XỬ LÝ SUBMIT ===== */
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

    if (!$lesson_id || !$title) {
        $error = "Vui lòng chọn bài học và nhập tiêu đề thử thách";
    } else {
        $conn->begin_transaction();
        try {
            /* ===== 1. THỬ THÁCH ===== */
            $stmt = $conn->prepare("INSERT INTO lesson_challenges (lesson_id, title, subtitle, description, instructions) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $lesson_id, $title, $subtitle, $description, $instructions);
            $stmt->execute();
            $challenge_id = $stmt->insert_id;
            $stmt->close();

            /* ===== MEDIA THỬ THÁCH ===== */
            if (!empty($_FILES['challenge_media']['name'][0])) {
                $mediaIds = uploadMedia($_FILES['challenge_media'], $conn, $uploadDir);
                foreach ($mediaIds as $mid) {
                    $conn->query("INSERT INTO lesson_challenge_media (challenge_id, media_id) VALUES ($challenge_id, $mid)");
                }
            }

            /* ===== 2. QUIZZES ===== */
            foreach ($quiz_texts as $q_idx => $q_text) {
                $q_type = $quiz_types[$q_idx];
                $q_text = trim($q_text);
                if ($q_text === '' || !in_array($q_type, ['single', 'multiple', 'open'])) {
                    continue;
                }

                // Thêm quiz
                $stmtQuiz = $conn->prepare("INSERT INTO lesson_quizzes (lesson_id, question_text, quiz_type) VALUES (?, ?, ?)");
                $stmtQuiz->bind_param("iss", $lesson_id, $q_text, $q_type);
                $stmtQuiz->execute();
                $quiz_id = $stmtQuiz->insert_id;
                $stmtQuiz->close();

                // Thêm đáp án
                if ($q_type !== 'open') {
                    foreach ($answers_all[$q_idx] as $a_idx => $ans) {
                        $ans = trim($ans);
                        if ($ans === '') {
                            continue;
                        }
                        $isCorrect = in_array($a_idx, $corrects_all[$q_idx] ?? []) ? 1 : 0;
                        $explanation = trim($explanations_all[$q_idx][$a_idx] ?? '');
                        $stmtA = $conn->prepare("INSERT INTO quiz_answers (quiz_id, answer_text, is_correct, explanation) VALUES (?, ?, ?, ?)");
                        $stmtA->bind_param("isis", $quiz_id, $ans, $isCorrect, $explanation);
                        $stmtA->execute();
                        $stmtA->close();
                    }
                } else {
                    // Tự luận: lưu 1 đáp án đúng
                    $ans = trim($answers_all[$q_idx][0] ?? '');
                    $explanation = trim($explanations_all[$q_idx][0] ?? '');
                    if ($ans !== '') {
                        $stmtA = $conn->prepare("INSERT INTO quiz_answers (quiz_id, answer_text, is_correct, explanation) VALUES (?, ?, 1, ?)");
                        $stmtA->bind_param("iss", $quiz_id, $ans, $explanation);
                        $stmtA->execute();
                        $stmtA->close();
                    }
                }
            }

            $conn->commit();
            $success = "Đã tạo thử thách và tổng kết thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}

/* ===== XÓA THỬ THÁCH ===== */
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id) {
        $conn->begin_transaction();
        try {
            // Lấy media liên quan
            $resMedia = $conn->query("SELECT m.url FROM lesson_challenge_media lcm 
                                      JOIN media m ON lcm.media_id = m.media_id
                                      WHERE lcm.challenge_id = $del_id");
            while ($m = $resMedia->fetch_assoc()) {
                $filePath = $uploadDir . $m['url'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Xóa liên kết media
            $conn->query("DELETE FROM lesson_challenge_media WHERE challenge_id = $del_id");

            // Xóa quiz và đáp án
            $quizRes = $conn->query("SELECT quiz_id FROM lesson_quizzes WHERE lesson_id=(SELECT lesson_id FROM lesson_challenges WHERE challenge_id=$del_id)");
            while ($q = $quizRes->fetch_assoc()) {
                $conn->query("DELETE FROM quiz_answers WHERE quiz_id=" . $q['quiz_id']);
            }
            $conn->query("DELETE FROM lesson_quizzes WHERE lesson_id=(SELECT lesson_id FROM lesson_challenges WHERE challenge_id=$del_id)");

            // Xóa thử thách
            $conn->query("DELETE FROM lesson_challenges WHERE challenge_id=$del_id");

            $conn->commit();
            header("Location: create_lesson_challenge.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Xóa thất bại: " . $e->getMessage();
        }
    }
}

/* ===== DỮ LIỆU ===== */
$lessonResult = $conn->query("SELECT lesson_id, title FROM lessons ORDER BY created_at ASC");
$challengeResult = $conn->query("
    SELECT lc.*, l.title AS lesson_title
    FROM lesson_challenges lc
    JOIN lessons l ON lc.lesson_id = l.lesson_id
    ORDER BY lc.challenge_id DESC
");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo thử thách</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/body.css">
    <link rel="stylesheet" href="../css/create_program.css">
    <link rel="stylesheet" href="../css/create_subcourses.css">
    <link rel="stylesheet" href="../css/edit_lesson_detail.css">
    <style>
        .answers-row { display: flex; gap: 10px; margin-bottom: 6px; align-items: center; }
        .quiz-block { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; border-radius: 6px; }
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
                            <option value="">-- Chọn bài học --</option>
                            <?php 
                                $lessonResult->data_seek(0);
                                while ($l = $lessonResult->fetch_assoc()): 
                            ?>
                                <option value="<?= $l['lesson_id'] ?>" 
                                    <?= (isset($_POST['lesson_id']) && $_POST['lesson_id'] == $l['lesson_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['title']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Tiêu đề</label>
                        <input type="text" name="title" required>
                    </div>

                    <div class="input-group">
                        <label>Phụ đề</label>
                        <input type="text" name="subtitle">
                    </div>

                    <div class="input-group">
                        <label>Mô tả</label>
                        <textarea name="description" rows="6" style="width:100%;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Hướng dẫn</label>
                        <textarea name="instructions" rows="6" style="width:100%;"></textarea>
                    </div>

                    <div class="input-group">
                        <label>Media thử thách</label>
                        <input type="file" name="challenge_media[]" multiple accept="image/*,video/*">
                    </div>

                    <h2>Tổng kết</h2>
                    <div id="quizzes-container">
                        <div class="quiz-block">
                            <h3>Câu hỏi 1</h3>
                            <div class="input-group">
                                <label>Câu hỏi</label>
                                <textarea name="quiz_text[]" rows="5" style="width:100%;"></textarea>
                            </div>
                            <div class="input-group select-program">
                                <label>Loại câu hỏi</label>
                                <select name="quiz_type[]">
                                    <option value="single">1 đáp án</option>
                                    <option value="multiple">Nhiều đáp án</option>
                                    <option value="open">Tự luận</option>
                                </select>
                            </div>

                            <div class="answers-container">
                                <div class="multiple-answers">
                                    <?php for($i=0; $i<4; $i++): ?>
                                        <div class="answers-row">
                                            <input type="text" name="answers[0][]" style="width:50%;" placeholder="Đáp án <?= $i+1 ?>">
                                            <input type="checkbox" name="correct_answers[0][]" value="<?= $i ?>">
                                            <input type="text" name="explanations[0][]" style="width:50%;" placeholder="Giải thích">
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <div class="answers-row open-answer" style="display:none;">
                                    <input type="text" name="answers[0][]" style="width:80%;" placeholder="Đáp án đúng">
                                    <input type="text" name="explanations[0][]" style="width:80%;" placeholder="Giải thích">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-quiz">Thêm câu hỏi</button>
                    <br><br>
                    <button type="submit" name="challenge_submit" class="submit-btn">THÊM</button>
                    <a href="lesson.php" class="btn-back">Quay lại</a>
                </form>
            </div>
        </div>

        <div class="container-table">
            <h2>Danh sách thử thách</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Bài học</th>
                        <th>Tiêu đề</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $challengeResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['lesson_title']) ?></td>
                            <td><?= htmlspecialchars($c['title']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_lesson_challenge.php?id=<?= $c['challenge_id'] ?>" class="btn-edit">Sửa</a>
                                    <a href="?delete=<?= $c['challenge_id'] ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Bạn có chắc chắn muốn xóa thử thách này và các media liên quan?')">
                                       Xóa
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // ================== THÊM CÂU HỎI ==================
        document.getElementById('add-quiz').addEventListener('click', function () {
            const container = document.getElementById('quizzes-container');
            const count = container.querySelectorAll('.quiz-block').length;
            const firstQuiz = container.querySelector('.quiz-block');
            if (!firstQuiz) return;

            const newQuiz = firstQuiz.cloneNode(true);

            // Reset dữ liệu
            newQuiz.querySelectorAll('textarea, input[type=text]').forEach(i => i.value = '');
            newQuiz.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);

            // Reset hiển thị
            newQuiz.querySelector('.multiple-answers').style.display = 'block';
            newQuiz.querySelector('.open-answer').style.display = 'none';
            newQuiz.querySelector('select').value = 'single';

            // Update name index
            newQuiz.querySelectorAll('input, textarea, select').forEach(el => {
                if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${count}]`);
            });

            newQuiz.querySelector('h3').textContent = 'Câu hỏi ' + (count + 1);
            container.appendChild(newQuiz);
        });

        // ================== XỬ LÝ CHỌN LOẠI CÂU HỎI ==================
        document.getElementById('quizzes-container').addEventListener('change', function (e) {
            if (e.target.tagName !== 'SELECT') return;

            const quiz = e.target.closest('.quiz-block');
            const multipleBox = quiz.querySelector('.multiple-answers');
            const openBox = quiz.querySelector('.open-answer');

            if (e.target.value === 'open') {
                multipleBox.style.display = 'none';
                openBox.style.display = 'flex';
            } else {
                multipleBox.style.display = 'block';
                openBox.style.display = 'none';
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
        });

        // ================== CHỈ CHO PHÉP 1 CHECKBOX KHI SINGLE ==================
        document.getElementById('quizzes-container').addEventListener('click', function (e) {
            if (e.target.type !== 'checkbox') return;

            const quiz = e.target.closest('.quiz-block');
            const type = quiz.querySelector('select').value;

            if (type === 'single') {
                quiz.querySelectorAll('input[type=checkbox]').forEach(cb => {
                    if (cb !== e.target) cb.checked = false;
                });
            }
        });
    </script>

    <?php include('../pages/footer.php'); ?>
</body>
</html> 