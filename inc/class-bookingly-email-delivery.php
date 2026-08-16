<?php
/**
 * Contact-form email delivery and abuse controls.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deliver Bookingly contact messages through Brevo with a WordPress fallback.
 */
final class Bookingly_Email_Delivery {

	const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';
	const STATE_PREFIX   = 'bookingly_cf_state_';
	const RATE_PREFIX    = 'bookingly_cf_rate_';
	const STATE_TTL      = 1800;

	/**
	 * Return sanitized runtime configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function configuration() {
		$options = bookingly_get_options();
		$email   = isset( $options['email'] ) && is_array( $options['email'] ) ? $options['email'] : array();
		$contact = isset( $options['contact'] ) && is_array( $options['contact'] ) ? $options['contact'] : array();
		$provider = sanitize_key( $email['provider'] ?? 'wordpress' );
		$provider = in_array( $provider, array( 'wordpress', 'brevo' ), true ) ? $provider : 'wordpress';

		$stored_key = isset( $email['brevo_api_key'] ) ? trim( (string) $email['brevo_api_key'] ) : '';

		/*
		 * HAVENLY_BREVO_API_KEY is still honoured: the theme was renamed, and a
		 * site that set the old constant in wp-config.php would otherwise lose
		 * its transport silently.
		 */
		$constant_key = '';
		foreach ( array( 'BOOKINGLY_BREVO_API_KEY', 'HAVENLY_BREVO_API_KEY' ) as $constant ) {
			if ( defined( $constant ) && is_string( constant( $constant ) ) && '' !== trim( constant( $constant ) ) ) {
				$constant_key = trim( constant( $constant ) );
				break;
			}
		}

		$api_key = '' !== $constant_key ? $constant_key : $stored_key;

		$config = array(
			'provider'              => $provider,
			'api_key'               => $api_key,
			'api_key_from_constant' => '' !== $constant_key,
			'admin_recipient'       => self::first_valid_email(
				array(
					$contact['contact_form_to'] ?? '',
					$contact['email'] ?? '',
					get_option( 'admin_email' ),
				)
			),
			'sender_name'           => sanitize_text_field( $email['sender_name'] ?? '' ),
			'sender_email'          => self::first_valid_email(
				array(
					$email['sender_email'] ?? '',
					$contact['email'] ?? '',
					get_option( 'admin_email' ),
				)
			),
			'customer_confirmation' => ! empty( $email['customer_confirmation'] ),
			'admin_subject'         => sanitize_text_field( $email['admin_subject'] ?? '' ),
			'customer_subject'      => sanitize_text_field( $email['customer_subject'] ?? '' ),
			'customer_message'      => sanitize_textarea_field( $email['customer_message'] ?? '' ),
		);

		if ( '' === $config['sender_name'] ) {
			$config['sender_name'] = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		}

		/**
		 * Filter email delivery configuration, primarily for controlled tests.
		 *
		 * @param array<string,mixed> $config Sanitized configuration.
		 */
		return apply_filters( 'bookingly_email_delivery_config', $config );
	}

	/**
	 * Whether the customer acknowledgement is enabled.
	 *
	 * @return bool
	 */
	public static function customer_confirmation_enabled() {
		$config = self::configuration();
		return ! empty( $config['customer_confirmation'] );
	}

	/**
	 * Send the administrator notification.
	 *
	 * @param array<string,string> $fields        Validated contact fields.
	 * @param string               $submission_id Submission UUID.
	 * @return array<string,mixed>
	 */
	public static function send_admin( $fields, $submission_id ) {
		$config       = self::configuration();
		$replacements = self::template_replacements( $fields );
		$subject      = self::interpolate_subject( $config['admin_subject'], $replacements );
		$plain        = implode(
			"\n",
			array(
				__( 'New contact message', 'bookingly' ),
				'',
				__( 'Name:', 'bookingly' ) . ' ' . $fields['name'],
				__( 'Email:', 'bookingly' ) . ' ' . $fields['email'],
				__( 'Phone:', 'bookingly' ) . ' ' . $fields['phone'],
				__( 'Topic:', 'bookingly' ) . ' ' . $fields['topic'],
				'',
				__( 'Message:', 'bookingly' ),
				$fields['message'],
			)
		);

		return self::deliver(
			'admin',
			$config['admin_recipient'],
			$config['sender_name'],
			$subject,
			self::cap_text( $plain, 12000 ),
			self::render_admin_html( $fields ),
			array( 'email' => $fields['email'], 'name' => $fields['name'] ),
			$submission_id,
			$config
		);
	}

	/**
	 * Send the customer acknowledgement.
	 *
	 * @param array<string,string> $fields        Validated contact fields.
	 * @param string               $submission_id Submission UUID.
	 * @return array<string,mixed>
	 */
	public static function send_customer( $fields, $submission_id ) {
		$config       = self::configuration();
		$replacements = self::template_replacements( $fields );
		$subject      = self::interpolate_subject( $config['customer_subject'], $replacements );
		$plain        = self::interpolate_text( $config['customer_message'], $replacements, 12000 );

		return self::deliver(
			'customer',
			$fields['email'],
			$fields['name'],
			$subject,
			$plain,
			self::render_customer_html( $plain, $fields ),
			array( 'email' => $config['sender_email'], 'name' => $config['sender_name'] ),
			$submission_id,
			$config
		);
	}

	/**
	 * Deliver through the selected transport and approved fallback.
	 *
	 * @param string              $stage         admin or customer.
	 * @param string              $to_email      Recipient email.
	 * @param string              $to_name       Recipient name.
	 * @param string              $subject       Safe subject.
	 * @param string              $plain         Plain-text content.
	 * @param string              $html          Rendered HTML body, or '' to derive one from $plain.
	 * @param array<string,string> $reply_to      Reply-To identity.
	 * @param string              $submission_id Submission UUID.
	 * @param array<string,mixed>  $config        Runtime configuration.
	 * @return array<string,mixed>
	 */
	private static function deliver( $stage, $to_email, $to_name, $subject, $plain, $html, $reply_to, $submission_id, $config ) {
		if ( ! self::valid_email( $to_email ) ) {
			self::report_failure( $stage, 'wp_mail', 'invalid_recipient' );
			return array( 'accepted' => false, 'transport' => '', 'fallback' => false, 'error_code' => 'invalid_recipient' );
		}

		if ( '' === trim( (string) $html ) ) {
			$html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#17231f">' . nl2br( esc_html( $plain ) ) . '</div>';
		}
		$used_fallback = false;

		if ( 'brevo' === $config['provider'] ) {
			$brevo = self::send_brevo( $to_email, $to_name, $subject, $plain, $html, $reply_to, $config );
			if ( $brevo['accepted'] ) {
				return array( 'accepted' => true, 'transport' => 'brevo', 'fallback' => false, 'error_code' => '' );
			}
			self::report_failure( $stage, 'brevo', $brevo['error_code'] );
			$used_fallback = true;
		}

		$wordpress = self::send_wordpress( $to_email, $to_name, $subject, $html, $reply_to, $submission_id, $config );
		if ( $wordpress['accepted'] ) {
			return array( 'accepted' => true, 'transport' => 'wp_mail', 'fallback' => $used_fallback, 'error_code' => '' );
		}

		self::report_failure( $stage, 'wp_mail', $wordpress['error_code'] );
		return array( 'accepted' => false, 'transport' => 'wp_mail', 'fallback' => $used_fallback, 'error_code' => $wordpress['error_code'] );
	}

	/**
	 * Attempt one Brevo transactional email.
	 *
	 * @return array{accepted:bool,error_code:string}
	 */
	private static function send_brevo( $to_email, $to_name, $subject, $plain, $html, $reply_to, $config ) {
		if ( empty( $config['api_key'] ) ) {
			return array( 'accepted' => false, 'error_code' => 'missing_api_key' );
		}
		if ( ! self::valid_email( $config['sender_email'] ) ) {
			return array( 'accepted' => false, 'error_code' => 'invalid_sender' );
		}

		$payload = array(
			'sender'      => array( 'email' => $config['sender_email'], 'name' => $config['sender_name'] ),
			'to'          => array( array( 'email' => $to_email, 'name' => $to_name ) ),
			'replyTo'     => array( 'email' => $reply_to['email'], 'name' => $reply_to['name'] ),
			'subject'     => $subject,
			'htmlContent' => $html,
			'textContent' => $plain,
		);

		$response = wp_remote_post(
			self::BREVO_ENDPOINT,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'api-key'      => $config['api_key'],
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'accepted' => false, 'error_code' => 'request_failed' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return array( 'accepted' => false, 'error_code' => 'rejected' );
		}

		return array( 'accepted' => true, 'error_code' => '' );
	}

	/**
	 * Attempt one WordPress email.
	 *
	 * @return array{accepted:bool,error_code:string}
	 */
	private static function send_wordpress( $to_email, $to_name, $subject, $html, $reply_to, $submission_id, $config ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'X-Bookingly-Submission-ID: ' . $submission_id,
		);
		if ( self::valid_email( $config['sender_email'] ) ) {
			$headers[] = 'From: ' . self::header_name( $config['sender_name'] ) . ' <' . $config['sender_email'] . '>';
		}
		if ( self::valid_email( $reply_to['email'] ) ) {
			$headers[] = 'Reply-To: ' . self::header_name( $reply_to['name'] ) . ' <' . $reply_to['email'] . '>';
		}
		$to = self::header_name( $to_name ) . ' <' . $to_email . '>';

		return wp_mail( $to, $subject, $html, $headers )
			? array( 'accepted' => true, 'error_code' => '' )
			: array( 'accepted' => false, 'error_code' => 'wp_mail_failed' );
	}

	/**
	 * Build the branded palette from the saved theme colors.
	 *
	 * Every tone is derived from the four stored colors so the email always
	 * matches the site without a second place to configure.
	 *
	 * @return array<string,string>
	 */
	private static function palette() {
		$primary    = self::hex_color( bookingly_option( 'colors.primary', '' ), '#2B6E58' );
		$accent     = self::hex_color( bookingly_option( 'colors.accent', '' ), '#E7A33E' );
		$background = self::hex_color( bookingly_option( 'colors.background', '' ), '#F5F7F5' );

		$palette = array(
			'primary'      => $primary,
			'primary_dark' => self::hex_color( bookingly_option( 'colors.primary_dark', '' ), '#1E4F3F' ),
			'accent'       => $accent,
			'background'   => $background,
			'surface'      => '#FFFFFF',
			'text'         => '#17231F',
			'muted'        => '#67766F',
			'border'       => self::mix( $primary, '#FFFFFF', 0.86 ),
			'panel'        => self::mix( $primary, '#FFFFFF', 0.95 ),
			'on_primary'   => self::readable_on( $primary ),
		);
		$palette['header_muted'] = self::mix( $palette['on_primary'], $primary, 0.32 );

		/**
		 * Filter the branded contact-email palette.
		 *
		 * @param array<string,string> $palette Resolved colors.
		 */
		return apply_filters( 'bookingly_email_palette', $palette );
	}

	/** Normalize a stored color, accepting #abc and #aabbcc. */
	private static function hex_color( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( 1 === preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $short ) ) {
			$value = '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
		}
		return 1 === preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtoupper( $value ) : $fallback;
	}

	/** @return array{0:int,1:int,2:int} */
	private static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/** Blend $hex toward $target by $weight (0-1). */
	private static function mix( $hex, $target, $weight ) {
		$weight = max( 0.0, min( 1.0, (float) $weight ) );
		$from   = self::rgb( $hex );
		$to     = self::rgb( $target );
		$out    = '#';

		foreach ( array( 0, 1, 2 ) as $channel ) {
			$value = (int) round( $from[ $channel ] + ( ( $to[ $channel ] - $from[ $channel ] ) * $weight ) );
			$out  .= str_pad( dechex( max( 0, min( 255, $value ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return strtoupper( $out );
	}

	/** Pick legible foreground text for an arbitrary theme color. */
	private static function readable_on( $hex ) {
		list( $red, $green, $blue ) = self::rgb( $hex );
		$luminance = ( ( 0.2126 * $red ) + ( 0.7152 * $green ) + ( 0.0722 * $blue ) ) / 255;
		return $luminance > 0.6 ? '#17231F' : '#FFFFFF';
	}

	/** Escape text for HTML email while keeping author line breaks. */
	private static function esc_block( $text ) {
		return nl2br( esc_html( (string) $text ), false );
	}

	/**
	 * Render the shared responsive email shell.
	 *
	 * Table-based with inline styles only, so Outlook, Gmail and Apple Mail all
	 * render the same layout.
	 *
	 * @param string $preheader Inbox preview line.
	 * @param string $heading   Card heading.
	 * @param string $inner     Prebuilt, already-escaped body markup.
	 * @param string $footnote  Already-escaped footer sentence.
	 * @return string
	 */
	private static function render_shell( $preheader, $heading, $inner, $footnote ) {
		$p         = self::palette();
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$home      = home_url( '/' );
		$font      = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

		$html  = '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head>';
		$html .= '<meta charset="utf-8">';
		$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
		$html .= '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only">';
		$html .= '<title>' . esc_html( $heading ) . '</title>';
		$html .= '</head><body style="margin:0;padding:0;width:100%;background-color:' . $p['background'] . ';">';

		// Inbox preview text, hidden in the rendered message.
		$html .= '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">'
			. esc_html( $preheader ) . str_repeat( '&#8203;&nbsp;', 30 ) . '</div>';

		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $p['background'] . ';">'
			. '<tr><td align="center" style="padding:32px 16px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:' . $p['surface'] . ';border:1px solid ' . $p['border'] . ';border-radius:16px;overflow:hidden;">';

		// Accent rule.
		$html .= '<tr><td style="height:4px;line-height:4px;font-size:0;background-color:' . $p['accent'] . ';">&nbsp;</td></tr>';

		// Header.
		$html .= '<tr><td style="padding:30px 36px 26px 36px;background-color:' . $p['primary'] . ';">'
			. '<div style="margin:0 0 10px 0;font-family:' . $font . ';font-size:12px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:' . $p['header_muted'] . ';">'
			. esc_html( $site_name ) . '</div>'
			. '<h1 style="margin:0;font-family:' . $font . ';font-size:24px;line-height:1.3;font-weight:700;color:' . $p['on_primary'] . ';">'
			. esc_html( $heading ) . '</h1>'
			. '</td></tr>';

		// Body.
		$html .= '<tr><td style="padding:32px 36px;font-family:' . $font . ';font-size:15px;line-height:1.65;color:' . $p['text'] . ';">' . $inner . '</td></tr>';

		// Footer.
		$html .= '<tr><td style="padding:22px 36px;background-color:' . $p['panel'] . ';border-top:1px solid ' . $p['border'] . ';font-family:' . $font . ';font-size:12px;line-height:1.6;color:' . $p['muted'] . ';">'
			. '<div style="margin:0 0 4px 0;font-weight:600;color:' . $p['text'] . ';">' . esc_html( $site_name ) . '</div>'
			. '<div style="margin:0 0 8px 0;">' . $footnote . '</div>'
			. '<a href="' . esc_url( $home ) . '" style="color:' . $p['primary'] . ';text-decoration:none;font-weight:600;">' . esc_html( self::display_host( $home ) ) . '</a>'
			. '</td></tr>';

		$html .= '</table></td></tr></table></body></html>';

		return $html;
	}

	/** Render a labelled value row for the detail panel. */
	private static function detail_row( $label, $value_html, $p, $font, $first = false ) {
		$border = $first ? '' : 'border-top:1px solid ' . $p['border'] . ';';

		return '<tr>'
			. '<td style="' . $border . 'padding:' . ( $first ? '0' : '12px' ) . ' 14px 12px 0;font-family:' . $font . ';font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:' . $p['muted'] . ';white-space:nowrap;vertical-align:top;">'
			. esc_html( $label ) . '</td>'
			. '<td style="' . $border . 'padding:' . ( $first ? '0' : '12px' ) . ' 0 12px 0;font-family:' . $font . ';font-size:15px;line-height:1.5;color:' . $p['text'] . ';vertical-align:top;">'
			. $value_html . '</td>'
			. '</tr>';
	}

	/**
	 * Render the administrator notification body.
	 *
	 * @param array<string,string> $fields Validated contact fields.
	 * @return string
	 */
	private static function render_admin_html( $fields ) {
		$p    = self::palette();
		$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

		$inner = '<p style="margin:0 0 24px 0;font-family:' . $font . ';font-size:15px;line-height:1.65;color:' . $p['muted'] . ';">'
			. esc_html__( 'Someone just sent a message through the website contact form.', 'bookingly' ) . '</p>';

		// Sender details.
		$email_html = self::valid_email( $fields['email'] )
			? '<a href="mailto:' . esc_attr( $fields['email'] ) . '" style="color:' . $p['primary'] . ';text-decoration:none;font-weight:600;">' . esc_html( $fields['email'] ) . '</a>'
			: esc_html( $fields['email'] );

		$rows  = self::detail_row( __( 'Name', 'bookingly' ), '<strong style="font-weight:600;">' . esc_html( $fields['name'] ) . '</strong>', $p, $font, true );
		$rows .= self::detail_row( __( 'Email', 'bookingly' ), $email_html, $p, $font );
		if ( '' !== $fields['phone'] ) {
			$rows .= self::detail_row( __( 'Phone', 'bookingly' ), esc_html( $fields['phone'] ), $p, $font );
		}
		$rows .= self::detail_row( __( 'Topic', 'bookingly' ), esc_html( $fields['topic'] ), $p, $font );

		$received = wp_date( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ) );
		if ( is_string( $received ) && '' !== $received ) {
			$rows .= self::detail_row( __( 'Received', 'bookingly' ), esc_html( $received ), $p, $font );
		}

		$inner .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 26px 0;">' . $rows . '</table>';

		// Message quote card.
		$inner .= '<div style="margin:0 0 10px 0;font-family:' . $font . ';font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:' . $p['muted'] . ';">'
			. esc_html__( 'Message', 'bookingly' ) . '</div>'
			. '<div style="margin:0 0 28px 0;padding:18px 20px;background-color:' . $p['panel'] . ';border-left:3px solid ' . $p['accent'] . ';border-radius:10px;font-family:' . $font . ';font-size:15px;line-height:1.7;color:' . $p['text'] . ';">'
			. self::esc_block( $fields['message'] ) . '</div>';

		// Reply button.
		if ( self::valid_email( $fields['email'] ) ) {
			$mailto = 'mailto:' . $fields['email'] . '?subject=' . rawurlencode( 'Re: ' . $fields['topic'] );
			$inner .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
				. '<td style="background-color:' . $p['primary'] . ';border-radius:10px;">'
				. '<a href="' . esc_url( $mailto, array( 'mailto' ) ) . '" style="display:inline-block;padding:13px 28px;font-family:' . $font . ';font-size:15px;font-weight:600;line-height:1;color:' . $p['on_primary'] . ';text-decoration:none;">'
				/* translators: %s: sender first name. */
				. esc_html( sprintf( __( 'Reply to %s', 'bookingly' ), self::first_word( $fields['name'] ) ) )
				. '</a></td></tr></table>';
		}

		return self::render_shell(
			sprintf( '%s — %s', $fields['name'], $fields['topic'] ),
			__( 'New contact message', 'bookingly' ),
			$inner,
			esc_html__( 'Sent automatically from your website contact form.', 'bookingly' )
		);
	}

	/**
	 * Render the customer acknowledgement body.
	 *
	 * @param string               $plain  Interpolated acknowledgement text.
	 * @param array<string,string> $fields Validated contact fields.
	 * @return string
	 */
	private static function render_customer_html( $plain, $fields ) {
		$p    = self::palette();
		$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

		$inner      = '';
		$paragraphs = preg_split( '/\n{2,}/', trim( (string) $plain ) );
		foreach ( (array) $paragraphs as $index => $paragraph ) {
			if ( '' === trim( (string) $paragraph ) ) {
				continue;
			}
			$inner .= '<p style="margin:0 0 ' . ( count( (array) $paragraphs ) - 1 === $index ? '0' : '16px' ) . ' 0;font-family:' . $font . ';font-size:15px;line-height:1.7;color:' . $p['text'] . ';">'
				. self::esc_block( $paragraph ) . '</p>';
		}

		// Copy of what they sent, so the acknowledgement is self-contained.
		if ( '' !== $fields['message'] ) {
			$inner .= '<div style="margin:28px 0 0 0;padding:18px 20px;background-color:' . $p['panel'] . ';border-left:3px solid ' . $p['accent'] . ';border-radius:10px;">'
				. '<div style="margin:0 0 8px 0;font-family:' . $font . ';font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:' . $p['muted'] . ';">'
				. esc_html__( 'Your message', 'bookingly' ) . '</div>'
				. '<div style="font-family:' . $font . ';font-size:14px;line-height:1.7;color:' . $p['text'] . ';">'
				. self::esc_block( $fields['message'] ) . '</div>'
				. '</div>';
		}

		return self::render_shell(
			__( 'Thanks for getting in touch — we have your message.', 'bookingly' ),
			__( 'We received your message', 'bookingly' ),
			$inner,
			esc_html__( 'You received this because you used the contact form on our website.', 'bookingly' )
		);
	}

	/** First word of a display name, for a short button label. */
	private static function first_word( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		return isset( $parts[0] ) && '' !== $parts[0] ? $parts[0] : (string) $name;
	}

	/** Host portion of a URL for footer display. */
	private static function display_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && '' !== $host ? $host : (string) $url;
	}

	/**
	 * Emit privacy-safe failure metadata only.
	 */
	private static function report_failure( $stage, $transport, $error_code ) {
		$stage      = in_array( $stage, array( 'admin', 'customer' ), true ) ? $stage : 'admin';
		$transport  = in_array( $transport, array( 'brevo', 'wp_mail' ), true ) ? $transport : 'wp_mail';
		$error_code = in_array( $error_code, array( 'invalid_recipient', 'invalid_sender', 'missing_api_key', 'request_failed', 'rejected', 'wp_mail_failed' ), true ) ? $error_code : 'request_failed';

		do_action(
			'bookingly_contact_delivery_failed',
			array(
				'stage'      => $stage,
				'transport'  => $transport,
				'error_code' => $error_code,
			)
		);
	}

	/**
	 * Apply independent client and recipient request limits.
	 *
	 * @param string $email     Customer email.
	 * @param string $client_ip Direct remote address.
	 * @return true|WP_Error
	 */
	public static function consume_rate_limit( $email, $client_ip ) {
		$options = bookingly_get_options();
		$email_options = isset( $options['email'] ) && is_array( $options['email'] ) ? $options['email'] : array();

		/**
		 * Filter the contact throttle. Defaults come from Theme Options; the
		 * window is expressed in seconds here for backward compatibility.
		 *
		 * @param array{client:int,recipient:int,window:int} $limits Resolved limits.
		 */
		$limits = apply_filters(
			'bookingly_contact_rate_limits',
			array(
				'client'    => absint( $email_options['rate_limit_client'] ?? 5 ),
				'recipient' => absint( $email_options['rate_limit_recipient'] ?? 3 ),
				'window'    => absint( $email_options['rate_limit_window'] ?? 10 ) * MINUTE_IN_SECONDS,
			)
		);
		$limits = array(
			'client'    => max( 1, min( 50, absint( $limits['client'] ?? 5 ) ) ),
			'recipient' => max( 1, min( 50, absint( $limits['recipient'] ?? 3 ) ) ),
			'window'    => max( 60, min( DAY_IN_SECONDS, absint( $limits['window'] ?? ( 10 * MINUTE_IN_SECONDS ) ) ) ),
		);
		$keys = self::rate_limit_keys( $email, $client_ip );

		if ( (int) get_transient( $keys['client'] ) >= $limits['client'] || (int) get_transient( $keys['recipient'] ) >= $limits['recipient'] ) {
			return new WP_Error( 'bookingly_contact_rate_limited', __( 'Please wait before sending another message.', 'bookingly' ) );
		}

		set_transient( $keys['client'], (int) get_transient( $keys['client'] ) + 1, $limits['window'] );
		set_transient( $keys['recipient'], (int) get_transient( $keys['recipient'] ) + 1, $limits['window'] );

		return true;
	}

	/**
	 * Return privacy-safe transient keys for rate limits.
	 *
	 * @return array{client:string,recipient:string}
	 */
	public static function rate_limit_keys( $email, $client_ip ) {
		$ip = '' !== trim( (string) $client_ip ) ? trim( (string) $client_ip ) : 'unknown';
		return array(
			'client'    => self::RATE_PREFIX . 'ip_' . self::digest( strtolower( $ip ) ),
			'recipient' => self::RATE_PREFIX . 'to_' . self::digest( strtolower( trim( (string) $email ) ) ),
		);
	}

	/** Return accepted delivery stages for a submission. */
	public static function submission_state( $submission_id ) {
		$state = get_transient( self::submission_state_key( $submission_id ) );
		return is_array( $state )
			? array( 'admin' => ! empty( $state['admin'] ), 'customer' => ! empty( $state['customer'] ) )
			: array( 'admin' => false, 'customer' => false );
	}

	/** Mark one accepted delivery stage without storing message data. */
	public static function mark_submission_stage( $submission_id, $stage ) {
		if ( ! in_array( $stage, array( 'admin', 'customer' ), true ) ) {
			return;
		}
		$state           = self::submission_state( $submission_id );
		$state[ $stage ] = true;
		set_transient( self::submission_state_key( $submission_id ), $state, self::STATE_TTL );
	}

	/** Return the HMAC-keyed transient name for a submission UUID. */
	public static function submission_state_key( $submission_id ) {
		return self::STATE_PREFIX . self::digest( strtolower( (string) $submission_id ) );
	}

	/** Validate the cache-safe client-generated submission UUID. */
	public static function valid_submission_id( $submission_id ) {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $submission_id );
	}

	/** Safely interpolate a single-line subject. */
	public static function interpolate_subject( $template, $replacements ) {
		$subject = strtr( (string) $template, $replacements );
		$subject = wp_strip_all_tags( $subject, true );
		$subject = preg_replace( '/[\r\n\t ]+/', ' ', $subject );
		return self::cap_text( trim( (string) $subject ), 180 );
	}

	/** Safely interpolate a plain-text template. */
	public static function interpolate_text( $template, $replacements, $limit = 12000 ) {
		return self::cap_text( strtr( (string) $template, $replacements ), $limit );
	}

	/** @return array<string,string> */
	private static function template_replacements( $fields ) {
		return array(
			'{name}'      => $fields['name'],
			'{email}'     => $fields['email'],
			'{phone}'     => $fields['phone'],
			'{topic}'     => $fields['topic'],
			'{message}'   => $fields['message'],
			'{site_name}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	/** Return the first valid, non-placeholder delivery address. */
	private static function first_valid_email( $candidates ) {
		foreach ( $candidates as $candidate ) {
			$candidate = sanitize_email( $candidate );
			if ( self::valid_email( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/** Whether an email is syntactically valid and not a reserved placeholder. */
	private static function valid_email( $email ) {
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return false;
		}
		$domain = strtolower( (string) substr( strrchr( $email, '@' ), 1 ) );
		return 'example' !== $domain && '.example' !== substr( $domain, -8 );
	}

	/** Remove header-breaking characters from a display name. */
	private static function header_name( $name ) {
		$name = preg_replace( '/[\r\n]+/', ' ', wp_strip_all_tags( (string) $name, true ) );
		return trim( self::cap_text( (string) $name, 120 ) );
	}

	/** Create a short keyed digest without retaining the source value. */
	private static function digest( $value ) {
		return substr( hash_hmac( 'sha256', (string) $value, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/** Unicode-safe text cap. */
	private static function cap_text( $text, $length ) {
		$text = (string) $text;
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}
}
