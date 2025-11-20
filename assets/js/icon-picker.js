/**
 * Multi-Library Icon Picker for Container Block Designer
 * Supports: Dashicons, Font Awesome, Material Icons, Lucide, Emojis
 */

(function($) {
    'use strict';

    // Icon Library Data
    const iconLibraries = {
        dashicons: {
            name: 'Dashicons',
            type: 'dashicons',
            categories: {
                'admin': [
                    'admin-appearance', 'admin-collapse', 'admin-comments', 'admin-generic',
                    'admin-home', 'admin-media', 'admin-network', 'admin-page', 'admin-plugins',
                    'admin-settings', 'admin-site', 'admin-tools', 'admin-users', 'dashboard',
                    'database', 'welcome-add-page', 'welcome-comments', 'welcome-learn-more'
                ],
                'post': [
                    'align-center', 'align-left', 'align-right', 'edit', 'trash', 'sticky',
                    'book', 'book-alt', 'archive', 'tagcloud', 'category', 'post-status',
                    'menu-alt', 'format-aside', 'format-audio', 'format-chat', 'format-gallery',
                    'format-image', 'format-quote', 'format-status', 'format-video'
                ],
                'media': [
                    'camera', 'camera-alt', 'images-alt', 'images-alt2', 'video-alt',
                    'video-alt2', 'video-alt3', 'media-archive', 'media-audio', 'media-code',
                    'media-default', 'media-document', 'media-interactive', 'media-spreadsheet',
                    'media-text', 'media-video', 'playlist-audio', 'playlist-video', 'controls-play',
                    'controls-pause', 'controls-forward', 'controls-skipforward', 'controls-back',
                    'controls-skipback', 'controls-repeat', 'controls-volumeon', 'controls-volumeoff'
                ],
                'misc': [
                    'star-filled', 'star-empty', 'star-half', 'flag', 'warning', 'info',
                    'shield', 'shield-alt', 'yes', 'yes-alt', 'no', 'no-alt', 'plus', 'plus-alt',
                    'minus', 'dismiss', 'marker', 'location', 'location-alt', 'vault', 'lightbulb',
                    'search', 'slides', 'analytics', 'chart-pie', 'chart-bar', 'chart-line',
                    'chart-area', 'groups', 'businessman', 'products', 'awards', 'forms'
                ],
                'social': [
                    'email', 'email-alt', 'facebook', 'facebook-alt', 'googleplus', 'networking',
                    'hammer', 'art', 'migrate', 'performance', 'universal-access',
                    'universal-access-alt', 'tickets', 'nametag', 'clipboard', 'heart',
                    'megaphone', 'schedule', 'twitter', 'rss', 'share', 'share-alt', 'share-alt2'
                ]
            }
        },
        fontawesome: {
            name: 'Font Awesome',
            type: 'fontawesome',
            categories: {
                'solid': [
                    'fa-solid fa-heart', 'fa-solid fa-star', 'fa-solid fa-user', 'fa-solid fa-home',
                    'fa-solid fa-search', 'fa-solid fa-envelope', 'fa-solid fa-phone', 'fa-solid fa-calendar',
                    'fa-solid fa-camera', 'fa-solid fa-image', 'fa-solid fa-video', 'fa-solid fa-music',
                    'fa-solid fa-book', 'fa-solid fa-bookmark', 'fa-solid fa-folder', 'fa-solid fa-file',
                    'fa-solid fa-chart-bar', 'fa-solid fa-chart-line', 'fa-solid fa-chart-pie', 'fa-solid fa-database',
                    'fa-solid fa-lock', 'fa-solid fa-unlock', 'fa-solid fa-key', 'fa-solid fa-shield-halved',
                    'fa-solid fa-bell', 'fa-solid fa-flag', 'fa-solid fa-tag', 'fa-solid fa-tags',
                    'fa-solid fa-shopping-cart', 'fa-solid fa-credit-card', 'fa-solid fa-gift', 'fa-solid fa-trophy',
                    'fa-solid fa-location-dot', 'fa-solid fa-map', 'fa-solid fa-compass', 'fa-solid fa-globe',
                    'fa-solid fa-cloud', 'fa-solid fa-cloud-arrow-down', 'fa-solid fa-cloud-arrow-up', 'fa-solid fa-download',
                    'fa-solid fa-upload', 'fa-solid fa-share-nodes', 'fa-solid fa-link', 'fa-solid fa-paperclip',
                    'fa-solid fa-check', 'fa-solid fa-xmark', 'fa-solid fa-circle-check', 'fa-solid fa-circle-xmark',
                    'fa-solid fa-circle-plus', 'fa-solid fa-circle-minus', 'fa-solid fa-square-plus', 'fa-solid fa-square-minus',
                    'fa-solid fa-play', 'fa-solid fa-pause', 'fa-solid fa-stop', 'fa-solid fa-forward',
                    'fa-solid fa-backward', 'fa-solid fa-volume-high', 'fa-solid fa-volume-low', 'fa-solid fa-volume-xmark'
                ],
                'regular': [
                    'fa-regular fa-heart', 'fa-regular fa-star', 'fa-regular fa-user', 'fa-regular fa-envelope',
                    'fa-regular fa-calendar', 'fa-regular fa-bookmark', 'fa-regular fa-folder', 'fa-regular fa-file',
                    'fa-regular fa-image', 'fa-regular fa-bell', 'fa-regular fa-flag', 'fa-regular fa-comment',
                    'fa-regular fa-circle-check', 'fa-regular fa-circle-xmark', 'fa-regular fa-square-check', 'fa-regular fa-square',
                    'fa-regular fa-circle', 'fa-regular fa-copy', 'fa-regular fa-eye', 'fa-regular fa-eye-slash',
                    'fa-regular fa-thumbs-up', 'fa-regular fa-thumbs-down', 'fa-regular fa-clock', 'fa-regular fa-hourglass'
                ],
                'brands': [
                    'fa-brands fa-wordpress', 'fa-brands fa-facebook', 'fa-brands fa-twitter', 'fa-brands fa-instagram',
                    'fa-brands fa-youtube', 'fa-brands fa-linkedin', 'fa-brands fa-github', 'fa-brands fa-gitlab',
                    'fa-brands fa-stack-overflow', 'fa-brands fa-reddit', 'fa-brands fa-discord', 'fa-brands fa-slack',
                    'fa-brands fa-whatsapp', 'fa-brands fa-telegram', 'fa-brands fa-pinterest', 'fa-brands fa-tiktok',
                    'fa-brands fa-snapchat', 'fa-brands fa-amazon', 'fa-brands fa-google', 'fa-brands fa-apple',
                    'fa-brands fa-microsoft', 'fa-brands fa-windows', 'fa-brands fa-android', 'fa-brands fa-chrome'
                ]
            }
        },
        material: {
            name: 'Material Icons',
            type: 'material',
            categories: {
                'action': [
                    'home', 'search', 'settings', 'account_circle', 'shopping_cart', 'favorite', 'delete',
                    'visibility', 'visibility_off', 'check_circle', 'cancel', 'info', 'help', 'lock', 'lock_open',
                    'stars', 'grade', 'bookmark', 'bookmark_border', 'dashboard', 'trending_up', 'trending_down'
                ],
                'content': [
                    'add', 'remove', 'add_circle', 'remove_circle', 'content_copy', 'content_cut', 'content_paste',
                    'create', 'save', 'send', 'mail', 'inbox', 'drafts', 'archive', 'unarchive',
                    'flag', 'report', 'block', 'clear', 'undo', 'redo'
                ],
                'communication': [
                    'call', 'email', 'message', 'chat', 'forum', 'textsms', 'comment', 'contact_mail',
                    'contact_phone', 'contacts', 'business', 'location_on', 'phone', 'phonelink'
                ],
                'file': [
                    'cloud', 'cloud_download', 'cloud_upload', 'folder', 'folder_open', 'insert_drive_file',
                    'attachment', 'link', 'file_download', 'file_upload'
                ],
                'navigation': [
                    'menu', 'close', 'arrow_back', 'arrow_forward', 'arrow_upward', 'arrow_downward',
                    'expand_more', 'expand_less', 'chevron_right', 'chevron_left', 'first_page', 'last_page',
                    'more_vert', 'more_horiz', 'refresh', 'fullscreen', 'fullscreen_exit'
                ]
            }
        },
        lucide: {
            name: 'Lucide',
            type: 'lucide',
            categories: {
                'general': [
                    'home', 'search', 'settings', 'user', 'users', 'heart', 'star', 'bookmark',
                    'bell', 'mail', 'message-square', 'phone', 'calendar', 'clock', 'map-pin', 'globe',
                    'image', 'video', 'music', 'file', 'folder', 'download', 'upload', 'share-2',
                    'link', 'copy', 'check', 'x', 'plus', 'minus', 'edit', 'trash-2',
                    'eye', 'eye-off', 'lock', 'unlock', 'shield', 'alert-circle', 'info',
                    'zap', 'trending-up', 'trending-down', 'activity', 'bar-chart-2', 'pie-chart'
                ],
                'arrows': [
                    'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right', 'chevron-up', 'chevron-down',
                    'chevron-left', 'chevron-right', 'move', 'maximize', 'minimize', 'expand', 'compress'
                ],
                'media': [
                    'play', 'pause', 'stop', 'skip-forward', 'skip-back', 'fast-forward', 'rewind',
                    'volume-2', 'volume-1', 'volume-x', 'mic', 'mic-off', 'camera', 'video'
                ]
            }
        }
    };

    let currentLibrary = 'dashicons';
    let currentCategory = 'all';
    let selectedIcon = null;

    // Initialize Icon Picker
    function initIconPicker() {
        // Open Icon Picker
        $('.cbd-open-icon-picker').on('click', function(e) {
            e.preventDefault();
            const $modal = $('.cbd-icon-picker-modal');

            // Parse current icon value
            const currentValue = $('#icon_value').val();
            selectedIcon = parseIconValue(currentValue);

            // Reset to first library
            switchLibrary('dashicons');

            $modal.show();
            $('body').addClass('modal-open');
        });

        // Close Icon Picker
        $('.cbd-close-icon-picker, .cbd-icon-picker-backdrop').on('click', function(e) {
            if (e.target === this) {
                $('.cbd-icon-picker-modal').hide();
                $('body').removeClass('modal-open');
            }
        });

        // Library Tab Switching
        $('.cbd-library-tab').on('click', function() {
            const library = $(this).data('library');
            switchLibrary(library);
        });

        // Category Switching
        $(document).on('click', '.cbd-icon-category', function() {
            $('.cbd-icon-category').removeClass('active');
            $(this).addClass('active');
            currentCategory = $(this).data('category');
            populateIconGrid();
        });

        // Search
        $('.cbd-icon-search-input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterIcons(searchTerm);
        });

        // Icon Selection
        $(document).on('click', '.cbd-icon-item', function() {
            $('.cbd-icon-item').removeClass('selected');
            $(this).addClass('selected');
            selectedIcon = {
                type: currentLibrary,
                value: $(this).data('icon')
            };
        });

        // Confirm Selection
        $('.cbd-select-icon').on('click', function() {
            if (selectedIcon) {
                saveIconSelection(selectedIcon);
                $('.cbd-icon-picker-modal').hide();
                $('body').removeClass('modal-open');
            }
        });
    }

    // Switch Library Tab
    function switchLibrary(library) {
        currentLibrary = library;
        currentCategory = 'all';

        // Update active tab
        $('.cbd-library-tab').removeClass('active');
        $(`.cbd-library-tab[data-library="${library}"]`).addClass('active');

        // Show/hide emoji picker
        if (library === 'emoji') {
            $('.cbd-icon-search').hide();
            $('.cbd-icon-categories').hide();
            $('.cbd-icon-grid').hide();
            $('.cbd-emoji-picker-container').show();

            // Initialize emoji picker
            initEmojiPicker();
        } else {
            $('.cbd-icon-search').show();
            $('.cbd-icon-categories').show();
            $('.cbd-icon-grid').show();
            $('.cbd-emoji-picker-container').hide();

            // Populate categories
            populateCategories();
            populateIconGrid();
        }
    }

    // Initialize Emoji Picker
    function initEmojiPicker() {
        const emojiPicker = document.querySelector('emoji-picker');

        if (!emojiPicker) {
            console.error('CBD: emoji-picker element not found');
            return;
        }

        // Remove old event listener if exists
        const oldListener = emojiPicker._cbdEmojiListener;
        if (oldListener) {
            emojiPicker.removeEventListener('emoji-click', oldListener);
        }

        // Create new event listener
        const newListener = function(event) {
            selectedIcon = {
                type: 'emoji',
                value: event.detail.unicode
            };

            // Show selected emoji in grid
            $('.cbd-icon-grid').empty().append(
                `<div class="cbd-icon-item selected" style="font-size: 48px; padding: 20px; text-align: center;">${event.detail.unicode}</div>`
            );

            console.log('CBD: Emoji selected:', event.detail.unicode);
        };

        // Store listener reference for cleanup
        emojiPicker._cbdEmojiListener = newListener;

        // Add event listener
        emojiPicker.addEventListener('emoji-click', newListener);
    }

    // Populate Category Buttons
    function populateCategories() {
        const $categories = $('.cbd-icon-categories');
        $categories.empty();

        if (!iconLibraries[currentLibrary]) return;

        const categories = iconLibraries[currentLibrary].categories;

        // Add "All" button
        $categories.append(`<button type="button" class="cbd-icon-category active" data-category="all">Alle</button>`);

        // Add category buttons
        Object.keys(categories).forEach(category => {
            const label = category.charAt(0).toUpperCase() + category.slice(1);
            $categories.append(`<button type="button" class="cbd-icon-category" data-category="${category}">${label}</button>`);
        });
    }

    // Populate Icon Grid
    function populateIconGrid() {
        const $grid = $('.cbd-icon-grid');
        $grid.empty();

        if (!iconLibraries[currentLibrary]) return;

        const library = iconLibraries[currentLibrary];
        let icons = [];

        // Get icons from selected category
        if (currentCategory === 'all') {
            Object.values(library.categories).forEach(categoryIcons => {
                icons = icons.concat(categoryIcons);
            });
        } else {
            icons = library.categories[currentCategory] || [];
        }

        // Render icons based on library type
        icons.forEach(icon => {
            const $item = createIconElement(icon, library.type);
            $grid.append($item);
        });
    }

    // Create Icon Element
    function createIconElement(icon, type) {
        const $item = $('<div class="cbd-icon-item"></div>');
        $item.attr('data-icon', icon);
        $item.attr('title', icon);

        let iconHTML = '';
        switch (type) {
            case 'dashicons':
                const dashiconClass = icon.startsWith('dashicons-') ? icon : `dashicons-${icon}`;
                iconHTML = `<span class="dashicons ${dashiconClass}"></span>`;
                $item.attr('data-icon', dashiconClass);
                break;

            case 'fontawesome':
                iconHTML = `<i class="${icon}"></i>`;
                break;

            case 'material':
                iconHTML = `<span class="material-icons">${icon}</span>`;
                break;

            case 'lucide':
                iconHTML = `<i class="lucide lucide-${icon}"></i>`;
                $item.attr('data-icon', icon);
                break;
        }

        $item.html(iconHTML);
        return $item;
    }

    // Filter Icons by Search Term
    function filterIcons(searchTerm) {
        if (!searchTerm) {
            $('.cbd-icon-item').show();
            return;
        }

        $('.cbd-icon-item').each(function() {
            const iconName = $(this).attr('data-icon').toLowerCase();
            if (iconName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    // Parse Icon Value (JSON or legacy)
    function parseIconValue(value) {
        if (!value) return null;

        try {
            const parsed = JSON.parse(value);
            if (parsed.type && parsed.value) {
                return parsed;
            }
        } catch (e) {
            // Legacy dashicons format
            if (value.startsWith('dashicons-')) {
                return {
                    type: 'dashicons',
                    value: value
                };
            }
        }

        return null;
    }

    // Save Icon Selection
    function saveIconSelection(iconData) {
        // Save as JSON
        const jsonValue = JSON.stringify(iconData);
        $('#icon_value').val(jsonValue);

        // Update preview
        const $selectedIcon = $('.cbd-selected-icon');
        $selectedIcon.find('.dashicons, .fa-solid, .fa-regular, .fa-brands, .material-icons, .lucide, .cbd-emoji-icon').remove();

        let iconHTML = '';
        switch (iconData.type) {
            case 'dashicons':
                iconHTML = `<span class="dashicons ${iconData.value}"></span>`;
                break;
            case 'fontawesome':
                iconHTML = `<i class="${iconData.value}"></i>`;
                break;
            case 'material':
                iconHTML = `<span class="material-icons">${iconData.value}</span>`;
                break;
            case 'lucide':
                iconHTML = `<i class="lucide lucide-${iconData.value}"></i>`;
                break;
            case 'emoji':
                iconHTML = `<span class="cbd-emoji-icon" style="font-size: 1.2em;">${iconData.value}</span>`;
                break;
        }

        $selectedIcon.prepend(iconHTML);
        $('.cbd-icon-name').text(`${iconData.type}: ${iconData.value}`);

        // Trigger live preview update if available
        if (typeof updateLivePreview === 'function') {
            updateLivePreview();
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initIconPicker();
    });

})(jQuery);
