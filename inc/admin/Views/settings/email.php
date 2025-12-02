<?php
/**
 * Admin View: Settings - Email Tab
 *
 * @package VielimousineChild
 * @since   2.1.0
 */

defined('ABSPATH') || exit;
?>

<form method="post" id="email-settings-form">
    <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>

    <table class="form-table" role="presentation">
        <tbody>
            <!-- From Name -->
            <tr>
                <th scope="row">
                    <label for="from_name"><?php esc_html_e('Tên người gửi', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text" id="from_name" name="from_name"
                        value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>"
                        class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <p class="description">
                        <?php esc_html_e('Tên hiển thị trong email gửi cho khách', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- From Email -->
            <tr>
                <th scope="row">
                    <label for="from_email"><?php esc_html_e('Email người gửi', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="email" id="from_email" name="from_email"
                        value="<?php echo esc_attr($settings['from_email'] ?? get_option('admin_email')); ?>"
                        class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description">
                        <?php esc_html_e('Email hiển thị khi gửi cho khách', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Admin Email -->
            <tr>
                <th scope="row">
                    <label for="admin_email"><?php esc_html_e('Email nhận thông báo', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text" id="admin_email" name="admin_email"
                        value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>"
                        class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description">
                        <?php esc_html_e('Email nhận thông báo khi có đặt phòng mới. Có thể nhập nhiều email, cách nhau bằng dấu phẩy (,)', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Logo URL -->
            <tr>
                <th scope="row">
                    <label for="logo_url"><?php esc_html_e('Logo URL', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="url" id="logo_url" name="logo_url"
                        value="<?php echo esc_attr($settings['logo_url'] ?? ''); ?>" class="large-text"
                        placeholder="https://example.com/logo.png">
                    <p class="description">
                        <?php esc_html_e('URL logo hiển thị trong email', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Footer Text -->
            <tr>
                <th scope="row">
                    <label for="footer_text"><?php esc_html_e('Footer Email', 'vielimousine'); ?></label>
                </th>
                <td>
                    <textarea id="footer_text" name="footer_text" rows="4" class="large-text"
                        placeholder="<?php esc_attr_e('Thông tin liên hệ, địa chỉ công ty...', 'vielimousine'); ?>"><?php echo esc_textarea($settings['footer_text'] ?? ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Nội dung footer hiển thị cuối email', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(__('Lưu cài đặt', 'vielimousine')); ?>
</form>

<hr>

<!-- Test Email -->
<h3><?php esc_html_e('Gửi email test', 'vielimousine'); ?></h3>
<p><?php esc_html_e('Gửi một email test để kiểm tra cấu hình', 'vielimousine'); ?></p>

<form method="post" id="test-email-form">
    <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="test_email"><?php esc_html_e('Email nhận', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="email" id="test_email" name="test_email"
                        value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text"
                        required>
                </td>
            </tr>
        </tbody>
    </table>

    <button type="submit" class="button button-secondary">
        <?php esc_html_e('Gửi email test', 'vielimousine'); ?>
    </button>
</form>

<script>
    jQuery(document).ready(function ($) {
        // Save email settings
        $('#email-settings-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('input[type="submit"]');

            $button.prop('disabled', true).val('<?php esc_attr_e('Đang lưu...', 'vielimousine'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=vie_save_email_settings',
                success: function (response) {
                    if (response.success) {
                        alert('<?php esc_html_e('Đã lưu cài đặt thành công!', 'vielimousine'); ?>');
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                    }
                },
                error: function () {
                    alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
                },
                complete: function () {
                    $button.prop('disabled', false).val('<?php esc_attr_e('Lưu cài đặt', 'vielimousine'); ?>');
                }
            });
        });

        // Send test email
        $('#test-email-form').on('submit', function (e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');

            $button.prop('disabled', true).text('<?php esc_attr_e('Đang gửi...', 'vielimousine'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=vie_test_email',
                success: function (response) {
                    if (response.success) {
                        alert('<?php esc_html_e('Đã gửi email test thành công! Vui lòng kiểm tra hộp thư.', 'vielimousine'); ?>');
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                    }
                },
                error: function () {
                    alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
                },
                complete: function () {
                    $button.prop('disabled', false).text('<?php esc_attr_e('Gửi email test', 'vielimousine'); ?>');
                }
            });
        });
    });
</script>