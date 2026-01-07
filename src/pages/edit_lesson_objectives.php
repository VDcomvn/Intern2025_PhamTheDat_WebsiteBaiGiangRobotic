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

/* ===== KIỂM TRA ID ===== */
if (!isset($_GET['lesson_id']) || !is_numeric($_GET['lesson_id'])) {
   header("Location: create_lesson_objectives.php");
   exit;
}

$lesson_id = intval($_GET['lesson_id']);

/* ===== LẤY OBJECTIVE HIỆN TẠI ===== */
$stmt = $conn->prepare("
   SELECT lo.*, l.title 
   FROM lesson_objectives lo
   JOIN lessons l ON lo.lesson_id = l.lesson_id
   WHERE lo.lesson_id = ?
");
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
   header("Location: create_lesson_objectives.php");
   exit;
}

$objective = $result->fetch_assoc();
$stmt->close();

/* ===== CẬP NHẬT ===== */
if (isset($_POST['update_objective'])) {

   $knowledge = trim($_POST['knowledge_objective']);
   $thinking  = trim($_POST['thinking_objective']);
   $skills    = trim($_POST['skills_objective']);
   $attitude  = trim($_POST['attitude_objective']);

   $stmt = $conn->prepare("
      UPDATE lesson_objectives
      SET knowledge_objective = ?,
          thinking_objective  = ?,
          skills_objective    = ?,
          attitude_objective  = ?
      WHERE lesson_id = ?
   ");
   $stmt->bind_param(
      "ssssi",
      $knowledge,
      $thinking,
      $skills,
      $attitude,
      $lesson_id
   );

   if ($stmt->execute()) {
      $success = "Cập nhật mục tiêu bài học thành công!";
   } else {
      $error = "Cập nhật thất bại!";
   }

   $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
   <meta charset="UTF-8">
   <title>Sửa mục tiêu bài học</title>

   <link rel="stylesheet" href="../css/user.css">
   <link rel="stylesheet" href="../css/body.css">
   <link rel="stylesheet" href="../css/create_program.css">
   <link rel="stylesheet" href="../css/create_subcourses.css">
   <link rel="stylesheet" href="../css/edit_lesson_detail.css">
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
            <h2>Sửa mục tiêu bài học</h2>

            <div class="input-group">
               <label>Bài học</label>
               <input type="text"
                      value="<?= htmlspecialchars($objective['title']) ?>"
                      disabled>
            </div>

            <div class="input-group">
               <label>Kiến thức</label>
               <textarea name="knowledge_objective" rows="6" style="width:100%;" required><?= htmlspecialchars($objective['knowledge_objective']) ?>
               </textarea>
            </div>

            <div class="input-group">
               <label>Tư duy</label>
               <textarea name="thinking_objective" rows="6" style="width:100%;" required><?= htmlspecialchars($objective['thinking_objective']) ?>
               </textarea>
            </div>

            <div class="input-group">
               <label>Kĩ năng</label>
               <textarea name="skills_objective" rows="6" style="width:100%;" required><?= htmlspecialchars($objective['skills_objective']) ?>
               </textarea>
            </div>

            <div class="input-group">
               <label>Thái độ</label>
               <textarea name="attitude_objective" rows="6" style="width:100%;" required><?= htmlspecialchars($objective['attitude_objective']) ?>
               </textarea>
            </div>

            <button type="submit"
                    name="update_objective"
                    class="submit-btn">
               CẬP NHẬT
            </button>

            <a href="create_lesson_objectives.php"
               class="btn-back">
               Quay lại
            </a>
         </form>

      </div>
   </div>

</div>

<?php include('../pages/footer.php'); ?>
</body>
</html>
