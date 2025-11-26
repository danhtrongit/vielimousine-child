<?php
/**
 * SePay Payment Info Template
 * 
 * Hiển thị thông tin thanh toán QR VietQR cho đơn đặt phòng
 * 
 * Variables available:
 * @var object $booking - Booking data object
 * @var string $qr_code_url - VietQR URL
 * @var array $bank_info - Bank information array
 * @var string $account_number - Bank account number
 * @var string $account_holder - Account holder name
 * @var string $remark - Payment remark/code
 * @var string $bank_logo_url - Bank logo URL
 * 
 * @package VieHotelRooms
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$sepay = vie_sepay();
$formatted_amount = $sepay->format_currency($booking->total_amount);
?>

<section class="vie-sepay-payment">
    <!-- Header -->
    <div class="sepay-header">
        <h3>Thanh toán qua chuyển khoản ngân hàng</h3>
        <div class="powered-by">Tự động xác nhận bởi SePay</div>
    </div>

    <div class="sepay-body">
        <!-- Booking Summary -->
        <div class="sepay-booking-summary">
            <h4>📋 Thông tin đặt phòng #<?php echo esc_html($booking->booking_code); ?></h4>
            <div class="summary-row">
                <span>Ngày nhận phòng:</span>
                <span><?php echo esc_html(date_i18n('d/m/Y', strtotime($booking->check_in))); ?></span>
            </div>
            <div class="summary-row">
                <span>Ngày trả phòng:</span>
                <span><?php echo esc_html(date_i18n('d/m/Y', strtotime($booking->check_out))); ?></span>
            </div>
            <div class="summary-row">
                <span>Số phòng:</span>
                <span><?php echo esc_html($booking->num_rooms); ?> phòng</span>
            </div>
            <div class="summary-row">
                <span>Số khách:</span>
                <span><?php echo esc_html($booking->num_adults); ?> người lớn<?php echo $booking->num_children > 0 ? ', ' . $booking->num_children . ' trẻ em' : ''; ?></span>
            </div>
            <div class="summary-row summary-total">
                <span>Tổng thanh toán:</span>
                <span><?php echo esc_html($formatted_amount); ?></span>
            </div>
        </div>

        <!-- Success Message Area -->
        <div class="sepay-message"></div>

        <!-- Payment Info -->
        <div class="sepay-pay-info">
            <!-- QR Code Section -->
            <div class="sepay-qr-section">
                <div class="sepay-qr-title">
                    <strong>Cách 1:</strong> Mở app ngân hàng/Ví và <strong>quét mã QR</strong>
                </div>
                <div class="sepay-qr-wrapper">
                    <img src="<?php echo esc_url($qr_code_url); ?>" 
                         alt="QR Code thanh toán" 
                         class="sepay-qr-image"
                         loading="eager">
                </div>
                <div class="sepay-download-qr">
                    <a href="<?php echo esc_url($qr_code_url . '&download=yes'); ?>" download="qr-payment-<?php echo esc_attr($booking->booking_code); ?>.png">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                        Tải ảnh QR
                    </a>
                </div>
            </div>

            <!-- Manual Transfer Section -->
            <div class="sepay-manual-section">
                <div class="sepay-manual-title">
                    <strong>Cách 2:</strong> Chuyển khoản <strong>thủ công</strong> theo thông tin
                </div>

                <div class="sepay-bank-card">
                    <!-- Bank Logo -->
                    <div class="sepay-bank-header">
                        <img src="<?php echo esc_url($bank_logo_url); ?>" 
                             alt="<?php echo esc_attr($bank_info['short_name']); ?>" 
                             class="sepay-bank-logo">
                    </div>

                    <!-- Bank Info Rows -->
                    <div class="sepay-bank-rows">
                        <!-- Bank Name -->
                        <div class="sepay-bank-row">
                            <div class="sepay-bank-label">Ngân hàng</div>
                            <div class="sepay-bank-value">
                                <strong><?php echo esc_html($bank_info['short_name']); ?></strong>
                            </div>
                        </div>

                        <!-- Account Holder -->
                        <div class="sepay-bank-row">
                            <div class="sepay-bank-label">Thụ hưởng</div>
                            <div class="sepay-bank-value">
                                <strong><?php echo esc_html($account_holder); ?></strong>
                            </div>
                        </div>

                        <!-- Account Number -->
                        <div class="sepay-bank-row">
                            <div class="sepay-bank-label">Số tài khoản</div>
                            <div class="sepay-bank-value">
                                <strong><?php echo esc_html($account_number); ?></strong>
                                <button type="button" class="sepay-copy-btn" id="sepay_copy_account_number">
                                    <span class="copy-icon">
                                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    Sao chép
                                </button>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="sepay-bank-row">
                            <div class="sepay-bank-label">Số tiền</div>
                            <div class="sepay-bank-value">
                                <strong class="amount"><?php echo esc_html($formatted_amount); ?></strong>
                                <button type="button" class="sepay-copy-btn" id="sepay_copy_amount">
                                    <span class="copy-icon">
                                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    Sao chép
                                </button>
                            </div>
                        </div>

                        <!-- Transfer Content/Remark -->
                        <div class="sepay-bank-row">
                            <div class="sepay-bank-label">Nội dung CK</div>
                            <div class="sepay-bank-value">
                                <strong><?php echo esc_html($remark); ?></strong>
                                <button type="button" class="sepay-copy-btn" id="sepay_copy_remark">
                                    <span class="copy-icon">
                                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    Sao chép
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Note -->
                    <div class="sepay-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
                        </svg>
                        <span>
                            <strong>Lưu ý quan trọng:</strong> Vui lòng giữ nguyên nội dung chuyển khoản 
                            <strong><?php echo esc_html($remark); ?></strong> để hệ thống tự động xác nhận thanh toán.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer - Waiting Status -->
    <div class="sepay-pay-footer">
        <div class="loading-spinner">
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
        </div>
        <span>Trạng thái: Đang chờ thanh toán...</span>
    </div>
</section>

<?php
// Enqueue required scripts and styles
wp_enqueue_style(
    'vie-sepay-payment',
    VIE_HOTEL_ROOMS_URL . 'assets/css/sepay-payment.css',
    array(),
    VIE_HOTEL_ROOMS_VERSION
);

wp_enqueue_script(
    'vie-sepay-payment',
    VIE_HOTEL_ROOMS_URL . 'assets/js/sepay-payment.js',
    array('jquery'),
    VIE_HOTEL_ROOMS_VERSION,
    true
);

// Localize script with booking data
wp_localize_script('vie-sepay-payment', 'vie_sepay_vars', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('vie_check_payment'),
    'booking_id' => $booking->id,
    'booking_hash' => $booking->booking_hash,
    'account_number' => $account_number,
    'amount' => $booking->total_amount,
    'remark' => $remark,
    'success_message' => $sepay->get_setting('success_message'),
    'redirect_url' => '', // Optional: add redirect URL after payment
));
?>
