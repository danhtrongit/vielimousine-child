<?php
/**
 * ============================================================================
 * TÊN FILE: templates.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Helper functions cho việc load templates
 * 
 * CHỨC NĂNG:
 * - vie_get_template(): Load template part với biến
 * - vie_get_admin_template(): Load admin template
 * - vie_get_email_template(): Load email template
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Helpers
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Load template part với biến truyền vào
 * 
 * @since   2.0.0
 * 
 * @param   string  $template_name  Tên template (không có .php), relative từ template-parts/
 * @param   array   $args           Biến truyền vào template
 * @param   bool    $echo           Echo hay return. Default true.
 * 
 * @return  string|void             HTML nếu $echo = false
 * 
 * @example
 * // Load frontend template
 * vie_get_template('frontend/room-card', ['room' => $room]);
 * 
 * // Get template content as string
 * $html = vie_get_template('frontend/room-card', ['room' => $room], false);
 */
function vie_get_template(string $template_name, array $args = [], bool $echo = true) {
    $template_path = VIE_THEME_PATH . '/template-parts/' . $template_name . '.php';
    
    // Kiểm tra file tồn tại
    if (!file_exists($template_path)) {
        if (VIE_DEBUG) {
            error_log("[VIE Template] File not found: {$template_path}");
        }
        return $echo ? null : '';
    }
    
    // Extract biến để dùng trong template
    if (!empty($args)) {
        extract($args, EXTR_SKIP);
    }
    
    if ($echo) {
        include $template_path;
        return null;
    } else {
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}

/**
 * Load admin template
 * 
 * @since   2.0.0
 * 
 * @param   string  $template_name  Tên template (không có .php)
 * @param   array   $args           Biến truyền vào template
 * @param   bool    $echo           Echo hay return. Default true.
 * 
 * @return  string|void
 * 
 * @example
 * vie_get_admin_template('rooms/list', ['rooms' => $rooms]);
 */
function vie_get_admin_template(string $template_name, array $args = [], bool $echo = true) {
    return vie_get_template('admin/' . $template_name, $args, $echo);
}

/**
 * Load email template
 * 
 * @since   2.0.0
 * 
 * @param   string  $template_name  Tên template (không có .php)
 * @param   array   $args           Biến truyền vào template
 * 
 * @return  string  HTML content của email
 * 
 * @example
 * $email_body = vie_get_email_template('booking-confirmation', [
 *     'booking' => $booking,
 *     'room' => $room
 * ]);
 */
function vie_get_email_template(string $template_name, array $args = []): string {
    return vie_get_template('email/' . $template_name, $args, false) ?: '';
}

/**
 * Check template file exists
 * 
 * @since   2.0.0
 * 
 * @param   string  $template_name  Tên template
 * 
 * @return  bool
 */
function vie_template_exists(string $template_name): bool {
    $template_path = VIE_THEME_PATH . '/template-parts/' . $template_name . '.php';
    return file_exists($template_path);
}

/**
 * Render một partial template inline
 * 
 * @since   2.0.0
 * 
 * @param   string  $partial_name   Tên partial
 * @param   array   $args           Biến truyền vào
 * 
 * @return  void
 * 
 * @example
 * vie_partial('components/price-display', ['amount' => 1500000]);
 */
function vie_partial(string $partial_name, array $args = []): void {
    vie_get_template('partials/' . $partial_name, $args, true);
}
