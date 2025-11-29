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
        },

        /**
         * Bind all events
         */
        bindEvents: function () {
            var self = this;

            // Filter events
            $('#vie-num-children').on('change', this.toggleChildrenAges.bind(this));
            $('#vie-check-availability').on('click', this.checkAvailability.bind(this));

            // Room card events
            $(document).on('click', '.vie-btn-detail', this.openDetailModal.bind(this));
            $(document).on('click', '.vie-btn-book, .vie-btn-book-from-detail', this.openBookingPopup.bind(this));

            // Modal close events
            $(document).on('click', '.vie-modal-close, .vie-modal-overlay', this.closeAllModals.bind(this));
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') self.closeAllModals();
            });

            // Booking popup events
            $('#booking-num-children').on('change', this.toggleBookingChildrenAges.bind(this));
            $('#booking-checkin, #booking-checkout').on('change', this.onBookingDatesChange.bind(this));
            $('#booking-num-rooms, #booking-num-adults, #booking-num-children').on('change', this.recalculatePrice.bind(this));
            $('input[name="price_type"]').on('change', this.onPriceTypeChange.bind(this));
            $(document).on('change', '.vie-child-age-select', this.recalculatePrice.bind(this));

            // Booking step navigation
            $('.vie-btn-next').on('click', this.nextStep.bind(this));
            $('.vie-btn-back').on('click', this.prevStep.bind(this));
            $('#vie-booking-form').on('submit', this.submitBooking.bind(this));

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

            // Filter datepickers only
            $('#vie-checkin').datepicker($.extend({}, this.datepickerDefaults, {
                onSelect: function (date) {
                    var checkin = $(this).datepicker('getDate');
                    var minCheckout = new Date(checkin);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    $('#vie-checkout').datepicker('option', 'minDate', minCheckout);

                    // Auto set checkout if empty
                    if (!$('#vie-checkout').val()) {
                        $('#vie-checkout').datepicker('setDate', minCheckout);
                    }
                }
            }));

            $('#vie-checkout').datepicker($.extend({}, this.datepickerDefaults, {
                minDate: new Date(today.getTime() + 86400000)
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

            // Destroy existing datepickers if any
            if ($('#booking-checkin').hasClass('hasDatepicker')) {
                $('#booking-checkin').datepicker('destroy');
            }
            if ($('#booking-checkout').hasClass('hasDatepicker')) {
                $('#booking-checkout').datepicker('destroy');
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

            // Re-initialize
            $('#booking-checkin').datepicker($.extend({}, this.datepickerDefaults, {
                onSelect: function (date) {
                    var checkin = $(this).datepicker('getDate');
                    var minCheckout = new Date(checkin);
                    minCheckout.setDate(minCheckout.getDate() + 1);
                    $('#booking-checkout').datepicker('option', 'minDate', minCheckout);

                    if (!$('#booking-checkout').val()) {
                        $('#booking-checkout').datepicker('setDate', minCheckout);
                    }
                    self.recalculatePrice();
                }
            }));

            $('#booking-checkout').datepicker($.extend({}, this.datepickerDefaults, {
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
         * Toggle children ages inputs (filter)
         */
        toggleChildrenAges: function () {
            var numChildren = parseInt($('#vie-num-children').val()) || 0;
            var $container = $('#vie-children-ages');
            var $inputs = $container.find('.vie-ages-inputs');

            if (numChildren > 0) {
                $inputs.empty();
                for (var i = 0; i < numChildren; i++) {
                    $inputs.append(this.createAgeInput(i + 1));
                }
                $container.slideDown();
            } else {
                $container.slideUp();
            }
        },

        /**
         * Toggle children ages in booking popup
         */
        toggleBookingChildrenAges: function () {
            var numChildren = parseInt($('#booking-num-children').val()) || 0;
            var $container = $('#booking-children-ages');
            var $inputs = $container.find('.vie-ages-inputs');

            if (numChildren > 0) {
                $inputs.empty();
                for (var i = 0; i < numChildren; i++) {
                    $inputs.append(this.createBookingAgeInput(i + 1));
                }
                $container.slideDown();
            } else {
                $container.slideUp();
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
         */
        openDetailModal: function (e) {
            var $btn = $(e.currentTarget);
            var roomData = $btn.data('room');
            var $modal = $('#vie-room-detail-modal');

            // Populate data
            $modal.find('.vie-detail-title').text(roomData.name);
            $modal.find('.vie-price-value').text(this.formatCurrency(roomData.min_price));
            $modal.find('.vie-description-text').html(roomData.description || roomData.short_description || '');

            // Meta info
            var metaHtml = '';
            metaHtml += '<span class="vie-meta-item"><i class="dashicons dashicons-groups"></i> ' + roomData.max_adults + ' người lớn</span>';
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
            amenities.forEach(function (item) {
                amenitiesHtml += '<span class="vie-amenity">' + item + '</span>';
            });
            $modal.find('.vie-amenities-list').html(amenitiesHtml);

            // Gallery
            this.loadGallery(roomData);

            // Store room data for booking
            this.selectedRoom = roomData;
            $modal.find('.vie-btn-book-from-detail').attr('data-room-id', roomData.id);

            $modal.fadeIn(200);
            $('body').addClass('vie-modal-open');
        },

        /**
         * Load gallery images
         */
        loadGallery: function (roomData) {
            var self = this;
            var $wrapper = $('.vie-gallery-swiper .swiper-wrapper');
            $wrapper.empty();

            // Get images via AJAX
            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_room_detail',
                    room_id: roomData.id
                },
                success: function (response) {
                    if (response.success && response.data.gallery.length > 0) {
                        response.data.gallery.forEach(function (imgUrl) {
                            $wrapper.append('<div class="swiper-slide"><img src="' + imgUrl + '" alt=""></div>');
                        });

                        // Initialize/update swiper
                        if (typeof Swiper !== 'undefined') {
                            if (self.detailSwiper) {
                                self.detailSwiper.destroy();
                            }
                            self.detailSwiper = new Swiper('.vie-gallery-swiper', {
                                loop: true,
                                pagination: { el: '.swiper-pagination', clickable: true },
                                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }
                            });
                        } else {
                            console.warn('Swiper not loaded');
                        }
                    } else {
                        $wrapper.append('<div class="swiper-slide vie-no-image"><span class="dashicons dashicons-format-image"></span></div>');
                    }
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

            // Close detail modal if open
            $('#vie-room-detail-modal').hide();

            // Reset form
            this.currentStep = 1;
            this.updateStepUI();
            $('#vie-booking-form')[0].reset();
            $('#vie-price-summary').removeClass('has-data').html('<div class="vie-summary-placeholder"><span class="dashicons dashicons-calculator"></span><p>Chọn ngày để xem giá</p></div>');
            $('#booking-children-ages').hide().find('.vie-ages-inputs').empty();

            // Set room info
            $('#booking-hotel-id').val(vieBooking.hotelId);
            $('#booking-room-id').val(roomId);
            $('.vie-booking-room-name').text(roomName);

            // Store filter values before showing popup
            var filterCheckin = $('#vie-checkin').val();
            var filterCheckout = $('#vie-checkout').val();
            var filterRooms = $('#vie-num-rooms').val();
            var filterAdults = $('#vie-num-adults').val();
            var filterChildren = $('#vie-num-children').val();

            // Show popup
            $('#vie-booking-popup').fadeIn(200);
            $('body').addClass('vie-modal-open');

            // Initialize datepickers after popup is visible, then set values
            var self = this;
            setTimeout(function () {
                self.initBookingDatepickers();

                // Copy filter values after datepicker init
                if (filterCheckin) {
                    $('#booking-checkin').val(filterCheckin);
                }
                if (filterCheckout) {
                    $('#booking-checkout').val(filterCheckout);
                }
                $('#booking-num-rooms').val(filterRooms);
                $('#booking-num-adults').val(filterAdults);
                $('#booking-num-children').val(filterChildren).trigger('change');

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
            var checkin = $('#booking-checkin').val();
            var checkout = $('#booking-checkout').val();
            var roomId = $('#booking-room-id').val();

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
                    num_rooms: $('#booking-num-rooms').val(),
                    num_adults: $('#booking-num-adults').val(),
                    num_children: $('#booking-num-children').val(),
                    children_ages: childrenAges,
                    price_type: $('input[name="price_type"]:checked').val()
                },
                success: function (response) {
                    if (response.success) {
                        VieBooking.pricingData = response.data;
                        VieBooking.renderPriceSummary(response.data);
                    } else {
                        $summary.html('<div class="vie-summary-placeholder vie-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function () {
                    $summary.html('<div class="vie-summary-placeholder vie-error"><p>' + vieBooking.i18n.error + '</p></div>');
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

                this.currentStep = 2;
                this.updateStepUI();
                this.renderBookingSummary();
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

            // Show/hide step content
            $('.vie-booking-step-content').hide();
            $('.vie-booking-step-content[data-step="' + step + '"]').show();

            // Show/hide buttons
            if (step === 1) {
                $('.vie-btn-back, .vie-btn-submit').hide();
                $('.vie-btn-next').show();
            } else {
                $('.vie-btn-next').hide();
                $('.vie-btn-back, .vie-btn-submit').show();
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
            html += '<div class="vie-summary-item"><span>Ngày nhận</span><span>' + $('#booking-checkin').val() + '</span></div>';
            html += '<div class="vie-summary-item"><span>Ngày trả</span><span>' + $('#booking-checkout').val() + '</span></div>';
            html += '<div class="vie-summary-item"><span>Số đêm</span><span>' + data.num_nights + ' đêm</span></div>';
            html += '<div class="vie-summary-item"><span>Số phòng</span><span>' + data.num_rooms + ' phòng</span></div>';
            html += '<div class="vie-summary-item"><span>Loại giá</span><span>' + data.price_type_label + '</span></div>';

            html += '<div class="vie-summary-item"><span>Tiền phòng</span><span>' + data.rooms_total_formatted + '</span></div>';

            if (data.surcharges_total > 0) {
                html += '<div class="vie-summary-item"><span>Phụ thu</span><span>' + data.surcharges_formatted + '</span></div>';
            }

            html += '<div class="vie-summary-item total"><span>Tổng tiền</span><span>' + data.grand_total_formatted + '</span></div>';

            $('#vie-booking-summary').html(html);
        },

        /**
         * Submit booking
         */
        submitBooking: function (e) {
            e.preventDefault();

            var self = this;

            // Validate
            var name = $('#booking-name').val().trim();
            var phone = $('#booking-phone').val().trim();

            if (!name || !phone) {
                alert(vieBooking.i18n.required);
                return;
            }

            if (!this.pricingData) {
                alert(vieBooking.i18n.error);
                return;
            }

            // Validate transport
            if (!this.validateTransport()) {
                return;
            }

            // Validate Invoice
            var invoiceRequest = $('#booking-invoice-request').is(':checked');
            var invoiceData = {};

            if (invoiceRequest) {
                var companyName = $('#booking-invoice-company').val().trim();
                var taxId = $('#booking-invoice-tax').val().trim();
                var invoiceEmail = $('#booking-invoice-email').val().trim();

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

            var $btn = $('.vie-btn-submit');
            $btn.prop('disabled', true).html('<span class="vie-spinner"></span> Đang xử lý...');

            // Collect children ages
            var childrenAges = [];
            $('.vie-child-age-select').each(function () {
                childrenAges.push(parseInt($(this).val()) || 0);
            });

            var formData = {
                action: 'vie_submit_booking',
                nonce: vieBooking.nonce,
                hotel_id: $('#booking-hotel-id').val(),
                room_id: $('#booking-room-id').val(),
                check_in: $('#booking-checkin').val(),
                check_out: $('#booking-checkout').val(),
                num_rooms: $('#booking-num-rooms').val(),
                num_adults: $('#booking-num-adults').val(),
                num_children: $('#booking-num-children').val(),
                children_ages: childrenAges,
                price_type: $('input[name="price_type"]:checked').val(),
                bed_type: $('#booking-bed-type').val(),
                customer_name: name,
                customer_phone: phone,
                customer_email: $('#booking-email').val(),
                customer_note: $('#booking-note').val(),
                invoice_request: invoiceRequest ? 1 : 0,
                invoice_info: invoiceData,
                pricing_snapshot: this.pricingData.pricing_snapshot,
                surcharges_snapshot: this.pricingData.surcharges,
                base_amount: this.pricingData.rooms_total,
                surcharges_amount: this.pricingData.surcharges_total,
                total_amount: this.pricingData.grand_total,
                transport_info: this.getTransportData()
            };

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
                        alert(response.data.message || vieBooking.i18n.error);
                        $btn.prop('disabled', false).html(vieBooking.i18n.confirm);
                    }
                },
                error: function () {
                    alert(vieBooking.i18n.error);
                    $btn.prop('disabled', false).html(vieBooking.i18n.confirm);
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

            // Populate pickup times dropdown
            var $pickupSelect = $('#booking-transport-pickup');
            $pickupSelect.find('option:not(:first)').remove();
            if (transport.pickup_times && transport.pickup_times.length > 0) {
                transport.pickup_times.forEach(function (time) {
                    $pickupSelect.append('<option value="' + time + '">' + self.formatTime(time) + '</option>');
                });
            }

            // Populate dropoff times dropdown
            var $dropoffSelect = $('#booking-transport-dropoff');
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
                // Reset selections when hiding
                $('#booking-transport-pickup').val('');
                $('#booking-transport-dropoff').val('');
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

            // Combo selected - transport is REQUIRED
            var pickupTime = $('#booking-transport-pickup').val();
            var dropoffTime = $('#booking-transport-dropoff').val();

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

            // Combo selected - include transport data
            return {
                enabled: true,
                pickup_time: $('#booking-transport-pickup').val(),
                dropoff_time: $('#booking-transport-dropoff').val(),
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
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        if ($('.vie-room-listing').length) {
            VieBooking.init();
        }

        // Invoice Toggle
        $(document).on('change', '#booking-invoice-request', function () {
            if ($(this).is(':checked')) {
                $('#booking-invoice-info').slideDown(200);
            } else {
                $('#booking-invoice-info').slideUp(200);
            }
        });
    });

})(jQuery);
