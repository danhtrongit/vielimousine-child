<?php
/**
 * ============================================================================
 * TÊN FILE: CalendarAjax.php
 * ============================================================================
 *
 * MÔ TẢ:
 * AJAX Handler cho calendar operations trên frontend.
 * Xử lý: get calendar prices for datepicker.
 *
 * CHỨC NĂNG CHÍNH:
 * - Get calendar prices for month view
 * - Used by datepicker to show prices and availability
 *
 * AJAX ENDPOINTS (1):
 * - vie_get_calendar_prices
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Frontend/AJAX
 * @version     2.1.0
 * @since       2.0.0 (Refactored to AJAX Handler pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Calendar_Ajax
 * ============================================================================
 *
 * AJAX Handler cho calendar operations.
 *
 * ARCHITECTURE:
 * - AJAX Handler Pattern
 * - Public endpoints (nopriv)
 * - Service layer integration
 *
 * @since   2.0.0
 */
class Vie_Calendar_Ajax
{
    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Register AJAX endpoints.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_ajax_handlers()
    {
        // Get calendar prices
        add_action('wp_ajax_vie_get_calendar_prices', array($this, 'get_calendar_prices'));
        add_action('wp_ajax_nopriv_vie_get_calendar_prices', array($this, 'get_calendar_prices'));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: GET CALENDAR PRICES
     * -------------------------------------------------------------------------
     */

    /**
     * Get calendar prices for datepicker
     *
     * Returns pricing data for all rooms in hotel for specified month.
     * Used by datepicker to:
     * - Show min/max prices per day
     * - Highlight available/unavailable dates
     * - Disable sold-out dates
     *
     * REQUEST PARAMS:
     * - hotel_id: Hotel post ID
     * - year: Year (default: current year)
     * - month: Month 1-12 (default: current month)
     *
     * RESPONSE:
     * - dates: Object with date as key, price/availability as value
     *   {
     *     "2025-12-01": {
     *       "min_price": 500000,
     *       "max_price": 800000,
     *       "available": true,
     *       "status": "available"
     *     },
     *     ...
     *   }
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function get_calendar_prices()
    {
        $hotel_id = absint($_POST['hotel_id'] ?? 0);
        $year     = absint($_POST['year'] ?? date('Y'));
        $month    = absint($_POST['month'] ?? date('n'));

        if (!$hotel_id) {
            wp_send_json_error(array('message' => 'Thiếu thông tin khách sạn'));
        }

        // Validate month/year
        if ($month < 1 || $month > 12) {
            wp_send_json_error(array('message' => 'Tháng không hợp lệ'));
        }

        if ($year < 2020 || $year > 2100) {
            wp_send_json_error(array('message' => 'Năm không hợp lệ'));
        }

        // Get calendar prices
        $result = $this->get_hotel_calendar_prices($hotel_id, $year, $month);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * -------------------------------------------------------------------------
     * SERVICE INTEGRATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get hotel calendar prices for month
     *
     * @since   2.1.0
     * @param   int     $hotel_id   Hotel ID
     * @param   int     $year       Year
     * @param   int     $month      Month (1-12)
     * @return  array|WP_Error      Calendar prices
     */
    private function get_hotel_calendar_prices($hotel_id, $year, $month)
    {
        // Use PricingService if available (v2.1)
        if (class_exists('Vie_Pricing_Service')) {
            $service = Vie_Pricing_Service::get_instance();
            return $service->get_calendar_prices($hotel_id, $year, $month);
        }

        // Fallback to old engine (backward compatibility)
        if (class_exists('Vie_Pricing_Engine')) {
            $engine = Vie_Pricing_Engine::get_instance();
            return $engine->get_calendar_prices($hotel_id, $year, $month);
        }

        return new WP_Error('no_pricing_service', 'Pricing service not available');
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Auto-initialize
new Vie_Calendar_Ajax();
