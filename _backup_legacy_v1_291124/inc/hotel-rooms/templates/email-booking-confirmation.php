<?php
/**
 * Email Template: Booking Confirmation
 * 
 * Template hiện đại, chuyên nghiệp cho email xác nhận đặt phòng
 * Responsive, sử dụng inline CSS, table-based layout
 * 
 * Available variables:
 * @var string $customer_name - Tên khách hàng
 * @var string $booking_id - Mã đơn hàng
 * @var string $hotel_name - Tên khách sạn
 * @var string $hotel_address - Địa chỉ khách sạn
 * @var string $room_name - Loại phòng
 * @var string $package_type - Gói dịch vụ
 * @var string $bed_type - Loại giường
 * @var string $check_in_date - Ngày nhận phòng
 * @var string $check_in_time - Giờ nhận phòng
 * @var string $check_out_date - Ngày trả phòng
 * @var string $check_out_time - Giờ trả phòng
 * @var int $adults - Số người lớn
 * @var int $children - Số trẻ em
 * @var int $nights - Số đêm
 * @var float $price_per_night - Giá mỗi đêm
 * @var float $subtotal - Tạm tính
 * @var float $extra_charges - Phụ thu
 * @var float $discount - Giảm giá
 * @var float $total_amount - Tổng cộng
 * @var string $payment_status - Trạng thái thanh toán
 * @var string $booking_url - Link xem chi tiết đơn hàng
 * @var string $payment_url - Link thanh toán
 * @var string $company_name - Tên công ty
 * @var string $support_hotline - Hotline hỗ trợ
 * @var string $support_email - Email hỗ trợ
 * @var string $logo_url - URL logo
 * @var string $price_includes - Giá bao gồm (từ room type)
 * @var string $cancellation_policy - Chính sách hủy phòng (từ room type)
 */

// Default values nếu không có
$customer_name = isset($customer_name) ? $customer_name : 'Quý khách';
$company_name = isset($company_name) ? $company_name : 'Vie Limousine';
$brand_color = '#e03d25'; // Màu cam chủ đạo
$text_color = '#333333';
$light_gray = '#F4F4F4';
$border_color = '#EEEEEE';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Xác nhận đặt phòng #<?php echo $booking_id; ?></title>
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; }
            .content { padding: 15px !important; }
            .two-col { display: block !important; width: 100% !important; }
            .two-col td { display: block !important; width: 100% !important; box-sizing: border-box; }
            .mobile-text-center { text-align: center !important; }
            .mobile-padding { padding: 10px !important; }
            h1 { font-size: 24px !important; }
            h2 { font-size: 20px !important; }
            .price-total { font-size: 24px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: <?php echo $light_gray; ?>;">
    
    <!-- Email Container -->
    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: <?php echo $light_gray; ?>; padding: 20px 0;">
        <tr>
            <td align="center">
                
                <!-- Main Content Wrapper -->
                <table class="wrapper" width="600" border="0" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, <?php echo $brand_color; ?> 0%, #c73520 100%); padding: 30px 20px; text-align: center;">
                            <?php if (isset($logo_url) && $logo_url): ?>
                            <img src="<?php echo $logo_url; ?>" alt="<?php echo $company_name; ?>" style="max-width: 180px; height: auto; margin-bottom: 15px;">
                            <?php endif; ?>
                            <h1 style="margin: 0; padding: 0; color: #FFFFFF; font-size: 28px; font-weight: 700; letter-spacing: 0.5px;">
                                ✓ XÁC NHẬN ĐẶT PHÒNG
                            </h1>
                            <?php if (isset($payment_status) && $payment_status === 'pending'): ?>
                            <p style="margin: 10px 0 0 0; color: #FFFFFF; font-size: 14px; font-weight: 500;">
                                VUI LÒNG THANH TOÁN ĐỂ HOÀN TẤT ĐẶT PHÒNG
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- GREETING -->
                    <tr>
                        <td class="content" style="padding: 30px 40px 20px 40px;">
                            <h2 style="margin: 0 0 15px 0; color: <?php echo $text_color; ?>; font-size: 22px; font-weight: 600;">
                                Xin chào <?php echo $customer_name; ?>,
                            </h2>
                            <p style="margin: 0; color: #666666; font-size: 15px; line-height: 1.6;">
                                Cảm ơn bạn đã lựa chọn <strong style="color: <?php echo $brand_color; ?>;"><?php echo $company_name; ?></strong>. 
                                Dưới đây là chi tiết đơn đặt phòng của bạn:
                            </p>
                        </td>
                    </tr>
                    
                    <!-- BOOKING ID -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <table width="100%" border="0" cellpadding="15" cellspacing="0" style="background-color: #FFF9F5; border-left: 4px solid <?php echo $brand_color; ?>; border-radius: 4px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: #666666; font-size: 13px; font-weight: 500;">MÃ ĐƠN HÀNG</p>
                                        <p style="margin: 5px 0 0 0; color: <?php echo $brand_color; ?>; font-size: 20px; font-weight: 700; letter-spacing: 1px;">
                                            #<?php echo $booking_id; ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- HOTEL INFO -->
                    <tr>
                        <td style="padding: 25px 40px 15px 40px;">
                            <h3 style="margin: 0 0 10px 0; color: <?php echo $text_color; ?>; font-size: 18px; font-weight: 600; border-bottom: 2px solid <?php echo $border_color; ?>; padding-bottom: 10px;">
                                📍 Thông tin khách sạn
                            </h3>
                            <?php if (isset($hotel_name)): ?>
                            <p style="margin: 15px 0 5px 0; color: <?php echo $text_color; ?>; font-size: 17px; font-weight: 700;">
                                <?php echo $hotel_name; ?>
                            </p>
                            <?php endif; ?>
                            <?php if (isset($hotel_address)): ?>
                            <p style="margin: 0; color: #888888; font-size: 14px; line-height: 1.5;">
                                <?php echo $hotel_address; ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- BOOKING DETAILS - 2 COLUMN GRID -->
                    <tr>
                        <td style="padding: 15px 40px;">
                            <h3 style="margin: 0 0 15px 0; color: <?php echo $text_color; ?>; font-size: 18px; font-weight: 600; border-bottom: 2px solid <?php echo $border_color; ?>; padding-bottom: 10px;">
                                🛏️ Chi tiết đặt phòng
                            </h3>
                            
                            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border: 1px solid <?php echo $border_color; ?>; border-radius: 6px; overflow: hidden;">
                                
                                <!-- Row 1: Room Type & Package -->
                                <tr class="two-col">
                                    <td style="padding: 15px; border-bottom: 1px solid <?php echo $border_color; ?>; border-right: 1px solid <?php echo $border_color; ?>; width: 50%; vertical-align: top;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            Loại phòng
                                        </p>
                                        <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 16px; font-weight: 600;">
                                            <?php echo isset($room_name) ? $room_name : 'N/A'; ?>
                                        </p>
                                    </td>
                                    <td style="padding: 15px; border-bottom: 1px solid <?php echo $border_color; ?>; width: 50%; vertical-align: top; background-color: #FFF9F5;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            ⭐ Gói áp dụng
                                        </p>
                                        <p style="margin: 0; color: <?php echo $brand_color; ?>; font-size: 16px; font-weight: 700;">
                                            <?php echo isset($package_type) ? $package_type : 'Đặt phòng lẻ'; ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Row 2: Bed Type & Guests -->
                                <tr class="two-col">
                                    <td style="padding: 15px; border-bottom: 1px solid <?php echo $border_color; ?>; border-right: 1px solid <?php echo $border_color; ?>; width: 50%; vertical-align: top;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            Loại giường
                                        </p>
                                        <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 15px;">
                                            <?php echo isset($bed_type) ? $bed_type : 'N/A'; ?>
                                        </p>
                                    </td>
                                    <td style="padding: 15px; border-bottom: 1px solid <?php echo $border_color; ?>; width: 50%; vertical-align: top;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            Số khách
                                        </p>
                                        <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 15px;">
                                            <?php echo isset($adults) ? $adults : 0; ?> Người lớn
                                            <?php if (isset($children) && $children > 0): ?>
                                            , <?php echo $children; ?> Trẻ em
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Row 3: Check-in & Check-out -->
                                <tr class="two-col">
                                    <td style="padding: 15px; border-right: 1px solid <?php echo $border_color; ?>; width: 50%; vertical-align: top;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            ✅ Nhận phòng
                                        </p>
                                        <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 15px; font-weight: 600;">
                                            <?php echo isset($check_in_date) ? $check_in_date : 'N/A'; ?>
                                        </p>
                                        <?php if (isset($check_in_time)): ?>
                                        <p style="margin: 3px 0 0 0; color: #888888; font-size: 13px;">
                                            Từ <?php echo $check_in_time; ?>
                                        </p>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 15px; width: 50%; vertical-align: top;">
                                        <p style="margin: 0 0 5px 0; color: #888888; font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                            📤 Trả phòng
                                        </p>
                                        <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 15px; font-weight: 600;">
                                            <?php echo isset($check_out_date) ? $check_out_date : 'N/A'; ?>
                                        </p>
                                        <?php if (isset($check_out_time)): ?>
                                        <p style="margin: 3px 0 0 0; color: #888888; font-size: 13px;">
                                            Trước <?php echo $check_out_time; ?>
                                        </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                            </table>
                        </td>
                    </tr>
                    
                    <?php if (!empty($price_includes)): ?>
                    <!-- PRICE INCLUDES -->
                    <tr>
                        <td style="padding: 15px 40px;">
                            <h3 style="margin: 0 0 15px 0; color: <?php echo $text_color; ?>; font-size: 18px; font-weight: 600; border-bottom: 2px solid <?php echo $border_color; ?>; padding-bottom: 10px;">
                                ✨ Giá bao gồm
                            </h3>
                            <div style="background-color: #F9FFF9; padding: 15px; border-left: 4px solid #4CAF50; border-radius: 4px;">
                                <?php 
                                $includes = explode("\n", $price_includes);
                                echo '<ul style="margin: 0; padding-left: 20px; color: #333333; font-size: 14px; line-height: 1.8;">';
                                foreach ($includes as $item) {
                                    $item = trim($item);
                                    if (!empty($item)) {
                                        echo '<li>' . esc_html($item) . '</li>';
                                    }
                                }
                                echo '</ul>';
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- PRICING TABLE -->
                    <tr>
                        <td style="padding: 15px 40px 30px 40px;">
                            <h3 style="margin: 0 0 15px 0; color: <?php echo $text_color; ?>; font-size: 18px; font-weight: 600; border-bottom: 2px solid <?php echo $border_color; ?>; padding-bottom: 10px;">
                                💰 Chi tiết thanh toán
                            </h3>
                            
                            <table width="100%" border="0" cellpadding="12" cellspacing="0" style="border: 1px solid <?php echo $border_color; ?>; border-radius: 6px;">
                                
                                <?php if (isset($price_per_night) && isset($nights)): ?>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; color: #666666; font-size: 14px;">
                                        Đơn giá × <?php echo $nights; ?> đêm
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; text-align: right; color: <?php echo $text_color; ?>; font-size: 15px; font-weight: 600;">
                                        <?php echo number_format($price_per_night, 0, ',', '.'); ?> ₫
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (isset($subtotal)): ?>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; color: #666666; font-size: 14px;">
                                        Tạm tính
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; text-align: right; color: <?php echo $text_color; ?>; font-size: 15px; font-weight: 600;">
                                        <?php echo number_format($subtotal, 0, ',', '.'); ?> ₫
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (isset($extra_charges) && $extra_charges > 0): ?>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; color: #666666; font-size: 14px;">
                                        Phụ thu
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; text-align: right; color: #D32F2F; font-size: 15px; font-weight: 600;">
                                        +<?php echo number_format($extra_charges, 0, ',', '.'); ?> ₫
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (isset($discount) && $discount > 0): ?>
                                <tr>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; color: #666666; font-size: 14px;">
                                        Giảm giá
                                    </td>
                                    <td style="padding: 12px 15px; border-bottom: 1px solid <?php echo $border_color; ?>; text-align: right; color: #4CAF50; font-size: 15px; font-weight: 600;">
                                        -<?php echo number_format($discount, 0, ',', '.'); ?> ₫
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- Total -->
                                <tr style="background-color: #FFF9F5;">
                                    <td style="padding: 18px 15px; color: <?php echo $text_color; ?>; font-size: 16px; font-weight: 700; text-transform: uppercase;">
                                        Tổng cộng
                                    </td>
                                    <td class="price-total" style="padding: 18px 15px; text-align: right; color: <?php echo $brand_color; ?>; font-size: 28px; font-weight: 700;">
                                        <?php echo isset($total_amount) ? number_format($total_amount, 0, ',', '.') : '0'; ?> ₫
                                    </td>
                                </tr>
                                
                            </table>
                        </td>
                    </tr>
                    
                    <!-- CTA BUTTONS -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <?php if (isset($payment_status) && $payment_status === 'pending' && isset($payment_url)): ?>
                                    <td style="padding: 0 5px 10px 0;" width="50%">
                                        <a href="<?php echo $payment_url; ?>" style="display: block; background: linear-gradient(135deg, <?php echo $brand_color; ?> 0%, #c73520 100%); color: #FFFFFF; text-decoration: none; padding: 15px 20px; border-radius: 6px; text-align: center; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(224, 61, 37, 0.3);">
                                            💳 Thanh toán ngay
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (isset($booking_url)): ?>
                                    <td style="padding: 0 0 10px 5px;" width="<?php echo (isset($payment_status) && $payment_status === 'pending') ? '50%' : '100%'; ?>">
                                        <a href="<?php echo $booking_url; ?>" style="display: block; background-color: #FFFFFF; color: <?php echo $brand_color; ?>; text-decoration: none; padding: 15px 20px; border: 2px solid <?php echo $brand_color; ?>; border-radius: 6px; text-align: center; font-weight: 700; font-size: 15px;">
                                            📋 Xem chi tiết
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- SUPPORT INFO -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table width="100%" border="0" cellpadding="15" cellspacing="0" style="background-color: #F9F9F9; border-radius: 6px; border: 1px solid <?php echo $border_color; ?>;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 10px 0; color: <?php echo $text_color; ?>; font-size: 15px; font-weight: 600;">
                                            📞 Cần hỗ trợ?
                                        </p>
                                        <p style="margin: 0; color: #666666; font-size: 14px; line-height: 1.6;">
                                            <?php if (isset($support_hotline)): ?>
                                            <strong>Hotline:</strong> <a href="tel:<?php echo $support_hotline; ?>" style="color: <?php echo $brand_color; ?>; text-decoration: none; font-weight: 600;"><?php echo $support_hotline; ?></a><br>
                                            <?php endif; ?>
                                            <?php if (isset($support_email)): ?>
                                            <strong>Email:</strong> <a href="mailto:<?php echo $support_email; ?>" style="color: <?php echo $brand_color; ?>; text-decoration: none; font-weight: 600;"><?php echo $support_email; ?></a>
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- CANCELLATION POLICY -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <p style="margin: 0 0 8px 0; color: <?php echo $text_color; ?>; font-size: 14px; font-weight: 600;">
                                ⚠️ Chính sách hủy phòng
                            </p>
                            <?php if (!empty($cancellation_policy)): ?>
                            <div style="color: #888888; font-size: 13px; line-height: 1.5;">
                                <?php echo wpautop($cancellation_policy); ?>
                            </div>
                            <?php else: ?>
                            <p style="margin: 0; color: #888888; font-size: 13px; line-height: 1.5;">
                                Miễn phí hủy phòng trước 48 giờ so với giờ nhận phòng. 
                                Hủy muộn hơn hoặc không đến sẽ tính phí 100% tổng giá trị đơn hàng. 
                                Vui lòng xem <a href="#" style="color: <?php echo $brand_color; ?>; text-decoration: underline;">chi tiết chính sách</a>.
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #2C2C2C; padding: 25px 40px; text-align: center;">
                            <p style="margin: 0 0 10px 0; color: #FFFFFF; font-size: 15px; font-weight: 600;">
                                <?php echo $company_name; ?>
                            </p>
                            <p style="margin: 0; color: #AAAAAA; font-size: 13px; line-height: 1.5;">
                                Email này được gửi tự động, vui lòng không trả lời.<br>
                                © 2025 <?php echo $company_name; ?>. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
