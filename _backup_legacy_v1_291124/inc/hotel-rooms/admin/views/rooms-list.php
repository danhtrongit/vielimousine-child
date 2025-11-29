<?php
/**
 * Admin View: Rooms List - Sử dụng WP native classes
 * @package VieHotelRooms
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Quản lý Loại Phòng', 'viechild'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add'); ?>" class="page-title-action"><?php _e('Thêm mới', 'viechild'); ?></a>
    <hr class="wp-header-end">
    
    <!-- Filter - using tablenav -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="vie-hotel-rooms">
                <label for="hotel_id" class="screen-reader-text"><?php _e('Lọc theo khách sạn', 'viechild'); ?></label>
                <select name="hotel_id" id="hotel_id">
                    <option value=""><?php _e('Tất cả khách sạn', 'viechild'); ?></option>
                    <?php foreach ($hotels as $hotel) : ?>
                        <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($selected_hotel, $hotel->ID); ?>>
                            <?php echo esc_html($hotel->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Lọc', 'viechild'), '', 'filter_action', false); ?>
            </form>
        </div>
        <br class="clear">
    </div>

    <?php if (!empty($rooms)) : ?>
    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" style="width:50px"><?php _e('ID', 'viechild'); ?></th>
                <th scope="col" style="width:70px"><?php _e('Ảnh', 'viechild'); ?></th>
                <th scope="col"><?php _e('Tên phòng', 'viechild'); ?></th>
                <th scope="col"><?php _e('Khách sạn', 'viechild'); ?></th>
                <th scope="col" style="width:100px"><?php _e('Sức chứa', 'viechild'); ?></th>
                <th scope="col" style="width:130px"><?php _e('Giá cơ bản', 'viechild'); ?></th>
                <th scope="col" style="width:80px"><?php _e('Số phòng', 'viechild'); ?></th>
                <th scope="col" style="width:90px"><?php _e('Trạng thái', 'viechild'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rooms as $room) : 
                $hotel_name = get_the_title($room->hotel_id);
                $thumb_url = $room->featured_image_id ? wp_get_attachment_image_url($room->featured_image_id, 'thumbnail') : '';
                $status_labels = ['active' => 'Hoạt động', 'inactive' => 'Tạm ngừng', 'draft' => 'Nháp'];
            ?>
            <tr>
                <td><?php echo esc_html($room->id); ?></td>
                <td>
                    <?php if ($thumb_url) : ?>
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:3px;">
                    <?php else : ?>
                        <span class="dashicons dashicons-format-image" style="font-size:30px;color:#ccc;"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><a class="row-title" href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add&room_id=' . $room->id); ?>"><?php echo esc_html($room->name); ?></a></strong>
                    <div class="row-actions">
                        <span class="edit"><a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add&room_id=' . $room->id); ?>"><?php _e('Sửa', 'viechild'); ?></a> | </span>
                        <span class="calendar"><a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-calendar&room_id=' . $room->id); ?>"><?php _e('Lịch giá', 'viechild'); ?></a> | </span>
                        <span class="trash"><a href="#" class="vie-delete-room submitdelete" data-room-id="<?php echo esc_attr($room->id); ?>" data-room-name="<?php echo esc_attr($room->name); ?>"><?php _e('Xóa', 'viechild'); ?></a></span>
                    </div>
                </td>
                <td><?php echo $hotel_name ? '<a href="' . get_edit_post_link($room->hotel_id) . '">' . esc_html($hotel_name) . '</a>' : '<span style="color:#999">—</span>'; ?></td>
                <td><?php printf('%d NL / %d TE', $room->max_adults, $room->max_children); ?></td>
                <td><strong><?php echo Vie_Hotel_Rooms_Helpers::format_currency($room->base_price); ?></strong></td>
                <td><?php echo esc_html($room->total_rooms); ?></td>
                <td><span class="vie-status vie-status-<?php echo esc_attr($room->status); ?>"><?php echo esc_html($status_labels[$room->status] ?? $room->status); ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col"><?php _e('ID', 'viechild'); ?></th>
                <th scope="col"><?php _e('Ảnh', 'viechild'); ?></th>
                <th scope="col"><?php _e('Tên phòng', 'viechild'); ?></th>
                <th scope="col"><?php _e('Khách sạn', 'viechild'); ?></th>
                <th scope="col"><?php _e('Sức chứa', 'viechild'); ?></th>
                <th scope="col"><?php _e('Giá cơ bản', 'viechild'); ?></th>
                <th scope="col"><?php _e('Số phòng', 'viechild'); ?></th>
                <th scope="col"><?php _e('Trạng thái', 'viechild'); ?></th>
            </tr>
        </tfoot>
    </table>
    <?php else : ?>
    <div class="vie-empty">
        <span class="dashicons dashicons-building"></span>
        <h3><?php _e('Chưa có loại phòng nào', 'viechild'); ?></h3>
        <p><?php _e('Bắt đầu bằng cách thêm loại phòng đầu tiên.', 'viechild'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add'); ?>" class="button button-primary button-hero"><?php _e('Thêm Loại Phòng', 'viechild'); ?></a>
    </div>
    <?php endif; ?>
</div>
