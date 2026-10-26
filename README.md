# TS10 – NHÓM MỘT NHÀ – PHP + MYSQL

## 1. Chạy bằng XAMPP (Windows)
1. Giải nén thư mục `TS10_PHP_NhomMotNha` vào `C:\xampp\htdocs\`.
2. Mở XAMPP, Start **Apache** và **MySQL**.
3. Mở `http://localhost/phpmyadmin`, tab **Import**, chọn `sql/database.sql`.
4. Bản này đã có sẵn `config.php` dùng cho XAMPP (MySQL root, không mật khẩu, database tuyensinh10). Nếu máy dùng cấu hình khác, sửa `config.php` cho phù hợp. Khi cập nhật vào dự án đang chạy, GIỮ file `config.php` cũ của bạn nếu đã hoạt động.
5. Mở CMD, chạy (ví dụ):
   `C:\xampp\php\php.exe C:\xampp\htdocs\TS10_PHP_NhomMotNha\tools\create_user.php so_admin "Admin@123456" "Cán bộ Sở" SO`
6. Vào `http://localhost/TS10_PHP_NhomMotNha/login.php` và đăng nhập bằng tài khoản bạn vừa tạo.

Không có đăng nhập bằng mật khẩu bất kỳ, không có tài khoản hardcode. Password hash PHP + session + chống CSRF; queries PDO prepared statements.

## 2. Chia việc – mỗi người chỉnh file riêng
| Thành viên | Trang | Chức năng |
|---|---|---|
| Đoàn Quốc Thái | `thai.php` | Phân bổ và duyệt chỉ tiêu (QT05) |
| Nguyễn Minh Tân | `tan.php` | Phân bổ phòng thi và SBD (QT06) |
| Nguyễn Minh Nghĩa | `nghia.php` | Phân công hội đồng chấm thi |
| Ngô Hữu Lộc | `loc.php` | Nhập điểm theo mã phách (QT07–08) |
| Trần Huỳnh Minh Nhật | `nhat.php` | Phê duyệt kết quả xét tuyển (QT10) |

**Không tự ý thay** `includes/bootstrap.php`, `includes/layout.php`, `assets/style.css`, `sql/database.sql` khi đang làm riêng; đề xuất thay đổi thì thống nhất trước.

## 3. Nhập dữ liệu
Trang `du_lieu.php` dành cho người có vai trò `SO` để nhập danh mục dữ liệu trong giai đoạn demo. Không tự chèn dữ liệu mẫu. Các bản ghi có khóa ngoại cần nhập theo thứ tự.

## 4. Giới hạn phải làm tiếp trước khi bảo vệ
- Đây là **bộ khung PHP/MySQL chạy được**, không phải triển khai hoàn chỉnh toàn bộ đặc tả hay toàn bộ 40 bảng báo cáo. Schema ở đây là tập con phục vụ đúng 5 màn hình code đầu tiên.
- QT06: danh sách được sắp xếp họ tên tiếng Việt (nếu PHP có Intl thì dùng Collator), giới hạn 24, không trùng thí sinh/SBD, xác nhận sau khi xếp. Quy tắc phân bổ địa điểm theo trường THCS gần nhau chưa có nguồn địa bàn để tự động kiểm tra.
- QT08: cột `da_thong_nhat_diem=1` là kết quả của quy trình **hai cán bộ chấm độc lập + đối chiếu**. Bộ khung chưa có màn hình cho hai cán bộ nhập hai lượt và đối chiếu, do đó **không được tự đánh dấu 1 khi chưa có bằng chứng**. Chỉ màn thư ký được nhập điểm một lần và Sở chốt.
- QT07: phân quyền hạn chế trang nhạy cảm, nhưng chưa triển khai mã hóa ánh xạ mã phách–SBD ở mức database. **Không đưa dữ liệu thật lên bản demo này** cho đến khi hoàn thành bảo mật theo QT07.
- QT10: màn Nhật **chỉ phê duyệt** một kết quả dự kiến được nhập từ nguồn có kiểm tra; thuật toán tạo kết quả NV1→NV3, xếp hạng, xử lý đồng điểm/ưu tiên và đối soát đầy đủ môn thi **chưa có**. Cần code thêm theo đặc tả trước khi tuyên bố kết quả chính thức.
- Các nghiệp vụ phúc khảo, ghép phách, tạo kết quả xét tuyển tự động, xác thực vai trò chi tiết trong RBAC đầy đủ... chưa nằm trong 5 chức năng giai đoạn đầu.
- Không sử dụng kết quả của bản demo cho tuyển sinh thực tế.


## Nhập file ngay trong từng giao diện (bản cập nhật)
- **Thái / thai.php:** nhập CSV chỉ tiêu trạng thái DỰ KIẾN, duyệt riêng trên màn hình.
- **Tân / tan.php:** nhập CSV phân bổ thí sinh hợp lệ (cần kỳ thi, phòng thi, thí sinh đã có trong CSDL). Mỗi file dùng mã SBD duy nhất.
- **Nghĩa / nghia.php:** nhập CSV phân công hai cán bộ khác nhau theo túi bài.
- **Lộc / loc.php:** thư ký nhập CSV điểm theo mã phách; chỉ cho phép những bài đã được hai cán bộ thống nhất, không tự bật `da_thong_nhat_diem`.
- **Nhật / nhat.php:** nhập CSV danh sách kết quả xét tuyển DỰ KIẾN được lập và kiểm tra trước; phê duyệt riêng theo nghiệp vụ. File không thay thế thuật toán xét tuyển NV1→NV3.

### Cách thực hiện
1. Vào giao diện của thành viên cần nhập; nhấn **Tải CSV mẫu cho chức năng này**.
2. Mở CSV bằng Excel, điền từng hàng bên dưới tiêu đề, giữ nguyên tên và thứ tự cột.
3. Trong Excel, **Save As → CSV UTF-8 (Comma delimited) (*.csv)**.
4. Ngay trên trang PHP đó, nhấn **Chọn file** → **Kiểm tra và nhập MySQL**.
5. Nếu một dòng bị lỗi, toàn bộ file không được lưu; thông báo nêu số dòng sai. Không import trực tiếp SQL hoặc Excel .xlsx.

**Dữ liệu nền** (kỳ thi, trường, hệ xét tuyển, thí sinh, phòng thi, nhân sự, túi bài, nguyện vọng) vẫn phải tồn tại trước khi nhập các chức năng tương ứng. `du_lieu.php` hiện vẫn là trang quản trị dữ liệu nền phục vụ demo, không phải giao diện nghiệp vụ chuẩn của từng vai trò.
