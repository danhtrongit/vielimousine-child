<?php
/**
 * ============================================================================
 * TEMPLATE: Booking Popup - Simplified
 * ============================================================================
 *
 * Popup đặt phòng đơn giản với 2 bước:
 * - Bước 1: Chọn ngày, số người, xem giá
 * - Bước 2: Điền thông tin khách hàng
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     3.0.0 (Simplified)
 * ============================================================================
 */

defined('ABSPATH') || exit;

$hotel_id = $hotel_id ?? get_the_ID();
?>

<div id="vie-booking-popup" class="vie-modal" style="display: none;" data-hotel-id="<?php echo esc_attr($hotel_id); ?>">
    <div class="vie-modal-overlay js-close-modal"></div>

    <div class="vie-modal-container vie-modal-lg">
        <button type="button" class="vie-modal-close js-close-modal" aria-label="<?php esc_attr_e('Đóng', 'viechild'); ?>">&times;</button>

        <div class="vie-booking-header">
            <h2 class="vie-booking-room-name" id="vie-booking-room-name"></h2>
            <div class="vie-booking-price">
                <span><?php esc_html_e('Từ', 'viechild'); ?></span>
                <strong class="vie-popup-room-price">--</strong>
                <span><?php esc_html_e('/đêm', 'viechild'); ?></span>
            </div>
        </div>

        <div class="vie-booking-steps">
            <div class="vie-step active" data-step="1">
                <span class="vie-step-num">1</span>
                <span><?php esc_html_e('Chọn thời gian', 'viechild'); ?></span>
            </div>
            <div class="vie-step" data-step="2">
                <span class="vie-step-num">2</span>
                <span><?php esc_html_e('Thông tin khách', 'viechild'); ?></span>
            </div>
        </div>

        <form id="vie-booking-form" novalidate>
            <input type="hidden" name="room_id" id="vie-book-room-id" value="">
            <input type="hidden" name="hotel_id" value="<?php echo esc_attr($hotel_id); ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vie_booking_nonce'); ?>">

            <!-- Step 1 -->
            <div class="vie-booking-step-content" id="vie-booking-step-1">

                <h3><?php esc_html_e('Chọn ngày', 'viechild'); ?></h3>

                <div class="vie-form-row">
                    <div class="vie-form-group">
                        <label for="vie-book-checkin">
                            <?php esc_html_e('Ngày nhận phòng', 'viechild'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="check_in" id="vie-book-checkin"
                               class="vie-datepicker vie-book-datepicker"
                               placeholder="dd/mm/yyyy" readonly required>
                    </div>

                    <div class="vie-form-group">
                        <label for="vie-book-checkout">
                            <?php esc_html_e('Ngày trả phòng', 'viechild'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="check_out" id="vie-book-checkout"
                               class="vie-datepicker vie-book-datepicker"
                               placeholder="dd/mm/yyyy" readonly required>
                    </div>
                </div>

                <h3><?php esc_html_e('Số phòng & Khách', 'viechild'); ?></h3>

                <div class="vie-form-row">
                    <div class="vie-form-group">
                        <label for="vie-book-rooms"><?php esc_html_e('Số phòng', 'viechild'); ?></label>
                        <select name="num_rooms" id="vie-book-rooms">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php esc_html_e('phòng', 'viechild'); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="vie-form-group">
                        <label for="vie-book-adults"><?php esc_html_e('Người lớn', 'viechild'); ?></label>
                        <select name="num_adults" id="vie-book-adults">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="vie-form-group">
                        <label for="vie-book-children"><?php esc_html_e('Trẻ em', 'viechild'); ?></label>
                        <select name="num_children" id="vie-book-children">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="vie-children-ages-booking" id="vie-book-children-ages" style="display: none;">
                    <label><?php esc_html_e('Tuổi trẻ em', 'viechild'); ?></label>
                    <div class="vie-ages-inputs"></div>
                    <p class="vie-help-text"><?php esc_html_e('Bé dưới 6 tuổi: Miễn phí | 6-11 tuổi: Phụ thu | Từ 12 tuổi: Tính như người lớn', 'viechild'); ?></p>
                </div>

                <h3><?php esc_html_e('Tùy chọn', 'viechild'); ?></h3>

                <div class="vie-form-group">
                    <label for="vie-book-bed-type"><?php esc_html_e('Loại giường', 'viechild'); ?></label>
                    <select name="bed_type" id="vie-book-bed-type">
                        <option value="double"><?php esc_html_e('Giường đôi', 'viechild'); ?></option>
                        <option value="twin"><?php esc_html_e('2 Giường đơn', 'viechild'); ?></option>
                    </select>
                </div>

                <div class="vie-price-type">
                    <label><?php esc_html_e('Loại giá', 'viechild'); ?></label>
                    <div class="vie-radio-group">
                        <label class="vie-radio">
                            <input type="radio" name="price_type" value="room" checked>
                            <span>
                                <strong><?php esc_html_e('Room Only', 'viechild'); ?></strong>
                                <small><?php esc_html_e('Chỉ tiền phòng', 'viechild'); ?></small>
                            </span>
                        </label>
                        <label class="vie-radio vie-radio-recommended">
                            <input type="radio" name="price_type" value="combo">
                            <span class="vie-radio-badge"><?php esc_html_e('Khuyên dùng', 'viechild'); ?></span>
                            <span>
                                <strong><?php esc_html_e('Combo Package', 'viechild'); ?></strong>
                                <small><?php esc_html_e('Phòng + Ăn sáng', 'viechild'); ?></small>
                            </span>
                        </label>
                    </div>
                </div>

                <h3><?php esc_html_e('Chi tiết giá', 'viechild'); ?></h3>
                <div class="vie-price-summary" id="vie-price-summary">
                    <div class="vie-summary-placeholder">
                        <p><?php esc_html_e('Chọn ngày để xem giá', 'viechild'); ?></p>
                    </div>
                </div>

                <div class="vie-form-group">
                    <label for="vie-book-coupon"><?php esc_html_e('Mã giảm giá', 'viechild'); ?></label>
                    <div class="vie-coupon-input-group">
                        <input type="text" name="coupon_code" id="vie-book-coupon" placeholder="<?php esc_attr_e('Nhập mã khuyến mãi', 'viechild'); ?>">
                        <button type="button" class="vie-btn vie-btn-outline vie-coupon-apply" id="vie-apply-coupon">
                            <?php esc_html_e('Áp dụng', 'viechild'); ?>
                        </button>
                    </div>
                    <div class="vie-coupon-message" id="vie-coupon-message" style="display: none;"></div>
                    <input type="hidden" name="coupon_applied" id="vie-coupon-applied" value="">
                    <input type="hidden" name="discount_amount" id="vie-discount-amount" value="0">
                </div>

            </div>

            <!-- Step 2 -->
            <div class="vie-booking-step-content" id="vie-booking-step-2" style="display: none;">

                <h3><?php esc_html_e('Tóm tắt đặt phòng', 'viechild'); ?></h3>
                <div class="vie-booking-summary" id="vie-booking-summary">
                    <div class="vie-summary-items"></div>
                </div>

                <h3><?php esc_html_e('Thông tin liên hệ', 'viechild'); ?></h3>

                <div class="vie-form-group">
                    <label for="vie-book-name">
                        <?php esc_html_e('Họ và tên', 'viechild'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="customer_name" id="vie-book-name"
                           placeholder="<?php esc_attr_e('Nguyễn Văn A', 'viechild'); ?>" required>
                </div>

                <div class="vie-form-row">
                    <div class="vie-form-group">
                        <label for="vie-book-phone">
                            <?php esc_html_e('Số điện thoại', 'viechild'); ?> <span class="required">*</span>
                        </label>
                        <input type="tel" name="customer_phone" id="vie-book-phone"
                               placeholder="0901 234 567" required>
                    </div>

                    <div class="vie-form-group">
                        <label for="vie-book-email"><?php esc_html_e('Email', 'viechild'); ?></label>
                        <input type="email" name="customer_email" id="vie-book-email"
                               placeholder="email@example.com">
                    </div>
                </div>

                <div class="vie-form-group">
                    <label for="vie-book-note"><?php esc_html_e('Ghi chú', 'viechild'); ?></label>
                    <textarea name="customer_note" id="vie-book-note" rows="3"
                              placeholder="<?php esc_attr_e('Yêu cầu đặc biệt...', 'viechild'); ?>"></textarea>
                </div>

                <!-- Transport for Combo -->
                <div class="vie-transport-section" id="vie-transport-section" style="display: none;">
                    <h4><?php esc_html_e('Xe đưa đón', 'viechild'); ?> <span class="required">*</span></h4>

                    <div class="vie-form-row">
                        <div class="vie-form-group">
                            <label for="vie-book-transport-pickup">
                                <?php esc_html_e('Giờ đi (SG → Resort)', 'viechild'); ?> <span class="required">*</span>
                            </label>
                            <select name="transport_pickup_time" id="vie-book-transport-pickup">
                                <option value=""><?php esc_html_e('-- Chọn giờ --', 'viechild'); ?></option>
                            </select>
                        </div>
                        <div class="vie-form-group">
                            <label for="vie-book-transport-dropoff">
                                <?php esc_html_e('Giờ về (Resort → SG)', 'viechild'); ?> <span class="required">*</span>
                            </label>
                            <select name="transport_dropoff_time" id="vie-book-transport-dropoff">
                                <option value=""><?php esc_html_e('-- Chọn giờ --', 'viechild'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="vie-transport-note" id="vie-transport-note"></div>
                    <div class="vie-transport-error" id="vie-transport-error" style="display: none;">
                        <?php esc_html_e('Vui lòng chọn giờ xe đưa đón', 'viechild'); ?>
                    </div>
                </div>

                <!-- Invoice -->
                <div class="vie-form-group">
                    <label class="vie-checkbox">
                        <input type="checkbox" name="invoice_request" id="vie-book-invoice-request" value="1">
                        <span><?php esc_html_e('Xuất hóa đơn VAT', 'viechild'); ?></span>
                    </label>

                    <div class="vie-invoice-info" id="vie-book-invoice-info" style="display: none;">
                        <div class="vie-form-group">
                            <label for="vie-book-invoice-company">
                                <?php esc_html_e('Tên công ty', 'viechild'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" name="invoice_company_name" id="vie-book-invoice-company">
                        </div>
                        <div class="vie-form-row">
                            <div class="vie-form-group">
                                <label for="vie-book-invoice-tax">
                                    <?php esc_html_e('Mã số thuế', 'viechild'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" name="invoice_tax_id" id="vie-book-invoice-tax">
                            </div>
                            <div class="vie-form-group">
                                <label for="vie-book-invoice-email"><?php esc_html_e('Email nhận hóa đơn', 'viechild'); ?></label>
                                <input type="email" name="invoice_email" id="vie-book-invoice-email">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="vie-booking-footer">
                <button type="button" class="vie-btn vie-btn-secondary js-booking-back" style="display: none;">
                    <?php esc_html_e('Quay lại', 'viechild'); ?>
                </button>

                <button type="button" class="vie-btn vie-btn-primary js-booking-next" disabled>
                    <?php esc_html_e('Tiếp tục', 'viechild'); ?>
                </button>

                <button type="submit" class="vie-btn vie-btn-primary js-booking-submit" style="display: none;">
                    <?php esc_html_e('Xác nhận đặt phòng', 'viechild'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
