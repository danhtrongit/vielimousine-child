<?php
/**
 * Template: Database Status Admin Panel
 * 
 * @package VielimousineChild
 * @version 2.0.0
 */

defined('ABSPATH') || exit;

// Get current status
$installer = class_exists('Vie_Database_Installer') ? Vie_Database_Installer::get_instance() : null;
$tables_status = $installer ? $installer->get_tables_status() : [];
$db_version = $installer ? $installer->get_version() : '0';
$is_installed = $installer ? $installer->is_installed() : false;
?>

<div class="wrap vie-admin-wrap">
    <h1>
        <span class="dashicons dashicons-database"></span>
        <?php esc_html_e('Database Status', 'viechild'); ?>
    </h1>

    <div class="vie-db-status-card">
        <div class="vie-db-header">
            <div class="vie-db-version">
                <strong><?php esc_html_e('Phiên bản Database:', 'viechild'); ?></strong>
                <span
                    class="vie-version-badge <?php echo $is_installed ? 'vie-badge-success' : 'vie-badge-warning'; ?>">
                    <?php echo esc_html($db_version ?: 'Chưa cài đặt'); ?>
                </span>
                <?php if ($db_version !== Vie_Database_Installer::DB_VERSION): ?>
                    <span class="vie-version-notice">
                        → Phiên bản mới nhất: <?php echo esc_html(Vie_Database_Installer::DB_VERSION); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="vie-db-actions">
                <button type="button" class="button button-primary" id="vie-repair-db">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('Cài đặt / Sửa chữa Database', 'viechild'); ?>
                </button>
                <button type="button" class="button" id="vie-refresh-status">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Làm mới', 'viechild'); ?>
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped vie-db-table">
            <thead>
                <tr>
                    <th style="width: 30%;"><?php esc_html_e('Tên bảng', 'viechild'); ?></th>
                    <th style="width: 35%;"><?php esc_html_e('Tên đầy đủ', 'viechild'); ?></th>
                    <th style="width: 15%;"><?php esc_html_e('Số dòng', 'viechild'); ?></th>
                    <th style="width: 20%;"><?php esc_html_e('Trạng thái', 'viechild'); ?></th>
                </tr>
            </thead>
            <tbody id="vie-db-tables-body">
                <?php if (!empty($tables_status)): ?>
                    <?php foreach ($tables_status as $key => $table): ?>
                        <tr data-table="<?php echo esc_attr($key); ?>">
                            <td>
                                <strong><?php echo esc_html($table['name']); ?></strong>
                                <?php if (!$table['required']): ?>
                                    <span class="vie-optional-badge">(Tùy chọn)</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($table['table']); ?></code></td>
                            <td><?php echo esc_html($table['row_count']); ?></td>
                            <td>
                                <?php if ($table['exists']): ?>
                                    <span class="vie-status-badge vie-status-ok">
                                        <span class="dashicons dashicons-yes-alt"></span> OK
                                    </span>
                                <?php elseif ($table['required']): ?>
                                    <span class="vie-status-badge vie-status-error">
                                        <span class="dashicons dashicons-warning"></span> Thiếu
                                    </span>
                                <?php else: ?>
                                    <span class="vie-status-badge vie-status-warning">
                                        <span class="dashicons dashicons-minus"></span> Chưa tạo
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">
                            <div class="vie-no-data">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('Không thể kiểm tra database. Vui lòng bấm "Cài đặt / Sửa chữa Database".', 'viechild'); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="vie-db-footer">
            <p class="description">
                <strong>Ghi chú:</strong> Bấm "Cài đặt / Sửa chữa Database" để tự động tạo các bảng còn thiếu và cập
                nhật schema.
                Thao tác này an toàn và không xóa dữ liệu hiện có.
            </p>
        </div>
    </div>

    <!-- Message container -->
    <div id="vie-db-message" class="notice" style="display: none;"></div>
</div>

<style>
    .vie-db-status-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin-top: 20px;
        padding: 20px;
    }

    .vie-db-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .vie-version-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 3px;
        font-weight: bold;
        margin-left: 10px;
    }

    .vie-badge-success {
        background: #d4edda;
        color: #155724;
    }

    .vie-badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .vie-version-notice {
        color: #dc3545;
        margin-left: 10px;
    }

    .vie-db-actions button {
        margin-left: 10px;
    }

    .vie-db-actions .dashicons {
        margin-right: 5px;
        vertical-align: middle;
    }

    .vie-db-table {
        margin-top: 10px;
    }

    .vie-optional-badge {
        color: #666;
        font-size: 12px;
        font-weight: normal;
    }

    .vie-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
    }

    .vie-status-badge .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        margin-right: 4px;
    }

    .vie-status-ok {
        background: #d4edda;
        color: #155724;
    }

    .vie-status-error {
        background: #f8d7da;
        color: #721c24;
    }

    .vie-status-warning {
        background: #fff3cd;
        color: #856404;
    }

    .vie-db-footer {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .vie-no-data {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .vie-no-data .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
        margin-right: 10px;
        vertical-align: middle;
        color: #dc3545;
    }
</style>

<script>
    jQuery(function ($) {
        var nonce = '<?php echo wp_create_nonce('vie_admin_nonce'); ?>';

        // Repair Database
        $('#vie-repair-db').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Đang xử lý...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vie_repair_database',
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        updateTablesUI(response.data.tables);
                        $('.vie-version-badge').text(response.data.version).removeClass('vie-badge-warning').addClass('vie-badge-success');
                    } else {
                        showMessage('error', response.data.message || 'Có lỗi xảy ra');
                    }
                },
                error: function () {
                    showMessage('error', 'Không thể kết nối server');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Cài đặt / Sửa chữa Database');
                }
            });
        });

        // Refresh Status
        $('#vie-refresh-status').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'vie_get_db_status',
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        updateTablesUI(response.data.tables);
                        $('.vie-version-badge').text(response.data.version || 'Chưa cài đặt');
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });

        function updateTablesUI(tables) {
            var $tbody = $('#vie-db-tables-body');
            $tbody.empty();

            $.each(tables, function (key, table) {
                var statusHtml = '';
                if (table.exists) {
                    statusHtml = '<span class="vie-status-badge vie-status-ok"><span class="dashicons dashicons-yes-alt"></span> OK</span>';
                } else if (table.required) {
                    statusHtml = '<span class="vie-status-badge vie-status-error"><span class="dashicons dashicons-warning"></span> Thiếu</span>';
                } else {
                    statusHtml = '<span class="vie-status-badge vie-status-warning"><span class="dashicons dashicons-minus"></span> Chưa tạo</span>';
                }

                var optionalBadge = !table.required ? '<span class="vie-optional-badge">(Tùy chọn)</span>' : '';

                var row = '<tr data-table="' + key + '">' +
                    '<td><strong>' + table.name + '</strong> ' + optionalBadge + '</td>' +
                    '<td><code>' + table.table + '</code></td>' +
                    '<td>' + table.row_count + '</td>' +
                    '<td>' + statusHtml + '</td>' +
                    '</tr>';

                $tbody.append(row);
            });
        }

        function showMessage(type, message) {
            var $msg = $('#vie-db-message');
            $msg.removeClass('notice-success notice-error')
                .addClass('notice-' + type)
                .html('<p>' + message + '</p>')
                .show();

            setTimeout(function () {
                $msg.fadeOut();
            }, 5000);
        }
    });
</script>