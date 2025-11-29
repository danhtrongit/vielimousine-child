/**
 * Hotel Rooms Admin - Bookings Page JavaScript
 * 
 * JavaScript specific to the bookings management page.
 * Only loaded on the bookings admin page.
 * 
 * @package VieHotelRooms
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    $(function () {
        /**
         * Room Code Modal Logic
         */
        $('.vie-update-room-code').on('click', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            var code = $(this).data('code');

            $('#vie-room-code-booking-id').val(id);
            $('#vie-room-code-input').val(code);
            $('#vie-room-code-modal').fadeIn(200);
        });

        /**
         * Save Room Code
         */
        $('#vie-save-room-code').on('click', function () {
            var $btn = $(this);
            var id = $('#vie-room-code-booking-id').val();
            var code = $('#vie-room-code-input').val();

            if (!code) {
                alert(vieHotelRooms.i18n.error || 'Vui lòng nhập mã nhận phòng');
                return;
            }

            $btn.prop('disabled', true).text(vieHotelRooms.i18n.saving || 'Đang xử lý...');

            $.post(ajaxurl, {
                action: 'vie_update_room_code',
                nonce: vieHotelRooms.nonce,
                booking_id: id,
                room_code: code
            }, function (res) {
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message);
                    $btn.prop('disabled', false).text('Lưu & Gửi Email');
                }
            });
        });

        /**
         * Update Booking Status (Detail Page)
         */
        $('#update-booking-status').on('click', function () {
            var $btn = $(this);
            var id = $btn.data('id');
            var status = $('#booking-status').val();
            var paymentStatus = $('#payment-status').val();
            var adminNote = $('#admin-note').val();

            $btn.prop('disabled', true).text(vieHotelRooms.i18n.saving || 'Đang lưu...');

            $.post(ajaxurl, {
                action: 'vie_update_booking_status',
                nonce: vieHotelRooms.nonce,
                booking_id: id,
                status: status,
                payment_status: paymentStatus,
                admin_note: adminNote
            }, function (res) {
                if (res.success) {
                    alert(res.data.message || vieHotelRooms.i18n.saved);
                    location.reload();
                } else {
                    alert(res.data.message || vieHotelRooms.i18n.error);
                    $btn.prop('disabled', false).text('Cập nhật');
                }
            });
        });

        /**
         * Delete Booking
         */
        $('#delete-booking').on('click', function () {
            var $btn = $(this);
            var id = $btn.data('id');
            var confirmMsg = $btn.data('confirm') || vieHotelRooms.i18n.confirmDelete;

            if (!confirm(confirmMsg)) {
                return;
            }

            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'vie_delete_booking',
                nonce: vieHotelRooms.nonce,
                booking_id: id
            }, function (res) {
                if (res.success) {
                    alert(res.data.message || vieHotelRooms.i18n.saved);
                    window.location.href = 'admin.php?page=vie-hotel-bookings';
                } else {
                    alert(res.data.message || vieHotelRooms.i18n.error);
                    $btn.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);
