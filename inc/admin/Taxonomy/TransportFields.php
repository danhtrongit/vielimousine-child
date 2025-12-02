<?php
/**
 * ============================================================================
 * TÊN FILE: TransportFields.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Thêm custom fields cho taxonomy 'hotel-location'
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Taxonomy
 * @version     1.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

class Vie_Transport_Fields {

    public function __construct() {
        // Add custom fields to 'hotel-location'
        add_action('hotel-location_add_form_fields', array($this, 'add_fields'));
        add_action('hotel-location_edit_form_fields', array($this, 'edit_fields'));
        
        // Save custom fields
        add_action('created_hotel-location', array($this, 'save_fields'));
        add_action('edited_hotel-location', array($this, 'save_fields'));
    }

    /**
     * Add fields to "Add New Location" form
     */
    public function add_fields($taxonomy) {
        ?>
        <div class="form-field term-group">
            <label for="pickup_times"><?php _e('Giờ đón (Mỗi dòng một giờ)', 'viechild'); ?></label>
            <textarea name="pickup_times" id="pickup_times" rows="5" cols="40"></textarea>
            <p class="description"><?php _e('Ví dụ:<br>10:00<br>14:00', 'viechild'); ?></p>
        </div>
        <div class="form-field term-group">
            <label for="dropoff_times"><?php _e('Giờ về (Mỗi dòng một giờ)', 'viechild'); ?></label>
            <textarea name="dropoff_times" id="dropoff_times" rows="5" cols="40"></textarea>
            <p class="description"><?php _e('Ví dụ:<br>12:00<br>16:00', 'viechild'); ?></p>
        </div>
        <div class="form-field term-group">
            <label for="transport_note"><?php _e('Ghi chú xe đưa đón', 'viechild'); ?></label>
            <textarea name="transport_note" id="transport_note" rows="3" cols="40"></textarea>
        </div>
        <?php
    }

    /**
     * Add fields to "Edit Location" form
     */
    public function edit_fields($term) {
        $pickup_times = get_term_meta($term->term_id, 'pickup_times', true);
        $dropoff_times = get_term_meta($term->term_id, 'dropoff_times', true);
        $transport_note = get_term_meta($term->term_id, 'transport_note', true);
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="pickup_times"><?php _e('Giờ đón', 'viechild'); ?></label></th>
            <td>
                <textarea name="pickup_times" id="pickup_times" rows="5" cols="50" class="large-text"><?php echo esc_textarea($pickup_times); ?></textarea>
                <p class="description"><?php _e('Nhập mỗi khung giờ trên một dòng.', 'viechild'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="dropoff_times"><?php _e('Giờ về', 'viechild'); ?></label></th>
            <td>
                <textarea name="dropoff_times" id="dropoff_times" rows="5" cols="50" class="large-text"><?php echo esc_textarea($dropoff_times); ?></textarea>
                <p class="description"><?php _e('Nhập mỗi khung giờ trên một dòng.', 'viechild'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="transport_note"><?php _e('Ghi chú xe đưa đón', 'viechild'); ?></label></th>
            <td>
                <textarea name="transport_note" id="transport_note" rows="3" cols="50" class="large-text"><?php echo esc_textarea($transport_note); ?></textarea>
            </td>
        </tr>
        <?php
    }

    /**
     * Save custom fields
     */
    public function save_fields($term_id) {
        if (isset($_POST['pickup_times'])) {
            update_term_meta($term_id, 'pickup_times', sanitize_textarea_field($_POST['pickup_times']));
        }
        if (isset($_POST['dropoff_times'])) {
            update_term_meta($term_id, 'dropoff_times', sanitize_textarea_field($_POST['dropoff_times']));
        }
        if (isset($_POST['transport_note'])) {
            update_term_meta($term_id, 'transport_note', sanitize_textarea_field($_POST['transport_note']));
        }
    }
}

new Vie_Transport_Fields();
