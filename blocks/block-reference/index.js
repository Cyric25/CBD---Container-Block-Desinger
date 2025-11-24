import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl, Placeholder, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

registerBlockType('cbd/block-reference', {
	edit: ({ attributes, setAttributes }) => {
		const { targetBlockId, targetPostId, targetBlockTitle, targetPostTitle, linkText, showIcon } = attributes;
		const [cbdBlocks, setCbdBlocks] = useState([]);
		const [loading, setLoading] = useState(true);

		const blockProps = useBlockProps({
			className: 'cbd-block-reference-editor'
		});

		// Fetch all CBD blocks
		useEffect(() => {
			setLoading(true);
			apiFetch({ path: '/cbd/v1/blocks' })
				.then((blocks) => {
					setCbdBlocks(blocks);
					setLoading(false);
				})
				.catch((error) => {
					console.error('Error fetching CBD blocks:', error);
					setLoading(false);
				});
		}, []);

		// Create options for SelectControl
		const blockOptions = [
			{ label: __('-- Block auswählen --', 'container-block-designer'), value: '' },
			...cbdBlocks.map((block) => ({
				label: `${block.postTitle} → ${block.blockTitle}`,
				value: JSON.stringify({
					blockId: block.blockId,
					postId: block.postId,
					blockTitle: block.blockTitle,
					postTitle: block.postTitle
				})
			}))
		];

		const handleBlockSelection = (value) => {
			if (!value) {
				setAttributes({
					targetBlockId: '',
					targetPostId: 0,
					targetBlockTitle: '',
					targetPostTitle: '',
					linkText: ''
				});
				return;
			}

			const data = JSON.parse(value);
			setAttributes({
				targetBlockId: data.blockId,
				targetPostId: data.postId,
				targetBlockTitle: data.blockTitle,
				targetPostTitle: data.postTitle,
				linkText: linkText || `Gehe zu: ${data.blockTitle}`
			});
		};

		// Get current selected value
		const selectedValue = targetBlockId ? JSON.stringify({
			blockId: targetBlockId,
			postId: targetPostId,
			blockTitle: targetBlockTitle,
			postTitle: targetPostTitle
		}) : '';

		return (
			<>
				<InspectorControls>
					<PanelBody title={__('Block-Referenz Einstellungen', 'container-block-designer')}>
						<SelectControl
							label={__('Ziel-Block', 'container-block-designer')}
							value={selectedValue}
							options={blockOptions}
							onChange={handleBlockSelection}
							help={__('Wähle einen Container-Block als Ziel aus', 'container-block-designer')}
						/>

						{targetBlockId && (
							<>
								<TextControl
									label={__('Link-Text', 'container-block-designer')}
									value={linkText}
									onChange={(value) => setAttributes({ linkText: value })}
									help={__('Optionaler Text für den Link', 'container-block-designer')}
								/>

								<ToggleControl
									label={__('Icon anzeigen', 'container-block-designer')}
									checked={showIcon}
									onChange={(value) => setAttributes({ showIcon: value })}
								/>
							</>
						)}
					</PanelBody>
				</InspectorControls>

				<div {...blockProps}>
					{loading ? (
						<Placeholder
							icon="admin-links"
							label={__('Block-Referenz', 'container-block-designer')}
						>
							<Spinner />
							<p>{__('Lade Container-Blöcke...', 'container-block-designer')}</p>
						</Placeholder>
					) : !targetBlockId ? (
						<Placeholder
							icon="admin-links"
							label={__('Block-Referenz', 'container-block-designer')}
							instructions={__('Wähle einen Container-Block in den Einstellungen rechts aus.', 'container-block-designer')}
						>
							<p style={{ fontSize: '14px', color: '#666' }}>
								{cbdBlocks.length > 0
									? __(`${cbdBlocks.length} Container-Blöcke verfügbar`, 'container-block-designer')
									: __('Keine Container-Blöcke gefunden', 'container-block-designer')}
							</p>
						</Placeholder>
					) : (
						<div className="cbd-block-reference-preview">
							<div className="cbd-block-reference-preview-header">
								<span className="dashicons dashicons-admin-links"></span>
								<strong>{__('Block-Referenz:', 'container-block-designer')}</strong>
							</div>
							<div className="cbd-block-reference-preview-content">
								<p className="cbd-block-reference-preview-post">
									<strong>{__('Seite:', 'container-block-designer')}</strong> {targetPostTitle}
								</p>
								<p className="cbd-block-reference-preview-block">
									<strong>{__('Block:', 'container-block-designer')}</strong> {targetBlockTitle}
								</p>
								{linkText && (
									<div className="cbd-block-reference-preview-link">
										{showIcon && <span className="dashicons dashicons-arrow-right-alt2"></span>}
										<span>{linkText}</span>
									</div>
								)}
							</div>
						</div>
					)}
				</div>
			</>
		);
	},

	save: () => {
		// Server-side rendering
		return null;
	}
});
