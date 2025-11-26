<?php
/**
 * SePay Admin with OAuth2 Auto Connect
 * 
 * Giao diện admin đơn giản với nút "Kết nối SePay"
 * Tự động thiết lập webhook và đồng bộ tài khoản
 * 
 * @package VieHotelRooms
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_SePay_Admin
{
    private static $instance = null;
    private $sepay;
    const MENU_SLUG = 'vie-hotel-sepay';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->sepay = vie_sepay();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_init', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_vie_sepay_setup_webhook', [$this, 'ajax_setup_webhook']);
    }

    public function add_menu_page()
    {
        add_submenu_page(
            'vie-hotel-rooms',
            'SePay Payment',
            '💳 SePay Payment',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Handle OAuth callback from SePay
     */
    public function handle_oauth_callback()
    {
        if (!isset($_GET['vie-sepay-oauth']) || !isset($_GET['access_token'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if ($this->sepay->handle_oauth_callback()) {
            // Redirect to settings page
            wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&connected=1'));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&error=oauth_failed'));
        exit;
    }

    /**
     * Handle admin actions (connect, disconnect)
     */
    public function handle_actions()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Connect action - redirect to OAuth
        if (isset($_GET['action']) && $_GET['action'] === 'connect') {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'vie_sepay_connect')) {
                wp_die('Invalid nonce');
            }

            $oauth_url = $this->sepay->get_oauth_url();
            if ($oauth_url) {
                wp_redirect($oauth_url);
                exit;
            }

            wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&error=oauth_init'));
            exit;
        }

        // Disconnect action
        if (isset($_GET['action']) && $_GET['action'] === 'disconnect') {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'vie_sepay_disconnect')) {
                wp_die('Invalid nonce');
            }

            $this->sepay->disconnect();
            wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&disconnected=1'));
            exit;
        }

        // Save settings
        if (isset($_POST['vie_sepay_save']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'vie_sepay_settings')) {
            $this->save_settings();
        }
    }

    /**
     * Save settings
     */
    private function save_settings()
    {
        $settings = [
            'enabled' => isset($_POST['enabled']) ? 'yes' : 'no',
            'bank_account' => sanitize_text_field($_POST['bank_account'] ?? ''),
            'pay_code_prefix' => preg_replace('/[^A-Z0-9]/i', '', $_POST['pay_code_prefix'] ?? 'VL'),
            'success_message' => wp_kses_post($_POST['success_message'] ?? ''),
        ];

        $this->sepay->update_settings($settings);

        // Setup webhook for selected bank account
        if (!empty($settings['bank_account'])) {
            $this->sepay->setup_webhook($settings['bank_account']);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&saved=1'));
        exit;
    }

    /**
     * AJAX: Setup webhook
     */
    public function ajax_setup_webhook()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        check_ajax_referer('vie_sepay_webhook', '_wpnonce');

        $bank_account_id = sanitize_text_field($_POST['bank_account_id'] ?? '');
        if (empty($bank_account_id)) {
            wp_send_json_error(['message' => 'Vui lòng chọn tài khoản ngân hàng']);
        }

        // Save settings
        $settings = $this->sepay->get_settings();
        $settings['bank_account'] = $bank_account_id;
        $settings['enabled'] = 'yes';
        $this->sepay->update_settings($settings);

        // Setup webhook
        if ($this->sepay->setup_webhook($bank_account_id)) {
            wp_send_json_success(['message' => 'Đã thiết lập thành công!']);
        }

        wp_send_json_error(['message' => 'Không thể tạo webhook. Vui lòng thử lại.']);
    }

    /**
     * Render admin page
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Show notices
        $this->show_notices();

        if ($this->sepay->is_connected()) {
            $this->render_connected_page();
        } else {
            $this->render_connect_page();
        }
    }

    /**
     * Show admin notices
     */
    private function show_notices()
    {
        if (isset($_GET['connected'])) {
            echo '<div class="notice notice-success"><p><strong>✅ Đã kết nối SePay thành công!</strong> Vui lòng chọn tài khoản ngân hàng bên dưới.</p></div>';
        }
        if (isset($_GET['disconnected'])) {
            echo '<div class="notice notice-info"><p>Đã ngắt kết nối SePay.</p></div>';
        }
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p><strong>✅ Đã lưu cài đặt!</strong></p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error"><p>Có lỗi xảy ra. Vui lòng thử lại.</p></div>';
        }
    }

    /**
     * Render connect page (not connected)
     */
    private function render_connect_page()
    {
        $connect_url = wp_nonce_url(
            admin_url('admin.php?page=' . self::MENU_SLUG . '&action=connect'),
            'vie_sepay_connect'
        );
        ?>
        <div class="wrap">
            <h1>
                <img src="https://my.sepay.vn/assets/img/icon_sepay.png" alt="SePay" style="height:32px;vertical-align:middle;margin-right:10px;">
                SePay Payment
            </h1>

            <div class="vie-sepay-connect-box">
                <div class="connect-header">
                    <img src="https://sepay.vn/assets/img/logo.svg" alt="SePay" style="max-width:200px;">
                </div>

                <h2>Kết nối tài khoản SePay của bạn</h2>
                <p>Kết nối tài khoản của bạn thông qua OAuth2 để trải nghiệm tính năng bảo mật cao và quản lý xác thực dễ dàng hơn.</p>

                <h3>Lợi ích khi sử dụng OAuth2:</h3>
                <ul>
                    <li>✅ Tự động thiết lập tài khoản ngân hàng và webhook</li>
                    <li>✅ Bảo mật thông tin và xác thực cao</li>
                    <li>✅ Tự động đồng bộ dữ liệu tài khoản ngân hàng</li>
                    <li>✅ Đồng bộ thông tin cấu hình công ty từ SEPAY sang WordPress</li>
                </ul>

                <p style="margin-top:20px;">
                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary button-hero">
                        🔗 Kết nối SePay
                    </a>
                </p>
                <p class="description">Bạn sẽ được chuyển hướng đến trang xác thực tài khoản an toàn.</p>
            </div>
        </div>

        <style>
            .vie-sepay-connect-box {
                max-width: 600px;
                background: #fff;
                padding: 30px;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                margin-top: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1);
            }
            .vie-sepay-connect-box .connect-header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
                margin-bottom: 20px;
            }
            .vie-sepay-connect-box h2 {
                margin-top: 0;
            }
            .vie-sepay-connect-box ul {
                background: #f8f9fa;
                padding: 15px 15px 15px 35px;
                border-radius: 4px;
            }
            .vie-sepay-connect-box ul li {
                margin-bottom: 8px;
            }
            .button-hero {
                font-size: 16px !important;
                padding: 12px 30px !important;
                height: auto !important;
            }
        </style>
        <?php
    }

    /**
     * Render connected page (settings)
     */
    private function render_connected_page()
    {
        $user_info = $this->sepay->get_user_info();
        $bank_accounts = $this->sepay->get_bank_accounts();
        $settings = $this->sepay->get_settings();
        $prefixes = $this->sepay->get_pay_code_prefixes();
        $webhook_id = get_option(Vie_SePay_Helper::OPT_WEBHOOK_ID);
        $last_connected = get_option(Vie_SePay_Helper::OPT_LAST_CONNECTED);

        $disconnect_url = wp_nonce_url(
            admin_url('admin.php?page=' . self::MENU_SLUG . '&action=disconnect'),
            'vie_sepay_disconnect'
        );
        ?>
        <div class="wrap">
            <h1>
                <img src="https://my.sepay.vn/assets/img/icon_sepay.png" alt="SePay" style="height:32px;vertical-align:middle;margin-right:10px;">
                SePay Payment
            </h1>

            <!-- Connection Status -->
            <div class="vie-sepay-status-box">
                <div class="status-header">
                    <span class="status-badge connected">✅ Đã kết nối</span>
                    <?php if ($user_info): ?>
                        <span class="user-email"><?php echo esc_html($user_info['email'] ?? ''); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($last_connected): ?>
                    <p class="last-connected">Kết nối lúc: <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($last_connected))); ?></p>
                <?php endif; ?>
                <p>
                    <a href="<?php echo esc_url($disconnect_url); ?>" class="button" onclick="return confirm('Bạn có chắc muốn ngắt kết nối?');">
                        ⛓️‍💥 Ngắt kết nối
                    </a>
                </p>
            </div>

            <?php if (empty($webhook_id)): ?>
                <!-- Setup Webhook -->
                <div class="vie-sepay-setup-box">
                    <h2>🔧 Thiết lập tài khoản ngân hàng</h2>
                    <p>Chọn tài khoản ngân hàng để nhận thanh toán. Webhook sẽ được tự động tạo.</p>

                    <form id="vie-sepay-setup-form">
                        <?php wp_nonce_field('vie_sepay_webhook', '_wpnonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th>Tài khoản ngân hàng</th>
                                <td>
                                    <select name="bank_account_id" id="bank_account_id" class="regular-text" required>
                                        <option value="">-- Chọn tài khoản --</option>
                                        <?php foreach ($bank_accounts as $account): ?>
                                            <option value="<?php echo esc_attr($account['id']); ?>">
                                                <?php echo esc_html(sprintf('%s - %s - %s', 
                                                    $account['bank']['short_name'],
                                                    $account['account_number'],
                                                    $account['account_holder_name']
                                                )); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="submit" class="button button-primary button-large">
                                🚀 Thiết lập ngay
                            </button>
                        </p>
                    </form>
                </div>

                <script>
                jQuery(function($) {
                    $('#vie-sepay-setup-form').on('submit', function(e) {
                        e.preventDefault();
                        var $btn = $(this).find('button[type="submit"]');
                        $btn.prop('disabled', true).text('Đang thiết lập...');

                        $.post(ajaxurl, {
                            action: 'vie_sepay_setup_webhook',
                            _wpnonce: $(this).find('[name="_wpnonce"]').val(),
                            bank_account_id: $('#bank_account_id').val()
                        }, function(res) {
                            if (res.success) {
                                alert('✅ ' + res.data.message);
                                location.reload();
                            } else {
                                alert('❌ ' + res.data.message);
                                $btn.prop('disabled', false).text('🚀 Thiết lập ngay');
                            }
                        });
                    });
                });
                </script>
            <?php else: ?>
                <!-- Settings Form -->
                <form method="post">
                    <?php wp_nonce_field('vie_sepay_settings'); ?>
                    <input type="hidden" name="vie_sepay_save" value="1">

                    <div class="vie-sepay-settings-box">
                        <h2>⚙️ Cài đặt thanh toán</h2>

                        <table class="form-table">
                            <tr>
                                <th>Kích hoạt</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enabled" value="yes" <?php checked($settings['enabled'] ?? 'yes', 'yes'); ?>>
                                        Bật thanh toán qua SePay
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Tài khoản ngân hàng</th>
                                <td>
                                    <select name="bank_account" class="regular-text">
                                        <?php foreach ($bank_accounts as $account): ?>
                                            <option value="<?php echo esc_attr($account['id']); ?>" <?php selected($settings['bank_account'] ?? '', $account['id']); ?>>
                                                <?php echo esc_html(sprintf('%s - %s - %s', 
                                                    $account['bank']['short_name'],
                                                    $account['account_number'],
                                                    $account['account_holder_name']
                                                )); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Tiền tố mã thanh toán</th>
                                <td>
                                    <?php if (!empty($prefixes)): ?>
                                        <select name="pay_code_prefix" class="regular-text">
                                            <?php foreach ($prefixes as $p): ?>
                                                <option value="<?php echo esc_attr($p['prefix']); ?>" <?php selected($settings['pay_code_prefix'] ?? '', $p['prefix']); ?>>
                                                    <?php echo esc_html($p['prefix']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="pay_code_prefix" value="<?php echo esc_attr($settings['pay_code_prefix'] ?? 'VL'); ?>" class="small-text">
                                    <?php endif; ?>
                                    <p class="description">Tiền tố + ID đơn hàng. VD: VL123</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Thông báo thành công</th>
                                <td>
                                    <textarea name="success_message" rows="3" class="large-text"><?php echo esc_textarea($settings['success_message'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>

                        <h3>📡 Thông tin Webhook</h3>
                        <table class="form-table">
                            <tr>
                                <th>URL Webhook</th>
                                <td>
                                    <code><?php echo esc_html($this->sepay->get_webhook_url()); ?></code>
                                    <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                                    <span style="color:green;">Đã thiết lập tự động</span>
                                </td>
                            </tr>
                            <tr>
                                <th>API Key</th>
                                <td>
                                    <code><?php echo esc_html(get_option(Vie_SePay_Helper::OPT_WEBHOOK_API_KEY)); ?></code>
                                    <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Lưu cài đặt'); ?>
                    </div>
                </form>

                <!-- Preview -->
                <?php
                $selected_bank = $settings['bank_account'] ?? '';
                if ($selected_bank):
                    $bank = $this->sepay->get_bank_account($selected_bank);
                    if ($bank):
                        $preview_qr = $this->sepay->generate_qr_url(999, 1000000);
                ?>
                <div class="vie-sepay-preview-box">
                    <h2>👁️ Xem trước QR Code</h2>
                    <div class="preview-content">
                        <div class="bank-info">
                            <img src="<?php echo esc_url($bank['bank']['logo_url']); ?>" alt="" style="max-height:50px;">
                            <p><strong><?php echo esc_html($bank['bank']['short_name']); ?></strong></p>
                            <p><?php echo esc_html($bank['account_number']); ?></p>
                            <p><?php echo esc_html($bank['account_holder_name']); ?></p>
                        </div>
                        <div class="qr-preview">
                            <img src="<?php echo esc_url($preview_qr); ?>" alt="QR Preview" style="max-width:200px;">
                            <p class="description">Mẫu: Đơn #999, 1.000.000₫</p>
                        </div>
                    </div>
                </div>
                <?php endif; endif; ?>
            <?php endif; ?>
        </div>

        <style>
            .vie-sepay-status-box, .vie-sepay-setup-box, .vie-sepay-settings-box, .vie-sepay-preview-box {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                margin-top: 20px;
                max-width: 800px;
            }
            .vie-sepay-status-box .status-header {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .status-badge.connected {
                background: #d4edda;
                color: #155724;
                padding: 5px 15px;
                border-radius: 20px;
                font-weight: bold;
            }
            .last-connected {
                color: #666;
                font-size: 13px;
            }
            .vie-sepay-preview-box .preview-content {
                display: flex;
                gap: 40px;
                align-items: center;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            .vie-sepay-preview-box .bank-info {
                text-align: center;
            }
            .vie-sepay-preview-box .qr-preview {
                text-align: center;
            }
            .vie-sepay-preview-box .qr-preview img {
                border: 1px solid #ddd;
                border-radius: 8px;
            }
        </style>
        <?php
    }
}

function vie_sepay_admin()
{
    return Vie_SePay_Admin::get_instance();
}
