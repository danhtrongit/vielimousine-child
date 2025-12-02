<?php
/**
 * Admin View: Settings - General Tab
 *
 * @package VielimousineChild
 * @since   2.1.0
 */

defined('ABSPATH') || exit;
?>

<form method="post" id="general-settings-form">
    <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Hotline -->
            <tr>
                <th scope="row">
                    <label for="hotline"><?php esc_html_e('Hotline', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="hotline"
                           name="hotline"
                           value="<?php echo esc_attr($settings['hotline'] ?? ''); ?>"
                           class="regular-text"
                           placeholder="0901234567">
                    <p class="description">
                        <?php esc_html_e('Số điện thoại hiển thị trên website', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Checkout Page -->
            <tr>
                <th scope="row">
                    <label for="checkout_page"><?php esc_html_e('Trang Checkout', 'vielimousine'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'checkout_page',
                        'id'               => 'checkout_page',
                        'selected'         => $settings['checkout_page'] ?? 0,
                        'show_option_none' => __('-- Chọn trang --', 'vielimousine'),
                    ));
                    ?>
                    <p class="description">
                        <?php esc_html_e('Trang chứa shortcode [vie_checkout]', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Thank You Page -->
            <tr>
                <th scope="row">
                    <label for="thank_you_page"><?php esc_html_e('Trang Cảm ơn', 'vielimousine'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'thank_you_page',
                        'id'               => 'thank_you_page',
                        'selected'         => $settings['thank_you_page'] ?? 0,
                        'show_option_none' => __('-- Chọn trang --', 'vielimousine'),
                    ));
                    ?>
                    <p class="description">
                        <?php esc_html_e('Trang hiển thị sau khi đặt phòng thành công', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Terms Page -->
            <tr>
                <th scope="row">
                    <label for="terms_page"><?php esc_html_e('Trang Điều khoản', 'vielimousine'); ?></label>
                </th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'terms_page',
                        'id'               => 'terms_page',
                        'selected'         => $settings['terms_page'] ?? 0,
                        'show_option_none' => __('-- Chọn trang --', 'vielimousine'),
                    ));
                    ?>
                    <p class="description">
                        <?php esc_html_e('Trang điều khoản & chính sách', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Currency Symbol -->
            <tr>
                <th scope="row">
                    <label for="currency_symbol"><?php esc_html_e('Ký hiệu tiền tệ', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="currency_symbol"
                           name="currency_symbol"
                           value="<?php echo esc_attr($settings['currency_symbol'] ?? 'VNĐ'); ?>"
                           class="small-text"
                           placeholder="VNĐ">
                    <p class="description">
                        <?php esc_html_e('Ví dụ: VNĐ, đ, USD, $', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Date Format -->
            <tr>
                <th scope="row">
                    <label for="date_format"><?php esc_html_e('Định dạng ngày', 'vielimousine'); ?></label>
                </th>
                <td>
                    <select id="date_format" name="date_format">
                        <option value="d/m/Y" <?php selected($settings['date_format'] ?? 'd/m/Y', 'd/m/Y'); ?>>
                            dd/mm/yyyy (<?php echo date('d/m/Y'); ?>)
                        </option>
                        <option value="m/d/Y" <?php selected($settings['date_format'] ?? 'd/m/Y', 'm/d/Y'); ?>>
                            mm/dd/yyyy (<?php echo date('m/d/Y'); ?>)
                        </option>
                        <option value="Y-m-d" <?php selected($settings['date_format'] ?? 'd/m/Y', 'Y-m-d'); ?>>
                            yyyy-mm-dd (<?php echo date('Y-m-d'); ?>)
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Định dạng hiển thị ngày tháng', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(__('Lưu cài đặt', 'vielimousine')); ?>
</form>

<script>
jQuery(document).ready(function($) {
    $('#general-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('input[type="submit"]');

        $button.prop('disabled', true).val('<?php esc_attr_e('Đang lưu...', 'vielimousine'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=vie_save_general_settings',
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Đã lưu cài đặt thành công!', 'vielimousine'); ?>');
                } else {
                    alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).val('<?php esc_attr_e('Lưu cài đặt', 'vielimousine'); ?>');
            }
        });
    });
});
</script>
