<?php
/**
 * ============================================================================
 * TÊN FILE: HotelPostType.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Đảm bảo Hotel post type sử dụng custom capabilities phù hợp với Hotel Manager role
 * 
 * CHỨC NĂNG:
 * - Map capabilities cho Hotel post type
 * - Cho phép Hotel Manager chỉnh sửa Hotel posts
 * - Đồng bộ quyền với role system
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin
 * @version     1.0.0
 * @since       1.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

class Vie_Hotel_Post_Type {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook vào init với priority cao để override nếu cần
        add_action('init', array($this, 'setup_hotel_post_type_capabilities'), 99);
        
        // Setup taxonomy capabilities
        add_action('init', array($this, 'setup_hotel_taxonomy_capabilities'), 99);
        
        // Map meta capabilities
        add_filter('map_meta_cap', array($this, 'map_hotel_meta_caps'), 10, 4);
    }

    /**
     * Setup Hotel post type với custom capabilities
     * 
     * @since 1.0.0
     */
    public function setup_hotel_post_type_capabilities() {
        // Kiểm tra xem post type 'hotel' đã tồn tại chưa
        if (!post_type_exists('hotel')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Vie Hotel Post Type] Hotel post type does not exist yet');
            }
            return;
        }

        global $wp_post_types;

        // Lấy object của hotel post type
        $hotel_post_type = $wp_post_types['hotel'];

        // Đảm bảo có object capabilities
        if (!isset($hotel_post_type->cap)) {
            $hotel_post_type->cap = new stdClass();
        }

        // Set custom capabilities cho Hotel post type
        $hotel_post_type->cap->edit_post          = 'edit_hotels';
        $hotel_post_type->cap->read_post          = 'read_hotels';
        $hotel_post_type->cap->delete_post        = 'delete_hotels';
        $hotel_post_type->cap->edit_posts         = 'edit_hotels';
        $hotel_post_type->cap->edit_others_posts  = 'edit_others_hotels';
        $hotel_post_type->cap->publish_posts      = 'publish_hotels';
        $hotel_post_type->cap->read_private_posts = 'read_private_hotels';
        $hotel_post_type->cap->delete_posts       = 'delete_hotels';
        $hotel_post_type->cap->delete_private_posts    = 'delete_private_hotels';
        $hotel_post_type->cap->delete_published_posts  = 'delete_published_hotels';
        $hotel_post_type->cap->delete_others_posts     = 'delete_others_hotels';
        $hotel_post_type->cap->edit_private_posts      = 'edit_private_hotels';
        $hotel_post_type->cap->edit_published_posts    = 'edit_published_hotels';
        $hotel_post_type->cap->create_posts            = 'edit_hotels';

        // Set capability_type
        $hotel_post_type->capability_type = array('hotel', 'hotels');
        $hotel_post_type->map_meta_cap = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Vie Hotel Post Type] Updated Hotel post type capabilities');
        }
    }

    /**
     * Map meta capabilities cho Hotel post type
     * 
     * @since 1.0.0
     * @param array  $caps    Required capabilities
     * @param string $cap     Capability being checked
     * @param int    $user_id User ID
     * @param array  $args    Additional arguments
     * @return array
     */
    public function map_hotel_meta_caps($caps, $cap, $user_id, $args) {
        // Chỉ xử lý cho hotel-related capabilities
        $hotel_caps = array(
            'edit_hotel',
            'delete_hotel',
            'read_hotel',
        );

        if (!in_array($cap, $hotel_caps)) {
            return $caps;
        }

        // Lấy post nếu có
        $post = null;
        if (isset($args[0])) {
            $post = get_post($args[0]);
        }

        // Nếu không phải hotel post type, không xử lý
        if ($post && $post->post_type !== 'hotel') {
            return $caps;
        }

        // Map capabilities
        switch ($cap) {
            case 'edit_hotel':
                if (!$post) {
                    $caps = array('edit_hotels');
                } else {
                    // Nếu là post của mình
                    if ($post->post_author == $user_id) {
                        $caps = array('edit_hotels');
                    } else {
                        // Nếu là post của người khác
                        $caps = array('edit_others_hotels');
                    }

                    // Nếu post đã published
                    if ($post->post_status === 'publish') {
                        $caps[] = 'edit_published_hotels';
                    }

                    // Nếu post là private
                    if ($post->post_status === 'private') {
                        $caps[] = 'edit_private_hotels';
                    }
                }
                break;

            case 'delete_hotel':
                if (!$post) {
                    $caps = array('delete_hotels');
                } else {
                    // Nếu là post của mình
                    if ($post->post_author == $user_id) {
                        $caps = array('delete_hotels');
                    } else {
                        // Nếu là post của người khác
                        $caps = array('delete_others_hotels');
                    }

                    // Nếu post đã published
                    if ($post->post_status === 'publish') {
                        $caps[] = 'delete_published_hotels';
                    }

                    // Nếu post là private
                    if ($post->post_status === 'private') {
                        $caps[] = 'delete_private_hotels';
                    }
                }
                break;

            case 'read_hotel':
                if (!$post) {
                    $caps = array('read');
                } else {
                    if ($post->post_status === 'private') {
                        // Private posts cần quyền đặc biệt
                        if ($post->post_author == $user_id) {
                            $caps = array('read');
                        } else {
                            $caps = array('read_private_hotels');
                        }
                    } else {
                        $caps = array('read');
                    }
                }
                break;
        }

        return $caps;
    }

    /**
     * Setup Hotel taxonomy capabilities
     * 
     * @since 1.0.0
     */
    public function setup_hotel_taxonomy_capabilities() {
        // Danh sách các taxonomy liên quan đến hotel
        $hotel_taxonomies = array(
            'hotel-location',
            'hotel-category', 
            'hotel-rank',
            'hotel-convenient',
        );

        foreach ($hotel_taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            global $wp_taxonomies;
            $tax_obj = $wp_taxonomies[$taxonomy];

            // Set custom capabilities cho taxonomy
            $tax_slug = str_replace('-', '_', $taxonomy);
            
            $tax_obj->cap->manage_terms = 'manage_' . $tax_slug;
            $tax_obj->cap->edit_terms = 'edit_' . $tax_slug;
            $tax_obj->cap->delete_terms = 'delete_' . $tax_slug;
            $tax_obj->cap->assign_terms = 'assign_' . $tax_slug;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Vie Hotel Post Type] Updated capabilities for taxonomy: ' . $taxonomy);
            }
        }
    }

    /**
     * Thêm capabilities cho Administrator role
     * 
     * @since 1.0.0
     */
    public static function grant_admin_hotel_capabilities() {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }

        // Danh sách capabilities cho Hotel post type
        $hotel_caps = array(
            'edit_hotels',
            'edit_others_hotels',
            'publish_hotels',
            'read_private_hotels',
            'delete_hotels',
            'delete_private_hotels',
            'delete_published_hotels',
            'delete_others_hotels',
            'edit_private_hotels',
            'edit_published_hotels',
        );

        // Danh sách capabilities cho Hotel taxonomies
        $taxonomy_caps = array(
            // hotel-location
            'manage_hotel_location',
            'edit_hotel_location',
            'delete_hotel_location',
            'assign_hotel_location',
            
            // hotel-category
            'manage_hotel_category',
            'edit_hotel_category',
            'delete_hotel_category',
            'assign_hotel_category',
            
            // hotel-rank
            'manage_hotel_rank',
            'edit_hotel_rank',
            'delete_hotel_rank',
            'assign_hotel_rank',
            
            // hotel-convenient
            'manage_hotel_convenient',
            'edit_hotel_convenient',
            'delete_hotel_convenient',
            'assign_hotel_convenient',
        );

        // Gộp tất cả capabilities
        $all_caps = array_merge($hotel_caps, $taxonomy_caps);

        // Thêm từng capability vào Administrator role
        foreach ($all_caps as $cap) {
            $admin_role->add_cap($cap);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Vie Hotel Post Type] Granted Hotel and Taxonomy capabilities to Administrator role');
        }
    }
}

// Initialize
Vie_Hotel_Post_Type::get_instance();

// Hook để cấp quyền cho Admin khi theme activate
add_action('after_switch_theme', array('Vie_Hotel_Post_Type', 'grant_admin_hotel_capabilities'));
