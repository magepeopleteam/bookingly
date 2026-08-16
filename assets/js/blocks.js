/**
 * Bookingly section blocks — one generic editor for every section.
 *
 * Controls are generated from the schema PHP hands over in `bookinglyBlocks`, and
 * the preview is a ServerSideRender of the very same render callback the front
 * end uses. No JSX, no build step, no per-block duplication.
 */
(function (blocks, element, components, blockEditor, ServerSideRender, i18n) {
	'use strict';

	if (!window.bookinglyBlocks || !blocks || !element) {
		return;
	}

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var strings = window.bookinglyBlocks.i18n || {};

	var InspectorControls = blockEditor.InspectorControls;
	var MediaUpload = blockEditor.MediaUpload;
	var MediaUploadCheck = blockEditor.MediaUploadCheck;
	var useBlockProps = blockEditor.useBlockProps;

	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var Button = components.Button;
	var Placeholder = components.Placeholder;

	/**
	 * Build one control for a schema field.
	 */
	function buildControl(key, field, attributes, setAttributes) {
		var value = attributes[key];
		var onChange = function (next) {
			var payload = {};
			payload[key] = next;
			setAttributes(payload);
		};

		var help = field.desc || (field.type !== 'toggle' ? strings.inherit : '');

		switch (field.type) {
			case 'textarea':
				return el(TextareaControl, {
					key: key,
					label: field.label,
					help: help,
					value: value || '',
					onChange: onChange,
					__nextHasNoMarginBottom: true
				});

			case 'toggle':
				return el(ToggleControl, {
					key: key,
					label: field.label,
					help: field.desc || '',
					checked: !!value,
					onChange: onChange,
					__nextHasNoMarginBottom: true
				});

			case 'select':
				var options = Object.keys(field.choices || {}).map(function (choiceKey) {
					return { value: choiceKey, label: field.choices[choiceKey] };
				});
				return el(SelectControl, {
					key: key,
					label: field.label,
					help: field.desc || '',
					value: value || '',
					options: options,
					onChange: onChange,
					__nextHasNoMarginBottom: true
				});

			case 'inherit_select':
				var inheritOptions = Object.keys(field.choices || {}).map(function (choiceKey) {
					return { value: choiceKey, label: field.choices[choiceKey] };
				});
				var inheritValue = value;
				if (value === true || value === 1 || value === '1' || value === 'yes' || value === 'on') {
					inheritValue = 'consent';
				} else if (value === false || value === 0 || value === '0' || value === 'no' || value === 'off') {
					inheritValue = 'immediate';
				} else if (!value) {
					inheritValue = 'inherit';
				}
				return el(SelectControl, {
					key: key,
					label: field.label,
					help: field.desc || '',
					value: inheritValue,
					options: inheritOptions,
					onChange: onChange,
					__nextHasNoMarginBottom: true
				});

			case 'number':
				return el(RangeControl, {
					key: key,
					label: field.label,
					help: field.desc || '',
					value: value || field.min,
					min: field.min,
					max: field.max,
					allowReset: true,
					onChange: function (next) {
						onChange(typeof next === 'number' ? next : 0);
					},
					__nextHasNoMarginBottom: true
				});

			case 'media':
				return el(
					MediaUploadCheck,
					{ key: key },
					el(
						'div',
						{ className: 'bookingly-block-media' },
						el('p', { className: 'bookingly-block-media__label' }, field.label),
						el(MediaUpload, {
							allowedTypes: ['image'],
							value: value || 0,
							onSelect: function (media) {
								onChange(media && media.id ? media.id : 0);
							},
							render: function (open) {
								return el(
									'div',
									{ className: 'bookingly-block-media__actions' },
									el(
										Button,
										{ variant: 'secondary', onClick: open.open },
										value ? strings.replace : strings.select
									),
									value
										? el(
												Button,
												{
													variant: 'tertiary',
													isDestructive: true,
													onClick: function () {
														onChange(0);
													}
												},
												strings.remove
										  )
										: null
								);
							}
						}),
						el('p', { className: 'bookingly-block-media__help' }, strings.inherit)
					)
				);

			case 'repeater':
				return buildRepeater(key, field, value, onChange);

			default:
				return el(TextControl, {
					key: key,
					label: field.label,
					help: help,
					value: value || '',
					type: field.type === 'url' ? 'url' : 'text',
					onChange: onChange,
					__nextHasNoMarginBottom: true
				});
		}
	}

	/**
	 * Repeating rows: add, remove, reorder, and edit each row's own fields.
	 *
	 * An empty repeater means "inherit from Theme Options", so the first edit
	 * seeds the rows from whatever is currently rendering rather than starting
	 * the editor from a blank list.
	 */
	function buildRepeater(key, field, value, onChange) {
		var rows = Array.isArray(value) ? value : [];
		var subKeys = Object.keys(field.fields || {});

		function emptyRow() {
			var row = {};
			subKeys.forEach(function (sub) {
				row[sub] = field.fields[sub].type === 'media' ? 0 : '';
			});
			return row;
		}

		function update(nextRows) {
			onChange(nextRows);
		}

		function setCell(index, sub, next) {
			var copy = rows.map(function (row, i) {
				if (i !== index) {
					return row;
				}
				var updated = {};
				Object.keys(row).forEach(function (k) { updated[k] = row[k]; });
				updated[sub] = next;
				return updated;
			});
			update(copy);
		}

		function move(index, delta) {
			var target = index + delta;
			if (target < 0 || target >= rows.length) {
				return;
			}
			var copy = rows.slice();
			var moved = copy.splice(index, 1)[0];
			copy.splice(target, 0, moved);
			update(copy);
		}

		var children = [];

		if (!rows.length) {
			children.push(
				el(
					'p',
					{ key: 'inherit', className: 'bookingly-repeater__empty' },
					strings.repeaterInherit || ''
				)
			);
		}

		rows.forEach(function (row, index) {
			var cells = subKeys.map(function (sub) {
				return buildControl(
					sub,
					field.fields[sub],
					row,
					function (payload) { setCell(index, sub, payload[sub]); }
				);
			});

			children.push(
				el(
					'div',
					{ key: 'row-' + index, className: 'bookingly-repeater__row' },
					el(
						'div',
						{ className: 'bookingly-repeater__head' },
						el('strong', null, field.label + ' ' + (index + 1)),
						el(
							'div',
							{ className: 'bookingly-repeater__tools' },
							el(Button, {
								size: 'small', variant: 'tertiary', icon: 'arrow-up-alt2',
								label: strings.moveUp, disabled: index === 0,
								onClick: function () { move(index, -1); }
							}),
							el(Button, {
								size: 'small', variant: 'tertiary', icon: 'arrow-down-alt2',
								label: strings.moveDown, disabled: index === rows.length - 1,
								onClick: function () { move(index, 1); }
							}),
							el(Button, {
								size: 'small', variant: 'tertiary', icon: 'trash',
								label: strings.removeRow, isDestructive: true,
								onClick: function () {
									update(rows.filter(function (_, i) { return i !== index; }));
								}
							})
						)
					),
					cells
				)
			);
		});

		children.push(
			el(
				Button,
				{
					key: 'add',
					variant: 'secondary',
					disabled: rows.length >= field.max,
					onClick: function () { update(rows.concat([emptyRow()])); }
				},
				strings.addRow || 'Add'
			)
		);

		return el('div', { key: key, className: 'bookingly-repeater' }, children);
	}

	Object.keys(window.bookinglyBlocks.schema).forEach(function (name) {
		var definition = window.bookinglyBlocks.schema[name];
		var fieldKeys = Object.keys(definition.fields || {});

		blocks.registerBlockType(name, {
			edit: function (props) {
				var blockProps = useBlockProps ? useBlockProps() : {};

				var controls = fieldKeys.length
					? el(
							InspectorControls,
							{ key: 'inspector' },
							el(
								PanelBody,
								{ title: strings.content || __('Content'), initialOpen: true },
								fieldKeys.map(function (key) {
									return buildControl(key, definition.fields[key], props.attributes, props.setAttributes);
								})
							)
					  )
					: null;

				var preview = ServerSideRender
					? el(ServerSideRender, {
							block: name,
							attributes: props.attributes,
							EmptyResponsePlaceholder: function () {
								return el(
									Placeholder,
									{ label: definition.label },
									definition.description
								);
							}
					  })
					: el(Placeholder, { label: definition.label }, definition.description);

				return el(
					Fragment,
					null,
					controls,
					el('div', blockProps, el('div', { className: 'bookingly-block-preview' }, preview))
				);
			},

			// Dynamic block: markup always comes from the PHP render callback.
			save: function () {
				return null;
			}
		});
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.serverSideRender,
	window.wp.i18n
);
