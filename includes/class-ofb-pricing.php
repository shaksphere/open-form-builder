<?php
defined( 'ABSPATH' ) || exit;

/**
 * Per-form pricing-rules engine. Pure computation — no WordPress, no I/O — so it
 * is trivially testable and gives the same answer on the front end (preview) and
 * the server (the figure actually charged).
 *
 * For S selected sessions:
 *   extra       = S - base_sessions
 *   full_blocks = floor(extra / block_size)
 *   remainder   = extra - (full_blocks * block_size)
 *   total = base_price
 *         + full_blocks * (base_price - block_discount)   // discount is $ or % of base_price
 *         + remainder   * extra_session_price
 *
 * Verified ($80/4, $20-per-block, $25/individual):
 *   4 -> 80, 6 -> 130, 8 -> 140, 12 -> 200, 16 -> 260
 */
class OFB_Pricing {

	/**
	 * @param array $pricing Sanitized pricing settings (see OFB_Security).
	 * @param int   $sessions Number of selected sessions.
	 * @return float Total price (>= 0).
	 */
	public static function total( array $pricing, int $sessions ): float {
		if ( empty( $pricing['enabled'] ) ) {
			return 0.0;
		}

		$base_price    = (float) ( $pricing['base_price'] ?? 0 );
		$base_sessions = (int) ( $pricing['base_sessions'] ?? 0 );
		$extra_price   = (float) ( $pricing['extra_session_price'] ?? 0 );
		$block_size    = max( 1, (int) ( $pricing['block_size'] ?? 1 ) );

		$discount = self::block_discount_amount( $pricing, $base_price );

		// Below or at the base tier: just the base price.
		if ( $sessions <= $base_sessions ) {
			return self::round( $base_price );
		}

		$extra       = $sessions - $base_sessions;
		$full_blocks = intdiv( $extra, $block_size );
		$remainder   = $extra - ( $full_blocks * $block_size );

		$total = $base_price
			+ $full_blocks * ( $base_price - $discount )
			+ $remainder * $extra_price;

		return self::round( max( 0.0, $total ) );
	}

	/**
	 * "Priced options" model: a flat base fee plus the price of every selected
	 * choice option, plus each number field's value × its unit price. Used for
	 * service/booking forms (courses, cleaning, A/C quotes) where the total is the
	 * sum of what the visitor picked rather than a session count.
	 *
	 * @param array $pricing Sanitized pricing settings.
	 * @param array $fields  name => field definition (from OFB_Schema::fields_by_name).
	 * @param array $data    Clean, visible submission data: name => [label, value].
	 * @return float Total price (>= 0).
	 */
	public static function options_total( array $pricing, array $fields, array $data ): float {
		if ( empty( $pricing['enabled'] ) ) {
			return 0.0;
		}
		$total = (float) ( $pricing['base_fee'] ?? 0 );

		foreach ( $data as $name => $entry ) {
			$field = $fields[ $name ] ?? null;
			if ( ! is_array( $field ) ) {
				continue;
			}
			$value = is_array( $entry ) ? ( $entry['value'] ?? '' ) : $entry;
			$type  = $field['type'] ?? '';

			// Choice fields: add the price of each selected option.
			if ( in_array( $type, OFB_Schema::CHOICE_TYPES, true ) ) {
				$selected = is_array( $value ) ? array_map( 'strval', $value ) : [ (string) $value ];
				foreach ( ( $field['options'] ?? [] ) as $opt ) {
					if ( in_array( (string) ( $opt['value'] ?? '' ), $selected, true ) ) {
						$total += (float) ( $opt['price'] ?? 0 );
					}
				}
				continue;
			}

			// Number fields: value × unit price (e.g. rooms × $30).
			if ( 'number' === $type ) {
				$unit = (float) ( $field['config']['unit_price'] ?? 0 );
				if ( $unit && is_numeric( $value ) ) {
					$total += (float) $value * $unit;
				}
			}
		}

		return self::round( max( 0.0, $total ) );
	}

	/** Resolve the per-block discount as an absolute dollar amount. */
	public static function block_discount_amount( array $pricing, float $base_price ): float {
		$type  = ( $pricing['block_discount']['type'] ?? 'amount' ) === 'percent' ? 'percent' : 'amount';
		$value = (float) ( $pricing['block_discount']['value'] ?? 0 );
		if ( 'percent' === $type ) {
			return $base_price * ( $value / 100 );
		}
		return $value;
	}

	/** Stripe charges in the smallest currency unit (cents). */
	public static function to_cents( float $amount ): int {
		return (int) round( $amount * 100 );
	}

	private static function round( float $v ): float {
		return round( $v, 2 );
	}
}
