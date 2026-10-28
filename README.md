# HỆ THỐNG QUẢN LÝ TUYỂN SINH LỚP 10

## Giới thiệu

Đây là đồ án xây dựng website quản lý tuyển sinh lớp 10 THPT tại TP. Hồ Chí Minh của **Nhóm Một Nhà**.

Hệ thống được xây dựng bằng PHP và MySQL, giúp quản lý một số công việc trong quá trình tuyển sinh như phân bổ chỉ tiêu, xếp phòng thi, phân công chấm thi, nhập điểm và xét tuyển.

Dự án được thực hiện nhằm áp dụng kiến thức đã học vào một hệ thống quản lý thực tế và rèn luyện kỹ năng làm việc nhóm.

## Công nghệ sử dụng

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Cơ sở dữ liệu:** MySQL
- **Môi trường chạy:** XAMPP
- **Công cụ lập trình:** Visual Studio Code
- **Quản lý mã nguồn:** Git, GitHub

## Các chức năng chính

Nhóm tập trung thực hiện 5 chức năng:

1. **Phân bổ và phê duyệt chỉ tiêu tuyển sinh:** Quản lý số lượng tuyển sinh của các trường THPT.
2. **Phân bổ thí sinh vào phòng thi:** Sắp xếp phòng thi và cấp số báo danh cho thí sinh.
3. **Phân công Hội đồng chấm thi:** Quản lý việc phân công cán bộ tham gia chấm thi.
4. **Cập nhật điểm thi theo mã phách:** Nhập và lưu điểm thi của thí sinh.
5. **Phê duyệt kết quả xét tuyển:** Tính điểm, xét tuyển theo nguyện vọng và phê duyệt kết quả.

Ngoài ra, hệ thống còn có chức năng đăng nhập, đăng xuất và phân quyền người dùng.

## Thành viên nhóm

| STT | Họ và tên | Chức năng phụ trách |
|---|---|---|
| 1 | Đoàn Quốc Thái | Phân bổ và phê duyệt chỉ tiêu |
| 2 | Nguyễn Minh Tân | Phân bổ phòng thi và cấp SBD |
| 3 | Nguyễn Minh Nghĩa | Phân công Hội đồng chấm thi |
| 4 | Ngô Hữu Lộc | Cập nhật điểm thi theo mã phách |
| 5 | Trần Huỳnh Minh Nhật | Phê duyệt kết quả xét tuyển |

## Hướng dẫn cài đặt

**Bước 1: Tải dự án từ GitHub**

```bash
git clone https://github.com/MinhNhat1608/TuyenSinhLop10.git
```

Đưa thư mục dự án vào:

```text
C:\xampp\htdocs\TS10_PHP_NhomMotNha
```

**Bước 2: Khởi động XAMPP**

Mở XAMPP và bật hai dịch vụ:

- Apache
- MySQL

**Bước 3: Tạo cơ sở dữ liệu**

Truy cập:

```text
http://localhost/phpmyadmin
```

Tạo database tên `tuyensinh10`, sau đó import file `sql/database.sql`.

**Bước 4: Cấu hình kết nối**

Đổi tên file `config.example.php` thành `config.php` và chỉnh thông tin kết nối database nếu cần.

**Bước 5: Tạo tài khoản đăng nhập**

Mở CMD và chạy:

```cmd
C:\xampp\php\php.exe C:\xampp\htdocs\TS10_PHP_NhomMotNha\tools\create_user.php so_admin "Admin@123456" "Cán bộ Sở" SO
```

Đây là tài khoản mẫu dùng để kiểm thử.

**Bước 6: Chạy website**

Mở trình duyệt và truy cập:

```text
http://localhost/TS10_PHP_NhomMotNha/login.php
```

Đăng nhập để sử dụng các chức năng của hệ thống.

## Làm việc nhóm

Nhóm sử dụng GitHub để quản lý và chia sẻ mã nguồn. Mỗi thành viên thực hiện chức năng được phân công trên nhánh riêng, sau đó đưa lên GitHub để kiểm tra và gộp vào nhánh `main`.

## Ghi chú

Dự án được thực hiện phục vụ mục đích học tập và demo. Một số chức năng vẫn đang được nhóm tiếp tục hoàn thiện.

---

**NHÓM MỘT NHÀ – Đồ án Hệ thống quản lý tuyển sinh lớp 10**
