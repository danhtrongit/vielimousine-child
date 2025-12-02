<?php
/**
 * Admin View: Settings - Email Templates Tab
 *
 * @package VielimousineChild
 * @since   2.1.0
 */

defined('ABSPATH') || exit;
?>

<!-- Template Tabs -->
<h2 class="nav-tab-wrapper">
    <?php foreach ($template_tabs as $type => $label): ?>
        <a href="<?php echo esc_url(add_query_arg(array('tab' => 'templates', 'template' => $type))); ?>"
            class="nav-tab <?php echo $template_type === $type ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html($label); ?>
        </a>
    <?php endforeach; ?>
</h2>

<!-- Description -->
<div class="notice notice-info inline" style="margin: 20px 0;">
    <p>
        <strong><?php echo esc_html($template_tabs[$template_type]); ?>:</strong>
        <?php echo esc_html($descriptions[$template_type]); ?>
    </p>
</div>

<!-- Template Editor Form -->
<form method="post" id="template-editor-form">
    <?php wp_nonce_field('vie_save_settings', 'nonce'); ?>
    <input type="hidden" name="template_type" value="<?php echo esc_attr($template_type); ?>">

    <table class="form-table" role="presentation">
        <tbody>
            <!-- Subject -->
            <tr>
                <th scope="row">
                    <label for="subject"><?php esc_html_e('Tiêu đề email', 'vielimousine'); ?></label>
                </th>
                <td>
                    <input type="text" id="subject" name="subject"
                        value="<?php echo esc_attr($template_data['subject'] ?? ''); ?>" class="large-text"
                        placeholder="<?php esc_attr_e('Tiêu đề email...', 'vielimousine'); ?>" required>
                    <p class="description">
                        <?php esc_html_e('Tiêu đề hiển thị trong hộp thư khách hàng', 'vielimousine'); ?>
                    </p>
                </td>
            </tr>

            <!-- Body -->
            <tr>
                <th scope="row">
                    <label for="body"><?php esc_html_e('Nội dung email', 'vielimousine'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $template_data['body'] ?? '',
                        'body',
                        array(
                            'textarea_name' => 'body',
                            'textarea_rows' => 15,
                            'teeny' => false,
                            'media_buttons' => true,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                                'toolbar2' => 'forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,outdent,indent,|,hr,|,code',
                            ),
                        )
                    );
                    ?>

                    <!-- Available Variables -->
                    <div style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                        <p style="margin: 0 0 10px 0;">
                            <strong><?php esc_html_e('Biến có thể sử dụng:', 'vielimousine'); ?></strong>
                        </p>
                        <p style="margin: 0; font-family: monospace; font-size: 12px;">
                            {customer_name}, {customer_email}, {customer_phone},<br>
                            {booking_id}, {hotel_name}, {hotel_address}, {room_name}, {package_type}, {bed_type},<br>
                            {check_in}, {check_out}, {adults}, {children}, {total_amount}, {status}<br>
                            <?php if ($template_type === 'completed' || $template_type === 'room_code'): ?>
                                <strong>{room_code}</strong>
                                <?php esc_html_e('(Chỉ dùng cho email Hoàn thành/Mã phòng)', 'vielimousine'); ?><br>
                            <?php endif; ?>
                            <?php if ($template_type === 'admin_notification'): ?>
                                <strong>{admin_order_url}</strong>
                                <?php esc_html_e('(Link xem đơn hàng trong Admin)', 'vielimousine'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(__('Lưu Template', 'vielimousine')); ?>
</form>

<script>
    jQuery(document).ready(function ($) {
        // Save template
        $('#template-editor-form').on('submit', function (e) {
            e.preventDefault();

            // Get TinyMCE content
            if (typeof tinyMCE !== 'undefined') {
                tinyMCE.triggerSave();
            }

            var $form = $(this);
            var $button = $form.find('input[type="submit"]');

            $button.prop('disabled', true).val('<?php esc_attr_e('Đang lưu...', 'vielimousine'); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=vie_save_email_template',
                success: function (response) {
                    if (response.success) {
                        alert('<?php esc_html_e('Đã lưu template thành công!', 'vielimousine'); ?>');
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                    }
                },
                error: function () {
                    alert('<?php esc_html_e('Lỗi kết nối', 'vielimousine'); ?>');
                },
                complete: function () {
                    $button.prop('disabled', false).val('<?php esc_attr_e('Lưu Template', 'vielimousine'); ?>');
                }
            });
        });
    });
</script>