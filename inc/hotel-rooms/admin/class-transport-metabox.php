<?php
/**
 * Transport Configuration Meta Box for Hotel Post Type
 * 
 * Cấu hình xe đưa đón cho từng khách sạn
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Transport_Metabox {
    
    /**
     * Meta keys
     */
    const META_ENABLED = '_vie_transport_enabled';
    const META_PICKUP_TIMES = '_vie_transport_pickup_times';
    const META_DROPOFF_TIMES = '_vie_transport_dropoff_times';
    const META_PICKUP_NOTE = '_vie_transport_pickup_note';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_hotel', array($this, 'save_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'vie_transport_config',
            __('Cấu hình Xe đưa đón', 'flavor'),
            array($this, 'render_meta_box'),
            'hotel',
            'normal',
            'high'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type !== 'hotel' || !in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_style(
            'vie-transport-metabox',
            VIE_HOTEL_ROOMS_URL . 'assets/css/transport-metabox.css',
            array(),
            VIE_HOTEL_ROOMS_VERSION
        );
        
        wp_enqueue_script(
            'vie-transport-metabox',
            VIE_HOTEL_ROOMS_URL . 'assets/js/transport-metabox.js',
            array('jquery'),
            VIE_HOTEL_ROOMS_VERSION,
            true
        );
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        wp_nonce_field('vie_transport_meta_box', 'vie_transport_nonce');
        
        // Get saved values
        $enabled = get_post_meta($post->ID, self::META_ENABLED, true);
        $pickup_times = get_post_meta($post->ID, self::META_PICKUP_TIMES, true);
        $dropoff_times = get_post_meta($post->ID, self::META_DROPOFF_TIMES, true);
        $pickup_note = get_post_meta($post->ID, self::META_PICKUP_NOTE, true);
        
        // Parse times arrays
        $pickup_times = !empty($pickup_times) ? (array) $pickup_times : array();
        $dropoff_times = !empty($dropoff_times) ? (array) $dropoff_times : array();
        
        ?>
        <div class="vie-transport-metabox">
            <!-- Enable Transport Toggle -->
            <div class="vie-transport-field vie-transport-toggle">
                <label class="vie-toggle-switch">
                    <input type="checkbox" 
                           name="vie_transport_enabled" 
                           id="vie_transport_enabled" 
                           value="1" 
                           <?php checked($enabled, '1'); ?>>
                    <span class="vie-toggle-slider"></span>
                </label>
                <div class="vie-toggle-label">
                    <strong><?php _e('Có hỗ trợ xe đưa đón không?', 'flavor'); ?></strong>
                    <p class="description"><?php _e('Bật để hiển thị tùy chọn đăng ký xe đưa đón trong form đặt phòng', 'flavor'); ?></p>
                </div>
            </div>
            
            <!-- Transport Config Fields (shown when enabled) -->
            <div class="vie-transport-config" id="vie-transport-config" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                
                <!-- Pickup Times -->
                <div class="vie-transport-field">
                    <label>
                        <strong><?php _e('Giờ đi (Pick-up Times)', 'flavor'); ?></strong>
                        <span class="description"><?php _e('Các khung giờ xe chạy đi từ điểm đón', 'flavor'); ?></span>
                    </label>
                    <div class="vie-time-repeater" id="vie-pickup-times">
                        <?php if (!empty($pickup_times)) : ?>
                            <?php foreach ($pickup_times as $index => $time) : ?>
                            <div class="vie-time-item">
                                <input type="time" 
                                       name="vie_transport_pickup_times[]" 
                                       value="<?php echo esc_attr($time); ?>" 
                                       class="vie-time-input">
                                <button type="button" class="vie-remove-time button-link-delete">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button vie-add-time" data-target="vie-pickup-times" data-name="vie_transport_pickup_times[]">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Thêm giờ đi', 'flavor'); ?>
                    </button>
                </div>
                
                <!-- Dropoff Times -->
                <div class="vie-transport-field">
                    <label>
                        <strong><?php _e('Giờ về (Drop-off Times)', 'flavor'); ?></strong>
                        <span class="description"><?php _e('Các khung giờ xe chạy về từ khách sạn', 'flavor'); ?></span>
                    </label>
                    <div class="vie-time-repeater" id="vie-dropoff-times">
                        <?php if (!empty($dropoff_times)) : ?>
                            <?php foreach ($dropoff_times as $index => $time) : ?>
                            <div class="vie-time-item">
                                <input type="time" 
                                       name="vie_transport_dropoff_times[]" 
                                       value="<?php echo esc_attr($time); ?>" 
                                       class="vie-time-input">
                                <button type="button" class="vie-remove-time button-link-delete">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button vie-add-time" data-target="vie-dropoff-times" data-name="vie_transport_dropoff_times[]">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Thêm giờ về', 'flavor'); ?>
                    </button>
                </div>
                
                <!-- Pickup Note -->
                <div class="vie-transport-field">
                    <label for="vie_transport_pickup_note">
                        <strong><?php _e('Ghi chú / Địa điểm đón', 'flavor'); ?></strong>
                        <span class="description"><?php _e('Ví dụ: "Đón tại Nhà hát lớn lúc..."', 'flavor'); ?></span>
                    </label>
                    <textarea name="vie_transport_pickup_note" 
                              id="vie_transport_pickup_note" 
                              rows="3" 
                              class="large-text"
                              placeholder="<?php _e('Nhập thông tin địa điểm đón, ghi chú cho khách...', 'flavor'); ?>"><?php echo esc_textarea($pickup_note); ?></textarea>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_box($post_id) {
        // Verify nonce
        if (!isset($_POST['vie_transport_nonce']) || 
            !wp_verify_nonce($_POST['vie_transport_nonce'], 'vie_transport_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save enabled status
        $enabled = isset($_POST['vie_transport_enabled']) ? '1' : '0';
        update_post_meta($post_id, self::META_ENABLED, $enabled);
        
        // Save pickup times
        $pickup_times = array();
        if (!empty($_POST['vie_transport_pickup_times']) && is_array($_POST['vie_transport_pickup_times'])) {
            foreach ($_POST['vie_transport_pickup_times'] as $time) {
                $time = sanitize_text_field($time);
                if (!empty($time)) {
                    $pickup_times[] = $time;
                }
            }
        }
        update_post_meta($post_id, self::META_PICKUP_TIMES, $pickup_times);
        
        // Save dropoff times
        $dropoff_times = array();
        if (!empty($_POST['vie_transport_dropoff_times']) && is_array($_POST['vie_transport_dropoff_times'])) {
            foreach ($_POST['vie_transport_dropoff_times'] as $time) {
                $time = sanitize_text_field($time);
                if (!empty($time)) {
                    $dropoff_times[] = $time;
                }
            }
        }
        update_post_meta($post_id, self::META_DROPOFF_TIMES, $dropoff_times);
        
        // Save pickup note
        $pickup_note = isset($_POST['vie_transport_pickup_note']) 
            ? sanitize_textarea_field($_POST['vie_transport_pickup_note']) 
            : '';
        update_post_meta($post_id, self::META_PICKUP_NOTE, $pickup_note);
    }
    
    /**
     * Get transport config for a hotel
     * 
     * @param int $hotel_id
     * @return array
     */
    public static function get_transport_config($hotel_id) {
        $enabled = get_post_meta($hotel_id, self::META_ENABLED, true);
        
        if (!$enabled) {
            return array(
                'enabled' => false,
                'pickup_times' => array(),
                'dropoff_times' => array(),
                'pickup_note' => ''
            );
        }
        
        $pickup_times = get_post_meta($hotel_id, self::META_PICKUP_TIMES, true);
        $dropoff_times = get_post_meta($hotel_id, self::META_DROPOFF_TIMES, true);
        $pickup_note = get_post_meta($hotel_id, self::META_PICKUP_NOTE, true);
        
        return array(
            'enabled' => true,
            'pickup_times' => !empty($pickup_times) ? (array) $pickup_times : array(),
            'dropoff_times' => !empty($dropoff_times) ? (array) $dropoff_times : array(),
            'pickup_note' => $pickup_note ?: ''
        );
    }
}
