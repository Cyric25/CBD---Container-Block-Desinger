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

$target_stable_id = isset($attributes['targetStableId']) ? (string) $attributes['targetStableId'] : '';
$target_anchor = isset($attributes['targetAnchor']) ? (string) $attributes['targetAnchor'] : '';
$target_block_id = isset($attributes['targetBlockId']) ? (string) $attributes['targetBlockId'] : '';
$target_post_id = isset($attributes['targetPostId']) ? (int) $attributes['targetPostId'] : 0;
$target_block_title = isset($attributes['targetBlockTitle']) ? (string) $attributes['targetBlockTitle'] : '';
$target_post_title = isset($attributes['targetPostTitle']) ? (string) $attributes['targetPostTitle'] : '';
$link_text = isset($attributes['linkText']) ? (string) $attributes['linkText'] : '';
$show_icon = isset($attributes['showIcon']) ? (bool) $attributes['showIcon'] : true;

// Anzeigemodus. Zulaessig sind ausschliesslich 'modal' und 'link'; alles
// andere faellt auf 'modal' zurueck (Vorgabe aus block.json). Der Wert wird
// als data-Attribut ausgegeben und steuert view.js.
$display_mode = isset($attributes['displayMode']) ? (string) $attributes['displayMode'] : 'modal';
if ('link' !== $display_mode && 'modal' !== $display_mode) {
	$display_mode = 'modal';
}

// Sprungmarke bestimmen:
// 1. der echte HTML-Anker des Ziels (liegt am inneren .cbd-container-block)
// 2. ALTBESTAND: frueher wurde targetBlockId als Anker verwendet
// Ohne Anker entsteht bewusst KEIN Fragment - das Ziel wird ueber
// data-target-stable-id bzw. den Parameter cbd-ref gefunden.
$fragment = '';
if ('' !== $target_anchor) {
	$fragment = $target_anchor;
} elseif ('' === $target_stable_id && '' !== $target_block_id) {
	$fragment = $target_block_id;
}

// Ohne Ziel wird nichts ausgegeben.
if (empty($target_post_id) || ('' === $target_stable_id && '' === $fragment)) {
	return;
}

// Generate link text if not provided
if ('' === $link_text) {
	$link_text = sprintf(__('Gehe zu: %s', 'container-block-designer'), $target_block_title);
}

// Get target post URL
$target_url = get_permalink($target_post_id);
if (!$target_url) {
	return;
}

// SELBE REGEL WIE CBD_Inline_Reference::ziel_href() (includes/class-cbd-inline-reference.php) -- beide Fassungen zusammen aendern, sonst laufen sie auseinander (AP-3.fix5, Befund S7).
// Ohne Anker traegt die URL den Bezeichner als Parameter, damit auch der
// Aufruf einer ANDEREN Seite beim Zielblock landet (view.js wertet ihn beim
// Laden aus).
if ('' === $fragment && '' !== $target_stable_id) {
	$full_url = add_query_arg('cbd-ref', $target_stable_id, $target_url);
} else {
	$full_url = $target_url;
	if ('' !== $fragment) {
		$full_url .= '#' . $fragment;
	}
}

// Get current post ID to check if same page
$current_post_id = (int) get_the_ID();
$is_same_page = ($current_post_id === $target_post_id);

// Block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes(array(
	'class' => 'cbd-block-reference-wrapper cbd-block-reference-mode-' . $display_mode,
	'data-same-page' => $is_same_page ? 'true' : 'false',
	'data-display-mode' => $display_mode,
));

// Der Verweis bleibt in BEIDEN Modi ein <a> mit vollstaendiger Ziel-URL, nie
// ein <button>: Ohne JavaScript fuehrt er dann immer noch zur Zielseite
// (fortschreitende Verbesserung). Das Modal entsteht erst durch
// preventDefault() in view.js.
$titel_text = 'modal' === $display_mode
	? sprintf(__('Block anzeigen: %s', 'container-block-designer'), $target_block_title)
	: sprintf(__('Gehe zu Block: %s', 'container-block-designer'), $target_block_title);

?>
<div <?php echo $wrapper_attributes; ?>>
	<a
		href="<?php echo esc_url($full_url); ?>"
		class="cbd-block-reference-link"
		data-target-stable-id="<?php echo esc_attr($target_stable_id); ?>"
		data-target-anchor="<?php echo esc_attr($fragment); ?>"
		data-target-post="<?php echo esc_attr((string) $target_post_id); ?>"
		data-same-page="<?php echo $is_same_page ? 'true' : 'false'; ?>"
		data-display-mode="<?php echo esc_attr($display_mode); ?>"
		data-target-title="<?php echo esc_attr($target_block_title); ?>"
		<?php if ('modal' === $display_mode) : ?>aria-haspopup="dialog"<?php endif; ?>
		title="<?php echo esc_attr($titel_text); ?>"
	>
		<div class="cbd-block-reference-content">
			<?php if ($show_icon) : ?>
				<span class="cbd-block-reference-icon" aria-hidden="true">🔗</span>
			<?php endif; ?>

			<div class="cbd-block-reference-text">
				<span class="cbd-block-reference-label"><?php echo esc_html($link_text); ?></span>
				<!-- IMMER die Seite anzeigen -->
				<span class="cbd-block-reference-page">
					<?php echo esc_html($target_post_title); ?>
				</span>
			</div>

			<span class="cbd-block-reference-arrow" aria-hidden="true">→</span>
		</div>
	</a>
</div>
