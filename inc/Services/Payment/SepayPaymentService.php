<?php
/**
 * ============================================================================
 * TÊN FILE: SepayPaymentService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service xử lý payment codes và QR code generation cho SePay.
 * Generate payment codes, QR URLs, và extract booking IDs.
 *
 * CHỨC NĂNG CHÍNH:
 * - Generate payment code cho booking
 * - Generate QR code URL
 * - Extract booking ID từ payment code
 * - Process payment confirmation
 *
 * PAYMENT CODE FORMAT:
 * - Standard: {PREFIX}{BOOKING_ID}
 * - VietinBank/ABBANK: "SEVQR {PREFIX}{BOOKING_ID}"
 * - Prefix: Configurable (default: 'VL')
 * - Example: "VL123", "SEVQR VL123"
 *
 * QR CODE:
 * - Uses SePay QR service: https://qr.sepay.vn
 * - Embeds: account number, bank BIN, amount, description
 * - Template: compact
 *
 * SỬ DỤNG:
 * $payment = new Vie_SePay_Payment_Service($settings, $bank_service);
 * $qr_url = $payment->generate_qr_url(123, 5000000);
 * $booking_id = $payment->extract_booking_id('VL123');
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Payment
 * @version     2.1.0
 * @since       2.1.0 (Split from SepayGateway trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_SePay_Payment_Service
 * ============================================================================
 *
 * Service xử lý payment codes và QR generation.
 *
 * ARCHITECTURE:
 * - Depends on: SepaySettingsManager, SepayBankAccountService
 * - Uses: SePay QR service (external)
 * - Returns: Payment codes, QR URLs
 *
 * SPECIAL BANK HANDLING:
 * - VietinBank (BIN: 970415) → Add "SEVQR " prefix
 * - ABBANK (BIN: 970425) → Add "SEVQR " prefix
 * - Other banks → Standard format
 *
 * @since   2.1.0
 */
class Vie_SePay_Payment_Service
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * QR service base URL
     * @var string
     */
    const QR_SERVICE_URL = 'https://qr.sepay.vn/img';

    /**
     * Banks requiring SEVQR prefix
     * @var array
     */
    const SEVQR_BANKS = array('970415', '970425'); // VietinBank, ABBANK

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Settings manager instance
     *
     * @var Vie_SePay_Settings_Manager
     */
    private $settings;

    /**
     * Bank account service instance
     *
     * @var Vie_SePay_Bank_Account_Service
     */
    private $bank_service;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * @since   2.1.0
     * @param   Vie_SePay_Settings_Manager      $settings       Settings manager
     * @param   Vie_SePay_Bank_Account_Service  $bank_service   Bank account service
     */
    public function __construct($settings, $bank_service)
    {
        $this->settings = $settings;
        $this->bank_service = $bank_service;
    }

    /**
     * -------------------------------------------------------------------------
     * PAYMENT CODE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get payment code cho booking
     *
     * Generate payment code theo format:
     * - Standard: {PREFIX}{BOOKING_ID}
     * - VietinBank/ABBANK: "SEVQR {PREFIX}{BOOKING_ID}"
     *
     * PREFIX:
     * - Lấy từ settings (pay_code_prefix)
     * - Default: 'VL'
     * - Configurable trong admin
     *
     * SPECIAL BANKS:
     * - VietinBank (BIN: 970415) và ABBANK (BIN: 970425) require "SEVQR " prefix
     * - Các banks khác không cần
     *
     * @since   2.1.0
     * @param   int     $booking_id Booking ID
     * @return  string              Payment code
     */
    public function get_payment_code($booking_id)
    {
        $prefix = $this->settings->get_pay_code_prefix();
        $code   = $prefix . $booking_id;

        // Check if bank requires SEVQR prefix
        $bank_account_id = $this->settings->get_bank_account();

        if ($bank_account_id) {
            $bank = $this->bank_service->get_bank_account($bank_account_id);

            if ($bank && isset($bank['bank']['bin'])) {
                $bin = $bank['bank']['bin'];

                if (in_array($bin, self::SEVQR_BANKS)) {
                    $code = 'SEVQR ' . $code;
                }
            }
        }

        return $code;
    }

    /**
     * Extract booking ID từ payment code
     *
     * Parse payment code để lấy booking ID.
     *
     * PARSING RULES:
     * 1. Remove "SEVQR " prefix (nếu có)
     * 2. Remove prefix từ settings (VD: 'VL')
     * 3. Trim whitespace
     * 4. Check if numeric
     * 5. Return int hoặc false
     *
     * EXAMPLES:
     * - "VL123" → 123
     * - "SEVQR VL123" → 123
     * - "VL" → false (không có number)
     * - "VLABC" → false (không phải numeric)
     *
     * @since   2.1.0
     * @param   string  $code       Payment code
     * @return  int|false           Booking ID hoặc false nếu invalid
     */
    public function extract_booking_id($code)
    {
        $prefix = $this->settings->get_pay_code_prefix();

        // Remove SEVQR prefix
        $code = str_replace(array('SEVQR ', 'SEVQR'), '', $code);

        // Remove configured prefix
        $code = str_replace($prefix, '', $code);

        // Trim
        $code = trim($code);

        // Check if numeric
        return is_numeric($code) ? intval($code) : false;
    }

    /**
     * -------------------------------------------------------------------------
     * QR CODE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Generate QR code URL
     *
     * Generate URL cho QR code image từ SePay QR service.
     *
     * QR SERVICE:
     * - URL: https://qr.sepay.vn/img
     * - Parameters: acc, bank, amount, des, template
     * - Returns: PNG image
     *
     * PARAMETERS:
     * - acc: Account number
     * - bank: Bank BIN code
     * - amount: Payment amount
     * - des: Description (payment code)
     * - template: 'compact' (layout style)
     *
     * EXAMPLE URL:
     * https://qr.sepay.vn/img?acc=1234567890&bank=970415&amount=5000000&des=VL123&template=compact
     *
     * @since   2.1.0
     * @param   int     $booking_id Booking ID
     * @param   float   $amount     Payment amount
     * @return  string              QR code image URL hoặc empty string nếu không có bank
     */
    public function generate_qr_url($booking_id, $amount)
    {
        // Get selected bank account
        $bank_account_id = $this->settings->get_bank_account();

        if (!$bank_account_id) {
            return '';
        }

        // Get bank account details
        $bank = $this->bank_service->get_bank_account($bank_account_id);

        if (!$bank) {
            return '';
        }

        // Get payment code (với SEVQR prefix nếu cần)
        $remark = $this->get_payment_code($booking_id);

        // Build QR URL
        return sprintf(
            '%s?acc=%s&bank=%s&amount=%s&des=%s&template=compact',
            self::QR_SERVICE_URL,
            urlencode($bank['account_number']),
            urlencode($bank['bank']['bin']),
            urlencode($amount),
            urlencode($remark)
        );
    }

    /**
     * -------------------------------------------------------------------------
     * PAYMENT PROCESSING
     * -------------------------------------------------------------------------
     */

    /**
     * Process payment confirmation
     *
     * Xử lý thanh toán sau khi nhận webhook từ SePay.
     * Update booking status và gửi email.
     *
     * FLOW:
     * 1. Get booking
     * 2. Check if already paid
     * 3. Update status to 'processing' và payment_status to 'paid'
     * 4. Send email confirmation
     *
     * @since   2.1.0
     * @param   int     $booking_id Booking ID
     * @return  bool                true nếu thành công, false nếu lỗi
     */
    public function process_payment($booking_id)
    {
        // Get booking manager
        $booking_manager = Vie_Booking_Service::get_instance();
        $booking = $booking_manager->get_booking($booking_id);

        if (!$booking) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Payment] Booking not found: ' . $booking_id);
            }
            return false;
        }

        // Check if already paid
        if ($booking->payment_status === 'paid') {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Payment] Booking already paid: ' . $booking_id);
            }
            return false; // Already paid, không process lại
        }

        // Update booking status
        $result = $booking_manager->update_booking($booking_id, array(
            'status'         => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'sepay',
        ));

        if (is_wp_error($result)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Payment] Update booking failed: ' . $result->get_error_message());
            }
            return false;
        }

        // Send email confirmation
        $email_service = Vie_Email_Service::get_instance();
        $email_service->send_email('processing', $booking_id);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Payment] Payment processed successfully for booking: ' . $booking_id);
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * VALIDATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate payment code format
     *
     * Check if payment code có format hợp lệ.
     *
     * @since   2.1.0
     * @param   string  $code   Payment code
     * @return  bool            true nếu valid
     */
    public function is_valid_payment_code($code)
    {
        if (empty($code)) {
            return false;
        }

        // Try extract booking ID
        $booking_id = $this->extract_booking_id($code);

        return $booking_id !== false;
    }

    /**
     * Get payment code template
     *
     * Get template string cho display (VD: "VL{BOOKING_ID}").
     *
     * @since   2.1.0
     * @return  string  Template string
     */
    public function get_payment_code_template()
    {
        $prefix = $this->settings->get_pay_code_prefix();
        return $prefix . '{BOOKING_ID}';
    }
}
