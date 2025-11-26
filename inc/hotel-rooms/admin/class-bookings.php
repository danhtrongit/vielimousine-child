<?php
/**
 * Admin Booking Management
 * 
 * Quản lý đơn đặt phòng: Danh sách, Chi tiết, Cập nhật trạng thái
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Admin_Bookings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 20);
        add_action('wp_ajax_vie_update_booking_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_vie_delete_booking', array($this, 'ajax_delete_booking'));
    }
    
    /**
     * Add admin menu
     */
    public function add_menu() {
        add_submenu_page(
            'vie-hotel-rooms',
            __('Quản lý Đặt phòng', 'flavor'),
            __('Đặt phòng', 'flavor'),
            'manage_options',
            'vie-hotel-bookings',
            array($this, 'render_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $booking_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        switch ($action) {
            case 'view':
                $this->render_booking_detail($booking_id);
                break;
            default:
                $this->render_bookings_list();
                break;
        }
    }
    
    /**
     * Render bookings list
     */
    private function render_bookings_list() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'hotel_bookings';
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        
        // Filters
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_hotel = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;
        $filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Build query
        $where = array("1=1");
        $params = array();
        
        if ($filter_status) {
            $where[] = "b.status = %s";
            $params[] = $filter_status;
        }
        
        if ($filter_hotel) {
            $where[] = "b.hotel_id = %d";
            $params[] = $filter_hotel;
        }
        
        if ($filter_date_from) {
            $where[] = "b.check_in >= %s";
            $params[] = $filter_date_from;
        }
        
        if ($filter_date_to) {
            $where[] = "b.check_in <= %s";
            $params[] = $filter_date_to;
        }
        
        if ($search) {
            $where[] = "(b.booking_code LIKE %s OR b.customer_name LIKE %s OR b.customer_phone LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_bookings} b WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total_items = $wpdb->get_var($count_sql);
        $total_pages = ceil($total_items / $per_page);
        
        // Get bookings
        $sql = "SELECT b.*, r.name as room_name 
                FROM {$table_bookings} b 
                LEFT JOIN {$table_rooms} r ON b.room_id = r.id 
                WHERE {$where_sql} 
                ORDER BY b.created_at DESC 
                LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $bookings = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Get hotels for filter
        $hotels = get_posts(array(
            'post_type' => 'hotel',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        // Status labels
        $statuses = array(
            'pending' => array('label' => __('Chờ xác nhận', 'flavor'), 'color' => '#f59e0b'),
            'confirmed' => array('label' => __('Đã xác nhận', 'flavor'), 'color' => '#3b82f6'),
            'cancelled' => array('label' => __('Đã hủy', 'flavor'), 'color' => '#ef4444'),
            'completed' => array('label' => __('Hoàn thành', 'flavor'), 'color' => '#10b981'),
            'no_show' => array('label' => __('Không đến', 'flavor'), 'color' => '#6b7280'),
        );
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Quản lý Đặt phòng', 'flavor'); ?></h1>
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="alignleft">
                    <input type="hidden" name="page" value="vie-hotel-bookings">
                    
                    <select name="status">
                        <option value=""><?php _e('Tất cả trạng thái', 'flavor'); ?></option>
                        <?php foreach ($statuses as $key => $st) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>>
                            <?php echo esc_html($st['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="hotel_id">
                        <option value=""><?php _e('Tất cả khách sạn', 'flavor'); ?></option>
                        <?php foreach ($hotels as $hotel) : ?>
                        <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($filter_hotel, $hotel->ID); ?>>
                            <?php echo esc_html($hotel->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" placeholder="<?php _e('Từ ngày', 'flavor'); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" placeholder="<?php _e('Đến ngày', 'flavor'); ?>">
                    
                    <input type="submit" class="button" value="<?php _e('Lọc', 'flavor'); ?>">
                </form>
                
                <form method="get" class="alignright">
                    <input type="hidden" name="page" value="vie-hotel-bookings">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Tìm theo mã, tên, SĐT...', 'flavor'); ?>">
                    <input type="submit" class="button" value="<?php _e('Tìm kiếm', 'flavor'); ?>">
                </form>
                
                <br class="clear">
            </div>
            
            <!-- Bookings Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:120px"><?php _e('Mã đặt phòng', 'flavor'); ?></th>
                        <th><?php _e('Khách hàng', 'flavor'); ?></th>
                        <th><?php _e('Khách sạn / Phòng', 'flavor'); ?></th>
                        <th style="width:100px"><?php _e('Check-in', 'flavor'); ?></th>
                        <th style="width:100px"><?php _e('Check-out', 'flavor'); ?></th>
                        <th style="width:100px"><?php _e('Tổng tiền', 'flavor'); ?></th>
                        <th style="width:100px"><?php _e('Trạng thái', 'flavor'); ?></th>
                        <th style="width:140px"><?php _e('Ngày đặt', 'flavor'); ?></th>
                        <th style="width:80px"><?php _e('Thao tác', 'flavor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)) : ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px">
                            <?php _e('Chưa có đơn đặt phòng nào', 'flavor'); ?>
                        </td>
                    </tr>
                    <?php else : ?>
                    <?php foreach ($bookings as $booking) : 
                        $hotel_name = get_the_title($booking->hotel_id);
                        $status_info = $statuses[$booking->status] ?? array('label' => $booking->status, 'color' => '#6b7280');
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id); ?>">
                                    <?php echo esc_html($booking->booking_code); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                            <small><?php echo esc_html($booking->customer_phone); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($hotel_name ?: 'N/A'); ?><br>
                            <small><?php echo esc_html($booking->room_name ?? ''); ?></small>
                        </td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in))); ?></td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_out))); ?></td>
                        <td><strong><?php echo Vie_Hotel_Rooms_Helpers::format_currency($booking->total_amount); ?></strong></td>
                        <td>
                            <span class="vie-status-badge" style="background:<?php echo esc_attr($status_info['color']); ?>">
                                <?php echo esc_html($status_info['label']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('d/m/Y H:i', strtotime($booking->created_at))); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id); ?>" 
                               class="button button-small"><?php _e('Xem', 'flavor'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
            .vie-status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                color: #fff;
            }
        </style>
        <?php
    }
    
    /**
     * Render booking detail
     */
    private function render_booking_detail($booking_id) {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'hotel_bookings';
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, r.name as room_name, r.base_occupancy 
             FROM {$table_bookings} b 
             LEFT JOIN {$table_rooms} r ON b.room_id = r.id 
             WHERE b.id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Không tìm thấy đơn đặt phòng', 'flavor') . '</p></div></div>';
            return;
        }
        
        $hotel_name = get_the_title($booking->hotel_id);
        $guests_info = json_decode($booking->guests_info, true) ?: array();
        $pricing_details = json_decode($booking->pricing_details, true) ?: array();
        $surcharges_details = json_decode($booking->surcharges_details, true) ?: array();
        $transport_info = json_decode($booking->transport_info ?? '', true) ?: array();
        
        // Calculate nights
        $date_in = new DateTime($booking->check_in);
        $date_out = new DateTime($booking->check_out);
        $num_nights = $date_out->diff($date_in)->days;
        
        $statuses = array(
            'pending' => __('Chờ xác nhận', 'flavor'),
            'confirmed' => __('Đã xác nhận', 'flavor'),
            'cancelled' => __('Đã hủy', 'flavor'),
            'completed' => __('Hoàn thành', 'flavor'),
            'no_show' => __('Không đến', 'flavor'),
        );
        
        $payment_statuses = array(
            'unpaid' => __('Chưa thanh toán', 'flavor'),
            'partial' => __('Thanh toán một phần', 'flavor'),
            'paid' => __('Đã thanh toán', 'flavor'),
            'refunded' => __('Đã hoàn tiền', 'flavor'),
        );
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php printf(__('Chi tiết đơn #%s', 'flavor'), $booking->booking_code); ?>
            </h1>
            <a href="<?php echo admin_url('admin.php?page=vie-hotel-bookings'); ?>" class="page-title-action">
                <?php _e('← Quay lại', 'flavor'); ?>
            </a>
            <hr class="wp-header-end">
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    
                    <!-- Main Content -->
                    <div id="post-body-content">
                        
                        <!-- Booking Info -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Thông tin đặt phòng', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Khách sạn', 'flavor'); ?></th>
                                        <td><strong><?php echo esc_html($hotel_name); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Loại phòng', 'flavor'); ?></th>
                                        <td><?php echo esc_html($booking->room_name ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Ngày nhận/trả phòng', 'flavor'); ?></th>
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($booking->check_in)); ?></strong>
                                            → 
                                            <strong><?php echo date('d/m/Y', strtotime($booking->check_out)); ?></strong>
                                            <span class="description">(<?php echo $num_nights; ?> đêm)</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Số phòng', 'flavor'); ?></th>
                                        <td><?php echo $booking->num_rooms; ?> phòng</td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Số khách', 'flavor'); ?></th>
                                        <td>
                                            <?php echo $booking->num_adults; ?> người lớn
                                            <?php if ($booking->num_children > 0) : ?>
                                            , <?php echo $booking->num_children; ?> trẻ em
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($guests_info['children_ages'])) : ?>
                                    <tr>
                                        <th><?php _e('Tuổi trẻ em', 'flavor'); ?></th>
                                        <td>
                                            <?php 
                                            $ages = array();
                                            foreach ($guests_info['children_ages'] as $i => $age) {
                                                $ages[] = sprintf('Bé %d: %d tuổi', $i + 1, $age);
                                            }
                                            echo implode(', ', $ages);
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th><?php _e('Loại giá', 'flavor'); ?></th>
                                        <td>
                                            <span class="vie-price-type-badge <?php echo $booking->price_type; ?>">
                                                <?php echo $booking->price_type === 'combo' ? __('Giá Combo', 'flavor') : __('Giá Room Only', 'flavor'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Customer Info -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Thông tin khách hàng', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Họ tên', 'flavor'); ?></th>
                                        <td><strong><?php echo esc_html($booking->customer_name); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Số điện thoại', 'flavor'); ?></th>
                                        <td>
                                            <a href="tel:<?php echo esc_attr($booking->customer_phone); ?>">
                                                <?php echo esc_html($booking->customer_phone); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php if ($booking->customer_email) : ?>
                                    <tr>
                                        <th><?php _e('Email', 'flavor'); ?></th>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                                                <?php echo esc_html($booking->customer_email); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($booking->customer_note) : ?>
                                    <tr>
                                        <th><?php _e('Ghi chú', 'flavor'); ?></th>
                                        <td><?php echo nl2br(esc_html($booking->customer_note ?? '')); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Transport Info -->
                        <?php if (!empty($transport_info) && !empty($transport_info['enabled'])) : ?>
                        <div class="postbox vie-transport-info-box">
                            <div class="postbox-header">
                                <h2>
                                    <span class="dashicons dashicons-car" style="color:#2563eb"></span>
                                    <?php _e('Thông tin Xe đưa đón', 'flavor'); ?>
                                </h2>
                            </div>
                            <div class="inside">
                                <div class="vie-transport-badge">
                                    <span class="vie-badge vie-badge-success">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Khách đăng ký xe đưa đón', 'flavor'); ?>
                                    </span>
                                </div>
                                <table class="form-table vie-transport-table">
                                    <tr>
                                        <th><?php _e('Giờ đi (Pick-up)', 'flavor'); ?></th>
                                        <td>
                                            <strong class="vie-time-display">
                                                <?php echo esc_html($transport_info['pickup_time'] ?? 'N/A'); ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('Giờ về (Drop-off)', 'flavor'); ?></th>
                                        <td>
                                            <strong class="vie-time-display">
                                                <?php echo esc_html($transport_info['dropoff_time'] ?? 'N/A'); ?>
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php if (!empty($transport_info['note'])) : ?>
                                    <tr>
                                        <th><?php _e('Ghi chú điểm đón', 'flavor'); ?></th>
                                        <td><?php echo nl2br(esc_html($transport_info['note'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Pricing Details -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Chi tiết giá', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Ngày', 'flavor'); ?></th>
                                            <th><?php _e('Thứ', 'flavor'); ?></th>
                                            <th style="text-align:right"><?php _e('Giá/đêm', 'flavor'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $day_names = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');
                                        foreach ($pricing_details as $date => $info) : 
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($date)); ?></td>
                                            <td><?php echo $day_names[$info['day_of_week'] ?? 0]; ?></td>
                                            <td style="text-align:right"><?php echo Vie_Hotel_Rooms_Helpers::format_currency($info['price']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2"><?php _e('Tổng tiền phòng', 'flavor'); ?> 
                                                (x<?php echo $booking->num_rooms; ?> phòng)</th>
                                            <th style="text-align:right"><?php echo Vie_Hotel_Rooms_Helpers::format_currency($booking->base_amount); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                                
                                <?php if (!empty($surcharges_details)) : ?>
                                <h4 style="margin-top:20px"><?php _e('Phụ thu', 'flavor'); ?></h4>
                                <table class="widefat">
                                    <tbody>
                                        <?php foreach ($surcharges_details as $surcharge) : ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($surcharge['label']); ?>
                                                <small>(x<?php echo $surcharge['quantity']; ?>
                                                <?php if ($surcharge['is_per_night']) echo ' x ' . $surcharge['nights'] . ' đêm'; ?>
                                                )</small>
                                            </td>
                                            <td style="text-align:right"><?php echo $surcharge['formatted']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th><?php _e('Tổng phụ thu', 'flavor'); ?></th>
                                            <th style="text-align:right"><?php echo Vie_Hotel_Rooms_Helpers::format_currency($booking->surcharges_amount); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                                <?php endif; ?>
                                
                                <div class="vie-total-box">
                                    <span><?php _e('TỔNG CỘNG', 'flavor'); ?></span>
                                    <strong><?php echo Vie_Hotel_Rooms_Helpers::format_currency($booking->total_amount); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Sidebar -->
                    <div id="postbox-container-1" class="postbox-container">
                        
                        <!-- Status Box -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Trạng thái', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <div class="vie-status-section">
                                    <label><?php _e('Trạng thái đơn', 'flavor'); ?></label>
                                    <select id="booking-status" class="widefat">
                                        <?php foreach ($statuses as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($booking->status, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="vie-status-section">
                                    <label><?php _e('Thanh toán', 'flavor'); ?></label>
                                    <select id="payment-status" class="widefat">
                                        <?php foreach ($payment_statuses as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($booking->payment_status, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="vie-status-section">
                                    <label><?php _e('Ghi chú Admin', 'flavor'); ?></label>
                                    <textarea id="admin-note" class="widefat" rows="3"><?php echo esc_textarea($booking->admin_note ?? ''); ?></textarea>
                                </div>
                                
                                <button type="button" id="update-booking-status" class="button button-primary widefat" data-id="<?php echo $booking->id; ?>">
                                    <?php _e('Cập nhật', 'flavor'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Meta Info -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Thông tin hệ thống', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <p><strong><?php _e('Ngày tạo:', 'flavor'); ?></strong><br>
                                <?php echo date('d/m/Y H:i:s', strtotime($booking->created_at)); ?></p>
                                
                                <?php if ($booking->updated_at !== $booking->created_at) : ?>
                                <p><strong><?php _e('Cập nhật lần cuối:', 'flavor'); ?></strong><br>
                                <?php echo date('d/m/Y H:i:s', strtotime($booking->updated_at)); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($booking->ip_address) : ?>
                                <p><strong><?php _e('IP:', 'flavor'); ?></strong><br>
                                <?php echo esc_html($booking->ip_address ?? ''); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Delete -->
                        <div class="postbox">
                            <div class="postbox-header"><h2><?php _e('Hành động', 'flavor'); ?></h2></div>
                            <div class="inside">
                                <button type="button" id="delete-booking" class="button button-link-delete widefat" 
                                        data-id="<?php echo $booking->id; ?>"
                                        data-confirm="<?php _e('Bạn có chắc muốn xóa đơn này?', 'flavor'); ?>">
                                    <?php _e('Xóa đơn đặt phòng', 'flavor'); ?>
                                </button>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
        </div>
        
        <style>
            .vie-total-box {
                margin-top: 20px;
                padding: 16px;
                background: #1e40af;
                color: #fff;
                border-radius: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .vie-total-box strong {
                font-size: 24px;
            }
            .vie-status-section {
                margin-bottom: 16px;
            }
            .vie-status-section label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }
            .vie-price-type-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }
            .vie-price-type-badge.room {
                background: #e0f2fe;
                color: #0369a1;
            }
            .vie-price-type-badge.combo {
                background: #fef3c7;
                color: #b45309;
            }
            #post-body.columns-2 #postbox-container-1 {
                width: 280px;
            }
            /* Transport Info Box Styles */
            .vie-transport-info-box {
                border-left: 4px solid #2563eb;
            }
            .vie-transport-info-box .postbox-header h2 {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .vie-transport-badge {
                margin-bottom: 16px;
            }
            .vie-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
            }
            .vie-badge-success {
                background: #dcfce7;
                color: #166534;
            }
            .vie-badge .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .vie-transport-table th {
                width: 140px;
                padding: 10px 10px 10px 0;
                font-weight: 600;
            }
            .vie-transport-table td {
                padding: 10px 0;
            }
            .vie-time-display {
                display: inline-block;
                padding: 6px 14px;
                background: #eff6ff;
                color: #1e40af;
                border-radius: 6px;
                font-size: 16px;
            }
        </style>
        
        <script>
        jQuery(function($) {
            // Update status
            $('#update-booking-status').on('click', function() {
                var $btn = $(this);
                var bookingId = $btn.data('id');
                
                $btn.prop('disabled', true).text('Đang lưu...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vie_update_booking_status',
                        nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
                        booking_id: bookingId,
                        status: $('#booking-status').val(),
                        payment_status: $('#payment-status').val(),
                        admin_note: $('#admin-note').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Đã cập nhật!');
                        } else {
                            alert(response.data.message || 'Có lỗi xảy ra');
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Cập nhật');
                    }
                });
            });
            
            // Delete booking
            $('#delete-booking').on('click', function() {
                var $btn = $(this);
                if (!confirm($btn.data('confirm'))) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vie_delete_booking',
                        nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
                        booking_id: $btn.data('id')
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '<?php echo admin_url('admin.php?page=vie-hotel-bookings'); ?>';
                        } else {
                            alert(response.data.message || 'Có lỗi xảy ra');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Update booking status
     */
    public function ajax_update_status() {
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }
        
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $payment_status = sanitize_text_field($_POST['payment_status'] ?? '');
        $admin_note = sanitize_textarea_field($_POST['admin_note'] ?? '');
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => $status,
                'payment_status' => $payment_status,
                'admin_note' => $admin_note
            ),
            array('id' => $booking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi cập nhật'));
        }
        
        wp_send_json_success(array('message' => 'Đã cập nhật'));
    }
    
    /**
     * AJAX: Delete booking
     */
    public function ajax_delete_booking() {
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }
        
        $booking_id = absint($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        
        $result = $wpdb->delete($table, array('id' => $booking_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi xóa'));
        }
        
        wp_send_json_success();
    }
}
