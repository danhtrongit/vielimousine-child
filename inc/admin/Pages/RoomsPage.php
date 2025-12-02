<?php
/**
 * ============================================================================
 * TÊN FILE: RoomsPage.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Admin Page Controller quản lý loại phòng khách sạn (Hotel Rooms).
 * Xử lý CRUD operations cho rooms và surcharges.
 *
 * CHỨC NĂNG CHÍNH:
 * - Hiển thị danh sách rooms với hotel filter
 * - Form thêm/sửa loại phòng
 * - Quản lý surcharges (phụ thu)
 * - AJAX handlers cho room operations
 * - Hotel metabox integration
 *
 * PAGE CONTROLLER PATTERN:
 * - Controller: Xử lý logic, database operations
 * - Views: Render HTML (separated)
 * - Direct DB access: Room operations
 *
 * ROUTING:
 * - ?action=list (default) → render_list()
 * - ?action=add → render_form()
 * - ?action=edit&room_id=X → render_form()
 *
 * SỬ DỤNG:
 * $page = new Vie_Admin_Rooms_Page();
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
 * CLASS: Vie_Admin_Rooms_Page
 * ============================================================================
 *
 * Page Controller cho Rooms admin page.
 *
 * ARCHITECTURE:
 * - Page Controller Pattern
 * - Direct database access
 * - WordPress metabox integration
 * - Views: Admin/Views/rooms/*.php
 *
 * AJAX HANDLERS:
 * - vie_save_room
 * - vie_delete_room
 * - vie_get_room
 * - vie_get_rooms_by_hotel
 *
 * @since   2.0.0
 */
class Vie_Admin_Rooms_Page
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Rooms table name
     *
     * @var string
     */
    private $table_rooms;

    /**
     * Surcharges table name
     *
     * @var string
     */
    private $table_surcharges;

    /**
     * Pricing table name
     *
     * @var string
     */
    private $table_pricing;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Initialize table names và register hooks.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->table_rooms      = $wpdb->prefix . 'hotel_rooms';
        $this->table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
        $this->table_pricing    = $wpdb->prefix . 'hotel_room_pricing';

        // Register hooks
        $this->register_ajax_handlers();
        $this->register_metabox();
    }

    /**
     * Register AJAX handlers
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_ajax_handlers()
    {
        add_action('wp_ajax_vie_save_room', array($this, 'ajax_save_room'));
        add_action('wp_ajax_vie_delete_room', array($this, 'ajax_delete_room'));
        add_action('wp_ajax_vie_get_room', array($this, 'ajax_get_room'));
        add_action('wp_ajax_vie_get_rooms_by_hotel', array($this, 'ajax_get_rooms_by_hotel'));
    }

    /**
     * Register hotel metabox
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_metabox()
    {
        add_action('add_meta_boxes', array($this, 'add_hotel_metabox'));
    }

    /**
     * -------------------------------------------------------------------------
     * PAGE RENDERING
     * -------------------------------------------------------------------------
     */

    /**
     * Render main page (router)
     *
     * Route request đến appropriate render method.
     *
     * ACTIONS:
     * - list (default): Rooms list
     * - add: Add room form
     * - edit: Edit room form
     *
     * @since   2.0.0
     * @return  void
     */
    public function render()
    {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_form();
                break;

            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Render rooms list page
     *
     * Hiển thị danh sách rooms với hotel filter.
     *
     * FILTERS:
     * - hotel_id: Filter by hotel
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_list()
    {
        global $wpdb;

        // Get filter
        $selected_hotel = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;

        // Get hotels cho filter
        $hotels = get_posts(array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Build query
        $where  = '1=1';
        $params = array();

        if ($selected_hotel > 0) {
            $where .= ' AND hotel_id = %d';
            $params[] = $selected_hotel;
        }

        $sql = "SELECT * FROM {$this->table_rooms} WHERE {$where} ORDER BY hotel_id, sort_order, name";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rooms = $wpdb->get_results($sql);

        // Load view
        $this->load_view('rooms/list', compact(
            'rooms',
            'hotels',
            'selected_hotel'
        ));
    }

    /**
     * Render room form page (add/edit)
     *
     * Hiển thị form thêm/sửa room.
     *
     * QUERY PARAMS:
     * - room_id: Room ID (for edit)
     * - hotel_id: Hotel ID (for add)
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_form()
    {
        global $wpdb;

        $room_id  = isset($_GET['room_id']) ? absint($_GET['room_id']) : 0;
        $hotel_id = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;

        $room       = null;
        $surcharges = array();

        // Get room data for edit
        if ($room_id > 0) {
            $room = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_rooms} WHERE id = %d",
                $room_id
            ));

            if ($room) {
                $hotel_id   = $room->hotel_id;
                $surcharges = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->table_surcharges} WHERE room_id = %d ORDER BY sort_order",
                    $room_id
                ));
            }
        }

        // Get hotels cho dropdown
        $hotels = get_posts(array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Load view
        $this->load_view('rooms/form', compact(
            'room',
            'room_id',
            'hotel_id',
            'surcharges',
            'hotels'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * HOTEL METABOX
     * -------------------------------------------------------------------------
     */

    /**
     * Add metabox to Hotel post type
     *
     * Hiển thị danh sách rooms trong hotel edit page.
     *
     * @since   2.0.0
     * @return  void
     */
    public function add_hotel_metabox()
    {
        add_meta_box(
            'vie_hotel_rooms_metabox',
            __('Danh sách Loại Phòng', 'vielimousine'),
            array($this, 'render_hotel_metabox'),
            'hotel',
            'normal',
            'high'
        );
    }

    /**
     * Render hotel metabox content
     *
     * @since   2.0.0
     * @param   WP_Post $post   Hotel post object
     * @return  void
     */
    public function render_hotel_metabox($post)
    {
        global $wpdb;

        $hotel_id = $post->ID;

        // Get rooms for this hotel
        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms} WHERE hotel_id = %d ORDER BY sort_order, name",
            $hotel_id
        ));

        ?>
        <div class="vie-hotel-rooms-metabox">
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms&action=add&hotel_id=' . $hotel_id)); ?>" class="button button-primary">
                    <?php esc_html_e('Thêm Loại Phòng Mới', 'vielimousine'); ?>
                </a>
            </p>

            <?php if (empty($rooms)): ?>
                <p><?php esc_html_e('Chưa có loại phòng nào.', 'vielimousine'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tên Phòng', 'vielimousine'); ?></th>
                            <th><?php esc_html_e('Số Phòng', 'vielimousine'); ?></th>
                            <th><?php esc_html_e('Trạng Thái', 'vielimousine'); ?></th>
                            <th><?php esc_html_e('Thao Tác', 'vielimousine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><strong><?php echo esc_html($room->name); ?></strong></td>
                                <td><?php echo esc_html($room->total_rooms); ?></td>
                                <td>
                                    <?php if ($room->status === 'active'): ?>
                                        <span style="color: #10b981;">●</span> Hoạt động
                                    <?php else: ?>
                                        <span style="color: #6b7280;">●</span> Tạm ngưng
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms&action=edit&room_id=' . $room->id)); ?>">
                                        Sửa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Save room
     *
     * Save (create/update) room data.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID (0 for new)
     * - hotel_id: Hotel ID
     * - name: Room name
     * - description: Description
     * - total_rooms: Total rooms
     * - status: Status (active/inactive)
     * - ...and more fields
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_room()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get params
        $room_id = absint($_POST['room_id'] ?? 0);

        // Prepare room data
        $room_data = array(
            'hotel_id'            => absint($_POST['hotel_id'] ?? 0),
            'name'                => sanitize_text_field($_POST['name'] ?? ''),
            'description'         => wp_kses_post($_POST['description'] ?? ''),
            'total_rooms'         => absint($_POST['total_rooms'] ?? 0),
            'max_adults'          => absint($_POST['max_adults'] ?? 2),
            'max_children'        => absint($_POST['max_children'] ?? 0),
            'base_occupancy'      => absint($_POST['base_occupancy'] ?? 2),
            'max_occupancy'       => absint($_POST['max_occupancy'] ?? 4),
            'room_size'           => sanitize_text_field($_POST['room_size'] ?? ''),
            'bed_type'            => sanitize_text_field($_POST['bed_type'] ?? ''),
            'view_type'           => sanitize_text_field($_POST['view_type'] ?? ''),
            'price_includes'      => sanitize_textarea_field($_POST['price_includes'] ?? ''),
            'cancellation_policy' => sanitize_textarea_field($_POST['cancellation_policy'] ?? ''),
            'featured_image_id'   => absint($_POST['featured_image_id'] ?? 0),
            'gallery_ids'         => isset($_POST['gallery_ids']) ? $_POST['gallery_ids'] : '', // Already JSON from form
            'status'              => in_array($_POST['status'] ?? '', array('active', 'inactive'))
                                        ? $_POST['status']
                                        : 'active',
            'sort_order'          => absint($_POST['sort_order'] ?? 0),
        );

        // Validate
        if (empty($room_data['hotel_id']) || empty($room_data['name'])) {
            wp_send_json_error(array('message' => 'Thiếu thông tin bắt buộc'));
        }

        // Save room
        if ($room_id > 0) {
            // Update existing
            $result = $wpdb->update($this->table_rooms, $room_data, array('id' => $room_id));
        } else {
            // Insert new
            $result  = $wpdb->insert($this->table_rooms, $room_data);
            $room_id = $wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi lưu dữ liệu: ' . $wpdb->last_error));
        }

        // Save surcharges (phụ thu)
        if (isset($_POST['surcharges']) && is_array($_POST['surcharges'])) {
            // Delete old surcharges
            $wpdb->delete($this->table_surcharges, array('room_id' => $room_id));

            // Insert new surcharges
            foreach ($_POST['surcharges'] as $index => $surcharge) {
                // Skip if missing required fields
                if (empty($surcharge['surcharge_type']) || !isset($surcharge['amount'])) {
                    continue;
                }

                $surcharge_data = array(
                    'room_id'          => $room_id,
                    'surcharge_type'   => in_array($surcharge['surcharge_type'], array('extra_bed', 'child', 'adult', 'breakfast', 'other'))
                                            ? $surcharge['surcharge_type']
                                            : 'other',
                    'label'            => sanitize_text_field($surcharge['label'] ?? ''),
                    'min_age'          => isset($surcharge['min_age']) && $surcharge['min_age'] !== ''
                                            ? absint($surcharge['min_age'])
                                            : null,
                    'max_age'          => isset($surcharge['max_age']) && $surcharge['max_age'] !== ''
                                            ? absint($surcharge['max_age'])
                                            : null,
                    'amount'           => floatval($surcharge['amount']),
                    'amount_type'      => in_array($surcharge['amount_type'] ?? '', array('fixed', 'percent'))
                                            ? $surcharge['amount_type']
                                            : 'fixed',
                    'is_per_night'     => !empty($surcharge['is_per_night']) ? 1 : 0,
                    'is_mandatory'     => !empty($surcharge['is_mandatory']) ? 1 : 0,
                    'applies_to_room'  => !empty($surcharge['applies_to_room']) ? 1 : 0,
                    'applies_to_combo' => !empty($surcharge['applies_to_combo']) ? 1 : 0,
                    'sort_order'       => absint($index),
                );

                $wpdb->insert($this->table_surcharges, $surcharge_data);
            }
        }

        wp_send_json_success(array(
            'message' => 'Đã lưu phòng thành công',
            'room_id' => $room_id,
        ));
    }

    /**
     * AJAX: Delete room
     *
     * Delete room và related data.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_delete_room()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        $room_id = absint($_POST['room_id'] ?? 0);

        if (!$room_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }

        // Delete room
        $result = $wpdb->delete($this->table_rooms, array('id' => $room_id));

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi xóa dữ liệu'));
        }

        // Delete related data
        $wpdb->delete($this->table_surcharges, array('room_id' => $room_id));
        $wpdb->delete($this->table_pricing, array('room_id' => $room_id));

        wp_send_json_success(array('message' => 'Đã xóa phòng'));
    }

    /**
     * AJAX: Get room
     *
     * Get single room data.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_get_room()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        $room_id = absint($_POST['room_id'] ?? 0);

        if (!$room_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }

        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms} WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        wp_send_json_success(array('room' => $room));
    }

    /**
     * AJAX: Get rooms by hotel
     *
     * Get all rooms cho 1 hotel.
     *
     * REQUEST PARAMS:
     * - hotel_id: Hotel ID
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_get_rooms_by_hotel()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        global $wpdb;

        $hotel_id = absint($_POST['hotel_id'] ?? 0);

        if (!$hotel_id) {
            wp_send_json_error(array('message' => 'Hotel ID không hợp lệ'));
        }

        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms} WHERE hotel_id = %d AND status = 'active' ORDER BY sort_order, name",
            $hotel_id
        ));

        wp_send_json_success(array('rooms' => $rooms));
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
     * @param   string  $template   Template name (e.g., 'rooms/list')
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
if (!class_exists('Vie_Admin_Rooms')) {
    class_alias('Vie_Admin_Rooms_Page', 'Vie_Admin_Rooms');
}

// Auto-initialize (maintains original behavior)
new Vie_Admin_Rooms_Page();
