/**
 * Transport Metabox Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Toggle transport config visibility
        $('#vie_transport_enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('#vie-transport-config').slideDown(200);
            } else {
                $('#vie-transport-config').slideUp(200);
            }
        });
        
        // Add time button
        $(document).on('click', '.vie-add-time', function() {
            var targetId = $(this).data('target');
            var inputName = $(this).data('name');
            var $container = $('#' + targetId);
            
            var html = '<div class="vie-time-item">';
            html += '<input type="time" name="' + inputName + '" value="" class="vie-time-input">';
            html += '<button type="button" class="vie-remove-time button-link-delete">';
            html += '<span class="dashicons dashicons-no-alt"></span>';
            html += '</button>';
            html += '</div>';
            
            $container.append(html);
            
            // Focus on new input
            $container.find('.vie-time-input').last().focus();
        });
        
        // Remove time button
        $(document).on('click', '.vie-remove-time', function() {
            $(this).closest('.vie-time-item').fadeOut(200, function() {
                $(this).remove();
            });
        });
        
    });

})(jQuery);
