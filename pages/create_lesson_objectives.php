<?php
session_start();
include '../database/connect.php';

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
   header("Location: login.php");
   exit;
}

$error   = "";
$success = "";

/* ===== XÓA ===== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $lesson_id = intval($_GET['delete']);

   $stmt = $conn->prepare("DELETE FROM lesson_objectives WHERE lesson_id=?");
   $stmt->bind_param("i", $lesson_id);
   $stmt->execute();
   $stmt->close();

   header("Location: create_lesson_objectives.php");
   exit;
}

/* ===== DANH SÁCH BÀI HỌC ===== */
$lessons = $conn->query("
   SELECT lesson_id, title 
   FROM lessons 
   ORDER BY created_at ASC
");

/* ===== THÊM ===== */
if (isset($_POST['objective_submit'])) {

   $lesson_id = intval($_POST['lesson_id']);
   $knowledge = trim($_POST['knowledge_objective']);
   $thinking  = trim($_POST['thinking_objective']);
   $skills    = trim($_POST['skills_objective']);
   $attitude  = trim($_POST['attitude_objective']);

   if (!$lesson_id) {
      $error = "Vui lòng chọn bài học.";
   } else {

      $stmt = $conn->prepare("
         INSERT INTO lesson_objectives
         (lesson_id, knowledge_objective, thinking_objective, skills_objective, attitude_objective)
         VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->bind_param(
         "issss",
         $lesson_id,
         $knowledge,
         $thinking,
         $skills,
         $attitude
      );
      $stmt->execute();
      $stmt->close();

      $success = "Thêm mục tiêu bài học thành công!";
   }
}

/* ===== DANH SÁCH OBJECTIVES ===== */
$objectiveList = $conn->query("
   SELECT lo.*, l.title AS lesson_title
   FROM lesson_objectives lo
   JOIN lessons l ON lo.lesson_id = l.lesson_id
   ORDER BY lo.objective_id ASC
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
   <meta charset="UTF-8">
   <title>LET'S CODE - Mục tiêu bài học</title>
   <link rel="stylesheet" href="../css/user.css">
   <link rel="stylesheet" href="../css/body.css">
   <link rel="stylesheet" href="../css/create_program.css">
   <link rel="stylesheet" href="../css/edit_lesson_detail.css">
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

         <form method="POST">
            <h2>Tạo mục tiêu bài học</h2>

            <div class="input-group select-program">
               <label>Bài học</label>
               <select name="lesson_id" required>
                  <option value="">-- Chọn bài học --</option>
                  <?php while ($l = $lessons->fetch_assoc()): ?>
                     <option value="<?= $l['lesson_id'] ?>">
                        <?= htmlspecialchars($l['title']) ?>
                     </option>
                  <?php endwhile; ?>
               </select>
            </div>

            <div class="input-group">
               <label>Kiến thức</label>
               <textarea name="knowledge_objective" rows="6" style="width:100%;" required></textarea>
            </div>

            <div class="input-group">
               <label>Tư duy</label>
               <textarea name="thinking_objective" rows="6" style="width:100%;" required></textarea>
            </div>

            <div class="input-group">
               <label>Kĩ năng</label>
               <textarea name="skills_objective" rows="6" style="width:100%;" required></textarea>
            </div>

            <div class="input-group">
               <label>Thái độ</label>
               <textarea name="attitude_objective" rows="6" style="width:100%;" required></textarea>
            </div>

            <button type="submit" name="objective_submit" class="submit-btn">
               THÊM MỤC TIÊU
            </button>

            <a href="lesson.php" class="btn-back">
                    Quay lại
            </a>
         </form>

      </div>
   </div>

   <div class="container-table">
      <h2>Danh sách mục tiêu bài học</h2>

      <table class="user-table">
         <thead>
            <tr>
               <th>Bài học</th>
               <th>Kiến thức</th>
               <th>Tư duy</th>
               <th>Kĩ năng</th>
               <th>Thái độ</th>
               <th>Hành động</th>
            </tr>
         </thead>

         <tbody>
            <?php while ($o = $objectiveList->fetch_assoc()): ?>
               <tr>
                  <td><?= htmlspecialchars($o['lesson_title']) ?></td>
                  <td><?= mb_strimwidth($o['knowledge_objective'], 0, 60, '...') ?></td>
                  <td><?= mb_strimwidth($o['thinking_objective'], 0, 60, '...') ?></td>
                  <td><?= mb_strimwidth($o['skills_objective'], 0, 60, '...') ?></td>
                  <td><?= mb_strimwidth($o['attitude_objective'], 0, 60, '...') ?></td>
                  <td> 
                     <div class="action-buttons"> 
                        <a href="edit_lesson_objectives.php?lesson_id=<?= $o['lesson_id'] ?>" class="btn-edit">
                            Sửa
                        </a>
                        <a href="?delete=<?= $o['lesson_id'] ?>" class="btn-delete" 
                           onclick="return confirm('Xóa mục tiêu bài học này?')">Xóa</a> 
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
