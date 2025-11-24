<?php
/**
 * Block-Referenz Render Template
 *
 * @package ContainerBlockDesigner
 * @var array $attributes Block attributes
 * @var string $content Block content
 * @var WP_Block $block Block instance
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

$target_block_id = $attributes['targetBlockId'] ?? '';
$target_post_id = $attributes['targetPostId'] ?? 0;
$target_block_title = $attributes['targetBlockTitle'] ?? '';
$target_post_title = $attributes['targetPostTitle'] ?? '';
$link_text = $attributes['linkText'] ?? '';
$show_icon = $attributes['showIcon'] ?? true;

// Don't render if no target is selected
if (empty($target_block_id) || empty($target_post_id)) {
	return;
}

// Generate link text if not provided
if (empty($link_text)) {
	$link_text = sprintf(__('Gehe zu: %s', 'container-block-designer'), $target_block_title);
}

// Get target post URL
$target_url = get_permalink($target_post_id);
if (!$target_url) {
	return;
}

// Build full URL with anchor
$full_url = $target_url . '#' . $target_block_id;

// Get current post ID to check if same page
$current_post_id = get_the_ID();
$is_same_page = ($current_post_id === $target_post_id);

// Block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'cbd-block-reference-wrapper',
	'data-same-page' => $is_same_page ? 'true' : 'false'
]);

?>
<div <?php echo $wrapper_attributes; ?>>
	<a
		href="<?php echo esc_url($full_url); ?>"
		class="cbd-block-reference-link"
		data-target-block="<?php echo esc_attr($target_block_id); ?>"
		data-same-page="<?php echo $is_same_page ? 'true' : 'false'; ?>"
		title="<?php echo esc_attr(sprintf(__('Gehe zu Block: %s', 'container-block-designer'), $target_block_title)); ?>"
	>
		<div class="cbd-block-reference-content">
			<?php if ($show_icon): ?>
				<span class="cbd-block-reference-icon">🔗</span>
			<?php endif; ?>

			<div class="cbd-block-reference-text">
				<span class="cbd-block-reference-label"><?php echo esc_html($link_text); ?></span>
				<!-- IMMER die Seite anzeigen -->
				<span class="cbd-block-reference-page">
					<?php echo esc_html($target_post_title); ?>
				</span>
			</div>

			<span class="cbd-block-reference-arrow">→</span>
		</div>
	</a>
</div>
