<?php
/**
 * Template Name: Page Checkout
 * Template Post Type: page
 * 
 * Hiển thị thông tin đơn đặt phòng và xử lý thanh toán
 * Security fix: Sử dụng booking_hash thay vì ID để tránh IDOR
 * UX fix: Auto-fill thông tin khách hàng từ database
 */

// Get booking by hash (Security fix)
$booking_hash = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';

if (empty($booking_hash)) {
    wp_redirect(home_url('/'));
    exit;
}

// Query booking from database
global $wpdb;
$table_bookings = $wpdb->prefix . 'hotel_bookings';
$booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_bookings} WHERE booking_hash = %s", $booking_hash));

if (!$booking) {
    wp_redirect(home_url('/'));
    exit;
}

// Check if booking is in pending_payment status
if ($booking->status !== 'pending_payment') {
    // Already confirmed or cancelled
    wp_redirect(home_url('/'));
    exit;
}

// Decode JSON data
$pricing_details = json_decode($booking->pricing_details, true);
$guests_info = json_decode($booking->guests_info, true);
$surcharges_details = json_decode($booking->surcharges_details, true);

// Get room info
$table_rooms = $wpdb->prefix . 'hotel_rooms';
$room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_rooms} WHERE id = %d", $booking->room_id));

// Get hotel info
$hotel_title = get_the_title($booking->hotel_id);

// Calculate nights
$check_in_date = new DateTime($booking->check_in);
$check_out_date = new DateTime($booking->check_out);
$num_nights = $check_in_date->diff($check_out_date)->days;

get_header();
?>

<div class="vie-checkout-container">
    <div class="container">
        <div class="vie-checkout-wrapper">

            <!-- Main: Checkout Form (Left) -->
            <main class="vie-checkout-main">
                <h1><?php _e('Thanh toán', 'flavor'); ?></h1>

                <div id="vie-checkout-content">
                    <div class="vie-checkout-step">
                        <h2><?php _e('Thông tin khách hàng', 'flavor'); ?></h2>
                        <form id="vie-checkout-form">
                            <?php wp_nonce_field('vie_checkout_action', 'checkout_nonce'); ?>
                            <input type="hidden" name="booking_hash" value="<?php echo esc_attr($booking_hash); ?>">

                            <div class="vie-form-row">
                                <div class="vie-form-group">
                                    <label><?php _e('Họ tên', 'flavor'); ?> <span class="required">*</span></label>
                                    <input type="text" name="customer_name"
                                        value="<?php echo esc_attr($booking->customer_name); ?>" required>
                                </div>
                            </div>

                            <div class="vie-form-row">
                                <div class="vie-form-group vie-form-half">
                                    <label><?php _e('Số điện thoại', 'flavor'); ?> <span
                                            class="required">*</span></label>
                                    <input type="tel" name="customer_phone"
                                        value="<?php echo esc_attr($booking->customer_phone); ?>" required>
                                </div>
                                <div class="vie-form-group vie-form-half">
                                    <label><?php _e('Email', 'flavor'); ?></label>
                                    <input type="email" name="customer_email"
                                        value="<?php echo esc_attr($booking->customer_email); ?>">
                                </div>
                            </div>

                            <div class="vie-form-row">
                                <div class="vie-form-group">
                                    <label><?php _e('Ghi chú', 'flavor'); ?></label>
                                    <textarea name="customer_note"
                                        rows="4"><?php echo esc_textarea($booking->customer_note); ?></textarea>
                                </div>
                            </div>

                            <div class="vie-form-row" style="margin-top:30px">
                                <label class="vie-checkbox">
                                    <input type="checkbox" name="agree_terms" id="agree_terms" required>
                                    <span><?php _e('Tôi đồng ý với điều khoản và chính sách', 'flavor'); ?></span>
                                </label>
                            </div>

                            <button type="submit" class="vie-btn vie-btn-primary vie-btn-lg"
                                style="width:100%;margin-top:20px" id="btn-confirm-info">
                                <?php _e('Xác nhận thông tin & Thanh toán', 'flavor'); ?>
                            </button>
                        </form>

                        <!-- SePay Payment Section (Hidden initially) -->
                        <div id="vie-sepay-payment" style="display:none;">
                            <?php
                            $sepay = vie_sepay();
                            if ($sepay->is_enabled()):
                                $bank_account_id = $sepay->get_setting('bank_account');
                                $bank = $bank_account_id ? $sepay->get_bank_account($bank_account_id) : null;

                                if ($bank):
                                    $remark = $sepay->get_payment_code($booking->id);
                                    $qr_url = $sepay->generate_qr_url($booking->id, $booking->total_amount);
                                    $formatted_amount = $sepay->format_currency($booking->total_amount);
                                    ?>
                                    <div class="sepay-checkout-box">
                                        <h2><?php _e('Thanh toán qua chuyển khoản', 'flavor'); ?></h2>
                                        <p class="sepay-subtitle">Quét mã QR hoặc chuyển khoản theo thông tin bên dưới</p>

                                        <div class="sepay-content">
                                            <!-- QR Code -->
                                            <div class="sepay-qr">
                                                <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code" id="sepay-qr-image">
                                                <a href="<?php echo esc_url($qr_url . '&download=yes'); ?>"
                                                    class="btn-download-qr" download>
                                                    📥 Tải ảnh QR
                                                </a>
                                            </div>

                                            <!-- Bank Info -->
                                            <div class="sepay-info">
                                                <div class="sepay-bank-logo">
                                                    <img src="<?php echo esc_url($bank['bank']['logo_url']); ?>"
                                                        alt="<?php echo esc_attr($bank['bank']['short_name']); ?>">
                                                </div>
                                                <table class="sepay-table">
                                                    <tr>
                                                        <td>Ngân hàng</td>
                                                        <td><strong><?php echo esc_html($bank['bank']['short_name']); ?></strong>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Chủ TK</td>
                                                        <td><strong><?php echo esc_html($bank['account_holder_name']); ?></strong>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Số TK</td>
                                                        <td>
                                                            <strong
                                                                id="sepay-account-number"><?php echo esc_html($bank['account_number']); ?></strong>
                                                            <button type="button" class="btn-copy"
                                                                onclick="copyText('<?php echo esc_js($bank['account_number']); ?>', this)">📋</button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Số tiền</td>
                                                        <td>
                                                            <strong
                                                                class="sepay-amount"><?php echo esc_html($formatted_amount); ?></strong>
                                                            <button type="button" class="btn-copy"
                                                                onclick="copyText('<?php echo esc_js($booking->total_amount); ?>', this)">📋</button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Nội dung CK</td>
                                                        <td>
                                                            <strong id="sepay-remark"><?php echo esc_html($remark); ?></strong>
                                                            <button type="button" class="btn-copy"
                                                                onclick="copyText('<?php echo esc_js($remark); ?>', this)">📋</button>
                                                        </td>
                                                    </tr>
                                                </table>

                                                <div class="sepay-warning">
                                                    ⚠️ <strong>Lưu ý:</strong> Giữ nguyên nội dung CK
                                                    <strong><?php echo esc_html($remark); ?></strong> để tự động xác nhận.
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Status -->
                                        <div class="sepay-status" id="sepay-status">
                                            <div class="status-waiting">
                                                <div class="spinner"></div>
                                                <span>Đang chờ thanh toán...</span>
                                            </div>
                                        </div>

                                        <!-- Success Message (Hidden) -->
                                        <div class="sepay-success" id="sepay-success" style="display:none;">
                                            <div class="success-icon">✅</div>
                                            <h3>Thanh toán thành công!</h3>
                                            <!-- <p>Mã đặt phòng: <strong><?php echo esc_html($booking->booking_code); ?></strong> -->
                                            </p>
                                            <p>Chúng tôi sẽ liên hệ xác nhận sớm nhất.</p>
                                            <a href="<?php echo home_url('/'); ?>" class="vie-btn vie-btn-primary">Về trang
                                                chủ</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="sepay-error">
                                        <p>⚠️ Chưa cấu hình tài khoản ngân hàng. Vui lòng liên hệ admin.</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="sepay-error">
                                    <p>⚠️ Thanh toán online chưa được kích hoạt.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Sidebar: Booking Summary (Right) -->
            <aside class="vie-checkout-sidebar">
                <div class="vie-order-summary">
                    <h3><?php _e('Tóm tắt đơn hàng', 'flavor'); ?></h3>

                    <div id="vie-booking-summary">
                        <div class="vie-summary-item">
                            <div class="vie-hotel-name"><?php echo esc_html($hotel_title); ?></div>
                            <strong class="vie-room-name"><?php echo esc_html($room->name); ?></strong>
                        </div>

                        <div class="vie-summary-divider"></div>

                        <div class="vie-summary-item">
                            <!-- <span><?php _e('Mã đặt phòng', 'flavor'); ?></span>
                            <strong><?php echo esc_html($booking->booking_code); ?></strong> -->
                        </div>

                        <div class="vie-summary-item">
                            <span><?php _e('Nhận phòng', 'flavor'); ?></span>
                            <strong><?php echo date_i18n('d/m/Y', strtotime($booking->check_in)); ?></strong>
                        </div>

                        <div class="vie-summary-item">
                            <span><?php _e('Trả phòng', 'flavor'); ?></span>
                            <strong><?php echo date_i18n('d/m/Y', strtotime($booking->check_out)); ?></strong>
                        </div>

                        <div class="vie-summary-item">
                            <span><?php _e('Số đêm', 'flavor'); ?></span>
                            <strong><?php echo $num_nights; ?> đêm</strong>
                        </div>

                        <div class="vie-summary-item">
                            <span><?php _e('Số phòng', 'flavor'); ?></span>
                            <strong><?php echo $booking->num_rooms; ?> phòng</strong>
                        </div>

                        <div class="vie-summary-item">
                            <span><?php _e('Số khách', 'flavor'); ?></span>
                            <strong><?php echo $booking->num_adults . ' người lớn'; ?>
                                <?php if ($booking->num_children > 0)
                                    echo ', ' . $booking->num_children . ' trẻ em'; ?></strong>
                        </div>

                        <div class="vie-summary-divider"></div>

                        <div class="vie-summary-item">
                            <span><?php _e('Tiền phòng', 'flavor'); ?></span>
                            <span><?php echo number_format($booking->base_amount, 0, ',', '.') . ' đ'; ?></span>
                        </div>

                        <?php if ($booking->surcharges_amount > 0): ?>
                            <div class="vie-summary-item">
                                <span><?php _e('Phụ thu', 'flavor'); ?></span>
                                <span><?php echo number_format($booking->surcharges_amount, 0, ',', '.') . ' đ'; ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="vie-summary-divider"></div>

                        <div class="vie-summary-item vie-summary-total">
                            <span><?php _e('Tổng tiền', 'flavor'); ?></span>
                            <strong
                                class="vie-total-amount"><?php echo number_format($booking->total_amount, 0, ',', '.') . ' đ'; ?></strong>
                        </div>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</div>

<style>
    .vie-checkout-container {
        padding: 40px 0;
        background: #f9fafb;
    }

    .vie-checkout-wrapper {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 40px;
    }

    .vie-checkout-main {
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vie-checkout-sidebar {
        position: sticky;
        top: 20px;
    }

    .vie-order-summary {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .vie-order-summary h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .vie-summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 14px;
    }

    .vie-summary-item span {
        color: #6b7280;
    }

    .vie-summary-item strong {
        color: #1f2937;
        font-weight: 600;
    }

    .vie-hotel-name {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .vie-room-name {
        font-size: 16px !important;
        display: block;
        margin-bottom: 8px;
    }

    .vie-summary-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 16px 0;
    }

    .vie-summary-total {
        padding-top: 8px;
        font-size: 16px;
    }

    .vie-total-amount {
        color: #3b82f6 !important;
        font-size: 18px !important;
    }

    .vie-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .vie-form-row .vie-form-group {
        grid-column: 1 / -1;
    }

    .vie-form-half {
        grid-column: auto !important;
    }

    .vie-form-group {
        display: flex;
        flex-direction: column;
    }

    .vie-form-group label {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .vie-form-group input,
    .vie-form-group textarea,
    .vie-form-group select {
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
    }

    .vie-form-group input:focus,
    .vie-form-group textarea:focus,
    .vie-form-group select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .vie-form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .vie-payment-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 20px 0;
    }

    .vie-payment-option {
        display: flex;
        align-items: flex-start;
        padding: 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .vie-payment-option:hover {
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .vie-payment-option input[type="radio"] {
        margin-top: 2px;
        margin-right: 12px;
        cursor: pointer;
    }

    .vie-payment-option input[type="radio"]:checked+.vie-payment-label {
        color: #1f2937;
    }

    .vie-payment-label {
        flex: 1;
        color: #6b7280;
    }

    .vie-payment-label strong {
        display: block;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .vie-bank-info {
        background: #eff6ff;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #3b82f6;
        margin-top: 20px;
        display: none;
    }

    .vie-bank-info.active {
        display: block;
    }

    .vie-bank-info h4 {
        margin: 0 0 12px 0;
        font-size: 14px;
        font-weight: 600;
    }

    .vie-bank-info p {
        margin: 0;
        font-size: 13px;
        line-height: 1.8;
        color: #1f2937;
    }

    .vie-checkbox {
        display: flex;
        align-items: center;
        cursor: pointer;
        font-size: 14px;
    }

    .vie-checkbox input[type="checkbox"] {
        margin-right: 8px;
        cursor: pointer;
    }

    .vie-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .vie-btn-primary {
        background: #3b82f6;
        color: #fff;
    }

    .vie-btn-primary:hover {
        background: #2563eb;
    }

    .vie-btn-primary:disabled {
        background: #d1d5db;
        cursor: not-allowed;
    }

    .vie-btn-lg {
        padding: 14px 28px;
        font-size: 16px;
    }

    @media (max-width: 768px) {
        .vie-checkout-wrapper {
            grid-template-columns: 1fr;
        }

        .vie-checkout-sidebar {
            position: static;
        }

        .vie-form-row {
            grid-template-columns: 1fr;
        }

        .vie-form-half {
            grid-column: 1 / -1 !important;
        }
    }

    .required {
        color: #ef4444;
    }

    /* SePay Checkout Styles */
    .sepay-checkout-box {
        margin-top: 30px;
        padding: 25px;
        border: 2px solid #3b82f6;
        border-radius: 12px;
        background: #f0f9ff;
    }

    .sepay-checkout-box h2 {
        margin: 0 0 8px 0;
        color: #1e40af;
    }

    .sepay-subtitle {
        color: #6b7280;
        margin: 0 0 20px 0;
    }

    .sepay-content {
        display: flex;
        gap: 30px;
        align-items: flex-start;
    }

    .sepay-qr {
        flex: 0 0 200px;
        text-align: center;
    }

    .sepay-qr img {
        width: 200px;
        height: 200px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .btn-download-qr {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 16px;
        background: #3b82f6;
        color: #fff;
        text-decoration: none;
        border-radius: 6px;
        font-size: 13px;
    }

    .sepay-info {
        flex: 1;
    }

    .sepay-bank-logo {
        margin-bottom: 15px;
    }

    .sepay-bank-logo img {
        max-height: 40px;
    }

    .sepay-table {
        width: 100%;
        border-collapse: collapse;
    }

    .sepay-table td {
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
        font-size: 14px;
    }

    .sepay-table td:first-child {
        color: #6b7280;
        width: 100px;
    }

    .sepay-table strong {
        color: #1f2937;
    }

    .sepay-amount {
        color: #3b82f6 !important;
        font-size: 18px !important;
    }

    .btn-copy {
        margin-left: 8px;
        padding: 4px 8px;
        border: 1px solid #d1d5db;
        background: #fff;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    .btn-copy:hover {
        background: #f3f4f6;
    }

    .btn-copy.copied {
        background: #d1fae5;
        border-color: #10b981;
    }

    .sepay-warning {
        margin-top: 15px;
        padding: 12px;
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 6px;
        font-size: 13px;
        color: #92400e;
    }

    .sepay-status {
        margin-top: 20px;
        padding: 15px;
        background: #fff;
        border-radius: 8px;
        text-align: center;
    }

    .status-waiting {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #6b7280;
    }

    .spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #e5e7eb;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .sepay-success {
        text-align: center;
        padding: 30px;
    }

    .success-icon {
        font-size: 60px;
        margin-bottom: 15px;
    }

    .sepay-success h3 {
        color: #059669;
        margin: 0 0 15px 0;
    }

    .sepay-error {
        padding: 20px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #dc2626;
    }

    @media (max-width: 640px) {
        .sepay-content {
            flex-direction: column;
            align-items: center;
        }

        .sepay-qr {
            flex: none;
        }

        .sepay-info {
            width: 100%;
        }
    }
</style>

<script>
    jQuery(function ($) { var paymentCheckInterval = null;
        var isPaid = false;

        // Copy text function (with fallback for HTTP)
        window.copyText = function (text, btn) {
            var success = false;

            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    showCopySuccess(btn);
                }).catch(function () {
                    fallbackCopy(text, btn);
                });
            } else {
                fallbackCopy(text, btn);
            }
        };

        // Fallback copy method for HTTP sites
        function fallbackCopy(text, btn) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            try {
                document.execCommand('copy');
                showCopySuccess(btn);
            } catch (e) {
                alert('Không thể sao chép. Vui lòng copy thủ công: ' + text);
            }
            $temp.remove();
        }

        // Show copy success feedback
        function showCopySuccess(btn) {
            $(btn).addClass('copied').text('✓');
            setTimeout(function () {
                $(btn).removeClass('copied').text('📋');
            }, 2000);
        }

        // Form submit - Show SePay payment
        $('#vie-checkout-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $('#btn-confirm-info');
            var $form = $(this);

            // Validate
            if (!$form.find('input[name="customer_name"]').val().trim()) {
                alert('Vui lòng nhập họ tên');
                return;
            }
            if (!$form.find('input[name="customer_phone"]').val().trim()) {
                alert('Vui lòng nhập số điện thoại');
                return;
            }
            if (!$('#agree_terms').is(':checked')) {
                alert('Vui lòng đồng ý với điều khoản');
                return;
            }

            $btn.prop('disabled', true).text('Đang xử lý...');

            // Update customer info via AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'vie_update_booking_info',
                    nonce: $('input[name="checkout_nonce"]').val(),
                    booking_hash: '<?php echo esc_js($booking_hash); ?>',
                    customer_name: $form.find('input[name="customer_name"]').val(),
                    customer_phone: $form.find('input[name="customer_phone"]').val(),
                    customer_email: $form.find('input[name="customer_email"]').val(),
                    customer_note: $form.find('textarea[name="customer_note"]').val()
                },
                success: function (response) {
                    if (response.success) {
                        // Hide form, show SePay payment
                        $('#vie-checkout-form').slideUp(300);
                        $('#vie-sepay-payment').slideDown(300);

                        // Start payment checking
                        startPaymentCheck();
                    } else {
                        alert(response.data ? response.data.message : 'Có lỗi xảy ra');
                        $btn.prop('disabled', false).text('Xác nhận thông tin & Thanh toán');
                    }
                },
                error: function () {
                    alert('Lỗi kết nối. Vui lòng thử lại.');
                    $btn.prop('disabled', false).text('Xác nhận thông tin & Thanh toán');
                }
            });
        });

        // Start payment status checking
        function startPaymentCheck() {
            // Check every 5 seconds
            paymentCheckInterval = setInterval(function () {
                if (isPaid) {
                    clearInterval(paymentCheckInterval);
                    return;
                }
                checkPaymentStatus();
            }, 5000);

            // Also check immediately
            checkPaymentStatus();
        }

        // Check payment status via AJAX
        function checkPaymentStatus() {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'vie_check_booking_payment',
                    nonce: '<?php echo wp_create_nonce('vie_check_payment'); ?>',
                    booking_id: <?php echo intval($booking->id); ?>,
                    booking_hash: '<?php echo esc_js($booking_hash); ?>'
                },
                success: function (response) {
                    if (response.success && response.data.is_paid) {
                        isPaid = true;
                        clearInterval(paymentCheckInterval);
                        showPaymentSuccess();
                    }
                }
            });
        }

        // Show payment success
        function showPaymentSuccess() {
            $('#sepay-status').hide();
            $('.sepay-content').hide();
            $('.sepay-subtitle').hide();
            $('.sepay-checkout-box h2').text('🎉 Thanh toán thành công!');
            $('#sepay-success').fadeIn(500);
        }
    });
</script>

<?php
get_footer();
