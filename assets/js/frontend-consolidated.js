/**
 * Container Block Designer - Consolidated Frontend JavaScript
 * All frontend functionality combined for better performance
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initContainerBlocks();
    });

    /**
     * Initialize all container blocks
     */
    function initContainerBlocks() {
        $('.cbd-container-block').each(function() {
            var $block = $(this);
            
            // Initialize features
            initCollapsible($block);
            initCopyText($block);
            initScreenshot($block);
            initNumbering($block);
            initAnimations($block);
        });
    }

    /**
     * Initialize collapsible functionality
     */
    function initCollapsible($block) {
        var $toggle = $block.find('.cbd-collapse-toggle');
        var $content = $block.find('.cbd-collapsible-content');
        
        if ($toggle.length && $content.length) {
            $toggle.on('click', function(e) {
                e.preventDefault();
                
                if ($block.hasClass('cbd-collapsed')) {
                    $block.removeClass('cbd-collapsed');
                    $content.slideDown(300);
                    $toggle.text($toggle.data('collapse-text') || 'Einklappen');
                } else {
                    $block.addClass('cbd-collapsed');
                    $content.slideUp(300);
                    $toggle.text($toggle.data('expand-text') || 'Ausklappen');
                }
            });
        }
    }

    /**
     * Initialize copy text functionality
     */
    function initCopyText($block) {
        var $copyBtn = $block.find('.cbd-copy-text-btn');
        
        if ($copyBtn.length) {
            $copyBtn.on('click', function(e) {
                e.preventDefault();
                
                var textToCopy = $block.find('.cbd-block-content').text().trim();
                
                // Modern clipboard API
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        showCopyFeedback($copyBtn, true);
                    }).catch(function() {
                        fallbackCopyText(textToCopy, $copyBtn);
                    });
                } else {
                    fallbackCopyText(textToCopy, $copyBtn);
                }
            });
        }
    }

    /**
     * Fallback copy text method
     */
    function fallbackCopyText(text, $btn) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand('copy');
            showCopyFeedback($btn, true);
        } catch (err) {
            showCopyFeedback($btn, false);
        }
        
        $temp.remove();
    }

    /**
     * Show copy feedback
     */
    function showCopyFeedback($btn, success) {
        var originalText = $btn.text();
        var feedbackText = success ? 'Kopiert!' : 'Fehler beim Kopieren';
        
        $btn.text(feedbackText);
        $btn.addClass(success ? 'cbd-copy-success' : 'cbd-copy-error');
        
        setTimeout(function() {
            $btn.text(originalText);
            $btn.removeClass('cbd-copy-success cbd-copy-error');
        }, 2000);
    }

    /**
     * Initialize screenshot functionality
     */
    function initScreenshot($block) {
        var $screenshotBtn = $block.find('.cbd-screenshot-btn');
        
        if ($screenshotBtn.length) {
            $screenshotBtn.on('click', function(e) {
                e.preventDefault();
                takeScreenshot($block, $screenshotBtn);
            });
        }
    }

    /**
     * Take screenshot of block
     */
    function takeScreenshot($block, $btn) {
        // Check if html2canvas is available
        if (typeof html2canvas === 'undefined') {
            console.warn('html2canvas library not loaded');
            return;
        }

        var originalText = $btn.text();
        $btn.text('Erstelle Screenshot...');
        $btn.prop('disabled', true);

        html2canvas($block[0], {
            backgroundColor: null,
            scale: 2,
            useCORS: true,
            allowTaint: false
        }).then(function(canvas) {
            // Create download link
            var link = document.createElement('a');
            link.download = 'container-block-' + Date.now() + '.png';
            link.href = canvas.toDataURL();
            link.click();
            
            $btn.text('Screenshot erstellt!');
            setTimeout(function() {
                $btn.text(originalText);
                $btn.prop('disabled', false);
            }, 2000);
        }).catch(function(error) {
            console.error('Screenshot error:', error);
            $btn.text('Fehler beim Screenshot');
            setTimeout(function() {
                $btn.text(originalText);
                $btn.prop('disabled', false);
            }, 2000);
        });
    }

    /**
     * Initialize numbering
     */
    function initNumbering($block) {
        var $number = $block.find('.cbd-block-number');
        
        if ($number.length) {
            var countingMode = $block.data('counting-mode') || 'same-design';
            var blockType = $block.data('block-type') || 'default';
            var format = $block.data('number-format') || 'numeric';
            
            updateBlockNumber($block, $number, countingMode, blockType, format);
        }
    }

    /**
     * Update block numbering
     */
    function updateBlockNumber($block, $number, countingMode, blockType, format) {
        var selector = countingMode === 'all-blocks' ? '.cbd-container-block' : '.cbd-container-block[data-block-type="' + blockType + '"]';
        var $allBlocks = $(selector);
        var index = $allBlocks.index($block) + 1;
        
        var numberText = formatNumber(index, format);
        $number.text(numberText);
    }

    /**
     * Format number based on format type
     */
    function formatNumber(number, format) {
        switch (format) {
            case 'alphabetic':
                return numberToLetter(number);
            case 'roman':
                return numberToRoman(number);
            default:
                return number.toString();
        }
    }

    /**
     * Convert number to letter (A, B, C...)
     */
    function numberToLetter(number) {
        var result = '';
        while (number > 0) {
            number--;
            result = String.fromCharCode(65 + (number % 26)) + result;
            number = Math.floor(number / 26);
        }
        return result;
    }

    /**
     * Convert number to Roman numeral
     */
    function numberToRoman(number) {
        var values = [1000, 900, 500, 400, 100, 90, 50, 40, 10, 9, 5, 4, 1];
        var symbols = ['M', 'CM', 'D', 'CD', 'C', 'XC', 'L', 'XL', 'X', 'IX', 'V', 'IV', 'I'];
        var result = '';
        
        for (var i = 0; i < values.length; i++) {
            while (number >= values[i]) {
                result += symbols[i];
                number -= values[i];
            }
        }
        
        return result;
    }

    /**
     * Initialize animations
     */
    function initAnimations($block) {
        if ($block.hasClass('cbd-animated')) {
            // Add intersection observer for scroll animations
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('cbd-in-view');
                        }
                    });
                }, {
                    threshold: 0.1
                });
                
                observer.observe($block[0]);
            }
        }
    }

    /**
     * Refresh all container blocks (useful after dynamic content changes)
     */
    window.cbdRefreshBlocks = function() {
        initContainerBlocks();
    };

    /**
     * Add new container block dynamically
     */
    window.cbdAddBlock = function($newBlock) {
        if ($newBlock && $newBlock.hasClass('cbd-container-block')) {
            initCollapsible($newBlock);
            initCopyText($newBlock);
            initScreenshot($newBlock);
            initNumbering($newBlock);
            initAnimations($newBlock);
        }
    };

})(jQuery);