<?php
/**
 * ============================================================================
 * TÊN FILE: SettingsPage.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Admin Page Controller quản lý trang cài đặt Hotel Rooms module.
 * Xử lý routing cho 5 tabs settings và AJAX handlers.
 *
 * CHỨC NĂNG CHÍNH:
 * - Quản lý 5 tabs: General, Google Sheets, SePay, Email, Templates
 * - AJAX handlers cho tất cả cài đặt
 * - View separation cho từng tab
 * - Email template editor với TinyMCE
 *
 * PAGE CONTROLLER PATTERN:
 * - Controller: Xử lý logic và data
 * - Views: Render HTML (5 separated views)
 * - AJAX: Save settings handlers
 *
 * TABS:
 * - general: Cài đặt chung (hotline, pages, currency)
 * - gsheets: Google Sheets config (coupons)
 * - sepay: SePay payment gateway config
 * - email: Email settings (from name/email, logo, footer)
 * - templates: Email templates editor (3 templates)
 *
 * SỬ DỤNG:
 * $page = new Vie_Admin_Settings_Page();
 * $page->render();
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Pages
 * @version     2.1.0
 * @since       2.0.0 (Refactored to Page Controller pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Admin_Settings_Page
 * ============================================================================
 *
 * Page Controller cho Settings admin page.
 *
 * ARCHITECTURE:
 * - Page Controller Pattern
 * - Tab-based routing (5 tabs)
 * - Separated views for each tab
 * - Views: Admin/Views/settings/*.php
 *
 * AJAX HANDLERS (7):
 * - vie_save_general_settings
 * - vie_save_gsheets_settings
 * - vie_save_sepay_settings
 * - vie_save_email_settings
 * - vie_save_email_template
 * - vie_sepay_disconnect
 * - vie_test_email
 *
 * @since   2.0.0
 */
class Vie_Admin_Settings_Page
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /** @var string Option key for general settings */
    const OPTION_GENERAL = 'vie_hotel_general_settings';

    /** @var string Option key for email settings */
    const OPTION_EMAIL = 'vie_hotel_email_settings';

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Initialize và register hooks.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        // Register AJAX handlers
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_ajax_handlers()
    {
        add_action('wp_ajax_vie_save_general_settings', array($this, 'ajax_save_general_settings'));
        add_action('wp_ajax_vie_save_gsheets_settings', array($this, 'ajax_save_gsheets_settings'));
        add_action('wp_ajax_vie_test_gsheets_connection', array($this, 'ajax_test_gsheets_connection'));
        add_action('wp_ajax_vie_save_sepay_settings', array($this, 'ajax_save_sepay_settings'));
        add_action('wp_ajax_vie_save_email_settings', array($this, 'ajax_save_email_settings'));
        add_action('wp_ajax_vie_save_email_template', array($this, 'ajax_save_email_template'));
        add_action('wp_ajax_vie_sepay_disconnect', array($this, 'ajax_sepay_disconnect'));
        add_action('wp_ajax_vie_test_email', array($this, 'ajax_test_email'));
    }

    /**
     * -------------------------------------------------------------------------
     * PAGE RENDERING
     * -------------------------------------------------------------------------
     */

    /**
     * Render main settings page (router)
     *
     * Route request đến appropriate tab view.
     *
     * TABS:
     * - general: General settings
     * - gsheets: Google Sheets config
     * - sepay: SePay config
     * - email: Email settings
     * - templates: Email template editor
     *
     * @since   2.0.0
     * @return  void
     */
    public function render()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        $tabs = array(
            'general' => __('Cài đặt chung', 'vielimousine'),
            'gsheets' => __('Google Sheets', 'vielimousine'),
            'sepay' => __('SePay', 'vielimousine'),
            'email' => __('Email', 'vielimousine'),
            'templates' => __('Email Templates', 'vielimousine'),
        );

        // Start page wrapper
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cài đặt Hotel Rooms', 'vielimousine'); ?></h1>

            <?php if (isset($_GET['connected']) && $_GET['connected'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Đã kết nối SePay thành công!', 'vielimousine'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>"
                        class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Tab Content -->
            <div class="tab-content" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-top:none;">
                <?php $this->render_tab_content($current_tab); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab content
     *
     * Load appropriate view based on current tab.
     *
     * @since   2.1.0
     * @param   string  $tab    Current tab name
     * @return  void
     */
    private function render_tab_content($tab)
    {
        switch ($tab) {
            case 'gsheets':
                $this->render_gsheets_tab();
                break;

            case 'sepay':
                $this->render_sepay_tab();
                break;

            case 'email':
                $this->render_email_tab();
                break;

            case 'templates':
                $this->render_templates_tab();
                break;

            default:
                $this->render_general_tab();
                break;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * TAB RENDER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Render General settings tab
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_general_tab()
    {
        $settings = get_option(self::OPTION_GENERAL, array(
            'hotline' => '',
            'checkout_page' => '',
            'thank_you_page' => '',
            'terms_page' => '',
            'currency_symbol' => 'VNĐ',
            'date_format' => 'd/m/Y',
        ));

        // Load view
        $this->load_view('settings/general', compact('settings'));
    }

    /**
     * Render Google Sheets settings tab
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_gsheets_tab()
    {
        $settings = get_option('vie_gsheets_settings', array(
            'service_account_json' => '',
            'spreadsheet_id' => '',
            'sheet_name' => 'Coupons',
            'sheet_range' => 'A2:G1000',
        ));

        // Check if connected by testing if we can get access token
        $is_connected = false;
        if (!empty($settings['service_account_json'])) {
            $auth = Vie_Google_Auth::get_instance();
            $token = $auth->get_access_token();
            $is_connected = !empty($token);
        }

        // Load view
        $this->load_view('settings/gsheets', compact('settings', 'is_connected'));
    }

    /**
     * Render SePay settings tab
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_sepay_tab()
    {
        $sepay = class_exists('Vie_SePay_Gateway') ? Vie_SePay_Gateway::get_instance() : null;

        if (!$sepay) {
            echo '<p class="notice notice-error">' . esc_html__('SePay Gateway class not found', 'vielimousine') . '</p>';
            return;
        }

        $is_connected = $sepay->is_connected();
        $settings = $sepay->get_settings();
        $bank_accounts = $is_connected ? $sepay->get_bank_accounts() : array();

        // Load view
        $this->load_view('settings/sepay', compact('sepay', 'is_connected', 'settings', 'bank_accounts'));
    }

    /**
     * Render Email settings tab
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_email_tab()
    {
        $settings = get_option(self::OPTION_EMAIL, array(
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'admin_email' => get_option('admin_email'),
            'logo_url' => '',
            'footer_text' => '',
        ));

        // Load view
        $this->load_view('settings/email', compact('settings'));
    }

    /**
     * Render Email Templates tab
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_templates_tab()
    {
        $template_type = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'pending';

        $template_tabs = array(
            'pending' => __('Email 1: Chờ thanh toán', 'vielimousine'),
            'processing' => __('Email 2: Đang xử lý', 'vielimousine'),
            'completed' => __('Email 3: Hoàn thành', 'vielimousine'),
            'admin_notification' => __('Email Admin: Thông báo đặt phòng', 'vielimousine'),
        );

        $descriptions = array(
            'pending' => 'Gửi ngay khi khách đặt phòng. Nhắc nhở thanh toán.',
            'processing' => 'Gửi khi đã nhận tiền. Thông báo đang liên hệ khách sạn.',
            'completed' => 'Gửi khi Admin đã nhập Mã nhận phòng. Chứa voucher để check-in.',
            'admin_notification' => 'Gửi cho Admin khi có khách đặt phòng mới.',
        );

        // Get current template
        $template_data = $this->get_email_template($template_type);

        // Load view
        $this->load_view('settings/templates', compact(
            'template_type',
            'template_tabs',
            'descriptions',
            'template_data'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Save general settings
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_general_settings()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        $settings = array(
            'hotline' => sanitize_text_field($_POST['hotline'] ?? ''),
            'checkout_page' => absint($_POST['checkout_page'] ?? 0),
            'thank_you_page' => absint($_POST['thank_you_page'] ?? 0),
            'currency_symbol' => sanitize_text_field($_POST['currency_symbol'] ?? 'VNĐ'),
        );

        update_option(self::OPTION_GENERAL, $settings);
        update_option('vie_hotline', $settings['hotline']);

        wp_send_json_success();
    }

    /**
     * AJAX: Save Google Sheets settings
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_gsheets_settings()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Get and sanitize service account JSON
        $service_account_json = isset($_POST['service_account_json']) ? wp_unslash($_POST['service_account_json']) : '';
        $service_account_json = trim($service_account_json);

        // Validate JSON if provided
        if (!empty($service_account_json)) {
            $credentials = json_decode($service_account_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array(
                    'message' => 'JSON không hợp lệ: ' . json_last_error_msg()
                ));
            }

            // Validate required fields
            $required_fields = ['client_email', 'private_key'];
            foreach ($required_fields as $field) {
                if (!isset($credentials[$field]) || empty($credentials[$field])) {
                    wp_send_json_error(array(
                        'message' => "JSON thiếu trường bắt buộc: {$field}"
                    ));
                }
            }

            // Validate email format
            if (!filter_var($credentials['client_email'], FILTER_VALIDATE_EMAIL)) {
                wp_send_json_error(array(
                    'message' => 'Service Account email không hợp lệ'
                ));
            }

            // Validate private key format
            if (strpos($credentials['private_key'], '-----BEGIN PRIVATE KEY-----') === false) {
                wp_send_json_error(array(
                    'message' => 'Private key không đúng định dạng'
                ));
            }
        }

        $settings = array(
            'service_account_json' => $service_account_json,
            'spreadsheet_id' => sanitize_text_field($_POST['spreadsheet_id'] ?? ''),
            'sheet_name' => sanitize_text_field($_POST['sheet_name'] ?? 'Coupons'),
            'sheet_range' => sanitize_text_field($_POST['sheet_range'] ?? 'A2:G1000'),
        );

        update_option('vie_gsheets_settings', $settings);

        // Clear cached access token to force refresh
        delete_transient('vie_google_access_token');

        wp_send_json_success(array(
            'message' => 'Đã lưu cài đặt thành công!'
        ));
    }

    /**
     * AJAX: Save SePay settings
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_sepay_settings()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        $sepay = Vie_SePay_Gateway::get_instance();

        // Normalize 'enabled' to 'yes'/'no'
        $enabled = (!empty($_POST['enabled']) && $_POST['enabled'] == '1') ? 'yes' : 'no';

        $settings = array(
            'enabled' => $enabled,
            'bank_account' => sanitize_text_field($_POST['bank_account'] ?? ''),
            'pay_code_prefix' => strtoupper(sanitize_text_field($_POST['pay_code_prefix'] ?? 'VL')),
        );

        $sepay->update_settings($settings);

        // Setup webhook if bank account selected
        if (!empty($settings['bank_account'])) {
            $sepay->setup_webhook($settings['bank_account']);
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Save email settings
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_email_settings()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Handle multiple admin emails
        $admin_emails_raw = $_POST['admin_email'] ?? '';
        $admin_emails_arr = explode(',', $admin_emails_raw);
        $clean_emails = array();

        foreach ($admin_emails_arr as $email) {
            $email = sanitize_email(trim($email));
            if (is_email($email)) {
                $clean_emails[] = $email;
            }
        }

        $settings = array(
            'from_name' => sanitize_text_field($_POST['from_name'] ?? ''),
            'from_email' => sanitize_email($_POST['from_email'] ?? ''),
            'admin_email' => implode(', ', $clean_emails),
            'logo_url' => esc_url_raw($_POST['logo_url'] ?? ''),
            'footer_text' => sanitize_textarea_field($_POST['footer_text'] ?? ''),
        );

        update_option(self::OPTION_EMAIL, $settings);
        update_option('vie_email_logo', $settings['logo_url']);

        wp_send_json_success();
    }

    /**
     * AJAX: Save email template
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_email_template()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        $type = sanitize_text_field($_POST['template_type'] ?? '');
        if (!in_array($type, array('pending', 'processing', 'completed', 'admin_notification'))) {
            wp_send_json_error(array('message' => 'Loại template không hợp lệ'));
        }

        $option_key = 'vie_hotel_email_' . $type;

        // Reset to default
        if (!empty($_POST['reset'])) {
            delete_option($option_key);
            wp_send_json_success();
        }

        // Save template
        $template = array(
            'subject' => sanitize_text_field($_POST['subject'] ?? ''),
            'body' => wp_kses_post($_POST['body'] ?? ''),
        );

        update_option($option_key, $template);
        wp_send_json_success();
    }

    /**
     * AJAX: Disconnect SePay
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_sepay_disconnect()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        $sepay = Vie_SePay_Gateway::get_instance();
        $sepay->disconnect();

        wp_send_json_success();
    }

    /**
     * AJAX: Send test email
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_test_email()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email không hợp lệ'));
        }

        $subject = '[' . get_bloginfo('name') . '] Email Test';
        $message = '<h2>Email Test</h2><p>Nếu bạn nhận được email này, cấu hình email đã hoạt động đúng.</p>';
        $message .= '<p>Thời gian: ' . current_time('d/m/Y H:i:s') . '</p>';

        $sent = wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));

        if ($sent) {
            wp_send_json_success(array('message' => 'Đã gửi email test đến ' . $email));
        } else {
            wp_send_json_error(array('message' => 'Gửi email thất bại. Vui lòng kiểm tra cấu hình email của server hoặc plugin SMTP.'));
        }
    }

    /**
     * AJAX: Test Google Sheets connection
     *
     * @since   2.1.0
     * @return  void    Outputs JSON response
     */
    public function ajax_test_gsheets_connection()
    {
        // Security check
        check_ajax_referer('vie_save_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Get settings
        $settings = get_option('vie_gsheets_settings', array());

        if (empty($settings['service_account_json'])) {
            wp_send_json_error(array('message' => 'Chưa cấu hình Service Account JSON'));
        }

        if (empty($settings['spreadsheet_id'])) {
            wp_send_json_error(array('message' => 'Chưa cấu hình Spreadsheet ID'));
        }

        // Test authentication
        $auth = Vie_Google_Auth::get_instance();
        $token = $auth->get_access_token();

        if (!$token) {
            wp_send_json_error(array('message' => 'Không thể lấy Access Token. Kiểm tra Service Account JSON.'));
        }

        // Test reading from sheet
        $sheets_api = new Vie_Google_Sheets_API();
        $sheet_name = !empty($settings['sheet_name']) ? $settings['sheet_name'] : 'Coupons';
        $test_range = $sheet_name . '!A1:A1';

        $result = $sheets_api->read_range($test_range);

        if ($result === false) {
            wp_send_json_error(array(
                'message' => 'Không thể đọc Google Sheet. Kiểm tra: 1) Spreadsheet ID đúng chưa, 2) Đã share sheet với Service Account email chưa'
            ));
        }

        // Success
        $credentials = json_decode($settings['service_account_json'], true);
        $service_email = $credentials['client_email'] ?? '';

        wp_send_json_success(array(
            'message' => sprintf(
                'Kết nối thành công! Service Account: %s',
                $service_email
            )
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get email template
     *
     * Get saved template or return default.
     *
     * @since   2.0.0
     * @param   string  $type   pending|processing|completed
     * @return  array           {subject, body}
     */
    private function get_email_template($type)
    {
        $saved = get_option('vie_hotel_email_' . $type, array());
        $defaults = $this->get_default_email_templates();

        return wp_parse_args($saved, $defaults[$type] ?? array('subject' => '', 'body' => ''));
    }

    /**
     * Get default email templates
     *
     * Default templates for all 3 email types.
     *
     * @since   2.0.0
     * @return  array   Default templates array
     */
    private function get_default_email_templates()
    {
        $site_name = get_bloginfo('name');

        return array(
            'pending' => array(
                'subject' => 'Xác nhận tiếp nhận đặt phòng #{booking_id}',
                'body' => '<h2>Xin chào {customer_name},</h2>
<p>Cảm ơn bạn đã đặt phòng tại <strong>{hotel_name}</strong>.</p>
<p>Đơn đặt phòng của bạn đang ở trạng thái <strong>Chờ thanh toán</strong>.</p>

<h3>Thông tin đặt phòng:</h3>
<ul>
<li><strong>Mã đơn hàng:</strong> #{booking_id}</li>
<li><strong>Khách sạn:</strong> {hotel_name}</li>
<li><strong>Loại phòng:</strong> {room_name}</li>
<li><strong>Gói dịch vụ:</strong> {package_type}</li>
<li><strong>Ngày nhận phòng:</strong> {check_in}</li>
<li><strong>Ngày trả phòng:</strong> {check_out}</li>
<li><strong>Số khách:</strong> {adults} người lớn, {children} trẻ em</li>
</ul>

<h3>Tổng thanh toán: {total_amount}</h3>

<p>Vui lòng thanh toán để hoàn tất đặt phòng.</p>

<p>Trân trọng,<br>' . $site_name . '</p>'
            ),
            'processing' => array(
                'subject' => 'Đã nhận thanh toán - Đang xử lý #{booking_id}',
                'body' => '<h2>Xin chào {customer_name},</h2>
<p>Chúng tôi đã nhận được thanh toán cho đơn hàng <strong>#{booking_id}</strong>.</p>
<p>Hệ thống đang liên hệ với khách sạn để lấy <strong>Mã nhận phòng</strong>.</p>
<p>Vui lòng chờ email xác nhận trong ít phút.</p>

<h3>Thông tin đặt phòng:</h3>
<ul>
<li><strong>Khách sạn:</strong> {hotel_name}</li>
<li><strong>Loại phòng:</strong> {room_name}</li>
<li><strong>Ngày nhận phòng:</strong> {check_in}</li>
<li><strong>Ngày trả phòng:</strong> {check_out}</li>
</ul>

<p>Trân trọng,<br>' . $site_name . '</p>'
            ),
            'completed' => array(
                'subject' => 'Xác nhận đặt phòng thành công - Mã: {room_code}',
                'body' => '<h2>Xin chào {customer_name},</h2>
<p>Chúc mừng! Đơn đặt phòng của bạn tại <strong>{hotel_name}</strong> đã được xác nhận.</p>

<div style="background:#e8f5e9;border:1px solid #c8e6c9;color:#2e7d32;padding:20px;border-radius:8px;text-align:center;margin:20px 0;">
<div style="font-size:14px;text-transform:uppercase;letter-spacing:1px;">Mã nhận phòng</div>
<div style="font-size:28px;font-weight:bold;margin-top:10px;">{room_code}</div>
</div>

<h3>Thông tin đặt phòng:</h3>
<ul>
<li><strong>Mã đơn hàng:</strong> #{booking_id}</li>
<li><strong>Khách sạn:</strong> {hotel_name}</li>
<li><strong>Địa chỉ:</strong> {hotel_address}</li>
<li><strong>Loại phòng:</strong> {room_name}</li>
<li><strong>Loại giường:</strong> {bed_type}</li>
<li><strong>Ngày nhận phòng:</strong> {check_in}</li>
<li><strong>Ngày trả phòng:</strong> {check_out}</li>
<li><strong>Số khách:</strong> {adults} người lớn, {children} trẻ em</li>
</ul>

<p><strong>Lưu ý:</strong> Vui lòng xuất trình Mã nhận phòng này cho lễ tân khi làm thủ tục check-in.</p>

<p>Trân trọng,<br>' . $site_name . '</p>'
            ),
            'admin_notification' => array(
                'subject' => 'Thông báo đặt phòng mới #{booking_id} - {customer_name}',
                'body' => '<h2>Thông báo đặt phòng mới</h2>
<p>Có một đơn đặt phòng mới trên hệ thống.</p>

<h3>Thông tin khách hàng:</h3>
<ul>
<li><strong>Họ tên:</strong> {customer_name}</li>
<li><strong>Email:</strong> {customer_email}</li>
<li><strong>Số điện thoại:</strong> {customer_phone}</li>
</ul>

<h3>Thông tin đặt phòng:</h3>
<ul>
<li><strong>Mã đơn hàng:</strong> #{booking_id}</li>
<li><strong>Khách sạn:</strong> {hotel_name}</li>
<li><strong>Loại phòng:</strong> {room_name}</li>
<li><strong>Gói dịch vụ:</strong> {package_type}</li>
<li><strong>Ngày nhận phòng:</strong> {check_in}</li>
<li><strong>Ngày trả phòng:</strong> {check_out}</li>
<li><strong>Số khách:</strong> {adults} người lớn, {children} trẻ em</li>
<li><strong>Tổng tiền:</strong> {total_amount}</li>
<li><strong>Trạng thái:</strong> {status}</li>
</ul>

<p>Vui lòng kiểm tra và xử lý đơn hàng.</p>
<p><a href="{admin_order_url}" style="background:#007cba;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;">Xem chi tiết đơn hàng</a></p>'
            )
        );
    }

    /**
     * -------------------------------------------------------------------------
     * VIEW LOADING
     * -------------------------------------------------------------------------
     */

    /**
     * Load view template
     *
     * Load view file từ Admin/Views/ directory.
     *
     * @since   2.1.0
     * @param   string  $template   Template name (e.g., 'settings/general')
     * @param   array   $data       Data to extract into view scope
     * @return  void
     */
    private function load_view($template, $data = array())
    {
        // Extract data into local scope
        extract($data);

        // Load view from new location
        $view_path = VIE_THEME_PATH . '/inc/admin/Views/' . $template . '.php';

        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Template not found
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo esc_html(sprintf('View template not found: %s', $template));
            echo '</p></div></div>';
        }
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Class alias for backward compatibility
if (!class_exists('Vie_Admin_Settings')) {
    class_alias('Vie_Admin_Settings_Page', 'Vie_Admin_Settings');
}

// Auto-initialize (maintains original behavior)
new Vie_Admin_Settings_Page();
