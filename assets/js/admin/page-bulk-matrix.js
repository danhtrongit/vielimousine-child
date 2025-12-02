/**
 * Price Matrix Grid JavaScript
 * 
 * Giao diện Ma trận Giá Tổng thể - All-in-One Grid
 * Features:
 * - Virtual scrolling / Lazy loading by hotel groups
 * - Drag-copy functionality (Excel-like)
 * - Row actions (apply day 1 to month, etc.)
 * - Bulk save with optimized batching
 * 
 * @package VieHotelRooms
 */

(function($) {
    'use strict';

    var VieMatrix = {
        // State
        data: null,           // Full data from server
        changes: {},          // Track changed cells { 'room_id-date': { price_combo, price_room, stock, status } }
        daysInMonth: [],      // Array of day objects
        todayStr: '',         // Today's date string (YYYY-MM-DD)
        
        // Selection state (for drag-copy)
        isSelecting: false,
        selectionStart: null,
        selectionEnd: null,
        selectedCells: [],
        
        // Current popup/menu target
        currentCell: null,
        currentRoom: null,
        
        // DOM references
        $container: null,
        $frozenRows: null,
        $matrixHeader: null,
        $matrixBody: null,
        $scrollable: null,
        $loadingOverlay: null,

        /**
         * Initialize
         */
        init: function() {
            this.$container = $('#vie-matrix-container');
            this.$table = $('#vie-matrix-table');
            this.$matrixHeader = $('#matrix-header');
            this.$matrixBody = $('#matrix-body');
            this.$loadingOverlay = $('#matrix-loading-overlay');
            
            this.todayStr = new Date().toISOString().split('T')[0];
            
            this.bindEvents();
            
            // Auto-load if URL has params
            var month = $('#matrix-month-val').val();
            var year = $('#matrix-year-val').val();
            if (month && year) {
                this.loadMatrixData();
            }
        },

        /**
         * Bind all events
         */
        bindEvents: function() {
            var self = this;
            
            // Form submit - prevent default, load via AJAX
            $('#vie-matrix-filter').on('submit', function(e) {
                e.preventDefault();
                self.loadMatrixData();
            });
            
            // Expand/Collapse all
            $('#btn-expand-all').on('click', function() {
                $('.row-hotel-header').removeClass('collapsed');
                $('.row-room-data').show();
            });
            
            $('#btn-collapse-all').on('click', function() {
                $('.row-hotel-header').addClass('collapsed');
                $('.row-room-data').hide();
            });
            
            // Hotel group toggle
            $(document).on('click', '.row-hotel-header td.col-first', function() {
                var $row = $(this).closest('tr');
                var hotelId = $row.data('hotel-id');
                $row.toggleClass('collapsed');
                // Toggle room rows visibility
                $('.row-room-data[data-hotel-id="' + hotelId + '"]').toggle(!$row.hasClass('collapsed'));
            });
            
            // Cell input change tracking
            $(document).on('input change', '.cell-data input', function() {
                var $cell = $(this).closest('.cell-data');
                self.markCellChanged($cell);
            });
            
            // Cell double-click to open popup
            $(document).on('dblclick', '.cell-data', function(e) {
                if ($(e.target).is('input')) return;
                self.openCellPopup($(this));
            });
            
            // Row actions button
            $(document).on('click', '.row-actions-btn', function(e) {
                e.stopPropagation();
                self.openRowActionsMenu($(this));
            });
            
            // Row actions menu clicks
            $('#row-actions-menu button').on('click', function() {
                var action = $(this).data('action');
                self.executeRowAction(action);
                self.closeRowActionsMenu();
            });
            
            // Popup events
            $('.popup-close, #popup-cancel').on('click', function() {
                self.closePopup();
            });
            
            $('#popup-apply').on('click', function() {
                self.applyPopupValues();
            });
            
            // Close popup/menu on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.vie-matrix-popup').length && !$(e.target).closest('.cell-data').length) {
                    self.closePopup();
                }
                if (!$(e.target).closest('.vie-row-actions-menu').length && !$(e.target).closest('.row-actions-btn').length) {
                    self.closeRowActionsMenu();
                }
            });
            
            // Drag selection for copy
            $(document).on('mousedown', '.cell-data', function(e) {
                if ($(e.target).is('input')) return;
                self.startSelection($(this));
            });
            
            $(document).on('mousemove', '.cell-data', function(e) {
                if (self.isSelecting) {
                    self.updateSelection($(this));
                }
            });
            
            $(document).on('mouseup', function() {
                if (self.isSelecting) {
                    self.endSelection();
                }
            });
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Ctrl+S to save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    if (!$('#btn-save-matrix').prop('disabled')) {
                        self.saveAllChanges();
                    }
                }
                
                // Escape to close popup
                if (e.key === 'Escape') {
                    self.closePopup();
                    self.closeRowActionsMenu();
                }
                
                // Ctrl+C to copy selected cells
                if (e.ctrlKey && e.key === 'c' && self.selectedCells.length > 0) {
                    self.copySelectedCells();
                }
                
                // Ctrl+V to paste
                if (e.ctrlKey && e.key === 'v' && self.selectedCells.length > 0) {
                    self.pasteToSelectedCells();
                }
            });
            
            // Save button
            $('#btn-save-matrix').on('click', function() {
                self.saveAllChanges();
            });
            
            // Warn before leaving with unsaved changes
            $(window).on('beforeunload', function() {
                if (Object.keys(self.changes).length > 0) {
                    return 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời đi?';
                }
            });
        },

        /**
         * Load matrix data via AJAX
         */
        loadMatrixData: function() {
            var self = this;
            var month = $('#matrix-month').val();
            var year = $('#matrix-year').val();
            var hotelId = $('#matrix-hotel').val();
            
            // Update hidden values
            $('#matrix-month-val').val(month);
            $('#matrix-year-val').val(year);
            $('#matrix-hotel-val').val(hotelId);
            
            // Show loading
            this.showLoading(true);
            $('#matrix-empty').hide();
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_get_matrix_data',
                    nonce: vieHotelRooms.nonce,
                    month: month,
                    year: year,
                    hotel_id: hotelId
                },
                success: function(response) {
                    self.showLoading(false);
                    
                    if (response.success) {
                        self.data = response.data;
                        self.changes = {};
                        
                        // Use dates from response if available, otherwise build from month/year
                        if (response.data.dates && response.data.dates.length > 0) {
                            self.daysInMonth = response.data.dates.map(function(d) {
                                return {
                                    day: parseInt(d.day),
                                    dateStr: d.date,
                                    dayName: d.dow_label,
                                    dow: d.dow,
                                    isWeekend: d.dow === 0 || d.dow === 6 || d.dow === 5,
                                    isToday: d.is_today
                                };
                            });
                        } else {
                            self.buildDaysArray(year, month);
                        }
                        
                        self.renderMatrix();
                        self.updateStats();
                        self.updateChangesCount();
                    } else {
                        alert(response.data.message || 'Có lỗi xảy ra');
                    }
                },
                error: function() {
                    self.showLoading(false);
                    alert('Lỗi kết nối server');
                }
            });
        },

        /**
         * Show/Hide loading overlay
         */
        showLoading: function(show) {
            if (show) {
                this.$loadingOverlay.removeClass('hidden');
            } else {
                this.$loadingOverlay.addClass('hidden');
            }
        },

        /**
         * Build days array for the month
         */
        buildDaysArray: function(year, month) {
            var self = this;
            var daysInMonth = new Date(year, month, 0).getDate();
            var dayNames = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
            
            this.daysInMonth = [];
            
            for (var d = 1; d <= daysInMonth; d++) {
                var date = new Date(year, month - 1, d);
                var dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                var dow = date.getDay(); // 0=Sunday, 6=Saturday
                
                this.daysInMonth.push({
                    day: d,
                    dateStr: dateStr,
                    dayName: dayNames[dow],
                    dow: dow,
                    isWeekend: dow === 0 || dow === 6 || dow === 5, // Friday, Saturday, Sunday
                    isToday: dateStr === this.todayStr
                });
            }
        },

        /**
         * Render the full matrix
         */
        renderMatrix: function() {
            var self = this;
            
            // Clear existing
            this.$matrixHeader.empty();
            this.$matrixBody.empty();
            
            if (!this.data || !this.data.hotels || this.data.hotels.length === 0) {
                $('#matrix-empty').show().find('h3').text('Không có dữ liệu');
                return;
            }
            
            // Render day headers
            this.renderDayHeaders();
            
            // Render hotels and rooms
            this.data.hotels.forEach(function(hotel) {
                self.renderHotelGroup(hotel);
            });
        },

        /**
         * Render day header row
         */
        renderDayHeaders: function() {
            var html = '<tr>';
            
            // Corner cell
            html += '<th class="cell-corner">Khách sạn / Phòng</th>';
            
            this.daysInMonth.forEach(function(day) {
                var classes = ['day-header'];
                if (day.isWeekend) classes.push('weekend');
                if (day.isToday) classes.push('today');
                
                html += '<th class="' + classes.join(' ') + '" data-date="' + day.dateStr + '">';
                html += '<span class="day-name">' + day.dayName + '</span>';
                html += '<span class="day-date">' + String(day.day).padStart(2, '0') + '</span>';
                html += '</th>';
            });
            
            html += '</tr>';
            this.$matrixHeader.html(html);
        },

        /**
         * Render a hotel group with its rooms
         */
        renderHotelGroup: function(hotel) {
            var self = this;
            
            // Hotel Header Row
            var headerHtml = '<tr class="row-hotel-header" data-hotel-id="' + hotel.id + '">';
            headerHtml += '<td class="col-first" colspan="' + (this.daysInMonth.length + 1) + '">';
            headerHtml += '<div class="hotel-header-content">';
            headerHtml += '<span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>';
            headerHtml += '<span class="hotel-name">' + this.escapeHtml(hotel.name) + '</span>';
            headerHtml += '<span class="room-count">' + hotel.rooms.length + '</span>';
            headerHtml += '</div>';
            headerHtml += '</td>';
            headerHtml += '</tr>';
            
            this.$matrixBody.append(headerHtml);
            
            // Room Rows
            hotel.rooms.forEach(function(room) {
                self.renderDataRow(room, hotel.id);
            });
        },

        /**
         * Render a data row for a room
         */
        renderDataRow: function(room, hotelId) {
            var self = this;
            var html = '<tr class="row-room-data" data-room-id="' + room.id + '" data-hotel-id="' + hotelId + '">';
            
            // First Column: Room Info
            html += '<td class="col-first">';
            html += '<div class="room-info-cell">';
            html += '<div class="room-details">';
            html += '<div class="room-name" title="' + self.escapeHtml(room.name) + '">' + self.escapeHtml(room.name) + '</div>';
            html += '<div class="room-meta">';
            html += '<span>Phòng: ' + (room.total_rooms || 0) + '</span>';
            html += '</div>'; // room-meta
            html += '</div>'; // room-details
            html += '<button type="button" class="row-actions-btn" data-room-id="' + room.id + '">';
            html += '<span class="dashicons dashicons-ellipsis"></span>';
            html += '</button>';
            html += '</div>'; // room-info-cell
            html += '</td>';
            
            // Pricing data - support both object and array format
            var pricingMap = room.pricing || {};
            
            this.daysInMonth.forEach(function(day) {
                var pricing = pricingMap[day.dateStr] || {};
                var classes = ['cell-data'];
                if (day.isWeekend) classes.push('weekend');
                if (day.isToday) classes.push('today');
                if (pricing.status === 'stop_sell') classes.push('stop-sell');
                
                var comboVal = pricing.price_combo || '';
                var roomVal = pricing.price_room || '';
                var stockVal = pricing.stock !== undefined ? pricing.stock : (room.total_rooms || '');
                
                html += '<td class="' + classes.join(' ') + '" ';
                html += 'data-room-id="' + room.id + '" ';
                html += 'data-date="' + day.dateStr + '" ';
                html += 'data-hotel-id="' + hotelId + '">';
                
                html += '<div class="cell-input-group">';
                
                // Combo price input (primary)
                html += '<div class="cell-input combo">';
                html += '<label>C</label>';
                html += '<input type="number" name="price_combo" value="' + comboVal + '" min="0" step="1000" placeholder="0">';
                html += '</div>';
                
                // Room price input
                html += '<div class="cell-input room">';
                html += '<label>R</label>';
                html += '<input type="number" name="price_room" value="' + roomVal + '" min="0" step="1000" placeholder="0">';
                html += '</div>';
                
                // Stock input
                html += '<div class="cell-input stock">';
                html += '<label>Q</label>';
                html += '<input type="number" name="stock" value="' + stockVal + '" min="0" placeholder="0">';
                html += '</div>';
                
                html += '</div>'; // cell-input-group
                html += '</td>';
            });
            
            html += '</tr>';
            this.$matrixBody.append(html);
        },

        /**
         * Mark a cell as changed
         */
        markCellChanged: function($cell) {
            var roomId = $cell.data('room-id');
            var date = $cell.data('date');
            var key = roomId + '-' + date;
            
            var priceCombo = $cell.find('input[name="price_combo"]').val();
            var priceRoom = $cell.find('input[name="price_room"]').val();
            var stock = $cell.find('input[name="stock"]').val();
            
            this.changes[key] = {
                room_id: roomId,
                date: date,
                price_combo: priceCombo,
                price_room: priceRoom,
                stock: stock
            };
            
            $cell.addClass('changed');
            this.updateChangesCount();
        },

        /**
         * Update changes counter
         */
        updateChangesCount: function() {
            var count = Object.keys(this.changes).length;
            var $changes = $('#matrix-changes');
            var $saveBtn = $('#btn-save-matrix');
            
            if (count > 0) {
                $changes.removeClass('hidden').find('.count').text(count);
                $saveBtn.prop('disabled', false);
            } else {
                $changes.addClass('hidden');
                $saveBtn.prop('disabled', true);
            }
        },

        /**
         * Update stats display
         */
        updateStats: function() {
            if (!this.data) return;
            
            var hotelCount = this.data.hotels.length;
            var roomCount = 0;
            this.data.hotels.forEach(function(h) {
                roomCount += h.rooms.length;
            });
            var cellCount = roomCount * this.daysInMonth.length;
            
            $('#matrix-stats').html(
                '<span class="stat-item"><strong>' + hotelCount + '</strong> Khách sạn</span>' +
                '<span class="stat-item"><strong>' + roomCount + '</strong> Loại phòng</span>' +
                '<span class="stat-item"><strong>' + cellCount.toLocaleString() + '</strong> Ô dữ liệu</span>'
            );
        },

        /**
         * Open cell popup for detailed editing
         */
        openCellPopup: function($cell) {
            var self = this;
            this.currentCell = $cell;
            
            var roomId = $cell.data('room-id');
            var date = $cell.data('date');
            
            // Get room name
            var roomName = $('.row-room-data[data-room-id="' + roomId + '"] .room-name').text();
            var dateFormatted = this.formatDateVN(date);
            
            // Populate popup
            var $popup = $('#matrix-popup');
            $popup.find('.popup-title').text(roomName + ' - ' + dateFormatted);
            
            $popup.find('#popup-price-combo').val($cell.find('input[name="price_combo"]').val());
            $popup.find('#popup-price-room').val($cell.find('input[name="price_room"]').val());
            $popup.find('#popup-stock').val($cell.find('input[name="stock"]').val());
            
            // Position popup near cell
            var cellOffset = $cell.offset();
            var popupLeft = cellOffset.left + $cell.outerWidth() + 10;
            var popupTop = cellOffset.top;
            
            // Keep within viewport
            if (popupLeft + 240 > $(window).width()) {
                popupLeft = cellOffset.left - 250;
            }
            if (popupTop + 200 > $(window).height() + $(window).scrollTop()) {
                popupTop = $(window).height() + $(window).scrollTop() - 220;
            }
            
            $popup.css({ left: popupLeft, top: popupTop }).show();
            $popup.find('#popup-price-combo').focus();
        },

        /**
         * Close popup
         */
        closePopup: function() {
            $('#matrix-popup').hide();
            this.currentCell = null;
        },

        /**
         * Apply popup values to cell
         */
        applyPopupValues: function() {
            if (!this.currentCell) return;
            
            var $popup = $('#matrix-popup');
            
            this.currentCell.find('input[name="price_combo"]').val($popup.find('#popup-price-combo').val());
            this.currentCell.find('input[name="price_room"]').val($popup.find('#popup-price-room').val());
            this.currentCell.find('input[name="stock"]').val($popup.find('#popup-stock').val());
            
            this.markCellChanged(this.currentCell);
            this.closePopup();
        },

        /**
         * Open row actions menu
         */
        openRowActionsMenu: function($btn) {
            var roomId = $btn.data('room-id');
            this.currentRoom = roomId;
            
            var $menu = $('#row-actions-menu');
            var btnOffset = $btn.offset();
            
            $menu.css({
                left: btnOffset.left - 180,
                top: btnOffset.top + $btn.outerHeight()
            }).show();
        },

        /**
         * Close row actions menu
         */
        closeRowActionsMenu: function() {
            $('#row-actions-menu').hide();
            this.currentRoom = null;
        },

        /**
         * Execute row action
         */
        executeRowAction: function(action) {
            if (!this.currentRoom) return;
            
            var self = this;
            var roomId = this.currentRoom;
            var $row = $('.row-room-data[data-room-id="' + roomId + '"]');
            var $cells = $row.find('.cell-data');
            
            switch (action) {
                case 'copy-first':
                    // Copy first day to all days
                    var $firstCell = $cells.first();
                    var values = this.getCellValues($firstCell);
                    
                    $cells.each(function(i) {
                        if (i > 0) {
                            self.setCellValues($(this), values);
                            self.markCellChanged($(this));
                        }
                    });
                    break;
                    
                case 'copy-weekday':
                    // Copy Monday values to Tue, Wed, Thu
                    var mondayValues = null;
                    
                    $cells.each(function() {
                        var $cell = $(this);
                        var date = $cell.data('date');
                        var dow = new Date(date).getDay();
                        
                        if (dow === 1) { // Monday
                            mondayValues = self.getCellValues($cell);
                        } else if (mondayValues && dow >= 2 && dow <= 4) { // Tue, Wed, Thu
                            self.setCellValues($cell, mondayValues);
                            self.markCellChanged($cell);
                        }
                    });
                    break;
                    
                case 'copy-weekend':
                    // Copy Friday values to Sat, Sun
                    var fridayValues = null;
                    
                    $cells.each(function() {
                        var $cell = $(this);
                        var date = $cell.data('date');
                        var dow = new Date(date).getDay();
                        
                        if (dow === 5) { // Friday
                            fridayValues = self.getCellValues($cell);
                        } else if (fridayValues && (dow === 6 || dow === 0)) { // Sat, Sun
                            self.setCellValues($cell, fridayValues);
                            self.markCellChanged($cell);
                        }
                    });
                    break;
                    
                case 'clear-row':
                    if (confirm('Xóa tất cả giá của dòng này?')) {
                        $cells.each(function() {
                            var $cell = $(this);
                            $cell.find('input').val('');
                            self.markCellChanged($cell);
                        });
                    }
                    break;
            }
        },

        /**
         * Get cell values
         */
        getCellValues: function($cell) {
            return {
                price_combo: $cell.find('input[name="price_combo"]').val(),
                price_room: $cell.find('input[name="price_room"]').val(),
                stock: $cell.find('input[name="stock"]').val()
            };
        },

        /**
         * Set cell values
         */
        setCellValues: function($cell, values) {
            $cell.find('input[name="price_combo"]').val(values.price_combo);
            $cell.find('input[name="price_room"]').val(values.price_room);
            $cell.find('input[name="stock"]').val(values.stock);
        },

        /**
         * Selection start (for drag-copy)
         */
        startSelection: function($cell) {
            this.isSelecting = true;
            this.selectionStart = $cell;
            this.clearSelection();
            $cell.addClass('selecting');
        },

        /**
         * Update selection
         */
        updateSelection: function($cell) {
            if (!this.isSelecting) return;
            
            this.selectionEnd = $cell;
            
            // Clear previous selection
            $('.cell-data.selecting').removeClass('selecting');
            
            // Get bounds
            var startRow = this.selectionStart.closest('tr').index();
            var endRow = $cell.closest('tr').index();
            var startCol = this.selectionStart.index();
            var endCol = $cell.index();
            
            // Normalize bounds
            var minRow = Math.min(startRow, endRow);
            var maxRow = Math.max(startRow, endRow);
            var minCol = Math.min(startCol, endCol);
            var maxCol = Math.max(startCol, endCol);
            
            // Highlight selection
            var $rows = this.$matrixBody.find('tr');
            for (var r = minRow; r <= maxRow; r++) {
                var $row = $rows.eq(r);
                // Only select data rows
                if ($row.hasClass('row-room-data')) {
                    var $cells = $row.find('td');
                    for (var c = minCol; c <= maxCol; c++) {
                        var $c = $cells.eq(c);
                        if ($c.hasClass('cell-data')) {
                            $c.addClass('selecting');
                        }
                    }
                }
            }
        },

        /**
         * End selection
         */
        endSelection: function() {
            this.isSelecting = false;
            
            // Convert selecting to selected
            this.selectedCells = [];
            var self = this;
            
            $('.cell-data.selecting').each(function() {
                $(this).removeClass('selecting').addClass('selected');
                self.selectedCells.push($(this));
            });
        },

        /**
         * Clear selection
         */
        clearSelection: function() {
            $('.cell-data.selected, .cell-data.selecting').removeClass('selected selecting');
            this.selectedCells = [];
        },

        /**
         * Copy selected cells (to clipboard state)
         */
        copySelectedCells: function() {
            if (this.selectedCells.length === 0) return;
            
            // Use first cell's values as source
            this.clipboardData = this.getCellValues(this.selectedCells[0]);
        },

        /**
         * Paste to selected cells
         */
        pasteToSelectedCells: function() {
            if (!this.clipboardData || this.selectedCells.length === 0) return;
            
            var self = this;
            this.selectedCells.forEach(function($cell) {
                self.setCellValues($cell, self.clipboardData);
                self.markCellChanged($cell);
            });
        },

        /**
         * Save all changes
         */
        saveAllChanges: function() {
            var self = this;
            var changesArray = Object.values(this.changes);
            
            if (changesArray.length === 0) {
                return;
            }
            
            // Disable save button
            var $saveBtn = $('#btn-save-matrix');
            $saveBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Đang lưu...');
            
            this.showLoading(true);
            $('#matrix-saved').addClass('hidden');
            
            $.ajax({
                url: vieHotelRooms.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_save_matrix_data',
                    nonce: vieHotelRooms.nonce,
                    changes: changesArray
                },
                success: function(response) {
                    self.showLoading(false);
                    
                    if (response.success) {
                        // Clear changes
                        self.changes = {};
                        $('.cell-data.changed').removeClass('changed');
                        self.updateChangesCount();
                        
                        // Show success
                        $('#matrix-saved').removeClass('hidden');
                        setTimeout(function() {
                            $('#matrix-saved').addClass('hidden');
                        }, 3000);
                        
                        $saveBtn.html('<span class="dashicons dashicons-saved"></span> Lưu tất cả');
                    } else {
                        alert(response.data.message || 'Có lỗi xảy ra');
                        $saveBtn.html('<span class="dashicons dashicons-saved"></span> Lưu tất cả').prop('disabled', false);
                    }
                },
                error: function() {
                    self.showLoading(false);
                    alert('Lỗi kết nối server');
                    $saveBtn.html('<span class="dashicons dashicons-saved"></span> Lưu tất cả').prop('disabled', false);
                }
            });
        },

        /**
         * Helper: Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            return str.replace(/[&<>"']/g, function(m) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m];
            });
        },

        /**
         * Helper: Format number to K format
         */
        formatK: function(num) {
            if (!num) return '0';
            num = parseInt(num);
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(0) + 'k';
            }
            return num.toString();
        },

        /**
         * Helper: Format date Vietnamese
         */
        formatDateVN: function(dateStr) {
            var d = new Date(dateStr);
            var days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
            return days[d.getDay()] + ', ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#vie-matrix-container').length) {
            VieMatrix.init();
        }
    });

})(jQuery);
