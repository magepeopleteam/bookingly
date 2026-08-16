<?php
/**
 * Generic Elementor widget for Bookingly sections.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Base widget: builds controls from a section schema and renders through it.
 */
abstract class Bookingly_Elementor_Section_Widget extends \Elementor\Widget_Base {

	/**
	 * Section id this widget renders.
	 *
	 * @return string
	 */
	abstract protected function section_id();

	/**
	 * Section definition, or an empty shell when the section was filtered out.
	 *
	 * @return array<string,mixed>
	 */
	protected function section() {
		$section = bookingly_get_section( $this->section_id() );

		return $section ? $section : array(
			'label'       => __( 'Bookingly Section', 'bookingly' ),
			'description' => '',
			'icon'        => 'eicon-theme-style',
			'fields'      => array(),
		);
	}

	/**
	 * Widget machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bookingly-' . str_replace( '_', '-', $this->section_id() );
	}

	/**
	 * Widget label.
	 *
	 * @return string
	 */
	public function get_title() {
		$section = $this->section();
		return $section['label'];
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$section = $this->section();
		return ! empty( $section['icon'] ) ? $section['icon'] : 'eicon-theme-style';
	}

	/**
	 * Panel category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'bookingly' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'bookingly', 'booking', 'service', str_replace( '_', ' ', $this->section_id() ) );
	}

	/**
	 * Bookingly sections are styled by the theme stylesheet, already enqueued.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'bookingly-main' );
	}

	/**
	 * Build controls from the section schema.
	 */
	protected function register_controls() {
		$section = $this->section();

		$this->start_controls_section(
			'bookingly_content',
			array(
				'label' => __( 'Content', 'bookingly' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		if ( ! empty( $section['description'] ) ) {
			$this->add_control(
				'bookingly_intro',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html( $section['description'] ),
					'content_classes' => 'elementor-descriptor',
				)
			);
		}

		if ( empty( $section['fields'] ) ) {
			$this->add_control(
				'bookingly_managed',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'This section has no settings. Its content is managed in Bookingly → Theme Options.', 'bookingly' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
		}

		foreach ( $section['fields'] as $key => $field ) {
			if ( 'repeater' === $field['type'] ) {
				$this->add_repeater_control( $key, $field );
				continue;
			}

			$this->add_control( $key, $this->control_args( $key, $field ) );
		}

		$this->end_controls_section();
	}

	/**
	 * Translate one schema field into Elementor control arguments.
	 *
	 * @param string              $key   Field key.
	 * @param array<string,mixed> $field Field definition.
	 * @return array<string,mixed>
	 */
	protected function control_args( $key, $field ) {
		unset( $key );

		$args = array(
			'label'       => $field['label'],
			'description' => $field['desc'] ? $field['desc'] : __( 'Leave empty to use the value from Theme Options.', 'bookingly' ),
			'label_block' => true,
		);

		switch ( $field['type'] ) {
			case 'textarea':
				$args['type'] = \Elementor\Controls_Manager::TEXTAREA;
				$args['rows'] = 4;
				break;

			case 'toggle':
				$args['type']        = \Elementor\Controls_Manager::SWITCHER;
				$args['label_on']    = __( 'Show', 'bookingly' );
				$args['label_off']   = __( 'Hide', 'bookingly' );
				$args['default']     = $field['default'] ? 'yes' : '';
				$args['return_value'] = 'yes';
				$args['description'] = $field['desc'];
				break;

			case 'select':
			case 'inherit_select':
				$args['type']    = \Elementor\Controls_Manager::SELECT;
				$args['options'] = $field['choices'];
				$args['default'] = (string) $field['default'];
				break;

			case 'number':
				$args['type'] = \Elementor\Controls_Manager::NUMBER;
				$args['min']  = $field['min'];
				$args['max']  = $field['max'];
				break;

			case 'media':
				$args['type'] = \Elementor\Controls_Manager::MEDIA;
				break;

			case 'url':
				$args['type']    = \Elementor\Controls_Manager::URL;
				$args['options'] = array( 'url' );
				break;

			case 'color':
				$args['type'] = \Elementor\Controls_Manager::COLOR;
				break;

			default:
				$args['type'] = \Elementor\Controls_Manager::TEXT;
				break;
		}

		return $args;
	}

	/**
	 * Build a native Elementor repeater from a schema repeater field.
	 *
	 * @param string              $key   Field key.
	 * @param array<string,mixed> $field Field definition.
	 */
	protected function add_repeater_control( $key, $field ) {
		$repeater = new \Elementor\Repeater();

		foreach ( $field['fields'] as $sub_key => $sub ) {
			$repeater->add_control( $sub_key, $this->control_args( $sub_key, $sub ) );
		}

		$title_field = '';
		foreach ( array( 'title', 'name', 'value', 'quote' ) as $candidate ) {
			if ( isset( $field['fields'][ $candidate ] ) ) {
				$title_field = '{{{ ' . $candidate . ' }}}';
				break;
			}
		}

		$this->add_control(
			$key,
			array(
				'label'       => $field['label'],
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(),
				'title_field' => $title_field,
				'description' => __( 'Leave empty to use the list from Theme Options.', 'bookingly' ),
			)
		);
	}

	/**
	 * Flatten Elementor's compound control values back to plain scalars.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @return array<string,mixed>
	 */
	protected function normalize_settings( $settings ) {
		$section = $this->section();
		$atts    = array();
		$raw_settings = $this->get_data( 'settings' );
		$raw_settings = is_array( $raw_settings ) ? $raw_settings : array();

		foreach ( $section['fields'] as $key => $field ) {
			$value = isset( $settings[ $key ] ) ? $settings[ $key ] : null;

			if ( 'repeater' === $field['type'] ) {
				// bookingly_resolve_repeater() handles Elementor's { id, url }
				// media shape and falls back to Theme Options when empty.
				$atts[ $key ] = is_array( $value ) ? $value : array();
				continue;
			}

			switch ( $field['type'] ) {
				case 'media':
					// Elementor stores { id, url }; the renderer wants the id.
					$atts[ $key ] = ! empty( $value['id'] ) ? (int) $value['id'] : '';
					break;

				case 'url':
					$atts[ $key ] = ! empty( $value['url'] ) ? $value['url'] : '';
					break;

				case 'toggle':
					// A switcher is always present in settings, so it is authoritative.
					$atts[ $key ] = ( 'yes' === $value ) ? 1 : 0;
					break;

				case 'inherit_select':
					if ( ! array_key_exists( $key, $raw_settings ) ) {
						$atts[ $key ] = 'inherit';
					} elseif ( '' === $raw_settings[ $key ] ) {
						// Elementor's previous switcher stored an explicit Off as empty.
						$atts[ $key ] = 'immediate';
					} else {
						$atts[ $key ] = $raw_settings[ $key ];
					}
					break;

				default:
					$atts[ $key ] = ( null === $value ) ? '' : $value;
					break;
			}
		}

		return $atts;
	}

	/**
	 * Render through the shared section callback.
	 */
	protected function render() {
		$atts = $this->normalize_settings( $this->get_settings_for_display() );

		echo bookingly_get_section_html( $this->section_id(), $atts ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in the renderer.
	}
}

/*
 * Concrete widget types. Elementor reconstructs a widget from its class name on
 * render, so each section needs its own class; these carry nothing else.
 */

/** Hero widget. */
class Bookingly_Elementor_Hero extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'hero'; }
}

/** Trust strip widget. */
class Bookingly_Elementor_Trust_Strip extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'trust_strip'; }
}

/** Services grid widget. */
class Bookingly_Elementor_Services extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'services'; }
}

/** How it works widget. */
class Bookingly_Elementor_How_It_Works extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'how_it_works'; }
}

/** About preview widget. */
class Bookingly_Elementor_About extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'about'; }
}

/** Testimonials widget. */
class Bookingly_Elementor_Testimonials extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'testimonials'; }
}

/** CTA band widget. */
class Bookingly_Elementor_Cta_Band extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'cta_band'; }
}

/** Stats band widget. */
class Bookingly_Elementor_Stats extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'stats'; }
}

/** Value cards widget. */
class Bookingly_Elementor_Value_Props extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'value_props'; }
}

/** Team widget. */
class Bookingly_Elementor_Team extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'team'; }
}

/** Story widget. */
class Bookingly_Elementor_Story extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'story'; }
}

/** Page hero widget. */
class Bookingly_Elementor_Page_Hero extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'page_hero'; }
}

/** Contact cards widget. */
class Bookingly_Elementor_Contact_Cards extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'contact_cards'; }
}

/** Contact form widget. */
class Bookingly_Elementor_Contact_Form extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'contact_form'; }
}

/** Business hours widget. */
class Bookingly_Elementor_Business_Hours extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'business_hours'; }
}

/** Map widget. */
class Bookingly_Elementor_Map extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'map'; }
}

/** Blog posts widget. */
class Bookingly_Elementor_Blog_Posts extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'blog_posts'; }
}

/** Follow band widget. */
class Bookingly_Elementor_Newsletter extends Bookingly_Elementor_Section_Widget {
	/** @return string */
	protected function section_id() { return 'newsletter'; }
}
