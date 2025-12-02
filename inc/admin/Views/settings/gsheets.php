<?php
/**
 * Admin View: Settings - Google Sheets Tab
 *
 * @package VielimousineChild
 * @since   2.1.0
 */

defined('ABSPATH') || exit;

// Check if service account is configured
$has_credentials = !empty($settings['service_account_json']);
?>

<form method="post" id="gsheets-settings-form">
    <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>

    <?php if ($has_credentials && $is_connected) : ?>
        <div class="notice notice-success inline">
            <p>
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Đã kết nối Google Sheets thành công!', 'vielimousine'); ?>
            </p>
        </div>
    <?php elseif ($has_credentials) : ?>
        <div class="notice notice-info inline">
            <p>
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('Đã cấu hình Service Account. Nhấn "Test Connection" để kiểm tra.', 'vielimousine'); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-warning inline">
            <p>
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Chưa cấu hình Google Service Account', 'vielimousine'); ?>
            </p>
        </div>
    <?php endif; ?>

    <h3><?php esc_html_e('1. Cấu hình Service Account', 'vielimousine'); ?></h3>
    <p class="description">
        <?php esc_html_e('Tải Service Account JSON từ Google Cloud Console > IAM & Admin > Service Accounts', 'vielimousine'); ?>
        <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">
            <?php esc_html_e('(Mở Google Cloud Console)', 'vielimousine'); ?>
        </a>
    </p>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Service Account JSON -->
            <tr>
                <th scope="row">
                    <label for="service_account_json"><?php esc_html_e('Service Account JSON', 'vielimousine'); ?></label>
                </th>
                <td>
                    <textarea
                        id="service_account_json"
                        name="service_account_json"
                        rows="10"
                        class="large-text code"
                        placeholder='{
  "type": "service_account",
  "project_id": "your-project",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "...@...iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token"
}'><?php echo esc_textarea($settings['service_account_json'] ?? ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Copy toàn bộ nội dung file JSON service account và paste vào đây.', 'vielimousine'); ?>
                        <br>
                        <strong><?php esc_html_e('Lưu ý bảo mật:', 'vielimousine'); ?></strong>
                        <?php esc_html_e('File JSON chứa private key, không chia sẻ với ai.', 'vielimousine'); ?>
                    </p>

                    <?php if ($has_credentials) : ?>
                        <p class="description" style="color: #46b450;">
                            <span class="dashicons dashicons-yes"></span>
                            <?php
                            $creds = json_decode($settings['service_account_json'], true);
                            if ($creds && isset($creds['client_email'])) {
                                printf(
                                    esc_html__('Service Account Email: %s', 'vielimousine'),
                                    '<code>' . esc_html($creds['client_email']) . '</code>'
                                );
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <hr>

    <h3><?php esc_html_e('2. Cấu hình Google Sheets', 'vielimousine'); ?></h3>
    <p class="description">
        <?php esc_html_e('Sau khi tạo Service Account, chia sẻ Google Sheet với email của Service Account (quyền Editor).', 'vielimousine'); ?>
    </p>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Spreadsheet ID -->
            <tr>
                <th scope="row">
                    <label for="spreadsheet_id"><?php esc_html_e('Spreadsheet ID', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="spreadsheet_id"
                           name="spreadsheet_id"
                           value="<?php echo esc_attr($settings['spreadsheet_id'] ?? ''); ?>"
                           class="large-text"
                           placeholder="1abc123XYZ456-example_sheet_id">
                    <p class="description">
                        <?php esc_html_e('Lấy từ URL Google Sheets:', 'vielimousine'); ?>
                        <code>https://docs.google.com/spreadsheets/d/<strong>{SHEET_ID}</strong>/edit</code>
                    </p>
                </td>
            </tr>

            <!-- Sheet Name -->
            <tr>
                <th scope="row">
                    <label for="sheet_name"><?php esc_html_e('Tên Sheet (Tab)', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="sheet_name"
                           name="sheet_name"
                           value="<?php echo esc_attr($settings['sheet_name'] ?? 'Coupons'); ?>"
                           class="regular-text"
                           placeholder="Coupons">
                    <p class="description">
                        <?php esc_html_e('Tên tab trong Google Sheets (ví dụ: Coupons, Bookings, Rooms)', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Sheet Range -->
            <tr>
                <th scope="row">
                    <label for="sheet_range"><?php esc_html_e('Sheet Range', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="sheet_range"
                           name="sheet_range"
                           value="<?php echo esc_attr($settings['sheet_range'] ?? 'A2:G1000'); ?>"
                           class="regular-text"
                           placeholder="A2:G1000">
                    <p class="description">
                        <?php esc_html_e('Range của dữ liệu (không bao gồm tên sheet). Ví dụ: A2:G1000', 'vielimousine'); ?>
                        <br>
                        <?php esc_html_e('Hệ thống sẽ tự ghép thành:', 'vielimousine'); ?>
                        <code id="full-range-preview"></code>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <?php submit_button(__('Lưu cài đặt', 'vielimousine'), 'primary', 'submit', false); ?>

        <?php if ($has_credentials) : ?>
            <button type="button" id="test-connection-btn" class="button button-secondary" style="margin-left: 10px;">
                <span class="dashicons dashicons-cloud"></span>
                <?php esc_html_e('Test Connection', 'vielimousine'); ?>
            </button>
        <?php endif; ?>
    </p>
</form>

<hr style="margin: 40px 0;">

<!-- Hướng dẫn chi tiết -->
<div class="vie-setup-guide">
    <h2><?php esc_html_e('📖 Hướng dẫn cài đặt Google Service Account', 'vielimousine'); ?></h2>

    <div class="vie-guide-steps">
        <div class="vie-step">
            <h3>
                <span class="vie-step-number">1</span>
                <?php esc_html_e('Tạo Google Cloud Project', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Truy cập <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Nhấn <strong>"Select a project"</strong> → <strong>"New Project"</strong></li>
                <li>Nhập tên project (ví dụ: "Vie Limousine Booking")</li>
                <li>Nhấn <strong>"Create"</strong></li>
            </ol>
        </div>

        <div class="vie-step">
            <h3>
                <span class="vie-step-number">2</span>
                <?php esc_html_e('Kích hoạt Google Sheets API', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Trong project vừa tạo, vào menu <strong>"APIs & Services"</strong> → <strong>"Library"</strong></li>
                <li>Tìm kiếm <strong>"Google Sheets API"</strong></li>
                <li>Nhấn vào kết quả → <strong>"Enable"</strong></li>
            </ol>
        </div>

        <div class="vie-step">
            <h3>
                <span class="vie-step-number">3</span>
                <?php esc_html_e('Tạo Service Account', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Vào menu <strong>"IAM & Admin"</strong> → <strong>"Service Accounts"</strong>
                    (<a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">hoặc nhấn vào đây</a>)</li>
                <li>Nhấn <strong>"+ CREATE SERVICE ACCOUNT"</strong></li>
                <li>Nhập thông tin:
                    <ul>
                        <li><strong>Service account name:</strong> vie-sheets-service</li>
                        <li><strong>Service account ID:</strong> (tự động)</li>
                        <li><strong>Description:</strong> Service account for Google Sheets integration</li>
                    </ul>
                </li>
                <li>Nhấn <strong>"Create and Continue"</strong></li>
                <li>Bỏ qua phần "Grant access" → Nhấn <strong>"Continue"</strong> → <strong>"Done"</strong></li>
            </ol>
        </div>

        <div class="vie-step">
            <h3>
                <span class="vie-step-number">4</span>
                <?php esc_html_e('Tải JSON Credentials', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Trong danh sách Service Accounts, nhấn vào service account vừa tạo</li>
                <li>Vào tab <strong>"Keys"</strong></li>
                <li>Nhấn <strong>"Add Key"</strong> → <strong>"Create new key"</strong></li>
                <li>Chọn <strong>"JSON"</strong> → <strong>"Create"</strong></li>
                <li>File JSON sẽ được tải về máy tính của bạn</li>
                <li>Mở file JSON bằng Notepad/TextEdit, copy toàn bộ nội dung và paste vào ô <strong>"Service Account JSON"</strong> ở trên</li>
            </ol>
        </div>

        <div class="vie-step">
            <h3>
                <span class="vie-step-number">5</span>
                <?php esc_html_e('Tạo Google Sheets và Share với Service Account', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Tạo Google Sheets mới tại <a href="https://sheets.google.com" target="_blank">sheets.google.com</a></li>
                <li>Đặt tên sheet tab (ví dụ: <strong>"Coupons"</strong>)</li>
                <li>Tạo cấu trúc dữ liệu theo bảng mẫu bên dưới</li>
                <li>Nhấn nút <strong>"Share"</strong> (góc trên bên phải)</li>
                <li>Paste <strong>Service Account Email</strong> (có dạng: xxx@xxx.iam.gserviceaccount.com)<br>
                    <em>Email này sẽ hiển thị sau khi bạn lưu Service Account JSON ở bước 6</em></li>
                <li>Chọn quyền <strong>"Editor"</strong></li>
                <li>Bỏ tick <strong>"Notify people"</strong></li>
                <li>Nhấn <strong>"Share"</strong></li>
                <li>Copy Spreadsheet ID từ URL (phần giữa <code>/d/</code> và <code>/edit</code>)</li>
            </ol>
        </div>

        <div class="vie-step">
            <h3>
                <span class="vie-step-number">6</span>
                <?php esc_html_e('Cấu hình trong WordPress', 'vielimousine'); ?>
            </h3>
            <ol>
                <li>Paste nội dung JSON vào ô <strong>"Service Account JSON"</strong></li>
                <li>Nhập <strong>Spreadsheet ID</strong></li>
                <li>Nhập <strong>Tên Sheet</strong> (ví dụ: Coupons)</li>
                <li>Nhập <strong>Sheet Range</strong> (ví dụ: A2:G1000)</li>
                <li>Nhấn <strong>"Lưu cài đặt"</strong></li>
                <li>Nhấn <strong>"Test Connection"</strong> để kiểm tra kết nối</li>
            </ol>
        </div>
    </div>
</div>

<hr style="margin: 40px 0;">

<!-- Bảng dữ liệu mẫu -->
<div class="vie-sample-data">
    <h2><?php esc_html_e('📊 Cấu trúc bảng dữ liệu mẫu', 'vielimousine'); ?></h2>

    <p class="description">
        <?php esc_html_e('Tạo Google Sheets với cấu trúc dưới đây. Dòng 1 là header, dữ liệu bắt đầu từ dòng 2.', 'vielimousine'); ?>
    </p>

    <h3><?php esc_html_e('Ví dụ 1: Sheet Coupons (Mã giảm giá) - Cấu trúc 4 cột', 'vielimousine'); ?></h3>
    <div class="vie-table-wrapper">
        <table class="vie-sample-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>A: Mã</th>
                    <th>B: Giá trị (VNĐ)</th>
                    <th>C: Đã dùng lúc</th>
                    <th>D: Dùng bởi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>SUMMER2024</code></td>
                    <td>200000</td>
                    <td>01/12/2025 10:30</td>
                    <td>Nguyễn Văn A - 0912345678</td>
                </tr>
                <tr>
                    <td><code>WELCOME500</code></td>
                    <td>500000</td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td><code>VIP2024</code></td>
                    <td>300000</td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="vie-field-descriptions">
        <h4><?php esc_html_e('Giải thích các cột:', 'vielimousine'); ?></h4>
        <ul>
            <li><strong>A - Mã:</strong> Mã coupon (chữ in hoa, không dấu, không khoảng trắng). Ví dụ: VIP2024, WELCOME500</li>
            <li><strong>B - Giá trị (VNĐ):</strong> Số tiền giảm cố định (không có dấu phẩy). Ví dụ: 500000 = giảm 500.000đ</li>
            <li><strong>C - Đã dùng lúc:</strong> Thời gian sử dụng (để trống nếu chưa dùng). Hệ thống tự động cập nhật khi khách apply</li>
            <li><strong>D - Dùng bởi:</strong> Thông tin khách hàng (để trống nếu chưa dùng). Hệ thống tự động ghi "Tên - SĐT"</li>
        </ul>
        <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <p style="margin: 0;"><strong>💡 Lưu ý quan trọng:</strong></p>
            <ul style="margin: 10px 0 0 20px;">
                <li>Mỗi mã chỉ dùng được <strong>1 lần duy nhất</strong></li>
                <li>Cột C và D <strong>phải để trống</strong> cho mã chưa sử dụng</li>
                <li>Sau khi khách apply, hệ thống sẽ tự động ghi thời gian và thông tin khách vào cột C và D</li>
                <li>Giá trị giảm là <strong>số tiền cố định</strong> (VNĐ), không phải phần trăm</li>
            </ul>
        </div>
    </div>

    <h3 style="margin-top: 30px;"><?php esc_html_e('Ví dụ 2: Sheet Bookings (Đặt phòng)', 'vielimousine'); ?></h3>
    <div class="vie-table-wrapper">
        <table class="vie-sample-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>A: Mã booking</th>
                    <th>B: Tên khách</th>
                    <th>C: SĐT</th>
                    <th>D: Email</th>
                    <th>E: Check-in</th>
                    <th>F: Check-out</th>
                    <th>G: Loại phòng</th>
                    <th>H: Tổng tiền</th>
                    <th>I: Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>VIE-251201-A1B2</code></td>
                    <td>Nguyễn Văn A</td>
                    <td>0912345678</td>
                    <td>nguyenvana@email.com</td>
                    <td>15/12/2024</td>
                    <td>17/12/2024</td>
                    <td>Deluxe Room</td>
                    <td>5000000</td>
                    <td>confirmed</td>
                </tr>
                <tr>
                    <td><code>VIE-251201-C3D4</code></td>
                    <td>Trần Thị B</td>
                    <td>0987654321</td>
                    <td>tranthib@email.com</td>
                    <td>20/12/2024</td>
                    <td>25/12/2024</td>
                    <td>Suite Room</td>
                    <td>12000000</td>
                    <td>pending</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 style="margin-top: 30px;"><?php esc_html_e('Ví dụ 3: Sheet Rooms (Danh sách phòng)', 'vielimousine'); ?></h3>
    <div class="vie-table-wrapper">
        <table class="vie-sample-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>A: Room ID</th>
                    <th>B: Tên phòng</th>
                    <th>C: Giá cơ bản</th>
                    <th>D: Sức chứa</th>
                    <th>E: Mô tả</th>
                    <th>F: Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>101</td>
                    <td>Deluxe Room</td>
                    <td>2500000</td>
                    <td>2</td>
                    <td>Phòng Deluxe với view biển</td>
                    <td>available</td>
                </tr>
                <tr>
                    <td>201</td>
                    <td>Suite Room</td>
                    <td>4500000</td>
                    <td>4</td>
                    <td>Suite cao cấp với phòng khách riêng</td>
                    <td>available</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="vie-tips">
        <h4><?php esc_html_e('💡 Lưu ý quan trọng:', 'vielimousine'); ?></h4>
        <ul>
            <li>Dòng 1 luôn là header (tiêu đề cột)</li>
            <li>Dữ liệu thực tế bắt đầu từ dòng 2</li>
            <li>Không để ô trống ở cột quan trọng (Mã, ID, Tên)</li>
            <li>Định dạng ngày: dd/mm/yyyy (ví dụ: 31/12/2024)</li>
            <li>Số tiền: Không có dấu phẩy, chỉ số (ví dụ: 5000000)</li>
            <li>Service Account cần quyền <strong>Editor</strong> để có thể đọc và ghi</li>
            <li>Range nên để dư (ví dụ: A2:G1000) để không phải thay đổi khi thêm dữ liệu</li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Update full range preview
    function updateRangePreview() {
        var sheetName = $('#sheet_name').val() || 'Coupons';
        var range = $('#sheet_range').val() || 'A2:G1000';
        $('#full-range-preview').text(sheetName + '!' + range);
    }

    $('#sheet_name, #sheet_range').on('input', updateRangePreview);
    updateRangePreview();

    // Save settings
    $('#gsheets-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('input[type="submit"]');
        var formData = $form.serialize() + '&action=vie_save_gsheets_settings';

        $button.prop('disabled', true).val('<?php esc_attr_e('Đang lưu...', 'vielimousine'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Đã lưu cài đặt thành công!', 'vielimousine'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).val('<?php esc_attr_e('Lưu cài đặt', 'vielimousine'); ?>');
            }
        });
    });

    // Test connection
    $('#test-connection-btn').on('click', function() {
        var $btn = $(this);
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php esc_attr_e('Đang kiểm tra...', 'vielimousine'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vie_test_gsheets_connection',
                nonce: $('input[name="nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + (response.data.message || '<?php esc_html_e('Kết nối thành công!', 'vielimousine'); ?>'));
                } else {
                    alert('❌ ' + (response.data.message || '<?php esc_html_e('Kết nối thất bại', 'vielimousine'); ?>'));
                }
            },
            error: function() {
                alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>

<style>
#service_account_json {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
.notice.inline {
    margin: 0 0 20px 0;
    padding: 10px 15px;
}
.notice.inline .dashicons {
    vertical-align: middle;
    margin-right: 5px;
}

/* Hướng dẫn setup */
.vie-setup-guide {
    background: #f9f9f9;
    padding: 30px;
    border-radius: 8px;
    margin-top: 20px;
}

.vie-setup-guide h2 {
    color: #2271b1;
    margin-top: 0;
    font-size: 24px;
}

.vie-guide-steps {
    margin-top: 30px;
}

.vie-step {
    background: white;
    padding: 25px;
    margin-bottom: 20px;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vie-step h3 {
    color: #1d2327;
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.vie-step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #2271b1;
    color: white;
    border-radius: 50%;
    font-size: 16px;
    font-weight: bold;
}

.vie-step ol {
    margin-left: 0;
    padding-left: 20px;
}

.vie-step li {
    margin-bottom: 10px;
    line-height: 1.6;
}

.vie-step ul {
    margin-top: 8px;
    margin-bottom: 8px;
}

.vie-step a {
    color: #2271b1;
    text-decoration: none;
}

.vie-step a:hover {
    text-decoration: underline;
}

.vie-step code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

/* Bảng dữ liệu mẫu */
.vie-sample-data {
    background: #f9f9f9;
    padding: 30px;
    border-radius: 8px;
    margin-top: 20px;
}

.vie-sample-data h2 {
    color: #2271b1;
    margin-top: 0;
    font-size: 24px;
}

.vie-sample-data h3 {
    color: #1d2327;
    font-size: 18px;
    margin-top: 30px;
    margin-bottom: 15px;
}

.vie-table-wrapper {
    overflow-x: auto;
    margin: 20px 0;
    background: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vie-sample-table {
    margin: 0;
}

.vie-sample-table th {
    background: #2271b1;
    color: white;
    font-weight: 600;
    padding: 12px;
    text-align: left;
}

.vie-sample-table td {
    padding: 10px 12px;
}

.vie-sample-table code {
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: #d63638;
    font-weight: 600;
}

.vie-field-descriptions {
    background: white;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.vie-field-descriptions h4 {
    margin-top: 0;
    color: #1d2327;
    font-size: 16px;
}

.vie-field-descriptions ul {
    margin: 10px 0;
    padding-left: 20px;
}

.vie-field-descriptions li {
    margin-bottom: 8px;
    line-height: 1.6;
}

.vie-field-descriptions code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.vie-tips {
    background: #fffbcc;
    border-left: 4px solid #f0b429;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
}

.vie-tips h4 {
    margin-top: 0;
    color: #1d2327;
    font-size: 16px;
}

.vie-tips ul {
    margin: 10px 0 0;
    padding-left: 20px;
}

.vie-tips li {
    margin-bottom: 8px;
    line-height: 1.6;
}
</style>
