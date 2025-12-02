/**
 * Hotel Rooms Admin JavaScript
 * 
 * Xử lý các chức năng AJAX cho giao diện Admin
 * 
 * @package VieHotelRooms
 */

(function($) {
    'use strict';

    // Namespace
    var VieRooms = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initMediaUploader();
            this.initSurchargeRepeater();
            this.initDatepickers();
            this.initBulkUpdate();
        },

        /**
         * Bind general events
         */
        bindEvents: function() {
            // Room form submit
            $('#vie-room-form').on('submit', this.handleRoomFormSubmit.bind(this));
            
            // Delete room
            $(document).on('click', '.vie-delete-room', this.handleDeleteRoom.bind(this));
            
            // Hotel select change (load rooms)
            $('#cal-hotel-select, #bulk-hotel-select').on('change', this.handleHotelChange.bind(this));
            
            // Room select change (redirect or load info)
            $('#cal-room-select').on('change', this.handleRoomSelectChange.bind(this));
            $('#bulk-room-select').on('change', this.handleBulkRoomSelect.bind(this));
        },

        /**
         * Initialize WordPress Media Uploader
         */
        initMediaUploader: function() {
            var self = this;
            var featuredFrame, galleryFrame;

            // Featured Image
            $('#vie-select-featured-image').on('click', function(e) {
                e.preventDefault();

                if (featuredFrame) {
                    featuredFrame.open();
                    return;
                }

                featuredFrame = wp.media({
                    title: 'Chọn ảnh đại diện',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });

                featuredFrame.on('select', function() {
                    var attachment = featuredFrame.state().get('selection').first().toJSON();
                    $('#featured_image_id').val(attachment.id);
                    var imgUrl = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#vie-featured-image-preview').html('<img src="' + imgUrl + '" alt="">');
                    $('#vie-remove-featured-image').show();
                });

                featuredFrame.open();
            });

            // Remove Featured Image
            $('#vie-remove-featured-image').on('click', function(e) {
                e.preventDefault();
                $('#featured_image_id').val('');
                $('#vie-featured-image-preview').empty();
                $(this).hide();
            });

            // Gallery Images
            $('#vie-add-gallery-images').on('click', function(e) {
                e.preventDefault();

                if (galleryFrame) {
                    galleryFrame.open();
                    return;
                }

                galleryFrame = wp.media({
                    title: 'Chọn ảnh cho Gallery',
                    button: { text: 'Thêm vào Gallery' },
                    multiple: true
                });

                galleryFrame.on('select', function() {
                    var attachments = galleryFrame.state().get('selection').toJSON();
                    var currentIds = JSON.parse($('#gallery_ids').val() || '[]');
                    var $preview = $('#vie-gallery-preview');

                    attachments.forEach(function(attachment) {
                        if (currentIds.indexOf(attachment.id) === -1) {
                            currentIds.push(attachment.id);
                            var thumbUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                            $preview.append(
                                '<div class="vie-gallery-item" data-id="' + attachment.id + '">' +
                                '<img src="' + thumbUrl + '" alt="">' +
                                '<button type="button" class="remove">&times;</button>' +
                                '</div>'
                            );
                        }
                    });

                    $('#gallery_ids').val(JSON.stringify(currentIds));
                });

                galleryFrame.open();
            });

            // Remove Gallery Item
            $(document).on('click', '.vie-gallery-item .remove', function(e) {
                e.preventDefault();
                var $item = $(this).closest('.vie-gallery-item');
                var removeId = $item.data('id');
                var currentIds = JSON.parse($('#gallery_ids').val() || '[]');
                
                currentIds = currentIds.filter(function(id) {
                    return id !== removeId;
                });
                
                $('#gallery_ids').val(JSON.stringify(currentIds));
                $item.remove();
            });
        },

        /**
         * Initialize Surcharge Repeater
         */
        initSurchargeRepeater: function() {
            var self = this;
            var $container = $('#vie-surcharges-container');
            var template = $('#vie-surcharge-template').html();

            // Add new surcharge row
            $('#vie-add-surcharge').on('click', function() {
                var index = $container.find('.vie-repeater-item').length;
                var html = template
                    .replace(/{{index}}/g, index)
                    .replace(/{{number}}/g, index + 1);
                $container.append(html);
                self.updateRowNumbers();
            });

            // Remove surcharge row
            $(document).on('click', '.vie-remove-surcharge', function() {
                $(this).closest('.vie-repeater-item').remove();
                self.updateRowNumbers();
                self.reindexSurcharges();
            });

            // Toggle age fields based on type
            $(document).on('change', '.surcharge-type-select', function() {
                var $row = $(this).closest('.vie-repeater-item');
                var type = $(this).val();
                
                if (type === 'child') {
                    $row.find('.vie-age-fields').show();
                } else {
                    $row.find('.vie-age-fields').hide();
                }
            });
        },

        /**
         * Update row numbers
         */
        updateRowNumbers: function() {
            $('#vie-surcharges-container .vie-repeater-item').each(function(index) {
                $(this).find('.row-number').text(index + 1);
            });
        },

        /**
         * Reindex surcharge field names
         */
        reindexSurcharges: function() {
            $('#vie-surcharges-container .vie-repeater-item').each(function(index) {
                $(this).attr('data-index', index);
                $(this).find('[name]').each(function() {
                    var name = $(this).attr('name');
                    name = name.replace(/surcharges\[\d+\]/, 'surcharges[' + index + ']');
                    $(this).attr('name', name);
                });
            });
        },

        /**
         * Handle room form submit
         */
        handleRoomFormSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitBtn = $('#vie-save-room');
            var originalText = $submitBtn.html();
            
            // Validate
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            // Disable button and show loading
            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + vieHotelRooms.i18n.saving);
            
            // Get form data
            var formData = new FormData($form[0]);
            formData.append('action', 'vie_save_room');
            formData.append('nonce', vieHotelRooms.nonce);
            
            // Get description from TinyMCE if exists
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('description')) {
                formData.set('description', tinyMCE.get('description').getContent());
            }
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $submitBtn.html('<span class="dashicons dashicons-yes"></span> ' + vieHotelRooms.i18n.saved);
                        
                        // Update URL if new room created
                        if (response.data.room_id && !$form.find('[name="room_id"]').val()) {
                            var newUrl = window.location.href + '&room_id=' + response.data.room_id;
                            window.history.replaceState({}, '', newUrl);
                            $form.find('[name="room_id"]').val(response.data.room_id);
                        }
                        
                        setTimeout(function() {
                            $submitBtn.html(originalText).prop('disabled', false);
                        }, 2000);
                    } else {
                        alert(response.data.message || vieHotelRooms.i18n.error);
                        $submitBtn.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert(vieHotelRooms.i18n.error);
                    $submitBtn.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle delete room
         */
        handleDeleteRoom: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var roomId = $btn.data('room-id');
            var roomName = $btn.data('room-name');
            
            if (!confirm(vieHotelRooms.i18n.confirmDelete + '\n\n"' + roomName + '"')) {
                return;
            }
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_delete_room',
                    nonce: vieHotelRooms.nonce,
                    room_id: roomId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || vieHotelRooms.i18n.error);
                    }
                },
                error: function() {
                    alert(vieHotelRooms.i18n.error);
                }
            });
        },

        /**
         * Handle hotel select change
         */
        handleHotelChange: function(e) {
            var hotelId = $(e.target).val();
            var $roomSelect = $(e.target).attr('id') === 'cal-hotel-select' 
                ? $('#cal-room-select') 
                : $('#bulk-room-select');
            
            $roomSelect.prop('disabled', true).html('<option value="">Đang tải...</option>');
            
            if (!hotelId) {
                $roomSelect.html('<option value="">-- Chọn phòng --</option>');
                return;
            }
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_rooms_by_hotel',
                    nonce: vieHotelRooms.nonce,
                    hotel_id: hotelId
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<option value="">-- Chọn loại phòng --</option>';
                        response.data.rooms.forEach(function(room) {
                            html += '<option value="' + room.id + '">' + room.name + '</option>';
                        });
                        $roomSelect.html(html).prop('disabled', false);
                    }
                }
            });
        },

        /**
         * Handle room select change (calendar page)
         */
        handleRoomSelectChange: function(e) {
            var roomId = $(e.target).val();
            if (roomId) {
                window.location.href = 'admin.php?page=vie-hotel-rooms-calendar&room_id=' + roomId;
            }
        },

        /**
         * Handle bulk room select
         */
        handleBulkRoomSelect: function(e) {
            var roomId = $(e.target).val();
            var $infoBox = $('#bulk-room-info');
            
            if (!roomId) {
                $infoBox.hide();
                return;
            }
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_room',
                    nonce: vieHotelRooms.nonce,
                    room_id: roomId
                },
                success: function(response) {
                    if (response.success) {
                        var room = response.data.room;
                        $('#info-total-rooms').text(room.total_rooms + ' phòng');
                        $('#info-occupancy').text(room.base_occupancy + ' người (max ' + room.max_adults + ')');
                        $infoBox.show();
                    }
                }
            });
        },

        /**
         * Initialize datepickers
         */
        initDatepickers: function() {
            $('.vie-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                firstDay: 1,
                dayNamesMin: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
                monthNames: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 
                            'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
                onSelect: function() {
                    VieRooms.updateBulkPreview();
                }
            });
        },

        /**
         * Initialize Bulk Update functionality
         */
        initBulkUpdate: function() {
            var self = this;
            
            // Date presets
            $('.vie-date-preset').on('click', function() {
                var preset = $(this).data('preset');
                var startDate, endDate;
                var today = new Date();
                
                switch (preset) {
                    case 'this-month':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                        break;
                    case 'next-month':
                        startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
                        break;
                    case 'next-3-months':
                        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        endDate = new Date(today.getFullYear(), today.getMonth() + 3, 0);
                        break;
                    case 'year-2025':
                        startDate = new Date(2025, 0, 1);
                        endDate = new Date(2025, 11, 31);
                        break;
                }
                
                if (startDate && endDate) {
                    $('#bulk-start-date').datepicker('setDate', startDate);
                    $('#bulk-end-date').datepicker('setDate', endDate);
                    self.updateBulkPreview();
                }
            });
            
            // Daily rules presets - Check all
            $('.vie-preset-check-all').on('click', function() {
                $('.day-enabled-check').prop('checked', true);
                self.updateBulkPreview();
            });
            
            // Daily rules presets - Uncheck all
            $('.vie-preset-uncheck-all').on('click', function() {
                $('.day-enabled-check').prop('checked', false);
                self.updateBulkPreview();
            });
            
            // Daily rules presets - Weekday (T2-T5)
            $('.vie-preset-weekday').on('click', function() {
                $('.day-enabled-check').prop('checked', false);
                // Days 1,2,3,4 = Mon, Tue, Wed, Thu
                [1, 2, 3, 4].forEach(function(day) {
                    $('.vie-day-row[data-day="' + day + '"] .day-enabled-check').prop('checked', true);
                });
                self.updateBulkPreview();
            });
            
            // Daily rules presets - Weekend (T6-CN)
            $('.vie-preset-weekend').on('click', function() {
                $('.day-enabled-check').prop('checked', false);
                // Days 5,6,7 = Fri, Sat, Sun
                [5, 6, 7].forEach(function(day) {
                    $('.vie-day-row[data-day="' + day + '"] .day-enabled-check').prop('checked', true);
                });
                self.updateBulkPreview();
            });
            
            // Copy Mon values to Tue, Wed, Thu
            $('.vie-copy-mon-to-weekday').on('click', function() {
                var $monRow = $('.vie-day-row[data-day="1"]');
                var priceRoom = $monRow.find('.day-price-room').val();
                var priceCombo = $monRow.find('.day-price-combo').val();
                var stock = $monRow.find('.day-stock').val();
                var status = $monRow.find('.day-status').val();
                
                [2, 3, 4].forEach(function(day) {
                    var $row = $('.vie-day-row[data-day="' + day + '"]');
                    $row.find('.day-price-room').val(priceRoom);
                    $row.find('.day-price-combo').val(priceCombo);
                    $row.find('.day-stock').val(stock);
                    $row.find('.day-status').val(status);
                });
                self.updateBulkPreview();
            });
            
            // Copy Fri values to Sat, Sun
            $('.vie-copy-fri-to-weekend').on('click', function() {
                var $friRow = $('.vie-day-row[data-day="5"]');
                var priceRoom = $friRow.find('.day-price-room').val();
                var priceCombo = $friRow.find('.day-price-combo').val();
                var stock = $friRow.find('.day-stock').val();
                var status = $friRow.find('.day-status').val();
                
                [6, 7].forEach(function(day) {
                    var $row = $('.vie-day-row[data-day="' + day + '"]');
                    $row.find('.day-price-room').val(priceRoom);
                    $row.find('.day-price-combo').val(priceCombo);
                    $row.find('.day-stock').val(stock);
                    $row.find('.day-status').val(status);
                });
                self.updateBulkPreview();
            });
            
            // Day checkbox & inputs change
            $('.vie-daily-rules-table').on('change input', 'input, select', function() {
                self.updateBulkPreview();
            });
            
            // Form submit
            $('#vie-bulk-update-form').on('submit', this.handleBulkSubmit.bind(this));
        },

        /**
         * Update bulk preview
         */
        updateBulkPreview: function() {
            var self = this;
            var roomId = $('#bulk-room-select').val();
            var startDate = $('#bulk-start-date').val();
            var endDate = $('#bulk-end-date').val();
            
            var $preview = $('#bulk-preview-summary');
            var $submit = $('#vie-bulk-submit');
            
            // Check if we have minimum data
            if (!roomId || !startDate || !endDate) {
                $preview.html('<p class="placeholder">Điền đủ thông tin để xem tóm tắt</p>');
                $submit.prop('disabled', true);
                return;
            }
            
            // Check if any day is enabled with values
            var dayNames = {
                1: 'Thứ 2',
                2: 'Thứ 3',
                3: 'Thứ 4',
                4: 'Thứ 5',
                5: 'Thứ 6',
                6: 'Thứ 7',
                7: 'Chủ nhật'
            };
            
            var enabledDays = [];
            var hasAnyValue = false;
            
            $('.vie-day-row').each(function() {
                var $row = $(this);
                var day = $row.data('day');
                var isEnabled = $row.find('.day-enabled-check').is(':checked');
                
                if (isEnabled) {
                    var priceRoom = $row.find('.day-price-room').val();
                    var priceCombo = $row.find('.day-price-combo').val();
                    var stock = $row.find('.day-stock').val();
                    var status = $row.find('.day-status').val();
                    
                    if (priceRoom || priceCombo || stock || status) {
                        hasAnyValue = true;
                    }
                    
                    enabledDays.push({
                        day: day,
                        name: dayNames[day],
                        priceRoom: priceRoom,
                        priceCombo: priceCombo,
                        stock: stock,
                        status: status
                    });
                }
            });
            
            // Check if we have any enabled day with values
            if (enabledDays.length === 0) {
                $preview.html('<p class="placeholder">Chọn ít nhất 1 ngày để cập nhật</p>');
                $submit.prop('disabled', true);
                return;
            }
            
            if (!hasAnyValue) {
                $preview.html('<p class="placeholder">Nhập ít nhất một giá trị cần cập nhật</p>');
                $submit.prop('disabled', true);
                return;
            }
            
            // Build preview
            var roomName = $('#bulk-room-select option:selected').text();
            var html = '<ul>';
            html += '<li><strong>Phòng:</strong> ' + roomName + '</li>';
            html += '<li><strong>Từ:</strong> ' + startDate + ' <strong>đến</strong> ' + endDate + '</li>';
            html += '<li><strong>Áp dụng cho:</strong> ' + enabledDays.map(function(d) { return d.name; }).join(', ') + '</li>';
            html += '</ul>';
            
            // Show table for each day's config
            html += '<table class="vie-preview-table">';
            html += '<thead><tr><th>Ngày</th><th>Giá Room</th><th>Giá Combo</th><th>Số lượng</th><th>Trạng thái</th></tr></thead>';
            html += '<tbody>';
            enabledDays.forEach(function(d) {
                html += '<tr>';
                html += '<td><strong>' + d.name + '</strong></td>';
                html += '<td>' + (d.priceRoom ? self.formatCurrency(d.priceRoom) : '-') + '</td>';
                html += '<td>' + (d.priceCombo ? self.formatCurrency(d.priceCombo) : '-') + '</td>';
                html += '<td>' + (d.stock || '-') + '</td>';
                html += '<td>' + (d.status || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            
            $preview.html(html);
            $submit.prop('disabled', false);
        },

        /**
         * Handle bulk submit
         */
        handleBulkSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitBtn = $('#vie-bulk-submit');
            var $status = $('#bulk-submit-status');
            var $log = $('#vie-bulk-log');
            var $logContent = $('#bulk-log-content');
            
            // Disable button
            $submitBtn.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text('Đang xử lý...');
            
            // Collect daily_rules data
            var dailyRules = {};
            $('.vie-day-row').each(function() {
                var $row = $(this);
                var day = $row.data('day');
                var isEnabled = $row.find('.day-enabled-check').is(':checked');
                
                if (isEnabled) {
                    dailyRules[day] = {
                        enabled: 1,
                        price_room: $row.find('.day-price-room').val() || '',
                        price_combo: $row.find('.day-price-combo').val() || '',
                        stock: $row.find('.day-stock').val() || '',
                        status: $row.find('.day-status').val() || ''
                    };
                }
            });
            
            // Build form data
            var formData = {
                action: 'vie_bulk_update_pricing',
                nonce: vieHotelRooms.nonce,
                room_id: $('#bulk-room-select').val(),
                start_date: $('#bulk-start-date').val(),
                end_date: $('#bulk-end-date').val(),
                daily_rules: dailyRules
            };
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('loading').addClass('success')
                            .text('✓ ' + response.data.message);
                        
                        // Show log
                        var logText = '[' + new Date().toLocaleTimeString() + '] ';
                        logText += 'Cập nhật: ' + response.data.updated + ' ngày, ';
                        logText += 'Tạo mới: ' + response.data.created + ' ngày';
                        $logContent.append('<div>' + logText + '</div>');
                        $log.show();
                    } else {
                        $status.removeClass('loading').addClass('error')
                            .text('✗ ' + (response.data.message || 'Có lỗi xảy ra'));
                    }
                    $submitBtn.prop('disabled', false);
                },
                error: function() {
                    $status.removeClass('loading').addClass('error').text('✗ Lỗi kết nối');
                    $submitBtn.prop('disabled', false);
                }
            });
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            return parseInt(amount).toLocaleString('vi-VN') + ' ₫';
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VieRooms.init();
    });

})(jQuery);
