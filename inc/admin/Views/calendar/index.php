<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Calendar
 * ============================================================================
 * 
 * Giao diện quản lý lịch giá với FullCalendar.
 * 
 * Variables available:
 * @var array       $hotels     Danh sách hotels
 * @var array       $rooms      Danh sách rooms của hotel được chọn
 * @var object|null $room       Room object được chọn
 * @var int         $hotel_id   Hotel ID được chọn
 * @var int         $room_id    Room ID được chọn
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
    <h1 class="wp-heading-inline"><?php esc_html_e('Lịch Giá Phòng', 'flavor'); ?></h1>
    <hr class="wp-header-end">

    <!-- ====================================================================
         FILTERS
    ==================================================================== -->
    <div class="vie-calendar-filters" style="background:#fff;padding:15px;margin-bottom:20px;border:1px solid #ccd0d4;">
        <form method="get" id="vie-calendar-filter-form">
            <input type="hidden" name="page" value="vie-hotel-calendar">
            
            <select name="hotel_id" id="vie-hotel-select" style="min-width:200px;">
                <option value=""><?php esc_html_e('-- Chọn khách sạn --', 'flavor'); ?></option>
                <?php foreach ($hotels as $hotel) : ?>
                    <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($hotel_id, $hotel->ID); ?>>
                        <?php echo esc_html($hotel->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="room_id" id="vie-room-select" style="min-width:200px;">
                <option value=""><?php esc_html_e('-- Chọn loại phòng --', 'flavor'); ?></option>
                <?php foreach ($rooms as $r) : ?>
                    <option value="<?php echo esc_attr($r->id); ?>" <?php selected($room_id, $r->id); ?>>
                        <?php echo esc_html($r->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button button-primary"><?php esc_html_e('Xem lịch', 'flavor'); ?></button>

            <?php if ($room_id > 0) : ?>
                <button type="button" id="vie-bulk-update-btn" class="button" style="margin-left:10px;">
                    <?php esc_html_e('Cập nhật hàng loạt', 'flavor'); ?>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($room_id > 0 && $room) : ?>
    <!-- ====================================================================
         ROOM INFO
    ==================================================================== -->
    <div class="vie-room-info" style="background:#f0f9ff;padding:15px;margin-bottom:20px;border-left:4px solid #3b82f6;">
        <strong><?php echo esc_html($room->name); ?></strong>
        <span style="margin-left:20px;">
            <?php esc_html_e('Số phòng:', 'flavor'); ?>
            <strong><?php echo esc_html($room->total_rooms); ?></strong>
        </span>
    </div>

    <!-- ====================================================================
         CALENDAR
    ==================================================================== -->
    <div class="vie-calendar-wrapper" style="background:#fff;padding:20px;border:1px solid #ccd0d4;">
        <div id="vie-pricing-calendar"></div>
    </div>

    <!-- ====================================================================
         LEGEND
    ==================================================================== -->
    <div class="vie-calendar-legend" style="margin-top:15px;padding:10px;background:#f7f7f7;display:flex;gap:20px;flex-wrap:wrap;">
        <span><span style="display:inline-block;width:12px;height:12px;background:#28a745;margin-right:5px;"></span> <?php esc_html_e('Còn phòng', 'flavor'); ?></span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#ffc107;margin-right:5px;"></span> <?php esc_html_e('Sắp hết', 'flavor'); ?></span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#dc3545;margin-right:5px;"></span> <?php esc_html_e('Hết phòng', 'flavor'); ?></span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#6c757d;margin-right:5px;"></span> <?php esc_html_e('Ngừng bán', 'flavor'); ?></span>
    </div>

    <?php else : ?>
    <!-- ====================================================================
         PLACEHOLDER
    ==================================================================== -->
    <div style="background:#fff;padding:40px;text-align:center;border:1px solid #ccd0d4;">
        <span class="dashicons dashicons-calendar-alt" style="font-size:48px;color:#ccc;"></span>
        <p style="margin-top:15px;color:#666;"><?php esc_html_e('Vui lòng chọn khách sạn và loại phòng để xem lịch giá', 'flavor'); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- ====================================================================
     MODAL: Edit Single Date
==================================================================== -->
<div id="vie-date-modal" class="vie-modal" style="display:none;">
    <div class="vie-modal-box" style="max-width:500px;">
        <div class="vie-modal-header">
            <h3><?php esc_html_e('Cập nhật giá', 'flavor'); ?> - <span id="vie-modal-date"></span></h3>
            <button type="button" class="vie-modal-close">&times;</button>
        </div>
        <div class="vie-modal-body">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Giá Room Only (VNĐ)', 'flavor'); ?></th>
                    <td><input type="number" id="vie-modal-price-room" class="regular-text" min="0" step="1000"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Giá Combo (VNĐ)', 'flavor'); ?></th>
                    <td><input type="number" id="vie-modal-price-combo" class="regular-text" min="0" step="1000"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Số phòng còn', 'flavor'); ?></th>
                    <td><input type="number" id="vie-modal-stock" class="small-text" min="0"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Trạng thái', 'flavor'); ?></th>
                    <td>
                        <select id="vie-modal-status">
                            <option value="available"><?php esc_html_e('Còn phòng', 'flavor'); ?></option>
                            <option value="limited"><?php esc_html_e('Sắp hết', 'flavor'); ?></option>
                            <option value="sold_out"><?php esc_html_e('Hết phòng', 'flavor'); ?></option>
                            <option value="stop_sell"><?php esc_html_e('Ngừng bán', 'flavor'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <div class="vie-modal-footer">
            <button type="button" class="button vie-modal-close"><?php esc_html_e('Hủy', 'flavor'); ?></button>
            <button type="button" id="vie-save-date" class="button button-primary"><?php esc_html_e('Lưu', 'flavor'); ?></button>
        </div>
    </div>
</div>

<!-- ====================================================================
     MODAL: Bulk Update
==================================================================== -->
<div id="vie-bulk-modal" class="vie-modal" style="display:none;">
    <div class="vie-modal-box" style="max-width:700px;">
        <div class="vie-modal-header">
            <h3><?php esc_html_e('Cập nhật hàng loạt', 'flavor'); ?></h3>
            <button type="button" class="vie-modal-close">&times;</button>
        </div>
        <div class="vie-modal-body">
            <p>
                <label><?php esc_html_e('Từ ngày:', 'flavor'); ?></label>
                <input type="date" id="vie-bulk-start" style="margin-right:20px;">
                <label><?php esc_html_e('Đến ngày:', 'flavor'); ?></label>
                <input type="date" id="vie-bulk-end">
            </p>
            
            <h4><?php esc_html_e('Cấu hình theo ngày trong tuần', 'flavor'); ?></h4>
            <table class="widefat" id="vie-bulk-days-table">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e('Áp dụng', 'flavor'); ?></th>
                        <th><?php esc_html_e('Ngày', 'flavor'); ?></th>
                        <th><?php esc_html_e('Giá Room', 'flavor'); ?></th>
                        <th><?php esc_html_e('Giá Combo', 'flavor'); ?></th>
                        <th><?php esc_html_e('Stock', 'flavor'); ?></th>
                        <th><?php esc_html_e('Trạng thái', 'flavor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $days = array(1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật');
                    foreach ($days as $num => $label) : 
                    ?>
                    <tr>
                        <td><input type="checkbox" name="day_enabled[<?php echo $num; ?>]" value="1"></td>
                        <td><?php echo esc_html($label); ?></td>
                        <td><input type="number" name="day_price_room[<?php echo $num; ?>]" class="small-text" min="0" step="1000"></td>
                        <td><input type="number" name="day_price_combo[<?php echo $num; ?>]" class="small-text" min="0" step="1000"></td>
                        <td><input type="number" name="day_stock[<?php echo $num; ?>]" class="small-text" min="0" style="width:60px;"></td>
                        <td>
                            <select name="day_status[<?php echo $num; ?>]">
                                <option value=""><?php esc_html_e('-- Giữ nguyên --', 'flavor'); ?></option>
                                <option value="available"><?php esc_html_e('Còn phòng', 'flavor'); ?></option>
                                <option value="stop_sell"><?php esc_html_e('Ngừng bán', 'flavor'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="vie-modal-footer">
            <button type="button" class="button vie-modal-close"><?php esc_html_e('Hủy', 'flavor'); ?></button>
            <button type="button" id="vie-bulk-save" class="button button-primary"><?php esc_html_e('Cập nhật', 'flavor'); ?></button>
        </div>
    </div>
</div>

<!-- ====================================================================
     STYLES
==================================================================== -->
<style>
.vie-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.vie-modal-box {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    max-height: 90vh;
    overflow-y: auto;
}
.vie-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.vie-modal-header h3 { margin: 0; }
.vie-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.vie-modal-body { padding: 20px; }
.vie-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}
.vie-modal-footer .button { margin-left: 10px; }

#vie-pricing-calendar .fc-event { 
    cursor: pointer; 
    font-size: 11px;
    padding: 2px 4px;
}
</style>

<!-- ====================================================================
     SCRIPTS
==================================================================== -->
<?php if ($room_id > 0) : ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
jQuery(function($) {
    var roomId = <?php echo $room_id; ?>;
    var calendar;

    // Initialize FullCalendar
    var calendarEl = document.getElementById('vie-pricing-calendar');
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'vi',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        events: function(info, successCallback, failureCallback) {
            $.post(ajaxurl, {
                action: 'vie_get_pricing_calendar',
                nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
                room_id: roomId,
                start: info.startStr,
                end: info.endStr
            }, function(res) {
                if (res.success) {
                    successCallback(res.data.events);
                } else {
                    failureCallback();
                }
            });
        },
        eventClick: function(info) {
            var props = info.event.extendedProps;
            $('#vie-modal-date').text(info.event.startStr);
            $('#vie-modal-price-room').val(props.price_room || '');
            $('#vie-modal-price-combo').val(props.price_combo || '');
            $('#vie-modal-stock').val(props.stock || '');
            $('#vie-modal-status').val(props.status || 'available');
            $('#vie-date-modal').data('date', info.event.startStr).show();
        },
        dateClick: function(info) {
            $('#vie-modal-date').text(info.dateStr);
            $('#vie-modal-price-room, #vie-modal-price-combo, #vie-modal-stock').val('');
            $('#vie-modal-status').val('available');
            $('#vie-date-modal').data('date', info.dateStr).show();
        }
    });
    calendar.render();

    // Close modal
    $('.vie-modal-close').on('click', function() {
        $(this).closest('.vie-modal').hide();
    });

    // Save single date
    $('#vie-save-date').on('click', function() {
        var $btn = $(this);
        var date = $('#vie-date-modal').data('date');

        $btn.prop('disabled', true).text('<?php esc_html_e('Đang lưu...', 'flavor'); ?>');

        $.post(ajaxurl, {
            action: 'vie_save_single_date_pricing',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            room_id: roomId,
            date: date,
            price_room: $('#vie-modal-price-room').val(),
            price_combo: $('#vie-modal-price-combo').val(),
            stock: $('#vie-modal-stock').val(),
            status: $('#vie-modal-status').val()
        }, function(res) {
            if (res.success) {
                alert(res.data.message);
                $('#vie-date-modal').hide();
                calendar.refetchEvents();
            } else {
                alert(res.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'flavor'); ?>');
            }
            $btn.prop('disabled', false).text('<?php esc_html_e('Lưu', 'flavor'); ?>');
        });
    });

    // Open bulk modal
    $('#vie-bulk-update-btn').on('click', function() {
        $('#vie-bulk-modal').show();
    });

    // Save bulk
    $('#vie-bulk-save').on('click', function() {
        var $btn = $(this);
        var startDate = $('#vie-bulk-start').val();
        var endDate = $('#vie-bulk-end').val();

        if (!startDate || !endDate) {
            alert('<?php esc_html_e('Vui lòng chọn khoảng ngày', 'flavor'); ?>');
            return;
        }

        var dailyRules = {};
        $('#vie-bulk-days-table tbody tr').each(function() {
            var $row = $(this);
            var dayNum = $row.find('input[type="checkbox"]').attr('name').match(/\d+/)[0];
            
            if ($row.find('input[type="checkbox"]').is(':checked')) {
                dailyRules[dayNum] = {
                    enabled: true,
                    price_room: $row.find('input[name="day_price_room[' + dayNum + ']"]').val(),
                    price_combo: $row.find('input[name="day_price_combo[' + dayNum + ']"]').val(),
                    stock: $row.find('input[name="day_stock[' + dayNum + ']"]').val(),
                    status: $row.find('select[name="day_status[' + dayNum + ']"]').val()
                };
            }
        });

        if (Object.keys(dailyRules).length === 0) {
            alert('<?php esc_html_e('Vui lòng chọn ít nhất 1 ngày trong tuần', 'flavor'); ?>');
            return;
        }

        $btn.prop('disabled', true).text('<?php esc_html_e('Đang lưu...', 'flavor'); ?>');

        $.post(ajaxurl, {
            action: 'vie_bulk_update_pricing',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            room_id: roomId,
            start_date: startDate,
            end_date: endDate,
            daily_rules: dailyRules
        }, function(res) {
            if (res.success) {
                alert(res.data.message);
                $('#vie-bulk-modal').hide();
                calendar.refetchEvents();
            } else {
                alert(res.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'flavor'); ?>');
            }
            $btn.prop('disabled', false).text('<?php esc_html_e('Cập nhật', 'flavor'); ?>');
        });
    });

    // Hotel change - load rooms
    $('#vie-hotel-select').on('change', function() {
        var hotelId = $(this).val();
        var $roomSelect = $('#vie-room-select');

        $roomSelect.html('<option value=""><?php esc_html_e('-- Đang tải... --', 'flavor'); ?></option>');

        if (!hotelId) {
            $roomSelect.html('<option value=""><?php esc_html_e('-- Chọn loại phòng --', 'flavor'); ?></option>');
            return;
        }

        $.post(ajaxurl, {
            action: 'vie_get_rooms_by_hotel',
            nonce: '<?php echo wp_create_nonce('vie_hotel_rooms_nonce'); ?>',
            hotel_id: hotelId
        }, function(res) {
            var html = '<option value=""><?php esc_html_e('-- Chọn loại phòng --', 'flavor'); ?></option>';
            if (res.success && res.data.rooms) {
                res.data.rooms.forEach(function(room) {
                    html += '<option value="' + room.id + '">' + room.name + '</option>';
                });
            }
            $roomSelect.html(html);
        });
    });
});
</script>
<?php endif; ?>
