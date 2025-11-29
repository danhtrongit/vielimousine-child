/**
 * Hotel Rooms Admin - Common JavaScript
 * 
 * Shared utilities used across all admin pages.
 * 
 * @package VieHotelRooms
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Common Modal Handler
     */
    window.VieHotelModal = {
        /**
         * Open modal
         */
        open: function(modalId) {
            $('#' + modalId).fadeIn(200);
        },
        
        /**
         * Close modal
         */
        close: function(modalId) {
            $('#' + modalId).fadeOut(200);
        },
        
        /**
         * Initialize modal close handlers
         */
        init: function() {
            // Close on X button click
            $(document).on('click', '.vie-modal-close', function() {
                $(this).closest('.vie-modal').fadeOut(200);
            });
            
            // Close on backdrop click
            $(document).on('click', '.vie-modal', function(e) {
                if ($(e.target).hasClass('vie-modal')) {
                    $(this).fadeOut(200);
                }
            });
            
            // Close on ESC key
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    $('.vie-modal:visible').fadeOut(200);
                }
            });
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(function() {
        VieHotelModal.init();
    });
    
})(jQuery);
