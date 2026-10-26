# HỆ THỐNG QUẢN LÝ TUYỂN SINH LỚP 10

### Đề tài: Xây dựng hệ thống quản lý tuyển sinh lớp 10 THPT tại TP. Hồ Chí Minh

**Nhóm thực hiện:** NHÓM MỘT NHÀ

---

## 1. Giới thiệu dự án

Hệ thống Quản lý Tuyển sinh Lớp 10 là ứng dụng web được xây dựng nhằm hỗ trợ quản lý và tổ chức quy trình tuyển sinh vào lớp 10 tại các trường THPT trên địa bàn TP. Hồ Chí Minh.

Hệ thống hướng đến việc số hóa các nghiệp vụ tuyển sinh, từ quản lý hồ sơ thí sinh, đăng ký nguyện vọng, phân bổ chỉ tiêu, tổ chức kỳ thi, quản lý điểm thi đến xét tuyển và công bố kết quả.

Thông qua hệ thống, các đơn vị quản lý giáo dục, nhà trường và các đối tượng tham gia tuyển sinh có thể thực hiện nghiệp vụ theo vai trò được phân quyền, góp phần nâng cao tính chính xác, minh bạch và hiệu quả trong quá trình tuyển sinh.

## 2. Mục tiêu dự án

- Xây dựng hệ thống quản lý tuyển sinh lớp 10 trên nền tảng web.
- Hỗ trợ quản lý thông tin thí sinh, hồ sơ dự thi và nguyện vọng đăng ký.
- Quản lý chỉ tiêu tuyển sinh của các trường THPT.
- Hỗ trợ phân bổ thí sinh vào địa điểm và phòng thi.
- Quản lý công tác coi thi, chấm thi và cập nhật điểm thi.
- Hỗ trợ xử lý phúc khảo và xét tuyển theo nguyện vọng.
- Quản lý kết quả trúng tuyển và xác nhận nhập học.
- Phân quyền người dùng theo chức năng và trách nhiệm nghiệp vụ.

## 3. Công nghệ sử dụng

| Thành phần | Công nghệ |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Cơ sở dữ liệu | MySQL |
| Môi trường phát triển | XAMPP |
| Công cụ lập trình | Visual Studio Code |
| Quản lý mã nguồn | Git, GitHub |
| Quản lý cơ sở dữ liệu | phpMyAdmin |

## 4. Các chức năng của hệ thống

Hệ thống được phân tích và thiết kế theo các nhóm nghiệp vụ chính:

### 4.1. Quản lý tuyển sinh

- Quản lý hồ sơ dự thi của thí sinh.
- Quản lý chỉ tiêu tuyển sinh tại các trường THPT.
- Đăng ký và xác nhận nguyện vọng.
- Quản lý thông tin trường THCS và THPT.

### 4.2. Quản lý tổ chức kỳ thi

- Quản lý kỳ thi và địa điểm thi.
- Phân bổ thí sinh vào phòng thi.
- Cấp số báo danh cho thí sinh.
- Phân công nhân sự coi thi.
- Quản lý hội đồng ra đề và hội đồng chấm thi.

### 4.3. Quản lý chấm thi và phúc khảo

- Quản lý mã phách và mã bì bài thi.
- Phân công cán bộ chấm thi.
- Cập nhật và kiểm tra điểm thi.
- Quản lý kết quả phúc khảo.
- Theo dõi lịch sử điều chỉnh điểm.

### 4.4. Xét tuyển và nhập học

- Tổng hợp dữ liệu phục vụ xét tuyển.
- Xử lý kết quả xét tuyển theo thứ tự nguyện vọng.
- Phê duyệt kết quả tuyển sinh.
- Quản lý danh sách thí sinh trúng tuyển.
- Xác nhận nhập học tại trường THPT.

### 4.5. Quản trị hệ thống

- Đăng nhập và đăng xuất.
- Quản lý tài khoản người dùng.
- Phân quyền truy cập theo vai trò.
- Quản lý dữ liệu phục vụ tuyển sinh.
- Ghi nhận nhật ký hoạt động của hệ thống.

## 5. Các chức năng đang phát triển

Trong giai đoạn triển khai đầu tiên, nhóm tập trung xây dựng và tích hợp năm chức năng nghiệp vụ:

| Thành viên | Chức năng phụ trách | File PHP |
|---|---|---|
| Đoàn Quốc Thái | Phân bổ và phê duyệt chỉ tiêu tuyển sinh | `thai.php` |
| Nguyễn Minh Tân | Phân bổ thí sinh vào phòng thi và cấp số báo danh | `tan.php` |
| Nguyễn Minh Nghĩa | Phân công Hội đồng chấm thi | `nghia.php` |
| Ngô Hữu Lộc | Cập nhật điểm thi theo mã phách | `loc.php` |
| Trần Huỳnh Minh Nhật | Phê duyệt kết quả xét tuyển | `nhat.php` |

Mỗi thành viên phát triển chức năng trên một nhánh Git riêng trước khi tích hợp vào nhánh chính.

## 6. Cấu trúc thư mục dự án

```text
TS10_PHP_NhomMotNha/
│
├── assets/
│   └── style.css
│
├── includes/
│   ├── bootstrap.php
│   ├── layout.php
│   └── import_csv.php
│
├── sql/
│   └── database.sql
│
├── mau_csv/
│   ├── thai.csv
│   ├── tan.csv
│   ├── nghia.csv
│   ├── loc.csv
│   └── nhat.csv
│
├── tools/
│   └── create_user.php
│
├── team/
│
├── config.example.php
├── login.php
├── logout.php
├── index.php
├── du_lieu.php
├── thai.php
├── tan.php
├── nghia.php
├── loc.php
├── nhat.php
└── README.md
```

## 7. Hướng dẫn cài đặt và chạy dự án

### Bước 1: Tải mã nguồn

```bash
git clone https://github.com/MinhNhat1608/TuyenSinhLop10.git
```

Chuyển thư mục dự án vào:

```text
C:\xampp\htdocs\TS10_PHP_NhomMotNha
```

### Bước 2: Khởi động XAMPP

Mở XAMPP Control Panel và khởi động:

- Apache
- MySQL

### Bước 3: Khởi tạo cơ sở dữ liệu

Truy cập:

```text
http://localhost/phpmyadmin
```

Tạo cơ sở dữ liệu có tên:

```sql
CREATE DATABASE IF NOT EXISTS tuyensinh10
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Sau đó chọn database `tuyensinh10` và import file `sql/database.sql`.

### Bước 4: Cấu hình kết nối

Sao chép file:

```text
config.example.php
```

thành:

```text
config.php
```

Cập nhật thông tin kết nối MySQL phù hợp với môi trường máy tính.

### Bước 5: Tạo tài khoản quản trị ban đầu

Mở CMD và chạy:

```cmd
C:\xampp\php\php.exe C:\xampp\htdocs\TS10_PHP_NhomMotNha\tools\create_user.php so_admin "Admin@123456" "Cán bộ Sở" SO
```

Tài khoản trên chỉ dùng để khởi tạo và kiểm thử trong môi trường phát triển; cần thay mật khẩu khi sử dụng thực tế.

### Bước 6: Truy cập hệ thống

Mở trình duyệt và truy cập:

```text
http://localhost/TS10_PHP_NhomMotNha/login.php
```

Đăng nhập bằng tài khoản đã tạo để sử dụng các chức năng được phân quyền.

## 8. Quy trình làm việc nhóm trên GitHub

Dự án sử dụng Git và GitHub để quản lý mã nguồn, theo dõi thay đổi và tích hợp chức năng giữa các thành viên.

Các nhánh phát triển:

| Nhánh | Thành viên phụ trách |
|---|---|
| `main` | Mã nguồn tích hợp chung |
| `feature/thai` | Đoàn Quốc Thái |
| `feature/tan` | Nguyễn Minh Tân |
| `feature/nghia` | Nguyễn Minh Nghĩa |
| `feature/loc` | Ngô Hữu Lộc |
| `feature/nhat` | Trần Huỳnh Minh Nhật |

Quy trình làm việc:

1. Cập nhật mã nguồn mới nhất từ nhánh `main`.
2. Phát triển chức năng trên nhánh cá nhân.
3. Kiểm tra và commit các thay đổi.
4. Push mã nguồn lên GitHub.
5. Tạo Pull Request để nhóm kiểm tra.
6. Tích hợp chức năng vào nhánh `main` sau khi được thống nhất.

## 9. Trạng thái phát triển

Dự án đang trong giai đoạn phát triển và tích hợp chức năng.

Phiên bản hiện tại tập trung vào giao diện web, xác thực người dùng, kết nối cơ sở dữ liệu và năm nghiệp vụ được phân công. Các chức năng chuyên sâu như chấm thi độc lập, ghép phách, phúc khảo và xét tuyển tự động đầy đủ sẽ tiếp tục được hoàn thiện theo tài liệu đặc tả.

## 10. Nhóm phát triển

**NHÓM MỘT NHÀ**

- Đoàn Quốc Thái
- Nguyễn Minh Tân
- Nguyễn Minh Nghĩa
- Ngô Hữu Lộc
- Trần Huỳnh Minh Nhật

**Dự án học tập – Hệ thống quản lý tuyển sinh lớp 10 THPT tại TP. Hồ Chí Minh.**
