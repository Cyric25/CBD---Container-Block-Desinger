<?php
/**
 * Test-Datei für Delete AJAX Handler
 * 
 * Speichere diese Datei als test-delete-ajax.php im Plugin-Hauptverzeichnis
 * und rufe sie auf über: /wp-admin/admin.php?page=cbd-test-delete
 */

// Füge Test-Seite zum Admin-Menü hinzu
add_action('admin_menu', function() {
    add_submenu_page(
        null, // Hidden page
        'CBD Delete Test',
        'CBD Delete Test',
        'manage_options',
        'cbd-test-delete',
        'cbd_test_delete_page'
    );
});

function cbd_test_delete_page() {
    global $wpdb;
    
    // Hole ersten Block für Test
    $test_block = $wpdb->get_row("SELECT * FROM " . CBD_TABLE_BLOCKS . " LIMIT 1");
    
    if (!$test_block) {
        echo '<div class="wrap"><h1>Kein Block zum Testen vorhanden</h1></div>';
        return;
    }
    
    $nonce = wp_create_nonce('cbd-admin');
    ?>
    <div class="wrap">
        <h1>Container Block Designer - Delete Test</h1>
        
        <div class="card">
            <h2>Test Block Info</h2>
            <p><strong>ID:</strong> <?php echo $test_block->id; ?></p>
            <p><strong>Name:</strong> <?php echo esc_html($test_block->name); ?></p>
            <p><strong>Slug:</strong> <?php echo esc_html($test_block->slug); ?></p>
        </div>
        
        <div class="card">
            <h2>1. Direct PHP Test</h2>
            <form method="post">
                <?php wp_nonce_field('cbd_delete_test', 'cbd_delete_nonce'); ?>
                <input type="hidden" name="test_delete_id" value="<?php echo $test_block->id; ?>">
                <button type="submit" class="button button-secondary">Test PHP Delete Function</button>
            </form>
            <?php
            if (isset($_POST['test_delete_id']) && wp_verify_nonce($_POST['cbd_delete_nonce'], 'cbd_delete_test')) {
                $test_id = intval($_POST['test_delete_id']);
                echo '<h3>PHP Delete Test Result:</h3>';
                echo '<pre>';
                
                // Test the delete
                $result = $wpdb->delete(
                    CBD_TABLE_BLOCKS,
                    ['id' => $test_id],
                    ['%d']
                );
                
                if ($result !== false) {
                    echo "✅ Delete successful! Rows affected: " . $result;
                } else {
                    echo "❌ Delete failed!";
                    echo "\nLast Error: " . $wpdb->last_error;
                }
                echo '</pre>';
            }
            ?>
        </div>
        
        <div class="card">
            <h2>2. AJAX Test</h2>
            <button type="button" id="test-ajax-delete" class="button button-primary" data-id="<?php echo $test_block->id; ?>">
                Test AJAX Delete
            </button>
            <div id="ajax-result" style="margin-top: 20px;"></div>
        </div>
        
        <div class="card">
            <h2>3. Manual AJAX Test</h2>
            <p>Öffne die Browser-Konsole (F12) und führe aus:</p>
            <pre>
jQuery.ajax({
    url: '<?php echo admin_url('admin-ajax.php'); ?>',
    type: 'POST',
    data: {
        action: 'cbd_delete_block',
        block_id: <?php echo $test_block->id; ?>,
        nonce: '<?php echo $nonce; ?>'
    },
    success: function(response) {
        console.log('Success:', response);
    },
    error: function(xhr, status, error) {
        console.log('Error:', error);
        console.log('Response:', xhr.responseText);
    }
});
            </pre>
        </div>
        
        <div class="card">
            <h2>4. Debug Info</h2>
            <pre>
AJAX URL: <?php echo admin_url('admin-ajax.php'); ?>
Nonce: <?php echo $nonce; ?>
Table Name: <?php echo CBD_TABLE_BLOCKS; ?>
Current User Can Manage: <?php echo current_user_can('manage_options') ? 'YES' : 'NO'; ?>

Registered AJAX Actions:
<?php
global $wp_filter;
$ajax_actions = [];
if (isset($wp_filter['wp_ajax_cbd_delete_block'])) {
    echo "✅ wp_ajax_cbd_delete_block is registered\n";
    $callbacks = $wp_filter['wp_ajax_cbd_delete_block']->callbacks;
    foreach ($callbacks as $priority => $functions) {
        foreach ($functions as $function) {
            echo "   Priority $priority: ";
            if (is_array($function['function'])) {
                echo get_class($function['function'][0]) . '::' . $function['function'][1];
            } else {
                echo $function['function'];
            }
            echo "\n";
        }
    }
} else {
    echo "❌ wp_ajax_cbd_delete_block is NOT registered!\n";
}

// Check if ajax-handlers.php is loaded
if (function_exists('cbd_ajax_delete_block')) {
    echo "✅ cbd_ajax_delete_block function exists\n";
} else {
    echo "❌ cbd_ajax_delete_block function does NOT exist!\n";
}

// Check if includes are loaded
$included_files = get_included_files();
$ajax_handler_loaded = false;
foreach ($included_files as $file) {
    if (strpos($file, 'ajax-handlers.php') !== false) {
        echo "✅ ajax-handlers.php is loaded: " . $file . "\n";
        $ajax_handler_loaded = true;
        break;
    }
}
if (!$ajax_handler_loaded) {
    echo "❌ ajax-handlers.php is NOT loaded!\n";
}
?>
            </pre>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-ajax-delete').on('click', function() {
            const blockId = $(this).data('id');
            const $result = $('#ajax-result');
            
            $result.html('<p>Sending AJAX request...</p>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cbd_delete_block',
                    block_id: blockId,
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    console.log('AJAX Success:', response);
                    $result.html('<div class="notice notice-success"><p>✅ ' + JSON.stringify(response) + '</p></div>');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $result.html('<div class="notice notice-error"><p>❌ Error: ' + error + '<br>Response: <pre>' + xhr.responseText + '</pre></p></div>');
                }
            });
        });
    });
    </script>
    <?php
}

// Alternative: Direkter Test ohne AJAX
add_action('admin_init', function() {
    if (isset($_GET['cbd_test_direct_delete']) && current_user_can('manage_options')) {
        global $wpdb;
        $block_id = intval($_GET['cbd_test_direct_delete']);
        
        echo '<pre>';
        echo "Testing direct delete for block ID: $block_id\n";
        
        $result = $wpdb->delete(
            CBD_TABLE_BLOCKS,
            ['id' => $block_id],
            ['%d']
        );
        
        if ($result !== false) {
            echo "✅ Delete successful!";
        } else {
            echo "❌ Delete failed: " . $wpdb->last_error;
        }
        echo '</pre>';
        exit;
    }
});