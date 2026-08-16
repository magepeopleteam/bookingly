<?php
/**
 * Generic Divi module for Bookingly sections.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * Base module: schema in, shared section markup out.
 */
abstract class Bookingly_Divi_Section_Module extends ET_Builder_Module {

	/**
	 * Divi module slug.
	 *
	 * @var string
	 */
	public $slug = '';

	/**
	 * Visual builder support level.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * Section id this module renders.
	 *
	 * @return string
	 */
	abstract protected function section_id();

	/**
	 * Section definition with a safe fallback.
	 *
	 * @return array<string,mixed>
	 */
	protected function section() {
		$section = bookingly_get_section( $this->section_id() );

		return $section ? $section : array(
			'label'       => __( 'Bookingly Section', 'bookingly' ),
			'description' => '',
			'fields'      => array(),
		);
	}

	/**
	 * Divi init: name, slug and settings toggles.
	 */
	public function init() {
		$section = $this->section();

		$this->name = sprintf(
			/* translators: %s: section name */
			__( 'Bookingly %s', 'bookingly' ),
			$section['label']
		);

		$this->slug = 'et_pb_bookingly_' . $this->section_id();

		$this->settings_modal_toggles = array(
			'general' => array(
				'toggles' => array(
					'content' => __( 'Content', 'bookingly' ),
				),
			),
		);
	}

	/**
	 * Build Divi fields from the section schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields() {
		$section = $this->section();
		$fields  = array();

		foreach ( $section['fields'] as $key => $field ) {
			$base = array(
				'label'       => $field['label'],
				'description' => $field['desc'] ? $field['desc'] : __( 'Leave empty to use the value from Theme Options.', 'bookingly' ),
				'toggle_slug' => 'content',
				'tab_slug'    => 'general',
			);

			switch ( $field['type'] ) {
				case 'repeater':
					/*
					 * Divi has no first-class repeater for third-party modules,
					 * so rows are edited as JSON. Left empty, the section falls
					 * back to the Theme Options list, which is what most Divi
					 * users will want.
					 */
					$base['type']            = 'textarea';
					$base['option_category'] = 'basic_option';
					$base['description']     = __( 'Optional JSON list of rows. Leave empty to use the list from Theme Options.', 'bookingly' );
					break;

				case 'textarea':
					$base['type']            = 'textarea';
					$base['option_category'] = 'basic_option';
					break;

				case 'toggle':
					$base['type']            = 'yes_no_button';
					$base['option_category'] = 'configuration';
					$base['options']         = array(
						'off' => __( 'No', 'bookingly' ),
						'on'  => __( 'Yes', 'bookingly' ),
					);
					$base['default']         = $field['default'] ? 'on' : 'off';
					$base['description']     = $field['desc'];
					break;

				case 'select':
				case 'inherit_select':
					$base['type']            = 'select';
					$base['option_category'] = 'configuration';
					$base['options']         = $field['choices'];
					$base['default']         = (string) $field['default'];
					break;

				case 'number':
					$base['type']            = 'range';
					$base['option_category'] = 'configuration';
					$base['range_settings']  = array(
						'min'  => $field['min'],
						'max'  => $field['max'],
						'step' => 1,
					);
					$base['unitless']        = true;
					break;

				case 'media':
					$base['type']            = 'upload';
					$base['option_category'] = 'basic_option';
					$base['upload_button_text'] = __( 'Choose image', 'bookingly' );
					$base['choose_text']        = __( 'Choose an image', 'bookingly' );
					$base['update_text']        = __( 'Set image', 'bookingly' );
					$base['data_type']          = 'image';
					break;

				default:
					$base['type']            = 'text';
					$base['option_category'] = 'basic_option';
					break;
			}

			$fields[ $key ] = $base;
		}

		return $fields;
	}

	/**
	 * Divi hands media fields back as URLs; the renderer wants attachment ids.
	 *
	 * @param array<string,mixed> $props Module props.
	 * @return array<string,mixed>
	 */
	protected function normalize_props( $props ) {
		$section = $this->section();
		$atts    = array();

		foreach ( $section['fields'] as $key => $field ) {
			$value = isset( $props[ $key ] ) ? $props[ $key ] : '';

			switch ( $field['type'] ) {
				case 'media':
					$id = $value ? attachment_url_to_postid( $value ) : 0;
					$atts[ $key ] = $id ? (int) $id : '';
					break;

				case 'toggle':
					// Divi always sends on/off, so treat it as authoritative.
					$atts[ $key ] = ( 'on' === $value ) ? 1 : 0;
					break;

				default:
					$atts[ $key ] = $value;
					break;
			}
		}

		return $atts;
	}

	/**
	 * Render through the shared section callback.
	 *
	 * @param array<string,mixed> $attrs       Unprocessed attributes.
	 * @param string|null         $content     Enclosed content.
	 * @param string              $render_slug Module slug.
	 * @return string
	 */
	public function render( $attrs, $content = null, $render_slug = '' ) {
		unset( $attrs, $content, $render_slug );

		return bookingly_get_section_html( $this->section_id(), $this->normalize_props( $this->props ) );
	}
}

/*
 * Concrete modules. Divi keys its registry on the class, so each section needs
 * its own; these carry nothing but their id.
 */

/** Hero module. */
class Bookingly_Divi_Hero extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'hero'; }
}

/** Trust strip module. */
class Bookingly_Divi_Trust_Strip extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'trust_strip'; }
}

/** Services module. */
class Bookingly_Divi_Services extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'services'; }
}

/** How it works module. */
class Bookingly_Divi_How_It_Works extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'how_it_works'; }
}

/** About module. */
class Bookingly_Divi_About extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'about'; }
}

/** Testimonials module. */
class Bookingly_Divi_Testimonials extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'testimonials'; }
}

/** CTA band module. */
class Bookingly_Divi_Cta_Band extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'cta_band'; }
}

/** Stats module. */
class Bookingly_Divi_Stats extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'stats'; }
}

/** Value cards module. */
class Bookingly_Divi_Value_Props extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'value_props'; }
}

/** Team module. */
class Bookingly_Divi_Team extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'team'; }
}

/** Story module. */
class Bookingly_Divi_Story extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'story'; }
}

/** Page hero module. */
class Bookingly_Divi_Page_Hero extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'page_hero'; }
}

/** Contact cards module. */
class Bookingly_Divi_Contact_Cards extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'contact_cards'; }
}

/** Contact form module. */
class Bookingly_Divi_Contact_Form extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'contact_form'; }
}

/** Business hours module. */
class Bookingly_Divi_Business_Hours extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'business_hours'; }
}

/** Map module. */
class Bookingly_Divi_Map extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'map'; }
}

/** Blog posts module. */
class Bookingly_Divi_Blog_Posts extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'blog_posts'; }
}

/** Follow band module. */
class Bookingly_Divi_Newsletter extends Bookingly_Divi_Section_Module {
	/** @return string */
	protected function section_id() { return 'newsletter'; }
}
