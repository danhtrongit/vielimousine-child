<?php
/**
 * Admin Settings
 * 
 * Cấu hình Email và các cài đặt khác
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Admin_Settings
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 30);
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_menu()
    {
        add_submenu_page(
            'vie-hotel-rooms',
            __('Cấu hình Email', 'flavor'),
            __('Cấu hình Email', 'flavor'),
            'manage_options',
            'vie-hotel-settings',
            array($this, 'render_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('vie_hotel_email_settings', 'vie_hotel_email_pending');
        register_setting('vie_hotel_email_settings', 'vie_hotel_email_processing');
        register_setting('vie_hotel_email_settings', 'vie_hotel_email_completed');
    }

    /**
     * Render page
     */
    public function render_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pending';

        // Get Email Manager instance to get defaults
        require_once get_stylesheet_directory() . '/inc/hotel-rooms/includes/class-email-manager.php';
        $email_manager = Vie_Hotel_Rooms_Email_Manager::get_instance();

        ?>
        <div class="wrap">
            <h1><?php _e('Cấu hình Email thông báo', 'flavor'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=vie-hotel-settings&tab=pending"
                    class="nav-tab <?php echo $active_tab == 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email 1: Tiếp nhận (Pending)', 'flavor'); ?>
                </a>
                <a href="?page=vie-hotel-settings&tab=processing"
                    class="nav-tab <?php echo $active_tab == 'processing' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email 2: Đã thanh toán (Processing)', 'flavor'); ?>
                </a>
                <a href="?page=vie-hotel-settings&tab=completed"
                    class="nav-tab <?php echo $active_tab == 'completed' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email 3: Hoàn thành (Completed)', 'flavor'); ?>
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('vie_hotel_email_settings'); ?>

                <div class="card" style="margin-top: 20px; padding: 20px; max-width: 100%;">
                    <?php
                    $option_name = 'vie_hotel_email_' . $active_tab;
                    $current_settings = $email_manager->get_template($active_tab);

                    // Description
                    $descriptions = array(
                        'pending' => 'Gửi ngay khi khách đặt phòng. Lúc này chưa có mã phòng.',
                        'processing' => 'Gửi khi đã nhận tiền. Thông báo đang liên hệ khách sạn.',
                        'completed' => 'Gửi khi Admin đã nhập Mã nhận phòng. Chứa voucher để check-in.'
                    );
                    echo '<p class="description" style="font-size:14px; margin-bottom:20px">' . $descriptions[$active_tab] . '</p>';
                    ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('Tiêu đề Email', 'flavor'); ?></label></th>
                            <td>
                                <input type="text" name="<?php echo $option_name; ?>[subject]"
                                    value="<?php echo esc_attr($current_settings['subject']); ?>" class="regular-text"
                                    style="width: 100%;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('Nội dung Email', 'flavor'); ?></label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $current_settings['body'],
                                    $option_name . '_body',
                                    array(
                                        'textarea_name' => $option_name . '[body]',
                                        'textarea_rows' => 15,
                                        'media_buttons' => true
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                    </table>

                    <div class="vie-shortcodes-box"
                        style="background: #f0f0f1; padding: 15px; margin-top: 20px; border-radius: 4px;">
                        <h4><?php _e('Các biến có thể sử dụng:', 'flavor'); ?></h4>
                        <code style="display:inline-block; margin:5px">{customer_name}</code>: Tên khách hàng<br>
                        <code style="display:inline-block; margin:5px">{booking_id}</code>: Mã đơn hàng hệ thống<br>
                        <code style="display:inline-block; margin:5px">{hotel_name}</code>: Tên khách sạn<br>
                        <code style="display:inline-block; margin:5px">{room_name}</code>: Tên loại phòng<br>
                        <code style="display:inline-block; margin:5px">{check_in}</code>: Ngày nhận phòng<br>
                        <code style="display:inline-block; margin:5px">{check_out}</code>: Ngày trả phòng<br>
                        <code style="display:inline-block; margin:5px">{total_amount}</code>: Tổng tiền<br>
                        <?php if ($active_tab === 'completed'): ?>
                            <code style="display:inline-block; margin:5px; background: #dcfce7; color: #166534;">{room_code}</code>:
                            <strong>Mã nhận phòng</strong><br>
                        <?php endif; ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
