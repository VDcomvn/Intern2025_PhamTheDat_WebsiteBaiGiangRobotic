XÂY DỰNG WEBSITE BÀI GIẢNG ROBOTI 

Mô tả: Dự án xây dựng một website hỗ trợ giảng dạy Robotic cho trẻ em tại trung tâm Let’s Code.
Hệ thống cho phép quản lý bài giảng Robotic theo cấu trúc khoa học gồm: mục tiêu bài học, mô hình robot, nội dung hướng dẫn, thử thách sáng tạo và trắc nghiệm đánh giá.
Website đóng vai trò là công cụ hỗ trợ giáo viên trong quá trình giảng dạy, giúp bài học trực quan, dễ theo dõi và nâng cao hiệu quả đào tạo.

Hưỡng dẫn chạy:

Bước 1: Chuẩn bị môi trường
- Cài đặt Laragon (hoặc XAMPP/WAMP)
- PHP >= 7.4
- MySQL

Bước 2: Cài đặt source code
- Clone project từ GitHub:

git clone https://github.com/VDcomvn/Intern2025_PhamTheDat_WebsiteBaiGiangRobotic.git

- Copy thư mục project vào: laragon/www/

Bước 3: Import cơ sở dữ liệu
- Mở phpMyAdmin
- Tạo database mới (ví dụ: letscode)
- Import file .sql đi kèm trong project

Bước 4: Cấu hình kết nối CSDL
- Mở file cấu hình kết nối database (ví dụ connect.php)
- Chỉnh lại cấu hình kết nối cơ sở dữ liệu của bạn:

$host = "localhost";

$user = "root";

$password = "";

$database = "letscode";

Bước 5: Chạy project
- Mở Laragon → Start All
- Truy cập: http://localhost/ten-thu-muc-project

Ngôn ngữ lập trình 
- Front-end: HTML5, CSS3, Bootstrap
- Back-end: PHP
- Cơ sở dữ liệu: MySQL
- Môi trường phát triển: Laragon
- Công cụ quản lý CSDL: phpMyAdmin

Thông tin thực tập sinh
- Họ tên: Phạm Thế Đạt
- MSSV: 64130301
- Trường: Đại học Nha Trang – Khoa Công nghệ Thông tin
