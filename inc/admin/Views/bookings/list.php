<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Bookings List
 * ============================================================================
 * 
 * Hiển thị danh sách đơn đặt phòng với filters và pagination.
 * 
 * Variables available:
 * @var array   $bookings       Danh sách booking objects
 * @var int     $total_items    Tổng số bookings
 * @var int     $total_pages    Tổng số trang
 * @var int     $paged          Trang hiện tại
 * @var array   $hotels         Danh sách hotels
 * @var array   $statuses       Danh sách trạng thái
 * @var string  $filter_status  Filter status hiện tại
 * @var int     $filter_hotel   Filter hotel hiện tại
 * @var string  $filter_date_from   Filter ngày từ
 * @var string  $filter_date_to     Filter ngày đến
 * @var string  $search         Từ khóa tìm kiếm
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
    <h1 class="wp-heading-inline"><?php esc_html_e('Quản lý Đặt phòng', 'flavor'); ?></h1>
    <hr class="wp-header-end">

    <!-- ====================================================================
         FILTERS
    ==================================================================== -->
    <div class="tablenav top">
        <form method="get" class="alignleft">
            <input type="hidden" name="page" value="vie-hotel-bookings">

            <select name="status">
                <option value=""><?php esc_html_e('Tất cả trạng thái', 'flavor'); ?></option>
                <?php foreach ($statuses as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="hotel_id">
                <option value=""><?php esc_html_e('Tất cả khách sạn', 'flavor'); ?></option>
                <?php foreach ($hotels as $hotel) : ?>
                    <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($filter_hotel, $hotel->ID); ?>>
                        <?php echo esc_html($hotel->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" 
                   placeholder="<?php esc_attr_e('Từ ngày', 'flavor'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" 
                   placeholder="<?php esc_attr_e('Đến ngày', 'flavor'); ?>">

            <input type="submit" class="button" value="<?php esc_attr_e('Lọc', 'flavor'); ?>">
        </form>

        <form method="get" class="alignright">
            <input type="hidden" name="page" value="vie-hotel-bookings">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                   placeholder="<?php esc_attr_e('Tìm theo mã, tên, SĐT...', 'flavor'); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Tìm kiếm', 'flavor'); ?>">
        </form>

        <br class="clear">
    </div>

    <!-- ====================================================================
         BOOKINGS TABLE
    ==================================================================== -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:120px"><?php esc_html_e('Mã đặt phòng', 'flavor'); ?></th>
                <th><?php esc_html_e('Khách hàng', 'flavor'); ?></th>
                <th><?php esc_html_e('Khách sạn / Phòng', 'flavor'); ?></th>
                <th style="width:100px"><?php esc_html_e('Check-in', 'flavor'); ?></th>
                <th style="width:100px"><?php esc_html_e('Check-out', 'flavor'); ?></th>
                <th style="width:120px"><?php esc_html_e('Tổng tiền', 'flavor'); ?></th>
                <th style="width:120px"><?php esc_html_e('Trạng thái', 'flavor'); ?></th>
                <th style="width:140px"><?php esc_html_e('Ngày đặt', 'flavor'); ?></th>
                <th style="width:100px"><?php esc_html_e('Thao tác', 'flavor'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)) : ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:40px">
                        <?php esc_html_e('Chưa có đơn đặt phòng nào', 'flavor'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($bookings as $booking) : 
                    $hotel_name = get_the_title($booking->hotel_id);
                ?>
                <tr>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)); ?>">
                                <?php echo esc_html($booking->booking_code); ?>
                            </a>
                        </strong>
                    </td>
                    <td>
                        <strong><?php echo esc_html($booking->customer_name); ?></strong>
                        <?php if (!empty($booking->invoice_info)) : ?>
                            <span class="dashicons dashicons-media-spreadsheet" 
                                  title="<?php esc_attr_e('Yêu cầu xuất hóa đơn', 'flavor'); ?>" 
                                  style="color:#dc2626;font-size:16px;width:16px;height:16px;vertical-align:middle;"></span>
                        <?php endif; ?>
                        <br>
                        <small><?php echo esc_html($booking->customer_phone); ?></small>
                    </td>
                    <td>
                        <?php echo esc_html($hotel_name ?: 'N/A'); ?><br>
                        <small><?php echo esc_html($booking->room_name ?? ''); ?></small>
                    </td>
                    <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in))); ?></td>
                    <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_out))); ?></td>
                    <td><strong><?php echo vie_format_currency($booking->total_amount); ?></strong></td>
                    <td><?php echo Vie_Admin_Bookings::get_status_badge($booking->status); ?></td>
                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($booking->created_at))); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)); ?>" 
                           class="button button-small">
                            <?php esc_html_e('Xem', 'flavor'); ?>
                        </a>
                        
                        <?php if (in_array($booking->status, array('processing', 'confirmed', 'paid'))) : ?>
                        <button type="button" class="button button-small button-primary vie-update-room-code" 
                                data-id="<?php echo esc_attr($booking->id); ?>"
                                data-code="<?php echo esc_attr($booking->room_code ?? ''); ?>"
                                title="<?php esc_attr_e('Cập nhật Mã nhận phòng', 'flavor'); ?>">
                            <span class="dashicons dashicons-tickets-alt" style="line-height:1.3;font-size:14px;"></span>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ====================================================================
         PAGINATION
    ==================================================================== -->
    <?php if ($total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $paged
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================================================
     ROOM CODE MODAL
     Modal cập nhật mã nhận phòng từ khách sạn
======================================================================== -->
<div id="vie-room-code-modal" class="vie-modal" style="display:none;">
    <div class="vie-modal-box">
        <div class="vie-modal-header">
            <h3><?php esc_html_e('Cập nhật Mã nhận phòng', 'flavor'); ?></h3>
            <button type="button" class="vie-modal-close" aria-label="<?php esc_attr_e('Đóng', 'flavor'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="vie-modal-body">
            <p><?php esc_html_e('Nhập mã xác nhận từ khách sạn để gửi cho khách hàng.', 'flavor'); ?></p>
            
            <div class="notice notice-info" style="margin:16px 0;">
                <p>
                    <strong><?php esc_html_e('Lưu ý:', 'flavor'); ?></strong><br>
                    <?php esc_html_e('Hệ thống sẽ tự động gửi Email chứa mã này cho khách và chuyển trạng thái đơn sang "Hoàn thành".', 'flavor'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="vie-room-code-input"><?php esc_html_e('Mã nhận phòng:', 'flavor'); ?></label>
                <input type="text" id="vie-room-code-input" class="regular-text" placeholder="VD: CONF-123456" style="width:100%;">
            </div>
            
            <input type="hidden" id="vie-room-code-booking-id">
        </div>
        <div class="vie-modal-footer">
            <button type="button" class="button vie-modal-close"><?php esc_html_e('Hủy', 'flavor'); ?></button>
            <button type="button" id="vie-save-room-code" class="button button-primary"><?php esc_html_e('Lưu & Gửi Email', 'flavor'); ?></button>
        </div>
    </div>
</div>

<!-- ========================================================================
     JAVASCRIPT: Room Code Modal Logic
======================================================================== -->
<script>
jQuery(function($) {
    // Mở modal khi click nút update room code
    $('.vie-update-room-code').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var code = $(this).data('code');
        
        $('#vie-room-code-booking-id').val(id);
        $('#vie-room-code-input').val(code);
        $('#vie-room-code-modal').fadeIn(200);
    });
    
    // Đóng modal
    $('.vie-modal-close').on('click', function() {
        $('#vie-room-code-modal').fadeOut(200);
    });
    
    // Đóng khi click backdrop
    $('.vie-modal').on('click', function(e) {
        if ($(e.target).hasClass('vie-modal')) {
            $(this).fadeOut(200);
        }
    });
    
    // Lưu mã nhận phòng
    $('#vie-save-room-code').on('click', function() {
        var $btn = $(this);
        var id = $('#vie-room-code-booking-id').val();
        var code = $('#vie-room-code-input').val();
        
        if (!code.trim()) {
            alert('<?php echo esc_js(__('Vui lòng nhập mã nhận phòng', 'flavor')); ?>');
            return;
        }
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Đang xử lý...', 'flavor')); ?>');
        
        $.post(ajaxurl, {
            action: 'vie_update_room_code',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            booking_id: id,
            room_code: code
        }, function(res) {
            if (res.success) {
                alert(res.data.message || '<?php echo esc_js(__('Đã cập nhật thành công!', 'flavor')); ?>');
                location.reload();
            } else {
                alert(res.data.message || '<?php echo esc_js(__('Có lỗi xảy ra', 'flavor')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Lưu & Gửi Email', 'flavor')); ?>');
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('Lỗi kết nối, vui lòng thử lại', 'flavor')); ?>');
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Lưu & Gửi Email', 'flavor')); ?>');
        });
    });
});
</script>
