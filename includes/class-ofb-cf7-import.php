<?php
defined( 'ABSPATH' ) || exit;

/**
 * Imports a Contact Form 7 form template into our schema.
 *
 * CF7 has no conditional logic, so we import fields + mail only; the admin adds
 * logic afterwards in our builder. We never fabricate conditional rules on import.
 *
 * The result is a *draft* the React builder loads — it is not persisted here.
 */
class OFB_CF7_Import {

	/** CF7 tag type => our field type. */
	const TYPE_MAP = [
		'text'       => 'text',
		'email'      => 'email',
		'tel'        => 'tel',
		'number'     => 'number',
		'range'      => 'number',
		'textarea'   => 'textarea',
		'select'     => 'select',
		'checkbox'   => 'checkbox',
		'radio'      => 'radio',
		'acceptance' => 'checkbox',
		'date'       => 'text',
	];

	/**
	 * @param string $source CF7 form template markup.
	 * @param string $mail   CF7 mail body (optional).
	 * @return array{name:string,schema:array,settings:array,notes:string[]}
	 */
	public static function import( string $source, string $mail = '' ): array {
		$fields = self::parse_form( $source );
		$notes  = [];

		$schema = [
			'version' => OFB_Schema::VERSION,
			'steps'   => [
				[
					'id'          => 'step_1',
					'title'       => __( 'Imported form', 'open-form-builder' ),
					'description' => '',
					'fields'      => $fields,
				],
			],
		];

		$settings = [];
		if ( '' !== trim( $mail ) ) {
			// Convert CF7 [field] mail-tags to our {field} personalisation tags.
			$body = self::convert_mail_tags( $mail );
			$settings['emails'] = [
				'confirmation' => [
					'enabled' => true,
					'to'      => '',
					'subject' => '',
					'body'    => wp_kses_post( $body ),
				],
			];
			$notes[] = __( 'Mail template imported into the Confirmation email. Set the recipient and add conditional routing in the builder.', 'open-form-builder' );
		}

		$notes[] = __( 'CF7 has no conditional logic — none was imported. Add show/hide rules per field as needed.', 'open-form-builder' );

		return [
			'name'     => __( 'Imported CF7 form', 'open-form-builder' ),
			'schema'   => OFB_Schema::normalize( $schema ),
			'settings' => $settings,
			'notes'    => $notes,
		];
	}

	/** Parse CF7 form tags into normalized field arrays, with best-effort labels. */
	private static function parse_form( string $source ): array {
		$fields = [];

		// Match CF7 tags: [type* name options...] — captures type, optional *, name, rest.
		if ( ! preg_match_all( '/\[([a-z_]+)(\*)?\s+([A-Za-z0-9_\-]+)([^\]]*)\]/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $fields;
		}

		foreach ( $matches as $m ) {
			$cf7_type = $m[1][0];
			$required = '*' === $m[2][0];
			$name     = $m[3][0];
			$rest     = $m[4][0];
			$offset   = (int) $m[0][1];

			if ( 'submit' === $cf7_type || ! isset( self::TYPE_MAP[ $cf7_type ] ) ) {
				continue; // skip submit and unsupported tags (quiz, file, captcha, etc.)
			}

			$type    = self::TYPE_MAP[ $cf7_type ];
			$options = self::parse_options( $rest );
			$label   = self::guess_label( $source, $offset, $name );

			$field = [
				'id'          => 'fld_' . substr( md5( $name . $offset ), 0, 8 ),
				'type'        => $type,
				'name'        => $name,
				'label'       => $label,
				'placeholder' => self::parse_placeholder( $rest ),
				'help'        => '',
				'required'    => $required,
			];
			if ( in_array( $type, OFB_Schema::CHOICE_TYPES, true ) ) {
				$field['options'] = array_map( fn( $o ) => [ 'label' => $o, 'value' => $o ], $options );
			}
			$fields[] = $field;
		}

		return $fields;
	}

	/** Quoted-string options, e.g. [select x "One" "Two"]. */
	private static function parse_options( string $rest ): array {
		$opts = [];
		if ( preg_match_all( '/"([^"]*)"/', $rest, $mm ) ) {
			foreach ( $mm[1] as $o ) {
				// Skip CF7's first-as-label / placeholder pseudo-options handled elsewhere.
				$opts[] = $o;
			}
		}
		return $opts;
	}

	private static function parse_placeholder( string $rest ): string {
		// CF7 uses `placeholder "..."`; a leading quoted value with the keyword.
		if ( preg_match( '/placeholder\s+"([^"]*)"/', $rest, $mm ) ) {
			return $mm[1];
		}
		return '';
	}

	/**
	 * Best-effort label: take the text immediately before the tag (same line /
	 * enclosing label), strip HTML and trailing punctuation. Falls back to a
	 * humanized field name.
	 */
	private static function guess_label( string $source, int $offset, string $name ): string {
		$before = substr( $source, 0, $offset );
		// Last chunk of text after the previous tag/newline/opening element.
		$chunk = preg_split( '/(\]|>|\n|\r)/', $before );
		$text  = is_array( $chunk ) ? trim( wp_strip_all_tags( (string) end( $chunk ) ) ) : '';
		$text  = trim( $text, " \t:*-" );
		if ( '' !== $text && strlen( $text ) <= 80 ) {
			return $text;
		}
		return ucwords( str_replace( [ '-', '_' ], ' ', $name ) );
	}

	/** CF7 mail-tags [field] become our {field} tags. Leaves [_special] CF7 tags. */
	private static function convert_mail_tags( string $mail ): string {
		return preg_replace_callback( '/\[([A-Za-z0-9_\-]+)\]/', function ( $m ) {
			$tag = $m[1];
			// CF7 special tags begin with an underscore; map a couple, drop the rest.
			if ( '_' === $tag[0] ) {
				return '';
			}
			return '{' . $tag . '}';
		}, $mail );
	}
}
