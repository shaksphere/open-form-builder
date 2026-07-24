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
