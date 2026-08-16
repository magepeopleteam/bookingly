<?php
/**
 * Block editor surface for Bookingly sections.
 *
 * Every section becomes a dynamic block rendered server-side through the same
 * callback the PHP templates use, so the editor preview and the front end can
 * never drift apart. Controls are generated from the section schema by a single
 * generic script — there is no per-block JS and no build step.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map a schema field type to a block attribute type.
 *
 * @param string $type Field type.
 * @return string
 */
function bookingly_block_attribute_type( $type ) {
	switch ( $type ) {
		case 'repeater':
			return 'array';
		case 'toggle':
			return 'boolean';
		case 'inherit_select':
			return '';
		case 'number':
		case 'media':
			return 'number';
		default:
			return 'string';
	}
}

/**
 * Build the block attribute map for a section.
 *
 * @param array<string,mixed> $section Section definition.
 * @return array<string,array<string,mixed>>
 */
function bookingly_block_attributes( $section ) {
	$attributes = array(
		'className' => array( 'type' => 'string' ),
		'anchor'    => array( 'type' => 'string' ),
	);

	foreach ( $section['fields'] as $key => $field ) {
		$type = bookingly_block_attribute_type( $field['type'] );
		if ( 'inherit_select' === $field['type'] ) {
			$attributes[ $key ] = array(
				'default' => 'inherit',
				'enum'    => array( 'inherit', 'consent', 'immediate', true, false, 1, 0, 'yes', 'no', 'on', 'off', '1', '0', '' ),
			);
			continue;
		}

		$attributes[ $key ] = array(
			'type'    => $type,
			'default' => bookingly_block_attribute_default( $type, $field ),
		);
	}

	return $attributes;
}

/**
 * Default value for a block attribute.
 *
 * @param string              $type  Attribute type.
 * @param array<string,mixed> $field Field definition.
 * @return mixed
 */
function bookingly_block_attribute_default( $type, $field ) {
	switch ( $type ) {
		case 'boolean':
			return (bool) $field['default'];
		case 'array':
			// Empty means "inherit the Theme Options list".
			return array();
		case 'number':
			return 0;
		default:
			return '';
	}
}

/**
 * Register one dynamic block per section.
 */
function bookingly_register_section_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	foreach ( bookingly_sections() as $id => $section ) {
		register_block_type(
			'bookingly/' . str_replace( '_', '-', $id ),
			array(
				'api_version'     => 3,
				'title'           => $section['label'],
				'description'     => $section['description'],
				'category'        => 'bookingly',
				'icon'            => 'layout',
				'attributes'      => bookingly_block_attributes( $section ),
				'supports'        => array(
					'html'   => false,
					'anchor' => true,
					'align'  => array( 'wide', 'full' ),
				),
				'editor_script'   => 'bookingly-blocks',
				'editor_style'    => 'bookingly-blocks-editor',
				'style'           => 'bookingly-main',
				'render_callback' => static function ( $attributes ) use ( $id ) {
					$atts = is_array( $attributes ) ? $attributes : array();

					if ( ! empty( $atts['className'] ) ) {
						$atts['class'] = $atts['className'];
					}

					return bookingly_get_section_html( $id, $atts );
				},
			)
		);
	}
}
add_action( 'init', 'bookingly_register_section_blocks', 20 );

/**
 * Add a Bookingly block category.
 *
 * @param array<int,array<string,mixed>> $categories Existing categories.
 * @return array<int,array<string,mixed>>
 */
function bookingly_register_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'bookingly',
			'title' => __( 'Bookingly Sections', 'bookingly' ),
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'bookingly_register_block_category' );

/**
 * Register the generic editor script and hand it the whole schema.
 */
function bookingly_register_block_assets() {
	wp_register_script(
		'bookingly-blocks',
		BOOKINGLY_URI . '/assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
		BOOKINGLY_VERSION,
		true
	);

	wp_register_style(
		'bookingly-blocks-editor',
		BOOKINGLY_URI . '/assets/css/blocks-editor.css',
		array(),
		BOOKINGLY_VERSION
	);

	$schema = array();
	foreach ( bookingly_sections() as $id => $section ) {
		$schema[ 'bookingly/' . str_replace( '_', '-', $id ) ] = array(
			'label'       => $section['label'],
			'description' => $section['description'],
			'fields'      => array_map(
				static function ( $field ) {
					$out = array(
						'type'    => $field['type'],
						'label'   => $field['label'],
						'desc'    => $field['desc'],
						'choices' => $field['choices'],
						'min'     => $field['min'],
						'max'     => $field['max'],
					);

					if ( 'repeater' === $field['type'] ) {
						$out['fields'] = array_map(
							static function ( $sub ) {
								return array(
									'type'    => $sub['type'],
									'label'   => $sub['label'],
									'desc'    => $sub['desc'],
									'choices' => $sub['choices'],
									'min'     => $sub['min'],
									'max'     => $sub['max'],
								);
							},
							$field['fields']
						);
					}

					return $out;
				},
				$section['fields']
			),
		);
	}

	wp_localize_script(
		'bookingly-blocks',
		'bookinglyBlocks',
		array(
			'schema' => $schema,
			'i18n'   => array(
				'content'  => __( 'Content', 'bookingly' ),
				'layout'   => __( 'Layout', 'bookingly' ),
				'select'   => __( 'Select image', 'bookingly' ),
				'replace'  => __( 'Replace image', 'bookingly' ),
				'remove'   => __( 'Remove image', 'bookingly' ),
				'inherit'  => __( 'Leave empty to use the value from Theme Options.', 'bookingly' ),
				'addRow'   => __( 'Add item', 'bookingly' ),
				'removeRow' => __( 'Remove item', 'bookingly' ),
				'moveUp'   => __( 'Move up', 'bookingly' ),
				'moveDown' => __( 'Move down', 'bookingly' ),
				'repeaterInherit' => __( 'Currently showing the list from Theme Options. Add an item to override it here.', 'bookingly' ),
			),
		)
	);
}
add_action( 'init', 'bookingly_register_block_assets', 5 );
