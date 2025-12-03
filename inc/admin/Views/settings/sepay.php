<?php
/**
 * Admin View: Settings - SePay Tab
 *
 * @package VielimousineChild
 * @since   2.1.0
 */

defined('ABSPATH') || exit;
?>

<?php if ($is_connected) : ?>
    <div class="notice notice-success inline">
        <p>
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e('Đã kết nối SePay thành công!', 'vielimousine'); ?>
        </p>
    </div>

    <form method="post" id="sepay-settings-form">
        <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Bank Account -->
                <tr>
                    <th scope="row">
                        <label for="bank_account_id"><?php esc_html_e('Tài khoản ngân hàng', 'vielimousine'); ?></label>
                    </th>
                    <td>
                        <select id="bank_account_id" name="bank_account_id" class="regular-text">
                            <option value=""><?php esc_html_e('-- Chọn tài khoản --', 'vielimousine'); ?></option>
                            <?php foreach ($bank_accounts as $account) : ?>
                                <?php
                                $bank_name = $account['bank_name'] ?? ($account['bank']['short_name'] ?? '');
                                $account_number = $account['account_number'] ?? '';
                                ?>
                                <option value="<?php echo esc_attr($account['id']); ?>"
                                        <?php selected($settings['bank_account_id'] ?? '', $account['id']); ?>>
                                    <?php echo esc_html($bank_name . ' - ' . $account_number); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Tài khoản nhận thanh toán', 'vielimousine'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Enabled -->
                <tr>
                    <th scope="row">
                        <label for="enabled"><?php esc_html_e('Kích hoạt', 'vielimousine'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="enabled"
                                   name="enabled"
                                   value="1"
                                   <?php checked(!empty($settings['enabled'])); ?>>
                            <?php esc_html_e('Bật thanh toán SePay', 'vielimousine'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Lưu cài đặt', 'vielimousine')); ?>
    </form>

    <hr>

    <!-- Disconnect Button -->
    <h3><?php esc_html_e('Ngắt kết nối', 'vielimousine'); ?></h3>
    <p><?php esc_html_e('Xóa kết nối với SePay. Bạn sẽ cần kết nối lại để sử dụng.', 'vielimousine'); ?></p>
    <button type="button" id="sepay-disconnect" class="button button-secondary">
        <?php esc_html_e('Ngắt kết nối SePay', 'vielimousine'); ?>
    </button>

<?php else : ?>
    <div class="notice notice-warning inline">
        <p>
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Chưa kết nối SePay', 'vielimousine'); ?>
        </p>
    </div>

    <h3><?php esc_html_e('Kết nối với SePay', 'vielimousine'); ?></h3>
    <p><?php esc_html_e('Nhấn nút bên dưới để kết nối tài khoản SePay. Bạn sẽ được chuyển đến trang đăng nhập SePay.', 'vielimousine'); ?></p>

    <?php if ($sepay) : ?>
        <a href="<?php echo esc_url($sepay->get_oauth_url()); ?>" class="button button-primary">
            <?php esc_html_e('Kết nối SePay', 'vielimousine'); ?>
        </a>
    <?php endif; ?>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // Save settings
    $('#sepay-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('input[type="submit"]');

        $button.prop('disabled', true).val('<?php esc_attr_e('Đang lưu...', 'vielimousine'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=vie_save_sepay_settings',
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

    // Disconnect
    $('#sepay-disconnect').on('click', function() {
        if (!confirm('<?php esc_html_e('Bạn có chắc muốn ngắt kết nối SePay?', 'vielimousine'); ?>')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('<?php esc_attr_e('Đang xử lý...', 'vielimousine'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vie_sepay_disconnect',
                nonce: '<?php echo wp_create_nonce('vie_save_settings'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Đã ngắt kết nối thành công!', 'vielimousine'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php esc_attr_e('Ngắt kết nối SePay', 'vielimousine'); ?>');
            }
        });
    });
});
</script>
