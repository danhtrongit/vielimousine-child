<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Booking Detail
 * ============================================================================
 * 
 * Hiển thị chi tiết đơn đặt phòng.
 * 
 * Variables available:
 * @var object  $booking            Booking object
 * @var string  $hotel_name         Tên khách sạn
 * @var array   $guests_info        Thông tin khách
 * @var array   $pricing_details    Chi tiết giá theo ngày
 * @var array   $surcharges_details Chi tiết phụ thu
 * @var array   $transport_info     Thông tin xe đưa đón
 * @var array   $invoice_info       Thông tin hóa đơn
 * @var int     $num_nights         Số đêm
 * @var array   $statuses           Danh sách trạng thái
 * @var array   $payment_statuses   Danh sách trạng thái thanh toán
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Templates
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

$day_names = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php printf(esc_html__('Chi tiết đơn #%s', 'flavor'), $booking->booking_code); ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-bookings')); ?>" class="page-title-action">
        <?php esc_html_e('← Quay lại', 'flavor'); ?>
    </a>
    <hr class="wp-header-end">

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">

            <!-- ================================================================
                 MAIN CONTENT
            ================================================================ -->
            <div id="post-body-content">

                <!-- Thông tin đặt phòng -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Thông tin đặt phòng', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Khách sạn', 'flavor'); ?></th>
                                <td><strong><?php echo esc_html($hotel_name); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Loại phòng', 'flavor'); ?></th>
                                <td><?php echo esc_html($booking->room_name ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Ngày nhận/trả phòng', 'flavor'); ?></th>
                                <td>
                                    <strong><?php echo date('d/m/Y', strtotime($booking->check_in)); ?></strong>
                                    →
                                    <strong><?php echo date('d/m/Y', strtotime($booking->check_out)); ?></strong>
                                    <span class="description">(<?php echo $num_nights; ?> đêm)</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Số phòng', 'flavor'); ?></th>
                                <td><?php echo esc_html($booking->num_rooms); ?> phòng</td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Số khách', 'flavor'); ?></th>
                                <td>
                                    <?php echo esc_html($booking->num_adults); ?> người lớn
                                    <?php if ($booking->num_children > 0) : ?>
                                        , <?php echo esc_html($booking->num_children); ?> trẻ em
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($guests_info['children_ages'])) : ?>
                            <tr>
                                <th><?php esc_html_e('Tuổi trẻ em', 'flavor'); ?></th>
                                <td>
                                    <?php 
                                    $ages = array();
                                    foreach ($guests_info['children_ages'] as $i => $age) {
                                        $ages[] = sprintf('Bé %d: %d tuổi', $i + 1, $age);
                                    }
                                    echo esc_html(implode(', ', $ages));
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php esc_html_e('Loại giường', 'flavor'); ?></th>
                                <td>
                                    <span class="dashicons dashicons-bed"></span>
                                    <?php 
                                    $bed_type_label = !empty($guests_info['bed_type_label']) 
                                        ? $guests_info['bed_type_label'] 
                                        : (($guests_info['bed_type'] ?? 'double') === 'twin' 
                                            ? '2 Giường đơn (Twin Beds)' 
                                            : '1 Giường đôi lớn (Double Bed)');
                                    echo esc_html($bed_type_label);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Loại giá', 'flavor'); ?></th>
                                <td>
                                    <span class="vie-price-type-badge">
                                        <?php echo $booking->price_type === 'combo' ? esc_html__('Giá Combo', 'flavor') : esc_html__('Giá Room Only', 'flavor'); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Thông tin khách hàng -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Thông tin khách hàng', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Họ tên', 'flavor'); ?></th>
                                <td><strong><?php echo esc_html($booking->customer_name); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Số điện thoại', 'flavor'); ?></th>
                                <td>
                                    <a href="tel:<?php echo esc_attr($booking->customer_phone); ?>">
                                        <?php echo esc_html($booking->customer_phone); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php if (!empty($booking->customer_email)) : ?>
                            <tr>
                                <th><?php esc_html_e('Email', 'flavor'); ?></th>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                                        <?php echo esc_html($booking->customer_email); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($booking->customer_note)) : ?>
                            <tr>
                                <th><?php esc_html_e('Ghi chú', 'flavor'); ?></th>
                                <td><?php echo nl2br(esc_html($booking->customer_note)); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Thông tin hóa đơn (nếu có) -->
                <?php if (!empty($invoice_info)) : ?>
                <div class="postbox" style="border-left:4px solid #dc2626;">
                    <div class="postbox-header">
                        <h2>
                            <span class="dashicons dashicons-media-spreadsheet" style="color:#dc2626"></span>
                            <?php esc_html_e('Thông tin xuất hóa đơn (VAT)', 'flavor'); ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Tên công ty', 'flavor'); ?></th>
                                <td><strong><?php echo esc_html($invoice_info['company_name'] ?? ''); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Mã số thuế', 'flavor'); ?></th>
                                <td><?php echo esc_html($invoice_info['tax_id'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Email nhận hóa đơn', 'flavor'); ?></th>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($invoice_info['email'] ?? ''); ?>">
                                        <?php echo esc_html($invoice_info['email'] ?? ''); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Thông tin xe đưa đón (nếu có) -->
                <?php if (!empty($transport_info) && !empty($transport_info['enabled'])) : ?>
                <div class="postbox" style="border-left:4px solid #2563eb;">
                    <div class="postbox-header">
                        <h2>
                            <span class="dashicons dashicons-car" style="color:#2563eb"></span>
                            <?php esc_html_e('Thông tin Xe đưa đón', 'flavor'); ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Giờ đi (Pick-up)', 'flavor'); ?></th>
                                <td><strong><?php echo esc_html($transport_info['pickup_time'] ?? 'N/A'); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Giờ về (Drop-off)', 'flavor'); ?></th>
                                <td><strong><?php echo esc_html($transport_info['dropoff_time'] ?? 'N/A'); ?></strong></td>
                            </tr>
                            <?php if (!empty($transport_info['note'])) : ?>
                            <tr>
                                <th><?php esc_html_e('Ghi chú điểm đón', 'flavor'); ?></th>
                                <td><?php echo nl2br(esc_html($transport_info['note'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Chi tiết giá -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Chi tiết giá', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Ngày', 'flavor'); ?></th>
                                    <th><?php esc_html_e('Thứ', 'flavor'); ?></th>
                                    <th style="text-align:right"><?php esc_html_e('Giá/đêm', 'flavor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pricing_details as $date => $info) : ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($date)); ?></td>
                                    <td><?php echo esc_html($day_names[$info['day_of_week'] ?? 0]); ?></td>
                                    <td style="text-align:right"><?php echo vie_format_currency($info['price']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">
                                        <?php esc_html_e('Tổng tiền phòng', 'flavor'); ?>
                                        (x<?php echo esc_html($booking->num_rooms); ?> phòng)
                                    </th>
                                    <th style="text-align:right"><?php echo vie_format_currency($booking->base_amount); ?></th>
                                </tr>
                            </tfoot>
                        </table>

                        <?php if (!empty($surcharges_details)) : ?>
                        <h4 style="margin-top:20px"><?php esc_html_e('Phụ thu', 'flavor'); ?></h4>
                        <table class="widefat">
                            <tbody>
                                <?php foreach ($surcharges_details as $surcharge) : ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($surcharge['label']); ?>
                                        <small>
                                            (x<?php echo esc_html($surcharge['quantity']); ?>
                                            <?php if (!empty($surcharge['is_per_night'])) echo ' x ' . $surcharge['nights'] . ' đêm'; ?>)
                                        </small>
                                    </td>
                                    <td style="text-align:right"><?php echo esc_html($surcharge['formatted']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th><?php esc_html_e('Tổng phụ thu', 'flavor'); ?></th>
                                    <th style="text-align:right"><?php echo vie_format_currency($booking->surcharges_amount); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <?php endif; ?>

                        <?php if (!empty($booking->coupon_code) || !empty($booking->discount_amount)) : ?>
                        <h4 style="margin-top:20px;color:#059669;">
                            <span class="dashicons dashicons-tickets-alt" style="color:#059669;"></span>
                            <?php esc_html_e('Mã giảm giá', 'flavor'); ?>
                        </h4>
                        <table class="widefat">
                            <tbody>
                                <?php if (!empty($booking->coupon_code)) : ?>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e('Mã kích hoạt', 'flavor'); ?>:</strong>
                                        <code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;font-size:14px;font-weight:600;">
                                            <?php echo esc_html(strtoupper($booking->coupon_code)); ?>
                                        </code>
                                    </td>
                                    <td style="text-align:right;">
                                        <span style="color:#059669;font-weight:600;">
                                            -<?php echo vie_format_currency($booking->discount_amount ?? 0); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <div style="background:#f0f9ff;padding:16px;margin-top:20px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:16px;font-weight:600;"><?php esc_html_e('TỔNG CỘNG', 'flavor'); ?></span>
                            <strong style="font-size:20px;color:#1d4ed8;"><?php echo vie_format_currency($booking->total_amount); ?></strong>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ================================================================
                 SIDEBAR
            ================================================================ -->
            <div id="postbox-container-1" class="postbox-container">

                <!-- Trạng thái -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Trạng thái', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <p>
                            <label><strong><?php esc_html_e('Trạng thái đơn', 'flavor'); ?></strong></label>
                            <select id="booking-status" class="widefat" style="margin-top:4px;">
                                <?php foreach ($statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($booking->status, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label><strong><?php esc_html_e('Thanh toán', 'flavor'); ?></strong></label>
                            <select id="payment-status" class="widefat" style="margin-top:4px;">
                                <?php foreach ($payment_statuses as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($booking->payment_status, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </p>

                        <p>
                            <label><strong><?php esc_html_e('Ghi chú Admin', 'flavor'); ?></strong></label>
                            <textarea id="admin-note" class="widefat" rows="3" style="margin-top:4px;"><?php echo esc_textarea($booking->admin_note ?? ''); ?></textarea>
                        </p>

                        <button type="button" id="update-booking-status" class="button button-primary widefat" 
                                data-id="<?php echo esc_attr($booking->id); ?>">
                            <?php esc_html_e('Cập nhật', 'flavor'); ?>
                        </button>
                    </div>
                </div>

                <!-- Thông tin hệ thống -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Thông tin hệ thống', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <p>
                            <strong><?php esc_html_e('Ngày tạo:', 'flavor'); ?></strong><br>
                            <?php echo date('d/m/Y H:i:s', strtotime($booking->created_at)); ?>
                        </p>

                        <?php if ($booking->updated_at !== $booking->created_at) : ?>
                        <p>
                            <strong><?php esc_html_e('Cập nhật lần cuối:', 'flavor'); ?></strong><br>
                            <?php echo date('d/m/Y H:i:s', strtotime($booking->updated_at)); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($booking->ip_address)) : ?>
                        <p>
                            <strong><?php esc_html_e('IP:', 'flavor'); ?></strong><br>
                            <?php echo esc_html($booking->ip_address); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hành động -->
                <div class="postbox">
                    <div class="postbox-header"><h2><?php esc_html_e('Hành động', 'flavor'); ?></h2></div>
                    <div class="inside">
                        <button type="button" id="delete-booking" class="button button-link-delete widefat"
                                data-id="<?php echo esc_attr($booking->id); ?>"
                                data-confirm="<?php esc_attr_e('Bạn có chắc muốn xóa đơn này?', 'flavor'); ?>">
                            <?php esc_html_e('Xóa đơn đặt phòng', 'flavor'); ?>
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- ====================================================================
     JAVASCRIPT
==================================================================== -->
<script>
jQuery(function($) {
    // Update status
    $('#update-booking-status').on('click', function() {
        var $btn = $(this);
        var id = $btn.data('id');

        $btn.prop('disabled', true).text('<?php esc_html_e('Đang lưu...', 'flavor'); ?>');

        $.post(ajaxurl, {
            action: 'vie_update_booking_status',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            booking_id: id,
            status: $('#booking-status').val(),
            payment_status: $('#payment-status').val(),
            admin_note: $('#admin-note').val()
        }, function(res) {
            if (res.success) {
                alert(res.data.message);
            } else {
                alert(res.data.message || '<?php esc_html_e('Lỗi cập nhật', 'flavor'); ?>');
            }
            $btn.prop('disabled', false).text('<?php esc_html_e('Cập nhật', 'flavor'); ?>');
        });
    });

    // Delete booking
    $('#delete-booking').on('click', function() {
        var $btn = $(this);
        var id = $btn.data('id');

        if (!confirm($btn.data('confirm'))) {
            return;
        }

        $btn.prop('disabled', true).text('<?php esc_html_e('Đang xóa...', 'flavor'); ?>');

        $.post(ajaxurl, {
            action: 'vie_delete_booking',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            booking_id: id
        }, function(res) {
            if (res.success) {
                window.location.href = '<?php echo admin_url('admin.php?page=vie-hotel-bookings'); ?>';
            } else {
                alert(res.data.message || '<?php esc_html_e('Lỗi xóa', 'flavor'); ?>');
                $btn.prop('disabled', false).text('<?php esc_html_e('Xóa đơn đặt phòng', 'flavor'); ?>');
            }
        });
    });
});
</script>
