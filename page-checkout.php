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

                            <h2 style="margin-top:40px"><?php _e('Phương thức thanh toán', 'flavor'); ?></h2>

                            <div class="vie-payment-methods">
                                <label class="vie-payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer" checked>
                                    <span class="vie-payment-label">
                                        <strong><?php _e('Chuyển khoản ngân hàng', 'flavor'); ?></strong>
                                        <small><?php _e('Vui lòng chuyển khoản theo thông tin bên dưới', 'flavor'); ?></small>
                                    </span>
                                </label>

                                <label class="vie-payment-option">
                                    <input type="radio" name="payment_method" value="cash">
                                    <span class="vie-payment-label">
                                        <strong><?php _e('Thanh toán tại chỗ', 'flavor'); ?></strong>
                                        <small><?php _e('Thanh toán khi nhận phòng', 'flavor'); ?></small>
                                    </span>
                                </label>
                            </div>

                            <div id="vie-bank-info" class="vie-bank-info">
                                <h4><?php _e('Thông tin chuyển khoản', 'flavor'); ?></h4>
                                <p>
                                    <strong><?php _e('Ngân hàng:', 'flavor'); ?></strong> Vietcombank<br>
                                    <strong><?php _e('Số tài khoản:', 'flavor'); ?></strong> 1234567890<br>
                                    <strong><?php _e('Chủ tài khoản:', 'flavor'); ?></strong> Vie Limousine<br>
                                    <strong><?php _e('Nội dung:', 'flavor'); ?></strong> <span
                                        id="vie-transfer-content"><?php echo esc_html($booking->booking_code); ?></span>
                                </p>
                            </div>

                            <div class="vie-form-row" style="margin-top:30px">
                                <label class="vie-checkbox">
                                    <input type="checkbox" name="agree_terms" required>
                                    <span><?php _e('Tôi đồng ý với điều khoản và chính sách', 'flavor'); ?></span>
                                </label>
                            </div>

                            <button type="submit" class="vie-btn vie-btn-primary vie-btn-lg"
                                style="width:100%;margin-top:20px">
                                <?php _e('Xác nhận đặt phòng', 'flavor'); ?>
                            </button>
                        </form>
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
                            <span><?php _e('Mã đặt phòng', 'flavor'); ?></span>
                            <strong><?php echo esc_html($booking->booking_code); ?></strong>
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
</style>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    jQuery(function ($) {
        // Payment method change
        $('input[name="payment_method"]').on('change', function () {
            if ($(this).val() === 'bank_transfer') {
                $('#vie-bank-info').addClass('active');
            } else {
                $('#vie-bank-info').removeClass('active');
            }
        }).trigger('change');

        // Form submit with SweetAlert2
        $('#vie-checkout-form').on('submit', function (e) {
            e.preventDefault();

            var $btn = $(this).find('button[type="submit"]');
            var $form = $(this);

            $btn.prop('disabled', true).text('Đang xử lý...');

            // Collect form data for update
            var formData = {
                action: 'vie_process_checkout',
                nonce: $('input[name="checkout_nonce"]').val(),
                booking_hash: '<?php echo esc_js($booking_hash); ?>',
                payment_method: $('input[name="payment_method"]:checked').val(),
                // Customer info for update
                customer_name: $form.find('input[name="customer_name"]').val(),
                customer_phone: $form.find('input[name="customer_phone"]').val(),
                customer_email: $form.find('input[name="customer_email"]').val(),
                customer_note: $form.find('textarea[name="customer_note"]').val()
            };

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        // SweetAlert2 Success Modal
                        Swal.fire({
                            icon: 'success',
                            title: 'Đặt phòng thành công!',
                            html: '<p>Mã đơn hàng của bạn là: <strong>' + response.data.booking_code + '</strong></p>' +
                                '<p style="margin-top:10px;color:#6b7280;font-size:14px;">Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.</p>',
                            confirmButtonText: 'Về trang chủ',
                            confirmButtonColor: '#3b82f6',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = '<?php echo home_url('/'); ?>';
                            }
                        });
                    } else {
                        // SweetAlert2 Error Modal
                        Swal.fire({
                            icon: 'error',
                            title: 'Có lỗi xảy ra',
                            text: response.data ? response.data.message : 'Vui lòng thử lại sau',
                            confirmButtonText: 'Đóng',
                            confirmButtonColor: '#ef4444'
                        });
                        $btn.prop('disabled', false).text('Xác nhận đặt phòng');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);

                    // SweetAlert2 Network Error Modal
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi kết nối',
                        text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối mạng và thử lại.',
                        confirmButtonText: 'Đóng',
                        confirmButtonColor: '#ef4444'
                    });
                    $btn.prop('disabled', false).text('Xác nhận đặt phòng');
                }
            });
        });
    });
</script>

<?php
get_footer();
