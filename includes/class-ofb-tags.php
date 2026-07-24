<?php
defined( 'ABSPATH' ) || exit;

/**
 * Personalisation tags. Any {field_name} in confirmation content, email subjects
 * or bodies is replaced with the submitted value for that field. A few synthetic
 * tags expose submission-level data.
 *
 * Synthetic tags:
 *   {all_fields}        - every submitted field as "Label: value" lines
 *   {submission_id}     - the submission row id
 *   {amount}            - formatted total charged
 *   {site_name}         - blog name
 */
class OFB_Tags {

	/**
	 * @param string $text   Template containing {tags}.
	 * @param array  $data   Submission data: name => [ 'label' => .., 'value' => .. ].
	 * @param array  $extra  Synthetic context (submission_id, amount, currency).
	 */
	public static function replace( string $text, array $data, array $extra = [] ): string {
		if ( '' === $text || false === strpos( $text, '{' ) ) {
			return $text;
		}

		$map = [];
		foreach ( $data as $name => $entry ) {
			$value = is_array( $entry ) ? ( $entry['value'] ?? '' ) : $entry;
			$map[ '{' . $name . '}' ] = self::flatten( $value );
		}

		$map['{all_fields}']    = self::all_fields_block( $data );
		$map['{submission_id}'] = isset( $extra['submission_id'] ) ? (string) $extra['submission_id'] : '';
		$map['{amount}']        = isset( $extra['amount'] )
			? self::format_amount( (float) $extra['amount'], (string) ( $extra['currency'] ?? '' ) )
			: '';
		$map['{site_name}']     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		return strtr( $text, $map );
	}

	/** Plain-text "Label: value" block of every submitted field. */
	public static function all_fields_block( array $data ): string {
		$lines = [];
		foreach ( $data as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$lines[] = sprintf( '%s: %s', $entry['label'] ?? '', self::flatten( $entry['value'] ?? '' ) );
		}
		return implode( "\n", $lines );
	}

	private static function flatten( $value ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}
		return (string) $value;
	}

	private static function format_amount( float $amount, string $currency ): string {
		$symbol = '$';
		$label  = strtoupper( $currency );
		return $symbol . number_format( $amount, 2 ) . ( $label ? ' ' . $label : '' );
	}
}
