<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Rooms List
 * ============================================================================
 * 
 * Hiển thị danh sách loại phòng theo khách sạn.
 * 
 * Variables available:
 * @var array   $rooms          Danh sách room objects
 * @var array   $hotels         Danh sách hotels
 * @var int     $selected_hotel Filter hotel hiện tại
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Templates
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Quản lý Loại Phòng', 'flavor'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Thêm mới', 'flavor'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- ====================================================================
         FILTER BY HOTEL
    ==================================================================== -->
    <div class="tablenav top">
        <form method="get" class="alignleft">
            <input type="hidden" name="page" value="vie-hotel-rooms">
            
            <select name="hotel_id" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('Tất cả khách sạn', 'flavor'); ?></option>
                <?php foreach ($hotels as $hotel) : ?>
                    <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($selected_hotel, $hotel->ID); ?>>
                        <?php echo esc_html($hotel->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="submit" class="button" value="<?php esc_attr_e('Lọc', 'flavor'); ?>">
        </form>
        <br class="clear">
    </div>

    <!-- ====================================================================
         ROOMS TABLE
    ==================================================================== -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px"><?php esc_html_e('ID', 'flavor'); ?></th>
                <th><?php esc_html_e('Tên phòng', 'flavor'); ?></th>
                <th><?php esc_html_e('Khách sạn', 'flavor'); ?></th>
                <th style="width:100px"><?php esc_html_e('Sức chứa', 'flavor'); ?></th>
                <th style="width:80px"><?php esc_html_e('Số phòng', 'flavor'); ?></th>
                <th style="width:100px"><?php esc_html_e('Trạng thái', 'flavor'); ?></th>
                <th style="width:180px"><?php esc_html_e('Thao tác', 'flavor'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rooms)) : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px">
                        <?php esc_html_e('Chưa có loại phòng nào', 'flavor'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($rooms as $room) : 
                    $hotel_name = get_the_title($room->hotel_id);
                ?>
                <tr>
                    <td><?php echo esc_html($room->id); ?></td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms&action=edit&room_id=' . $room->id)); ?>">
                                <?php echo esc_html($room->name); ?>
                            </a>
                        </strong>
                        <?php if (!empty($room->description)) : ?>
                            <br><small><?php echo esc_html(wp_trim_words($room->description, 10)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(get_edit_post_link($room->hotel_id)); ?>">
                            <?php echo esc_html($hotel_name ?: 'N/A'); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo esc_html($room->base_occupancy); ?> người
                        <br>
                        <small>Max: <?php echo esc_html($room->max_occupancy); ?></small>
                    </td>
                    <td><?php echo esc_html($room->total_rooms); ?></td>
                    <td>
                        <?php
                        $status_colors = array(
                            'active'   => '#10b981',
                            'inactive' => '#ef4444',
                        );
                        $color = $status_colors[$room->status] ?? '#6b7280';
                        ?>
                        <span style="background:<?php echo esc_attr($color); ?>;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">
                            <?php echo esc_html(ucfirst($room->status)); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-rooms&action=edit&room_id=' . $room->id)); ?>" 
                           class="button button-small">
                            <?php esc_html_e('Sửa', 'flavor'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-calendar&room_id=' . $room->id)); ?>" 
                           class="button button-small">
                            <?php esc_html_e('Lịch giá', 'flavor'); ?>
                        </a>
                        <button type="button" class="button button-small button-link-delete vie-delete-room"
                                data-id="<?php echo esc_attr($room->id); ?>"
                                data-name="<?php echo esc_attr($room->name); ?>">
                            <?php esc_html_e('Xóa', 'flavor'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ====================================================================
     JAVASCRIPT
==================================================================== -->
<script>
jQuery(function($) {
    // Delete room
    $('.vie-delete-room').on('click', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var name = $btn.data('name');

        if (!confirm('<?php esc_html_e('Bạn có chắc muốn xóa phòng', 'flavor'); ?> "' + name + '"?\n\n<?php esc_html_e('Thao tác này sẽ xóa cả lịch giá và phụ thu của phòng.', 'flavor'); ?>')) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'vie_delete_room',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            room_id: id
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message || '<?php esc_html_e('Lỗi xóa phòng', 'flavor'); ?>');
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
