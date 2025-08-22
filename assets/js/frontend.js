/**
 * EOS Manager Frontend JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Animate progress bars
        $('.eos-progress-fill').each(function() {
            const $bar = $(this);
            const width = $bar.data('progress') || 0;
            
            setTimeout(function() {
                $bar.css('width', width + '%');
            }, Math.random() * 500);
        });
        
        // Add hover effects to stat cards
        $('.eos-stat-item').hover(
            function() {
                $(this).css('transform', 'translateY(-2px)');
            },
            function() {
                $(this).css('transform', 'translateY(0)');
            }
        );
    });
    
})(jQuery);