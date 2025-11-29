/**
 * Hotel Rooms Calendar JavaScript
 * 
 * FullCalendar integration cho quản lý lịch giá
 * 
 * @package VieHotelRooms
 */

(function($) {
    'use strict';

    var VieCalendar = {
        calendar: null,
        currentRoomId: null,

        /**
         * Initialize
         */
        init: function() {
            this.currentRoomId = typeof vieCalendarRoomId !== 'undefined' ? vieCalendarRoomId : null;
            
            if (this.currentRoomId) {
                this.initCalendar();
            }
            
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Modal events
            $('.vie-modal-box .close, #vie-modal-cancel').on('click', this.closeModal);
            $('#vie-modal-save').on('click', this.saveDatePricing.bind(this));
            
            // Close modal on outside click
            $('.vie-modal').on('click', function(e) {
                if ($(e.target).hasClass('vie-modal')) {
                    VieCalendar.closeModal();
                }
            });
            
            // ESC to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    VieCalendar.closeModal();
                }
            });
        },

        /**
         * Initialize FullCalendar
         */
        initCalendar: function() {
            var self = this;
            var calendarEl = document.getElementById('vie-pricing-calendar');
            
            if (!calendarEl) return;

            this.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'vi',
                firstDay: 1, // Monday
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                },
                buttonText: {
                    today: 'Hôm nay',
                    month: 'Tháng',
                    week: 'Tuần'
                },
                
                // Load events
                events: function(info, successCallback, failureCallback) {
                    self.loadPricing(info.startStr, info.endStr, successCallback, failureCallback);
                },
                
                // Click on date
                dateClick: function(info) {
                    self.openDateModal(info.dateStr);
                },
                
                // Click on event
                eventClick: function(info) {
                    var props = info.event.extendedProps;
                    self.openDateModal(info.event.startStr, props);
                },
                
                // Custom event rendering
                eventContent: function(arg) {
                    var props = arg.event.extendedProps;
                    var html = '<div class="vie-cal-event">';
                    
                    if (props.price_room > 0) {
                        html += '<div class="price-room">R: ' + VieCalendar.formatK(props.price_room) + '</div>';
                    }
                    if (props.price_combo > 0) {
                        html += '<div class="price-combo">C: ' + VieCalendar.formatK(props.price_combo) + '</div>';
                    }
                    html += '<div class="stock">📦 ' + props.stock + '</div>';
                    html += '</div>';
                    
                    return { html: html };
                },
                
                // Day cell customization
                dayCellDidMount: function(info) {
                    // Add day-of-week class
                    var dow = info.date.getDay();
                    if (dow === 0 || dow === 6) {
                        info.el.classList.add('vie-weekend');
                    }
                    if (dow === 5) {
                        info.el.classList.add('vie-friday');
                    }
                }
            });

            this.calendar.render();
            
            // Add custom styles
            this.addCalendarStyles();
        },

        /**
         * Load pricing data
         */
        loadPricing: function(start, end, successCallback, failureCallback) {
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_pricing_calendar',
                    nonce: vieHotelRooms.nonce,
                    room_id: this.currentRoomId,
                    start: start,
                    end: end
                },
                success: function(response) {
                    if (response.success) {
                        successCallback(response.data.events);
                    } else {
                        failureCallback(response.data.message);
                    }
                },
                error: function() {
                    failureCallback('Network error');
                }
            });
        },

        /**
         * Open date modal
         */
        openDateModal: function(dateStr, existingData) {
            var $modal = $('#vie-date-modal');
            var dateObj = new Date(dateStr);
            var dayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
            var displayDate = dayNames[dateObj.getDay()] + ', ' + dateObj.toLocaleDateString('vi-VN');
            
            // Set modal data
            $('#modal-date-display').text(displayDate);
            $('#modal-date').val(dateStr);
            $('#modal-room-id').val(this.currentRoomId);
            
            // Fill existing data or reset
            if (existingData) {
                $('#modal-price-room').val(existingData.price_room || '');
                $('#modal-price-combo').val(existingData.price_combo || '');
                $('#modal-stock').val(existingData.stock || 0);
                $('#modal-status').val(existingData.status || 'available');
            } else {
                $('#vie-date-pricing-form')[0].reset();
                $('#modal-date').val(dateStr);
                $('#modal-room-id').val(this.currentRoomId);
            }
            
            $modal.fadeIn(200);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#vie-date-modal').fadeOut(200);
        },

        /**
         * Save date pricing
         */
        saveDatePricing: function() {
            var self = this;
            var $form = $('#vie-date-pricing-form');
            var $saveBtn = $('#vie-modal-save');
            var originalHtml = $saveBtn.html();
            
            // Validate
            var priceRoom = $('#modal-price-room').val();
            var priceCombo = $('#modal-price-combo').val();
            
            if (priceRoom && parseFloat(priceRoom) < 0) {
                alert(vieHotelRooms.i18n.invalidPrice);
                return;
            }
            if (priceCombo && parseFloat(priceCombo) < 0) {
                alert(vieHotelRooms.i18n.invalidPrice);
                return;
            }
            
            // Disable button
            $saveBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + vieHotelRooms.i18n.saving);
            
            // Prepare data
            var formData = {
                action: 'vie_save_single_date_pricing',
                nonce: vieHotelRooms.nonce,
                room_id: $('#modal-room-id').val(),
                date: $('#modal-date').val(),
                price_room: priceRoom,
                price_combo: priceCombo,
                stock: $('#modal-stock').val(),
                status: $('#modal-status').val(),
                notes: $('#modal-notes').val()
            };
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Refresh calendar
                        self.calendar.refetchEvents();
                        
                        // Show success and close
                        $saveBtn.html('<span class="dashicons dashicons-yes"></span> ' + vieHotelRooms.i18n.saved);
                        setTimeout(function() {
                            self.closeModal();
                            $saveBtn.html(originalHtml).prop('disabled', false);
                        }, 1000);
                    } else {
                        alert(response.data.message || vieHotelRooms.i18n.error);
                        $saveBtn.html(originalHtml).prop('disabled', false);
                    }
                },
                error: function() {
                    alert(vieHotelRooms.i18n.error);
                    $saveBtn.html(originalHtml).prop('disabled', false);
                }
            });
        },

        /**
         * Format number to K
         */
        formatK: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(0) + 'k';
            }
            return num;
        },

        /**
         * Add custom calendar styles
         */
        addCalendarStyles: function() {
            var css = `
                .vie-cal-event {
                    padding: 2px 4px;
                    font-size: 11px;
                    line-height: 1.3;
                }
                .vie-cal-event .price-room {
                    color: #0073aa;
                    font-weight: 600;
                }
                .vie-cal-event .price-combo {
                    color: #00a32a;
                    font-weight: 600;
                }
                .vie-cal-event .stock {
                    color: #646970;
                    font-size: 10px;
                }
                .vie-weekend {
                    background-color: #fff8e6 !important;
                }
                .vie-friday {
                    background-color: #f0f7ff !important;
                }
                .fc-daygrid-day-number {
                    padding: 4px 8px !important;
                }
                .fc-daygrid-day-events {
                    min-height: 50px;
                }
                .dashicons.spin {
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    100% { transform: rotate(360deg); }
                }
            `;
            
            $('<style>').text(css).appendTo('head');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VieCalendar.init();
    });

})(jQuery);
