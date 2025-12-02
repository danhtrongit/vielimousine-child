/**
 * Frontend Booking JavaScript
 * Xử lý: Filters, Room Detail Modal, Booking Popup, Price Calculation
 */

(function ($) {
    'use strict';

    var VieBooking = {
        currentStep: 1,
        selectedRoom: null,
        pricingData: null,
        detailSwiper: null,
        calendarPrices: {}, // Cache for calendar prices by date (YYYY-MM-DD)
        calendarPricesLoading: {}, // Track loading state by month
        pricesPreloaded: false, // Flag to check if initial prices loaded

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            this.initDatepickers();
            this.initCardSwipers();
            this.renderRoomPrices(); // Fix 1: Render prices on load
        },

        /**
         * Bind all events
         */
        bindEvents: function () {
            var self = this;

            // Filter events
            $('#vie-num-children, #vie-filter-children').on('change', this.toggleChildrenAges.bind(this));
            $('#vie-check-availability').on('click', this.checkAvailability.bind(this));
            $('.vie-filter-submit').on('click', this.submitSearchFilter.bind(this)); // Fix 4: Search action

            // Room card events
            // NOTE: Classes must match template: room-card.php and room-detail-modal.php
            // - .js-open-room-detail for "Chi tiết" button (room card)
            // - .js-open-booking for "Đặt ngay" button (room card)
            // - .js-book-from-detail for "Đặt ngay" button (detail modal)
            $(document).on('click', '.vie-btn-detail, .js-open-room-detail', this.openDetailModal.bind(this));
            $(document).on('click', '.vie-btn-book, .vie-btn-book-from-detail, .js-open-booking, .js-book-from-detail', this.openBookingPopup.bind(this));

            // Modal close events - V2 classes
            $(document).on('click', '.vie-modal-close, .vie-modal-overlay, .js-close-modal', this.closeAllModals.bind(this));
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') self.closeAllModals();
            });

            // Booking popup events - V2 IDs
            $('#vie-book-children').on('change', this.toggleBookingChildrenAges.bind(this));
            $('#vie-book-checkin, #vie-book-checkout').on('change', this.onBookingDatesChange.bind(this));
            $('#vie-book-rooms, #vie-book-adults, #vie-book-children').on('change', this.recalculatePrice.bind(this));
            $('input[name="price_type"]').on('change', this.onPriceTypeChange.bind(this));
            $(document).on('change', '.vie-child-age-select', this.recalculatePrice.bind(this));

            // Booking step navigation - V2 classes
            $('.js-booking-next').on('click', this.nextStep.bind(this));
            $('.js-booking-back').on('click', this.prevStep.bind(this));
            $('#vie-booking-form').on('submit', this.submitBooking.bind(this));

            // Coupon apply button
            $('#vie-apply-coupon').on('click', this.applyCoupon.bind(this));
            $('#vie-book-coupon').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    VieBooking.applyCoupon();
                }
            });

            // Success modal
            $('.vie-close-success').on('click', function () {
                $('#vie-booking-success').hide();
                location.reload();
            });
        },

        /**
         * Initialize datepickers with price display
         */
        initDatepickers: function () {
            var self = this;
            var today = new Date();

            // Check if datepicker is available
            if (typeof $.fn.datepicker === 'undefined') {
                return;
            }

            this.datepickerDefaults = {
                dateFormat: vieBooking.dateFormat,
                minDate: today,
                showAnim: 'fadeIn',
                showOtherMonths: true,
                selectOtherMonths: true,
                beforeShow: function (input, inst) {
                    var $input = $(input);
                    var isBookingPopup = $input.closest('#vie-booking-popup').length > 0 ||
                        $input.attr('id') === 'booking-checkin' ||
                        $input.attr('id') === 'booking-checkout';

                    // Store state for other methods
                    self.isBookingDatepicker = isBookingPopup;

                    // If not booking popup (Search Filter), keep UI clean
                    if (!isBookingPopup) {
                        setTimeout(function () {
                            inst.dpDiv.removeClass('vie-datepicker-prices vie-datepicker-dual');
                        }, 0);
                        return;
                    }

                    // Fetch prices for current and next month
                    var date = $(input).datepicker('getDate') || new Date();
                    self.fetchCalendarPrices(date.getFullYear(), date.getMonth() + 1);
                    self.fetchCalendarPrices(date.getFullYear(), date.getMonth() + 2);

                    // FIX: Append datepicker to body to avoid modal overflow issues
                    setTimeout(function () {
                        var $input = $(input);
                        var $dpDiv = inst.dpDiv;

                        // Add custom class for dual price styling
                        $dpDiv.addClass('vie-datepicker-prices vie-datepicker-dual');

                        // CRITICAL FIX: Position and z-index for modal display
                        var isInModal = $input.closest('.vie-modal').length > 0;

                        if (isInModal) {
                            var inputOffset = $input.offset();
                            var inputHeight = $input.outerHeight();
                            var dpHeight = $dpDiv.outerHeight() || 350;
                            var windowHeight = $(window).height();
                            var scrollTop = $(window).scrollTop();

                            // Calculate position - show below or above input
                            var topPos = inputOffset.top - scrollTop + inputHeight + 8;
                            var leftPos = inputOffset.left;

                            // If datepicker would go below viewport, show above
                            if (topPos + dpHeight > windowHeight - 20) {
                                topPos = inputOffset.top - scrollTop - dpHeight - 8;
                            }

                            // Ensure left position doesn't overflow
                            var dpWidth = $dpDiv.outerWidth() || 340;
                            if (leftPos + dpWidth > $(window).width() - 10) {
                                leftPos = $(window).width() - dpWidth - 10;
                            }

                            $dpDiv.css({
                                position: 'fixed',
                                top: Math.max(10, topPos),
                                left: Math.max(10, leftPos),
                                zIndex: 1000000 // Above modal (modal is typically 99999)
                            });
                        } else {
                            $dpDiv.css({ zIndex: 1000000 });
                        }

                        // Inject dual prices after render (with retry for AJAX)
                        self.injectCalendarPrices($dpDiv);

                        // Retry injection after AJAX might have completed
                        setTimeout(function () {
                            $dpDiv.find('td.vie-price-injected').removeClass('vie-price-injected');
                            self.injectCalendarPrices($dpDiv);
                        }, 500);
                    }, 10);
                },
                beforeShowDay: function (date) {
                    return self.renderCalendarDay(date);
                },
                onChangeMonthYear: function (year, month, inst) {
                    // Check if we should show prices
                    if (!self.isBookingDatepicker) {
                        return;
                    }

                    // Fetch prices for the new month
                    self.fetchCalendarPrices(year, month);
                    self.fetchCalendarPrices(year, month + 1);

                    // Re-inject prices after month change
                    setTimeout(function () {
                        self.injectCalendarPrices(inst.dpDiv);
                    }, 100);
                }
            };

            // Filter datepickers (Search Filter) - Fix 3
            $('.filter-date-input').datepicker($.extend({}, this.datepickerDefaults, {
                beforeShow: function (input, inst) {
                    // Reset custom classes for filter datepicker
                    setTimeout(function () {
                        inst.dpDiv.removeClass('vie-datepicker-prices vie-datepicker-dual');
                    }, 0);
                },
                onSelect: function (date) {
                    var $this = $(this);
                    if ($this.hasClass('vie-filter-checkin')) {
                        var checkin = $this.datepicker('getDate');
                        var minCheckout = new Date(checkin);
                        minCheckout.setDate(minCheckout.getDate() + 1);
                        $('.vie-filter-checkout').datepicker('option', 'minDate', minCheckout);

                        // Auto set checkout if empty
                        if (!$('.vie-filter-checkout').val()) {
                            $('.vie-filter-checkout').datepicker('setDate', minCheckout);
                        }
                    }
                }
            }));
        },

        /**
         * Initialize booking popup datepickers (called when popup opens)
         */
        initBookingDatepickers: function () {
            var self = this;
            var today = new Date();

            // Check if datepicker is available
            if (typeof $.fn.datepicker === 'undefined') {
                return;
            }

            // Destroy existing datepickers if any - V2 IDs
            if ($('#vie-book-checkin').hasClass('hasDatepicker')) {
                $('#vie-book-checkin').datepicker('destroy');
            }
            if ($('#vie-book-checkout').hasClass('hasDatepicker')) {
                $('#vie-book-checkout').datepicker('destroy');
            }

            // PRE-LOAD prices BEFORE initializing datepickers (solve UX issue)
            var self = this;
            self.preloadCalendarPrices(function () {
                // Only initialize datepickers after prices are loaded
                self.initBookingDatepickersAfterLoad();
            });
        },

        /**
         * Pre-load calendar prices before showing datepicker
         */
        preloadCalendarPrices: function (callback) {
            var self = this;
            var today = new Date();
            var currentMonth = today.getMonth() + 1;
            var currentYear = today.getFullYear();
            var nextMonth = currentMonth + 1;
            var nextYear = currentYear;

            if (nextMonth > 12) {
                nextMonth = 1;
                nextYear++;
            }

            var loadCount = 0;
            var totalToLoad = 2;

            function checkComplete() {
                loadCount++;
                if (loadCount >= totalToLoad) {
                    self.pricesPreloaded = true;
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            }

            // Fetch current month
            self.fetchCalendarPrices(currentYear, currentMonth, checkComplete);
            // Fetch next month
            self.fetchCalendarPrices(nextYear, nextMonth, checkComplete);
        },

        /**
         * Initialize booking datepickers after prices are loaded
         */
        initBookingDatepickersAfterLoad: function () {
            var self = this;
            var today = new Date();

            // Re-initialize with V2 IDs
            $('#vie-book-checkin').datepicker($.extend({}, this.datepickerDefaults, {
                onSelect: function (date) {
                    var checkin = $(this).datepicker('getDate');
                    var minCheckout = new Date(checkin);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    $('#vie-book-checkout').datepicker('option', 'minDate', minCheckout);

                    if (!$('#vie-book-checkout').val()) {
                        $('#vie-book-checkout').datepicker('setDate', minCheckout);
                    }
                    self.recalculatePrice();
                }
            }));

            $('#vie-book-checkout').datepicker($.extend({}, this.datepickerDefaults, {
                minDate: new Date(today.getTime() + 86400000),
                onSelect: function () {
                    self.recalculatePrice();
                }
            }));
        },

        /**
         * Fetch calendar prices for a specific month
         * @param {number} year
         * @param {number} month
         * @param {function} callback - Optional callback when complete
         */
        fetchCalendarPrices: function (year, month, callback) {
            var self = this;

            // Normalize month (handle overflow)
            if (month > 12) {
                month = 1;
                year++;
            }

            var cacheKey = year + '-' + month;

            // Skip if already loaded
            if (this.calendarPricesLoading[cacheKey] === 'loaded') {
                if (typeof callback === 'function') callback();
                return;
            }

            // Skip if currently loading
            if (this.calendarPricesLoading[cacheKey] === 'loading') {
                return;
            }

            this.calendarPricesLoading[cacheKey] = 'loading';

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_calendar_prices',
                    nonce: vieBooking.nonce,
                    hotel_id: vieBooking.hotelId,
                    year: year,
                    month: month
                },
                success: function (response) {
                    if (response.success && response.data.prices) {
                        $.extend(self.calendarPrices, response.data.prices);
                        self.refreshOpenDatepickers();
                    }
                },
                complete: function () {
                    self.calendarPricesLoading[cacheKey] = 'loaded';
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },

        /**
         * Refresh open datepickers to show new prices
         */
        refreshOpenDatepickers: function () {
            var self = this;
            var $dpDiv = $('#ui-datepicker-div');

            if ($dpDiv.is(':visible')) {
                // Check if we should show prices
                if (!self.isBookingDatepicker) {
                    return;
                }

                // Remove injected class so prices get re-injected with new data
                $dpDiv.find('td.vie-price-injected').removeClass('vie-price-injected');
                self.injectCalendarPrices($dpDiv);
            }
        },

        /**
         * Render calendar day (beforeShowDay callback)
         */
        renderCalendarDay: function (date) {
            var today = new Date();
            today.setHours(0, 0, 0, 0);

            var dateStr = this.formatDateISO(date);
            var priceInfo = this.calendarPrices[dateStr];

            // Past dates are disabled
            if (date < today) {
                return [false, 'vie-day-past', ''];
            }

            // Check availability
            if (priceInfo) {
                if (priceInfo.sold_out || priceInfo.status === 'sold_out') {
                    return [false, 'vie-day-sold-out', 'Hết phòng'];
                }
                if (priceInfo.status === 'stop_sell') {
                    return [false, 'vie-day-stop-sell', 'Ngừng bán'];
                }
            }

            // Weekend class for styling
            var dayOfWeek = date.getDay();
            var extraClass = '';
            if (dayOfWeek === 0 || dayOfWeek === 5 || dayOfWeek === 6) {
                extraClass = 'vie-day-weekend';
            }

            return [true, extraClass, ''];
        },

        /**
         * Inject prices into calendar day cells
         * Only shows prices, keeps day number clean
         */
        injectCalendarPrices: function ($dpDiv) {
            var self = this;

            $dpDiv.find('td[data-handler="selectDay"]').each(function () {
                var $cell = $(this);
                var $link = $cell.find('a');

                if ($link.length === 0) return;

                // Skip if already has price (fully processed)
                if ($link.find('.vie-day-price').length > 0) {
                    return;
                }

                // Get date from cell data attributes
                var month = parseInt($cell.data('month')) + 1;
                var year = parseInt($cell.data('year'));

                // Get day number - use data-date attribute or parse from clean text
                var $existingNum = $link.find('.vie-day-number');
                var day;

                if ($existingNum.length) {
                    day = parseInt($existingNum.text());
                } else {
                    // Get clean day number from original link text
                    var linkText = $link.text().trim();
                    day = parseInt(linkText);
                }

                if (isNaN(day) || isNaN(month) || isNaN(year)) return;
                if (day < 1 || day > 31) return; // Validate day range

                var dateStr = self.formatDateISO(new Date(year, month - 1, day));
                var priceInfo = self.calendarPrices[dateStr];

                $cell.addClass('vie-price-injected');

                // Build HTML - day number + prices (if available)
                var html = '<span class="vie-day-number">' + day + '</span>';

                if (priceInfo && !priceInfo.sold_out) {
                    // Show COMBO price if available
                    if (priceInfo.combo_label) {
                        html += '<span class="vie-day-price vie-price-combo">' + priceInfo.combo_label + '</span>';
                    }

                    // Show ROOM price (with strikethrough if combo exists)
                    if (priceInfo.room_label) {
                        var roomClass = 'vie-day-price vie-price-room';
                        if (priceInfo.combo_label) {
                            roomClass += ' vie-price-strikethrough';
                        }
                        html += '<span class="' + roomClass + '">' + priceInfo.room_label + '</span>';
                    }
                } else if (priceInfo && priceInfo.sold_out) {
                    html += '<span class="vie-day-price vie-price-sold-out">Hết phòng</span>';
                }

                $link.html(html);
            });
        },

        /**
         * Format date to ISO string (YYYY-MM-DD)
         */
        formatDateISO: function (date) {
            var year = date.getFullYear();
            var month = ('0' + (date.getMonth() + 1)).slice(-2);
            var day = ('0' + date.getDate()).slice(-2);
            return year + '-' + month + '-' + day;
        },

        /**
         * Toggle children ages inputs (filter) - Fix 4
         */
        toggleChildrenAges: function (e) {
            var $target = $(e.currentTarget);
            var numChildren = parseInt($target.val()) || 0;

            // Determine container based on ID
            var $container;
            if ($target.attr('id') === 'vie-filter-children') {
                $container = $('#vie-children-ages');
            } else {
                $container = $('#vie-children-ages'); // Fallback
            }

            var $inputs = $container.find('.vie-ages-inputs');

            if (numChildren > 0) {
                $inputs.empty();
                for (var i = 0; i < numChildren; i++) {
                    $inputs.append(this.createAgeInput(i + 1));
                }
                $container.slideDown();
            } else {
                $container.slideUp();
                $inputs.empty();
            }
        },

        /**
         * Toggle children ages in booking popup
         */
        toggleBookingChildrenAges: function () {
            var numChildren = parseInt($('#vie-book-children').val()) || 0;
            var $container = $('#vie-book-children-ages');
            var $inputs = $container.find('.vie-ages-inputs');

            if (numChildren > 0) {
                $inputs.empty();
                for (var i = 0; i < numChildren; i++) {
                    $inputs.append(this.createBookingAgeInput(i + 1));
                }
                $container.slideDown();
            } else {
                $container.slideUp();
                $inputs.empty();
            }

            this.recalculatePrice();
        },

        /**
         * Create age input element (filter)
         */
        createAgeInput: function (index) {
            var html = '<div class="vie-age-input">';
            html += '<span>' + vieBooking.i18n.childAge + ' ' + index + ':</span>';
            html += '<select class="vie-child-age-filter" data-index="' + index + '">';
            for (var i = 0; i <= 17; i++) {
                html += '<option value="' + i + '">' + i + ' tuổi</option>';
            }
            html += '</select></div>';
            return html;
        },

        /**
         * Create age input for booking popup
         */
        createBookingAgeInput: function (index) {
            var html = '<div class="vie-age-item">';
            html += '<span>Bé ' + index + ':</span>';
            html += '<select class="vie-child-age-select" name="children_ages[]">';
            for (var i = 0; i <= 17; i++) {
                html += '<option value="' + i + '">' + i + ' tuổi</option>';
            }
            html += '</select></div>';
            return html;
        },

        /**
         * Check availability for all rooms
         */
        checkAvailability: function () {
            var checkin = $('#vie-checkin').val();
            var checkout = $('#vie-checkout').val();
            var numRooms = $('#vie-num-rooms').val();

            if (!checkin || !checkout) {
                alert(vieBooking.i18n.selectDates);
                return;
            }

            var $btn = $('#vie-check-availability');
            $btn.prop('disabled', true).html('<span class="vie-spinner"></span>');

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_check_availability',
                    nonce: vieBooking.nonce,
                    hotel_id: vieBooking.hotelId,
                    check_in: checkin,
                    check_out: checkout,
                    num_rooms: numRooms
                },
                success: function (response) {
                    if (response.success) {
                        VieBooking.updateRoomAvailability(response.data.rooms);
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).html(vieBooking.i18n.book || 'Kiểm tra');
                }
            });
        },

        /**
         * Update room cards with availability status
         */
        updateRoomAvailability: function (rooms) {
            $.each(rooms, function (roomId, data) {
                var $card = $('.vie-room-card[data-room-id="' + roomId + '"]');
                var $badge = $card.find('.vie-availability');
                var $bookBtn = $card.find('.vie-btn-book');

                $badge.removeClass('sold-out limited stop-sell').text('');
                $bookBtn.prop('disabled', false);

                if (!data.available) {
                    switch (data.status) {
                        case 'sold_out':
                            $badge.addClass('sold-out').text(vieBooking.i18n.soldOut);
                            break;
                        case 'stop_sell':
                            $badge.addClass('stop-sell').text(vieBooking.i18n.stopSell);
                            break;
                        case 'insufficient_stock':
                            $badge.addClass('limited').text(data.message);
                            break;
                    }
                    $bookBtn.prop('disabled', true);
                }
            });
        },

        /**
         * Open room detail modal
         * Supports both:
         * - data-room JSON object (legacy)
         * - data-room-id + fetch via AJAX (new approach)
         */
        openDetailModal: function (e) {
            var self = this;
            var $btn = $(e.currentTarget);
            var $modal = $('#vie-room-detail-modal');

            // Try to get room data from button attribute first
            var roomData = $btn.data('room');

            // If no room data object, try to get from parent card or fetch via AJAX
            if (!roomData || typeof roomData !== 'object') {
                var roomId = $btn.data('room-id');
                var $card = $btn.closest('.vie-room-card');

                if ($card.length) {
                    // Build room data from card's data attributes
                    roomData = {
                        id: roomId || $card.data('room-id'),
                        name: $card.data('room-name') || $card.find('.vie-room-name').text(),
                        min_price: $btn.data('base-price') || $card.data('base-price') || 0,
                        description: $card.find('.vie-room-desc').text() || '',
                        max_adults: $card.data('max-adults') || 2,
                        max_children: $card.data('max-children') || 0,
                        room_size: $card.data('room-size') || '',
                        bed_type: $card.data('bed-type') || '',
                        amenities: $card.data('amenities') || [],
                        surcharge_help: $card.find('.js-open-booking').data('surcharge-help') || ''
                    };
                }

                // If still no data, fetch via AJAX
                if (!roomData || !roomData.id) {
                    roomId = roomId || $btn.data('room-id');
                    if (roomId) {
                        self.fetchAndShowRoomDetail(roomId, $modal);
                        return;
                    } else {
                        console.error('[VieBooking] Cannot open room detail: no room ID');
                        return;
                    }
                }
            }

            // Populate modal with room data
            this.populateDetailModal(roomData, $modal);
        },

        /**
         * Fetch room detail via AJAX and show modal
         */
        fetchAndShowRoomDetail: function (roomId, $modal) {
            var self = this;

            // Show loading state
            $modal.find('.vie-detail-title').text('Đang tải...');
            $modal.find('.vie-price-value').text('--');
            $modal.find('.vie-detail-meta').html('<span class="vie-spinner"></span>');
            $modal.find('.vie-amenities-list').empty();
            $modal.find('.vie-description-text').empty();

            $modal.fadeIn(200);
            $('body').addClass('vie-modal-open');

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_room_detail',
                    nonce: vieBooking.nonce,
                    room_id: roomId
                },
                success: function (response) {
                    if (response.success && response.data.room) {
                        var room = response.data.room;
                        var roomData = {
                            id: room.id,
                            name: room.name,
                            min_price: 0, // Price will be calculated from schedule
                            description: room.description || room.short_description || '',
                            max_adults: room.max_adults || 2,
                            max_children: room.max_children || 0,
                            room_size: room.room_size || '',
                            bed_type: room.bed_type || '',
                            amenities: response.data.amenities || [],
                            surcharge_help: response.data.surcharge_help || ''
                        };
                        self.populateDetailModal(roomData, $modal);

                        // Load gallery from AJAX response
                        if (response.data.gallery && response.data.gallery.length > 0) {
                            self.loadGalleryFromUrls(response.data.gallery);
                        }
                    } else {
                        $modal.find('.vie-detail-title').text('Không tìm thấy thông tin phòng');
                    }
                },
                error: function () {
                    $modal.find('.vie-detail-title').text('Lỗi tải dữ liệu');
                }
            });
        },

        /**
         * Populate detail modal with room data
         */
        populateDetailModal: function (roomData, $modal) {
            // Populate data
            $modal.find('.vie-detail-title').text(roomData.name || 'Phòng');
            $modal.find('.vie-price-value').text(this.formatCurrency(roomData.min_price || 0));
            $modal.find('.vie-description-text').html(roomData.description || roomData.short_description || '');

            // Meta info
            var metaHtml = '';
            metaHtml += '<span class="vie-meta-item"><i class="dashicons dashicons-groups"></i> ' + (roomData.max_adults || 2) + ' người lớn</span>';
            if (roomData.max_children > 0) {
                metaHtml += '<span class="vie-meta-item"><i class="dashicons dashicons-admin-users"></i> ' + roomData.max_children + ' trẻ em</span>';
            }
            if (roomData.room_size) {
                metaHtml += '<span class="vie-meta-item"><i class="dashicons dashicons-editor-expand"></i> ' + roomData.room_size + 'm²</span>';
            }
            if (roomData.bed_type) {
                metaHtml += '<span class="vie-meta-item"><i class="dashicons dashicons-bed"></i> ' + roomData.bed_type + '</span>';
            }
            $modal.find('.vie-detail-meta').html(metaHtml);

            // Amenities
            var amenities = roomData.amenities || [];
            var amenitiesHtml = '';
            if (Array.isArray(amenities)) {
                amenities.forEach(function (item) {
                    amenitiesHtml += '<span class="vie-amenity">' + item + '</span>';
                });
            }
            $modal.find('.vie-amenities-list').html(amenitiesHtml);

            // Gallery (only if room data has id and we haven't loaded from AJAX)
            if (roomData.id && !roomData._galleryLoaded) {
                this.loadGallery(roomData);
            }

            // Store room data for booking
            this.selectedRoom = roomData;
            $modal.find('.vie-btn-book-from-detail, .js-book-from-detail').attr('data-room-id', roomData.id);
            $modal.find('.vie-btn-book-from-detail, .js-book-from-detail').attr('data-room-name', roomData.name);
            $modal.find('.vie-btn-book-from-detail, .js-book-from-detail').attr('data-base-price', roomData.min_price);
            $modal.find('.vie-btn-book-from-detail, .js-book-from-detail').attr('data-surcharge-help', roomData.surcharge_help || '');

            $modal.fadeIn(200);
            $('body').addClass('vie-modal-open');
        },

        /**
         * Load gallery from pre-fetched URLs
         * Supports both array of URLs (strings) or array of objects {id, url, thumb}
         */
        loadGalleryFromUrls: function (galleryUrls) {
            var self = this;
            var $wrapper = $('#vie-detail-gallery-wrapper');

            if (!$wrapper.length) {
                console.warn('[VieBooking] Gallery wrapper not found');
                return;
            }

            $wrapper.empty();

            if (galleryUrls && galleryUrls.length > 0) {
                galleryUrls.forEach(function (item) {
                    // Handle both object {id, url, thumb} and string URL
                    var imgUrl;
                    if (typeof item === 'object' && item !== null) {
                        imgUrl = item.url || item.thumb || '';
                    } else {
                        imgUrl = item;
                    }

                    if (imgUrl) {
                        $wrapper.append('<div class="swiper-slide"><img src="' + imgUrl + '" alt="Room Image"></div>');
                    }
                });

                // Initialize/update swiper after DOM is updated
                setTimeout(function () {
                    if (typeof Swiper !== 'undefined') {
                        // Destroy existing swiper if any
                        if (self.detailSwiper) {
                            self.detailSwiper.destroy(true, true);
                            self.detailSwiper = null;
                        }

                        // Initialize new swiper
                        self.detailSwiper = new Swiper('.vie-gallery-swiper', {
                            loop: galleryUrls.length > 1,
                            pagination: {
                                el: '.vie-gallery-swiper .swiper-pagination',
                                clickable: true
                            },
                            navigation: {
                                nextEl: '.vie-gallery-swiper .swiper-button-next',
                                prevEl: '.vie-gallery-swiper .swiper-button-prev'
                            },
                            autoplay: {
                                delay: 5000,
                                disableOnInteraction: true
                            }
                        });

                        console.log('[VieBooking] Gallery Swiper initialized with ' + galleryUrls.length + ' images');
                    } else {
                        console.warn('[VieBooking] Swiper library not loaded');
                    }
                }, 100);
            } else {
                $wrapper.append('<div class="swiper-slide vie-no-image"><span class="dashicons dashicons-format-image"></span><p>Không có ảnh</p></div>');
            }
        },

        /**
         * Load gallery images via AJAX
         */
        loadGallery: function (roomData) {
            var self = this;
            var $wrapper = $('#vie-detail-gallery-wrapper');

            if (!$wrapper.length || !roomData || !roomData.id) {
                return;
            }

            // Show loading state
            $wrapper.html('<div class="swiper-slide vie-no-image"><span class="vie-spinner"></span><p>Đang tải ảnh...</p></div>');

            // Get images via AJAX
            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_room_detail',
                    nonce: vieBooking.nonce,
                    room_id: roomData.id
                },
                success: function (response) {
                    if (response.success && response.data.gallery && response.data.gallery.length > 0) {
                        self.loadGalleryFromUrls(response.data.gallery);
                    } else {
                        $wrapper.html('<div class="swiper-slide vie-no-image"><span class="dashicons dashicons-format-image"></span><p>Không có ảnh</p></div>');
                        console.warn('[VieBooking] No gallery images returned for room ' + roomData.id);
                    }
                },
                error: function (xhr, status, error) {
                    $wrapper.html('<div class="swiper-slide vie-no-image"><span class="dashicons dashicons-warning"></span><p>Lỗi tải ảnh</p></div>');
                    console.error('[VieBooking] Gallery AJAX error:', error);
                }
            });
        },

        /**
         * Open booking popup
         */
        openBookingPopup: function (e) {
            var $btn = $(e.currentTarget);
            var roomId = $btn.data('room-id');
            var roomName = $btn.data('room-name') || (this.selectedRoom ? this.selectedRoom.name : '');
            var surchargeHelp = $btn.data('surcharge-help') || '';

            // Close detail modal if open
            $('#vie-room-detail-modal').hide();

            // Reset form
            this.currentStep = 1;
            this.updateStepUI();
            $('#vie-booking-form')[0].reset();
            $('#vie-price-summary').removeClass('has-data').html('<div class="vie-summary-placeholder"><span class="dashicons dashicons-calculator"></span><p>Chọn ngày để xem giá</p></div>');
            $('#vie-book-children-ages').hide().find('.vie-ages-inputs').empty();

            // Set room info - V2 IDs
            $('#vie-book-room-id').val(roomId);
            $('#vie-booking-room-name, .vie-booking-room-name').text(roomName);

            // Update surcharge help text if available
            if (surchargeHelp) {
                $('#vie-book-children-ages .vie-help-text').text(surchargeHelp);
            }

            // Fix 2: Update Modal Image & Price from button data
            var imageUrl = $btn.data('image-url');
            var basePrice = $btn.data('base-price');

            if (imageUrl) {
                $('.vie-popup-room-image img').attr('src', imageUrl);
            }

            if (basePrice) {
                $('.vie-popup-room-price').text(this.formatCurrency(basePrice));
            }

            // Store filter values before showing popup - V2 IDs
            var filterCheckin = $('#vie-filter-checkin').val();
            var filterCheckout = $('#vie-filter-checkout').val();
            var filterAdults = $('#vie-filter-adults').val();
            var filterChildren = $('#vie-filter-children').val();

            // Show popup
            $('#vie-booking-popup').fadeIn(200);
            $('body').addClass('vie-modal-open');

            // Initialize datepickers after popup is visible, then set values
            var self = this;
            setTimeout(function () {
                self.initBookingDatepickers();

                // Copy filter values after datepicker init - V2 IDs
                if (filterCheckin) {
                    $('#vie-book-checkin').val(filterCheckin);
                }
                if (filterCheckout) {
                    $('#vie-book-checkout').val(filterCheckout);
                }
                $('#vie-book-adults').val(filterAdults);
                $('#vie-book-children').val(filterChildren).trigger('change');

                // Calculate price if dates are set
                if (filterCheckin && filterCheckout) {
                    self.recalculatePrice();
                }

                // Initialize transport section
                self.initTransportSection();
            }, 100);
        },

        /**
         * Close all modals
         */
        closeAllModals: function (e) {
            if (e && $(e.target).closest('.vie-modal-container').length && !$(e.target).hasClass('vie-modal-close') && !$(e.target).hasClass('vie-modal-overlay')) {
                return;
            }
            $('.vie-modal').fadeOut(200);
            $('body').removeClass('vie-modal-open');
        },

        /**
         * On booking dates change
         */
        onBookingDatesChange: function () {
            this.recalculatePrice();
        },

        /**
         * Recalculate price via AJAX
         */
        recalculatePrice: function () {
            var checkin = $('#vie-book-checkin').val();
            var checkout = $('#vie-book-checkout').val();
            var roomId = $('#vie-book-room-id').val();

            if (!checkin || !checkout || !roomId) {
                return;
            }

            var $summary = $('#vie-price-summary');
            $summary.html('<div class="vie-summary-placeholder"><span class="vie-spinner"></span><p>' + vieBooking.i18n.calculating + '</p></div>');

            // Collect children ages
            var childrenAges = [];
            $('.vie-child-age-select').each(function () {
                childrenAges.push(parseInt($(this).val()) || 0);
            });

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_frontend_calculate_price',
                    nonce: vieBooking.nonce,
                    room_id: roomId,
                    check_in: checkin,
                    check_out: checkout,
                    num_rooms: $('#vie-book-rooms').val() || 1,
                    num_adults: $('#vie-book-adults').val() || 2,
                    num_children: $('#vie-book-children').val() || 0,
                    children_ages: childrenAges,
                    price_type: $('input[name="price_type"]:checked').val() || 'room'
                },
                success: function (response) {
                    if (response.success) {
                        VieBooking.pricingData = response.data;
                        VieBooking.renderPriceSummary(response.data);

                        // FIX: Re-apply discount if coupon exists
                        if (VieBooking.couponData) {
                            VieBooking.updatePriceWithDiscount();
                        }

                        // Enable Continue button after pricing is loaded
                        $('.js-booking-next').prop('disabled', false);
                    } else {
                        $summary.html('<div class="vie-summary-placeholder vie-error"><p>' + response.data.message + '</p></div>');
                        // Disable button if pricing fails
                        VieBooking.pricingData = null;
                        $('.js-booking-next').prop('disabled', true);
                    }
                },
                error: function () {
                    $summary.html('<div class="vie-summary-placeholder vie-error"><p>' + vieBooking.i18n.error + '</p></div>');
                    // Disable button on error
                    VieBooking.pricingData = null;
                    $('.js-booking-next').prop('disabled', true);
                }
            });
        },

        /**
         * Render price summary - CRO Enhancement: Detailed breakdown like supermarket receipt
         */
        renderPriceSummary: function (data) {
            var self = this;
            var priceType = $('input[name="price_type"]:checked').val();
            var isCombo = priceType === 'combo';

            var html = '<div class="vie-summary-header">';
            html += '<span class="vie-summary-title">Chi tiết giá</span>';
            if (isCombo) {
                html += '<span class="vie-combo-badge">Gói Combo</span>';
            }
            html += '</div>';

            // Group prices by weekday/weekend
            var weekdayPrices = [];
            var weekendPrices = [];
            var weekdayTotal = 0;
            var weekendTotal = 0;

            if (data.price_breakdown && data.price_breakdown.length > 0) {
                data.price_breakdown.forEach(function (item) {
                    // T6 (day 5), T7 (day 6), CN (day 0) are weekend
                    if (item.day_of_week === 5 || item.day_of_week === 6 || item.day_of_week === 0) {
                        weekendPrices.push(item);
                        weekendTotal += item.price;
                    } else {
                        weekdayPrices.push(item);
                        weekdayTotal += item.price;
                    }
                });
            }

            // Price breakdown section
            html += '<div class="vie-breakdown-section">';
            html += '<div class="vie-breakdown-label">Đơn giá cơ bản:</div>';

            // Weekday prices
            if (weekdayPrices.length > 0) {
                var avgWeekday = weekdayTotal / weekdayPrices.length;
                html += '<div class="vie-breakdown-row">';
                html += '<span class="vie-breakdown-detail">';
                html += self.formatCurrency(avgWeekday) + ' x ' + weekdayPrices.length + ' đêm (T2-T5)';
                html += '</span>';
                html += '<span class="vie-breakdown-value">' + self.formatCurrency(weekdayTotal) + '</span>';
                html += '</div>';
            }

            // Weekend prices
            if (weekendPrices.length > 0) {
                var avgWeekend = weekendTotal / weekendPrices.length;
                html += '<div class="vie-breakdown-row vie-weekend-row">';
                html += '<span class="vie-breakdown-detail">';
                html += self.formatCurrency(avgWeekend) + ' x ' + weekendPrices.length + ' đêm ';
                html += '<span class="vie-weekend-badge">';
                weekendPrices.forEach(function (item, idx) {
                    html += item.day_name;
                    if (idx < weekendPrices.length - 1) html += ', ';
                });
                html += '</span>';
                html += '</span>';
                html += '<span class="vie-breakdown-value">' + self.formatCurrency(weekendTotal) + '</span>';
                html += '</div>';
            }
            html += '</div>';

            // Subtotal for rooms
            if (data.num_rooms > 1) {
                html += '<div class="vie-summary-row vie-subtotal-row">';
                html += '<span>' + data.price_type_label + ' x ' + data.num_rooms + ' phòng</span>';
                html += '<span>' + data.rooms_total_formatted + '</span>';
                html += '</div>';
            } else {
                html += '<div class="vie-summary-row vie-subtotal-row">';
                html += '<span>Tiền phòng (' + data.num_nights + ' đêm)</span>';
                html += '<span>' + data.rooms_total_formatted + '</span>';
                html += '</div>';
            }

            // Surcharges section
            if (data.surcharges && data.surcharges.length > 0) {
                html += '<div class="vie-surcharges-section">';
                html += '<div class="vie-breakdown-label">Phụ thu:</div>';
                data.surcharges.forEach(function (surcharge) {
                    html += '<div class="vie-summary-row vie-summary-surcharge">';
                    html += '<span class="vie-surcharge-detail">';
                    html += surcharge.label;
                    if (surcharge.is_per_night && surcharge.nights > 1) {
                        html += ' (' + self.formatCurrency(surcharge.unit_amount) + ' x ' + surcharge.quantity + ' x ' + surcharge.nights + ' đêm)';
                    } else {
                        html += ' (x' + surcharge.quantity + ')';
                    }
                    html += '</span>';
                    html += '<span class="vie-surcharge-value">+' + surcharge.formatted + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }

            // Total
            html += '<div class="vie-summary-row vie-summary-total">';
            html += '<span>Tổng cộng</span>';
            html += '<span class="vie-summary-value">' + data.grand_total_formatted + '</span>';
            html += '</div>';

            var $summary = $('#vie-price-summary');
            $summary.addClass('has-data').html(html);

            // Add combo highlight class
            if (isCombo) {
                $summary.addClass('vie-combo-selected');
            } else {
                $summary.removeClass('vie-combo-selected');
            }
        },

        /**
         * Go to next step
         */
        nextStep: function () {
            if (this.currentStep === 1) {
                // Validate step 1
                if (!this.pricingData) {
                    alert(vieBooking.i18n.selectDates);
                    return;
                }

                // SECURITY: Save booking data to session BEFORE step 2
                // Server sẽ dùng session này để tính order_total khi validate coupon
                this.saveBookingSession(function () {
                    this.currentStep = 2;
                    this.updateStepUI();
                    this.renderBookingSummary();
                }.bind(this));
            }
        },

        /**
         * Go to previous step
         */
        prevStep: function () {
            if (this.currentStep === 2) {
                this.currentStep = 1;
                this.updateStepUI();
            }
        },

        /**
         * Update step UI
         */
        updateStepUI: function () {
            var step = this.currentStep;

            // Update step indicators
            $('.vie-step').removeClass('active');
            $('.vie-step[data-step="' + step + '"]').addClass('active');

            // Show/hide step content - V2 IDs
            $('#vie-booking-step-1, #vie-booking-step-2').hide();
            $('#vie-booking-step-' + step).show();

            // Show/hide buttons - V2 classes
            if (step === 1) {
                $('.js-booking-back, .js-booking-submit').hide();
                $('.js-booking-next').show().prop('disabled', !this.pricingData);
            } else {
                $('.js-booking-next').hide();
                $('.js-booking-back, .js-booking-submit').show();
            }
        },

        /**
         * Render booking summary for step 2
         */
        renderBookingSummary: function () {
            if (!this.pricingData) return;

            var data = this.pricingData;
            var html = '<h4>Thông tin đặt phòng</h4>';

            html += '<div class="vie-summary-item"><span>Loại phòng</span><span>' + data.room_name + '</span></div>';
            html += '<div class="vie-summary-item"><span>Ngày nhận</span><span>' + $('#vie-book-checkin').val() + '</span></div>';
            html += '<div class="vie-summary-item"><span>Ngày trả</span><span>' + $('#vie-book-checkout').val() + '</span></div>';
            html += '<div class="vie-summary-item"><span>Số đêm</span><span>' + data.num_nights + ' đêm</span></div>';
            html += '<div class="vie-summary-item"><span>Số phòng</span><span>' + data.num_rooms + ' phòng</span></div>';
            html += '<div class="vie-summary-item"><span>Loại giá</span><span>' + data.price_type_label + '</span></div>';

            html += '<div class="vie-summary-item"><span>Tiền phòng</span><span>' + data.rooms_total_formatted + '</span></div>';

            if (data.surcharges_total > 0) {
                html += '<div class="vie-summary-item"><span>Phụ thu</span><span>' + data.surcharges_formatted + '</span></div>';
            }

            // COUPON FIX: Show discount if coupon applied
            if (this.couponData && this.couponData.discount > 0) {
                var discount = this.couponData.discount;
                html += '<div class="vie-summary-item discount"><span>Giảm giá (' + this.couponData.code + ')</span><span class="vie-discount-value">-' + this.formatCurrency(discount) + '</span></div>';

                // Calculate final total with discount
                var finalTotal = data.grand_total - discount;
                html += '<div class="vie-summary-item total"><span>Tổng thanh toán</span><span>' + this.formatCurrency(finalTotal) + '</span></div>';
            } else {
                html += '<div class="vie-summary-item total"><span>Tổng tiền</span><span>' + data.grand_total_formatted + '</span></div>';
            }

            $('#vie-booking-summary').html(html);
        },

        /**
         * Submit booking
         */
        submitBooking: function (e) {
            e.preventDefault();

            var self = this;

            // Validate - V2 IDs
            var name = $('#vie-book-name').val().trim();
            var phone = $('#vie-book-phone').val().trim();

            if (!name || !phone) {
                alert(vieBooking.i18n.required || 'Vui lòng điền đầy đủ thông tin');
                return;
            }

            if (!this.pricingData) {
                alert(vieBooking.i18n.error);
                console.error('[VieBooking] ERROR: pricingData is null or undefined');
                return;
            }

            // Debug: Log pricing data structure
            console.log('[VieBooking] Current pricingData:', this.pricingData);

            // Validate that price is not zero
            if (!this.pricingData.grand_total || this.pricingData.grand_total <= 0) {
                console.error('[VieBooking] ERROR: grand_total is 0 or invalid:', this.pricingData.grand_total);
                alert('Giá phòng chưa được tính. Vui lòng quay lại bước 1 và chọn ngày để tính giá.');
                return;
            }

            // Validate rooms_total
            if (!this.pricingData.rooms_total || this.pricingData.rooms_total <= 0) {
                console.error('[VieBooking] ERROR: rooms_total is 0 or invalid:', this.pricingData.rooms_total);
                alert('Lỗi: Giá phòng không hợp lệ. Vui lòng quay lại bước 1 và tính lại giá.');
                return;
            }

            // Validate transport
            if (!this.validateTransport()) {
                return;
            }

            // Validate Invoice - V2 IDs
            var invoiceRequest = $('#vie-book-invoice-request').is(':checked');
            var invoiceData = null;

            if (invoiceRequest) {
                var companyName = $('#vie-book-invoice-company').val().trim();
                var taxId = $('#vie-book-invoice-tax').val().trim();
                var invoiceEmail = $('#vie-book-invoice-email').val().trim();

                if (!companyName || !taxId) {
                    alert('Vui lòng nhập Tên công ty và Mã số thuế để xuất hóa đơn.');
                    return;
                }

                invoiceData = {
                    company_name: companyName,
                    tax_id: taxId,
                    email: invoiceEmail
                };
            }

            var $btn = $('.js-booking-submit');
            $btn.prop('disabled', true).html('<span class="vie-spinner"></span> Đang xử lý...');

            // Collect children ages
            var childrenAges = [];
            $('.vie-child-age-select').each(function () {
                childrenAges.push(parseInt($(this).val()) || 0);
            });

            // V2 form data with V2 IDs
            var hotelId = $('#vie-booking-popup').data('hotel-id') || vieBooking.hotelId;

            var formData = {
                action: 'vie_submit_booking',
                nonce: vieBooking.nonce,
                hotel_id: hotelId,
                room_id: $('#vie-book-room-id').val(),
                check_in: $('#vie-book-checkin').val(),
                check_out: $('#vie-book-checkout').val(),
                num_rooms: $('#vie-book-rooms').val() || 1,
                num_adults: $('#vie-book-adults').val() || 2,
                num_children: $('#vie-book-children').val() || 0,
                children_ages: childrenAges,
                price_type: $('input[name="price_type"]:checked').val() || 'room',
                bed_type: $('#vie-book-bed-type').val() || 'double',
                customer_name: name,
                customer_phone: phone,
                customer_email: $('#vie-book-email').val(),
                customer_note: $('#vie-book-note').val(),
                invoice_request: invoiceRequest ? 1 : 0,
                invoice_info: invoiceData,
                transport_info: this.getTransportData(),
                pricing_snapshot: this.pricingData.pricing_snapshot,
                surcharges_snapshot: this.pricingData.surcharges,
                base_amount: this.pricingData.rooms_total,
                surcharges_amount: this.pricingData.surcharges_total,
                discount_amount: this.couponData ? this.couponData.discount : 0,
                coupon_code: this.couponData ? this.couponData.code : '',
                total_amount: this.couponData ? (this.pricingData.grand_total - this.couponData.discount) : this.pricingData.grand_total
            };

            // Debug logging
            if (typeof console !== 'undefined' && console.log) {
                console.log('[VieBooking] Submitting booking with pricing:', {
                    base_amount: formData.base_amount,
                    surcharges_amount: formData.surcharges_amount,
                    total_amount: formData.total_amount,
                    pricing_snapshot: formData.pricing_snapshot
                });
            }

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        // UX Fix: Redirect to checkout with secure hash
                        var checkoutUrl = vieBooking.homeUrl + '/checkout/?code=' + response.data.booking_hash;
                        window.location.href = checkoutUrl;
                    } else {
                        alert(response.data.message || vieBooking.i18n.error || 'Có lỗi xảy ra');
                        $btn.prop('disabled', false).html('Đặt phòng');
                    }
                },
                error: function () {
                    alert(vieBooking.i18n.error || 'Có lỗi xảy ra');
                    $btn.prop('disabled', false).html('Đặt phòng');
                }
            });
        },

        /**
         * Format currency
         */
        formatCurrency: function (amount) {
            return parseInt(amount).toLocaleString('vi-VN') + ' ' + vieBooking.currency;
        },

        /**
         * Initialize card swipers for room cards
         */
        initCardSwipers: function () {
            if (typeof Swiper === 'undefined') {
                return;
            }

            // Initialize all card swipers
            $('.vie-card-swiper').each(function () {
                new Swiper(this, {
                    loop: true,
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true
                    },
                    autoplay: {
                        delay: 5000,
                        disableOnInteraction: true
                    }
                });
            });
        },

        /**
         * Handle price type change - Show/hide transport based on selection
         */
        onPriceTypeChange: function () {
            var priceType = $('input[name="price_type"]:checked').val();
            this.updateTransportVisibility(priceType);
            this.recalculatePrice();
        },

        /**
         * Initialize transport section
         */
        initTransportSection: function () {
            var self = this;
            var transport = vieBooking.transport;

            // Check if transport is enabled for this hotel
            if (!transport || !transport.enabled) {
                $('#vie-transport-section').hide();
                return;
            }

            // Populate pickup times dropdown - V2 IDs
            var $pickupSelect = $('#vie-book-transport-pickup');
            $pickupSelect.find('option:not(:first)').remove();
            if (transport.pickup_times && transport.pickup_times.length > 0) {
                transport.pickup_times.forEach(function (time) {
                    $pickupSelect.append('<option value="' + time + '">' + self.formatTime(time) + '</option>');
                });
            }

            // Populate dropoff times dropdown - V2 IDs
            var $dropoffSelect = $('#vie-book-transport-dropoff');
            $dropoffSelect.find('option:not(:first)').remove();
            if (transport.dropoff_times && transport.dropoff_times.length > 0) {
                transport.dropoff_times.forEach(function (time) {
                    $dropoffSelect.append('<option value="' + time + '">' + self.formatTime(time) + '</option>');
                });
            }

            // Show pickup note if exists
            if (transport.pickup_note) {
                $('#vie-transport-note').html('<p><i class="dashicons dashicons-info"></i> ' + transport.pickup_note + '</p>').show();
            } else {
                $('#vie-transport-note').hide();
            }

            // Hide error initially
            $('#vie-transport-error').hide();

            // Show/hide based on current price type
            var priceType = $('input[name="price_type"]:checked').val();
            this.updateTransportVisibility(priceType);
        },

        /**
         * Update transport section visibility based on price type
         * Room Only: Hide transport section completely
         * Combo: Show transport section, make it required
         */
        updateTransportVisibility: function (priceType) {
            var transport = vieBooking.transport;

            // If transport not enabled for hotel, always hide
            if (!transport || !transport.enabled) {
                $('#vie-transport-section').hide();
                return;
            }

            if (priceType === 'combo') {
                // Show transport section for Combo
                $('#vie-transport-section').slideDown(200);
                $('#vie-transport-error').hide();
            } else {
                // Hide transport section for Room Only
                $('#vie-transport-section').slideUp(200);
                // Reset selections when hiding - V2 IDs
                $('#vie-book-transport-pickup').val('');
                $('#vie-book-transport-dropoff').val('');
                $('#vie-transport-error').hide();
            }
        },

        /**
         * Validate transport selection
         * Transport is REQUIRED for Combo price type
         */
        validateTransport: function () {
            var transport = vieBooking.transport;
            var priceType = $('input[name="price_type"]:checked').val();

            // If transport not enabled for hotel, skip validation
            if (!transport || !transport.enabled) {
                return true;
            }

            // If Room Only selected, transport is not required
            if (priceType !== 'combo') {
                return true;
            }

            // Combo selected - transport is REQUIRED - V2 IDs
            var pickupTime = $('#vie-book-transport-pickup').val();
            var dropoffTime = $('#vie-book-transport-dropoff').val();

            if (!pickupTime || !dropoffTime) {
                // Show error message
                $('#vie-transport-error').slideDown(200);
                // Scroll to transport section
                $('#vie-transport-section')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            $('#vie-transport-error').hide();
            return true;
        },

        /**
         * Get transport data for submission
         */
        getTransportData: function () {
            var transport = vieBooking.transport;
            var priceType = $('input[name="price_type"]:checked').val();

            // If transport not enabled for hotel
            if (!transport || !transport.enabled) {
                return null;
            }

            // If Room Only selected, no transport data
            if (priceType !== 'combo') {
                return null;
            }

            // Combo selected - include transport data - V2 IDs
            return {
                enabled: true,
                pickup_time: $('#vie-book-transport-pickup').val(),
                dropoff_time: $('#vie-book-transport-dropoff').val(),
                note: transport.pickup_note || ''
            };
        },

        /**
         * Format time for display (HH:mm to readable format)
         */
        formatTime: function (time) {
            if (!time) return '';
            // Time is already in HH:mm format, just return it
            return time;
        },

        /**
         * Fix 1: Render Room Prices (Combo > Room > Contact)
         * NOTE: Template already renders dual prices. This function is kept for dynamic updates only.
         */
        renderRoomPrices: function () {
            // Template (room-card.php) already renders prices correctly with dual display.
            // This function is now a no-op unless we need dynamic price updates.
            // Prices are rendered server-side with proper Room Only + Combo display.

            // If you need to update prices dynamically via AJAX, enable this:
            // var self = this;
            // $('.vie-room-card').each(function() {
            //     var $card = $(this);
            //     var comboPrice = parseInt($card.data('combo-price')) || 0;
            //     var roomPrice = parseInt($card.data('room-price')) || 0;
            //
            //     // Update room price display
            //     if (roomPrice > 0) {
            //         $card.find('.js-room-price-value').text(self.formatCurrency(roomPrice));
            //     }
            //
            //     // Update combo price display
            //     if (comboPrice > 0) {
            //         $card.find('.js-combo-price-value').text(self.formatCurrency(comboPrice));
            //     }
            // });
        },

        /**
         * Fix 4: Submit Search Filter - AJAX Based
         */
        submitSearchFilter: function () {
            var self = this;
            var checkin = $('#vie-filter-checkin').val();
            var checkout = $('#vie-filter-checkout').val();
            var adults = $('#vie-filter-adults').val();
            var children = $('#vie-filter-children').val();
            var hotelId = $('.vie-booking-filters').data('hotel-id') ||
                $('.vie-room-listing').data('hotel-id') ||
                vieBooking.hotelId;

            // Debug logging
            if (typeof vieBooking !== 'undefined' && vieBooking.debug) {
                console.log('[VieBooking] Filter submit data:', {
                    checkin: checkin,
                    checkout: checkout,
                    adults: adults,
                    children: children,
                    hotelId: hotelId
                });
            }

            // Validate dates
            if (!checkin || !checkout) {
                alert(vieBooking.i18n.selectDates || 'Vui lòng chọn ngày nhận và trả phòng');
                return;
            }

            // Validate hotel ID
            if (!hotelId) {
                alert('Không tìm thấy thông tin khách sạn. Vui lòng tải lại trang.');
                console.error('[VieBooking] Hotel ID not found');
                return;
            }

            // Collect children ages
            var ages = [];
            $('.vie-child-age-filter').each(function () {
                ages.push($(this).val());
            });

            // Show loading state
            var $btn = $('.vie-filter-submit');
            var originalText = $btn.text();
            $btn.prop('disabled', true).html('<span class="vie-spinner"></span> Đang kiểm tra...');

            // AJAX check availability
            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_check_availability',
                    nonce: vieBooking.nonce,
                    hotel_id: hotelId,
                    check_in: checkin,
                    check_out: checkout,
                    num_rooms: 1,
                    num_adults: adults || 2,
                    num_children: children || 0,
                    children_ages: ages
                },
                success: function (response) {
                    $btn.prop('disabled', false).text(originalText);

                    if (response.success && response.data.rooms) {
                        // Update room cards with availability status
                        self.updateRoomAvailability(response.data.rooms);

                        // Store filter values for booking popup
                        self.filterValues = {
                            checkin: checkin,
                            checkout: checkout,
                            adults: adults,
                            children: children,
                            ages: ages
                        };

                        // Show success message
                        self.showFilterMessage('Đã kiểm tra ' + Object.keys(response.data.rooms).length + ' phòng', 'success');
                    } else {
                        self.showFilterMessage(response.data.message || 'Không có phòng trống', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    $btn.prop('disabled', false).text(originalText);
                    console.error('[VieBooking] Filter AJAX error:', error, xhr.responseText);
                    self.showFilterMessage('Có lỗi xảy ra. Vui lòng thử lại. (Chi tiết: ' + error + ')', 'error');
                }
            });
        },

        /**
         * Update room cards with availability status
         */
        updateRoomAvailability: function (rooms) {
            $('.vie-room-card').each(function () {
                var $card = $(this);
                var roomId = $card.data('room-id');
                var roomData = rooms[roomId];

                // Remove old badges
                $card.find('.vie-room-badge.availability-badge').remove();
                $card.removeClass('vie-room-unavailable');

                if (roomData) {
                    if (!roomData.available) {
                        // Room not available
                        $card.addClass('vie-room-unavailable');
                        $card.find('.vie-room-image').append(
                            '<div class="vie-room-badge availability-badge sold-out">' +
                            (roomData.message || 'Hết phòng') + '</div>'
                        );
                        $card.find('.js-open-booking').prop('disabled', true);
                    } else if (roomData.status === 'limited') {
                        // Limited availability
                        $card.find('.vie-room-image').append(
                            '<div class="vie-room-badge availability-badge limited">Còn ít phòng!</div>'
                        );
                    }
                }
            });
        },

        /**
         * Show filter message
         */
        showFilterMessage: function (message, type) {
            var $container = $('.vie-booking-filters');
            $container.find('.vie-filter-message').remove();

            var className = type === 'success' ? 'vie-message-success' : 'vie-message-error';
            $container.append('<div class="vie-filter-message ' + className + '">' + message + '</div>');

            // Auto hide after 3 seconds
            setTimeout(function () {
                $container.find('.vie-filter-message').fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Apply coupon code
         */
        applyCoupon: function () {
            var self = this;
            var couponCode = $('#vie-book-coupon').val().trim();
            var $message = $('#vie-coupon-message');
            var $btn = $('#vie-apply-coupon');

            console.log('[VIE DEBUG] applyCoupon called');
            console.log('[VIE DEBUG] Coupon code:', couponCode);

            if (!couponCode) {
                $message.removeClass('vie-success').addClass('vie-error')
                    .text('Vui lòng nhập mã giảm giá').show();
                return;
            }

            if (!this.pricingData) {
                $message.removeClass('vie-success').addClass('vie-error')
                    .text('Vui lòng chọn ngày trước').show();
                return;
            }

            console.log('[VIE DEBUG] Pricing data:', this.pricingData);
            console.log('[VIE DEBUG] vieBooking.nonce:', vieBooking.nonce);

            $btn.prop('disabled', true).text('Đang kiểm tra...');
            $message.hide();

            // SECURITY: Save booking session first (if not already saved)
            // Server needs session data to calculate order_total
            console.log('[VIE DEBUG] Saving booking session before validating coupon...');
            this.saveBookingSession(function () {
                console.log('[VIE DEBUG] Session saved, now validating coupon...');

                // SECURITY FIX: Validate coupon WITHOUT sending order_total
                // Server sẽ tính order_total từ session data
                var requestData = {
                    action: 'vie_validate_coupon',
                    nonce: vieBooking.nonce,
                    coupon_code: couponCode
                    // ✅ REMOVED: order_total (server will calculate)
                };

                console.log('[VIE DEBUG] AJAX request data:', requestData);

                $.ajax({
                    url: vieBooking.ajaxUrl,
                    type: 'POST',
                    data: requestData,
                    success: function (response) {
                        console.log('[VIE DEBUG] AJAX success response:', response);
                        $btn.prop('disabled', false).text('Áp dụng');

                        if (response.success) {
                            // Store coupon data
                            self.couponData = {
                                code: couponCode,
                                discount: response.data.discount,
                                data: response.data
                            };

                            // Update hidden fields
                            $('#vie-coupon-applied').val(couponCode);
                            $('#vie-discount-amount').val(response.data.discount);

                            // Show success message
                            $message.removeClass('vie-error').addClass('vie-success')
                                .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message)
                                .show();

                            // Update price summary with discount
                            self.updatePriceWithDiscount();

                            // Disable coupon input
                            $('#vie-book-coupon').prop('disabled', true);
                            $btn.text('Đã áp dụng').prop('disabled', true);
                        } else {
                            $message.removeClass('vie-success').addClass('vie-error')
                                .html('<span class="dashicons dashicons-warning"></span> ' + (response.data.message || 'Mã không hợp lệ'))
                                .show();
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('[VIE DEBUG] AJAX error!');
                        console.error('[VIE DEBUG] Status:', status);
                        console.error('[VIE DEBUG] Error:', error);
                        console.error('[VIE DEBUG] XHR:', xhr);
                        console.error('[VIE DEBUG] Response text:', xhr.responseText);

                        $btn.prop('disabled', false).text('Áp dụng');

                        var errorMsg = 'Có lỗi xảy ra, vui lòng thử lại';
                        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            errorMsg = xhr.responseJSON.data.message;
                        }

                        $message.removeClass('vie-success').addClass('vie-error')
                            .text(errorMsg).show();
                    }
                });
            });
        },

        /**
         * Update price summary with discount
         */
        updatePriceWithDiscount: function () {
            if (!this.pricingData || !this.couponData) return;

            var discount = this.couponData.discount;
            var newTotal = this.pricingData.grand_total - discount;

            // Add discount row to price summary
            var discountHtml = '<div class="vie-summary-row vie-summary-discount">' +
                '<span>Giảm giá (' + this.couponData.code + ')</span>' +
                '<span class="vie-discount-value">-' + this.formatCurrency(discount) + '</span>' +
                '</div>';

            // Update total
            var $summary = $('#vie-price-summary');
            $summary.find('.vie-summary-discount').remove();
            $summary.find('.vie-summary-total').before(discountHtml);
            $summary.find('.vie-summary-total .vie-summary-value').text(this.formatCurrency(newTotal));

            // Update pricingData
            this.pricingData.discount = discount;
            this.pricingData.grand_total_with_discount = newTotal;
        },

        /**
         * Save booking data to session (SECURITY)
         *
         * Lưu booking data vào PHP session để server có thể tính order_total
         * KHÔNG tin tưởng order_total từ client!
         */
        saveBookingSession: function (callback) {
            var self = this;

            console.log('[VIE DEBUG] saveBookingSession called');

            // Get room_id from hidden input (set by openBookingPopup)
            var roomId = parseInt($('#vie-book-room-id').val()) || 0;

            // Collect children ages
            var childrenAges = [];
            $('.vie-child-age-select').each(function () {
                childrenAges.push(parseInt($(this).val()) || 0);
            });

            // Get price type
            var priceType = $('input[name="price_type"]:checked').val() || 'room';

            // Get booking data từ form
            var bookingData = {
                room_id: roomId,
                check_in: $('#vie-book-checkin').val(),
                check_out: $('#vie-book-checkout').val(),
                num_rooms: parseInt($('#vie-book-rooms').val()) || 1,
                num_adults: parseInt($('#vie-book-adults').val()) || 2,
                num_children: parseInt($('#vie-book-children').val()) || 0,
                children_ages: childrenAges,
                price_type: priceType,
                customer_name: $('#vie-book-name').val() || '',
                customer_phone: $('#vie-book-phone').val() || ''
            };

            console.log('[VIE DEBUG] Booking data to save:', bookingData);
            console.log('[VIE DEBUG] vieBooking.nonce:', vieBooking.nonce);

            // Validate required fields
            if (!bookingData.room_id || !bookingData.check_in || !bookingData.check_out) {
                console.error('[VIE DEBUG] Missing required booking data!');
                alert('Vui lòng chọn phòng và ngày nhận/trả phòng');
                return;
            }

            var requestData = {
                action: 'vie_save_booking_session',
                nonce: vieBooking.nonce,
                room_id: bookingData.room_id,
                check_in: bookingData.check_in,
                check_out: bookingData.check_out,
                num_rooms: bookingData.num_rooms,
                num_adults: bookingData.num_adults,
                num_children: bookingData.num_children,
                children_ages: bookingData.children_ages,
                price_type: bookingData.price_type,
                customer_name: bookingData.customer_name,
                customer_phone: bookingData.customer_phone,
                // Add coupon data to session
                coupon_code: self.couponData ? self.couponData.code : '',
                discount_amount: self.couponData ? self.couponData.discount : 0
            };

            console.log('[VIE DEBUG] AJAX request data:', requestData);

            // Save to session via AJAX
            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: requestData,
                success: function (response) {
                    console.log('[VIE DEBUG] saveBookingSession response:', response);

                    if (response.success) {
                        console.log('[Vie Booking] Session saved. Server-calculated total:', response.data.order_total);
                        console.log('[Vie Booking] Booking data verified:', response.data.booking_data);

                        // Callback để continue
                        if (callback) {
                            callback();
                        }
                    } else {
                        console.error('[VIE DEBUG] Failed to save session:', response.data.message);
                        alert('Lỗi: ' + (response.data.message || 'Không thể lưu thông tin đặt phòng'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[VIE DEBUG] saveBookingSession AJAX error!');
                    console.error('[VIE DEBUG] Status:', status);
                    console.error('[VIE DEBUG] Error:', error);
                    console.error('[VIE DEBUG] XHR:', xhr);
                    console.error('[VIE DEBUG] Response text:', xhr.responseText);
                    alert('Có lỗi xảy ra khi lưu thông tin. Vui lòng thử lại.');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        // Initialize if room grid or room card exists on page
        // Classes: .vie-rooms-grid (shortcode output), .vie-room-card (individual cards)
        if ($('.vie-rooms-grid').length || $('.vie-room-card').length) {
            VieBooking.init();

            if (typeof vieBooking !== 'undefined' && vieBooking.debug) {
                console.log('[VieBooking] Initialized on page with room cards');
            }
        }

        // Invoice Toggle - V2 IDs
        $(document).on('change', '#vie-book-invoice-request', function () {
            if ($(this).is(':checked')) {
                $('#vie-book-invoice-info').slideDown(200);
            } else {
                $('#vie-book-invoice-info').slideUp(200);
            }
        });
    });

})(jQuery);
