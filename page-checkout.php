<?php
/**
 * Template Name: Page Checkout
 * Template Post Type: page
 *
 * @package VielimousineChild
 * @version 3.0.0 - Simplified Design
 */

// Security: Get booking by hash (prevents IDOR)
// Support both 'code' and 'booking' parameters for backward compatibility
$booking_hash = '';
if (isset($_GET['code'])) {
    $booking_hash = sanitize_text_field($_GET['code']);
} elseif (isset($_GET['booking'])) {
    $booking_hash = sanitize_text_field($_GET['booking']);
}

if (empty($booking_hash)) {
    wp_redirect(home_url('/'));
    exit;
}

// Query booking
global $wpdb;
$table_bookings = $wpdb->prefix . 'hotel_bookings';
$booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_bookings} WHERE booking_hash = %s", $booking_hash));

if (!$booking || $booking->status !== 'pending_payment') {
    wp_redirect(home_url('/'));
    exit;
}

// Get related data
$table_rooms = $wpdb->prefix . 'hotel_rooms';
$room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_rooms} WHERE id = %d", $booking->room_id));
$hotel_title = get_the_title($booking->hotel_id);

// Calculate nights
$check_in_date = new DateTime($booking->check_in);
$check_out_date = new DateTime($booking->check_out);
$num_nights = $check_in_date->diff($check_out_date)->days;

// Parse JSON data
$pricing_details = json_decode($booking->pricing_details, true);
$guests_info = json_decode($booking->guests_info, true);
$transport_info = json_decode($booking->transport_info ?? '', true);

get_header();
?>

<div class="vie-checkout-page">
    <div class="vie-container">

        <!-- Progress Indicator -->
        <div class="vie-progress-bar">
            <div class="vie-progress-step completed">
                <div class="step-icon">✓</div>
                <span>Chọn phòng</span>
            </div>
            <div class="vie-progress-line completed"></div>
            <div class="vie-progress-step active">
                <div class="step-icon">2</div>
                <span>Thanh toán</span>
            </div>
            <div class="vie-progress-line"></div>
            <div class="vie-progress-step">
                <div class="step-icon">3</div>
                <span>Hoàn tất</span>
            </div>
        </div>

        <div class="vie-checkout-grid">

            <!-- Main Content (Left) -->
            <div class="vie-checkout-main">

                <!-- Booking Info Card -->
                <div class="vie-card vie-booking-info">
                    <h2 class="vie-card-title">Thông tin đặt phòng</h2>
                    <div class="booking-details">
                        <div class="detail-row">
                            <span class="label">Phòng:</span>
                            <span class="value"><?php echo esc_html($room->name ?? ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Nhận phòng:</span>
                            <span class="value"><?php echo date('d/m/Y', strtotime($booking->check_in)); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Trả phòng:</span>
                            <span class="value"><?php echo date('d/m/Y', strtotime($booking->check_out)); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Số đêm:</span>
                            <span class="value"><?php echo $num_nights; ?> đêm</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Khách:</span>
                            <span class="value"><?php echo $booking->num_adults; ?> người lớn<?php echo $booking->num_children > 0 ? ', ' . $booking->num_children . ' trẻ em' : ''; ?></span>
                        </div>
                        
                        <?php if (!empty($transport_info) && !empty($transport_info['enabled'])): ?>
                        <div class="detail-row">
                            <span class="label">Xe đưa đón:</span>
                            <span class="value">
                                <div><span class="dashicons dashicons-car"></span> Đón: <strong><?php echo esc_html($transport_info['pickup_time']); ?></strong></div>
                                <div><span class="dashicons dashicons-car"></span> Về: <strong><?php echo esc_html($transport_info['dropoff_time']); ?></strong></div>
                                <?php if (!empty($transport_info['note'])): ?>
                                <div style="font-size: 0.9em; color: #666; margin-top: 4px;"><em><?php echo esc_html($transport_info['note']); ?></em></div>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer Info (Pre-filled, readonly) -->
                <div class="vie-card vie-customer-info">
                    <h2 class="vie-card-title">Thông tin khách hàng</h2>
                    <div class="customer-details">
                        <div class="detail-row">
                            <span class="label">Họ tên:</span>
                            <span class="value"><?php echo esc_html($booking->customer_name); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Số điện thoại:</span>
                            <span class="value"><?php echo esc_html($booking->customer_phone); ?></span>
                        </div>
                        <?php if ($booking->customer_email): ?>
                        <div class="detail-row">
                            <span class="label">Email:</span>
                            <span class="value"><?php echo esc_html($booking->customer_email); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="vie-card vie-payment-card">
                    <h2 class="vie-card-title">Thanh toán</h2>

                    <?php
                    $sepay = function_exists('vie_sepay') ? vie_sepay() : null;
                    $show_sepay = false;
                    $bank = null;

                    if ($sepay && $sepay->is_enabled()) {
                        $bank_account_id = $sepay->get_setting('bank_account');
                        $bank = $bank_account_id ? $sepay->get_bank_account($bank_account_id) : null;

                        if ($bank) {
                            $show_sepay = true;
                        }
                    }

                    if ($show_sepay && $bank):
                        $remark = $sepay->get_payment_code($booking->id);
                        $qr_url = $sepay->generate_qr_url($booking->id, $booking->total_amount);
                        ?>

                        <div class="payment-method">
                            <div class="payment-header">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"/>
                                </svg>
                                <span>Chuyển khoản ngân hàng</span>
                            </div>

                            <div class="qr-section">
                                <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code" class="qr-code">
                                <p class="qr-instruction">Quét mã QR để thanh toán</p>
                            </div>

                            <div class="bank-info">
                                <div class="info-row">
                                    <span class="label">Ngân hàng</span>
                                    <span class="value"><?php echo esc_html($bank['bank']['short_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Số tài khoản</span>
                                    <div class="copyable-value">
                                        <span class="value" id="account-number"><?php echo esc_html($bank['account_number']); ?></span>
                                        <button type="button" class="btn-copy" onclick="copyToClipboard('account-number')">📋</button>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <span class="label">Chủ tài khoản</span>
                                    <span class="value"><?php echo esc_html($bank['account_holder_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Số tiền</span>
                                    <div class="copyable-value">
                                        <span class="value amount" id="amount"><?php echo number_format($booking->total_amount, 0, ',', '.'); ?> đ</span>
                                        <button type="button" class="btn-copy" onclick="copyToClipboard('amount-raw')">📋</button>
                                        <span id="amount-raw" style="display:none"><?php echo $booking->total_amount; ?></span>
                                    </div>
                                </div>
                                <div class="info-row highlight">
                                    <span class="label">Nội dung CK</span>
                                    <div class="copyable-value">
                                        <span class="value" id="transfer-code"><?php echo esc_html($remark); ?></span>
                                        <button type="button" class="btn-copy" onclick="copyToClipboard('transfer-code')">📋</button>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-note">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/>
                                </svg>
                                <span>Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống tự động xác nhận</span>
                            </div>

                            <!-- Payment Status -->
                            <div class="payment-status" id="payment-status">
                                <div class="status-checking">
                                    <div class="spinner"></div>
                                    <span>Đang chờ thanh toán...</span>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <p>Vui lòng liên hệ để thanh toán</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Sidebar (Right) -->
            <aside class="vie-checkout-sidebar">
                <div class="vie-card vie-price-summary sticky">
                    <h3 class="summary-title">Chi tiết giá</h3>

                    <div class="summary-items">
                        <div class="summary-row">
                            <span>Tiền phòng</span>
                            <span><?php echo number_format($booking->base_amount, 0, ',', '.'); ?> đ</span>
                        </div>

                        <?php if ($booking->surcharges_amount > 0): ?>
                        <div class="summary-row">
                            <span>Phụ thu</span>
                            <span><?php echo number_format($booking->surcharges_amount, 0, ',', '.'); ?> đ</span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($booking->discount_amount) && $booking->discount_amount > 0): ?>
                        <div class="summary-row discount">
                            <span>Giảm giá <?php echo !empty($booking->coupon_code) ? '(' . esc_html($booking->coupon_code) . ')' : ''; ?></span>
                            <span class="discount-value" style="color: #ef4444;">-<?php echo number_format($booking->discount_amount, 0, ',', '.'); ?> đ</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="summary-total">
                        <span>Tổng cộng</span>
                        <span class="total-amount"><?php echo number_format($booking->total_amount, 0, ',', '.'); ?> đ</span>
                    </div>

                    <div class="booking-code">
                        <span class="code-label">Mã đặt phòng</span>
                        <span class="code-value"><?php echo esc_html($booking->booking_code); ?></span>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</div>

<script>
// Copy to clipboard function
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent.trim();

    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✓';
        btn.style.color = '#10b981';

        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.color = '';
        }, 2000);
    });
}

// Payment status checking
<?php if ($sepay && $sepay->is_enabled()): ?>
let checkInterval;
let checkCount = 0;
const maxChecks = 60; // Check for 5 minutes (5s interval)

function checkPaymentStatus() {
    if (checkCount >= maxChecks) {
        clearInterval(checkInterval);
        return;
    }

    checkCount++;

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_payment_status&booking_id=<?php echo $booking->id; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.status === 'paid') {
            clearInterval(checkInterval);
            document.getElementById('payment-status').innerHTML =
                '<div class="status-success"><svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg><span>Thanh toán thành công!</span></div>';

            setTimeout(() => {
                window.location.href = '<?php echo home_url('/thank-you/?code=' . $booking_hash); ?>';
            }, 2000);
        }
    });
}

// Start checking after page load
window.addEventListener('load', () => {
    checkInterval = setInterval(checkPaymentStatus, 5000);
    checkPaymentStatus(); // Check immediately
});
<?php endif; ?>
</script>

<?php get_footer(); ?>
