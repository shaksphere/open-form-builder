<?php
defined( 'ABSPATH' ) || exit;

/**
 * Google Sheet export. Posts the submission as JSON to an admin-entered Google
 * Apps Script web-app URL, which appends a row. No service account, no OAuth —
 * the Apps Script snippet ships in docs/apps-script.md.
 *
 * Fire-and-forget: a slow or failing sheet must never block the submission.
 */
class OFB_Sheets {

	/**
	 * @param array $settings Form settings (sheet_export block).
	 * @param array $data     Submission data: name => [label, value].
	 * @param array $extra    submission_id, amount, currency, form_id, status.
	 */
	public static function export( array $settings, array $data, array $extra ): void {
		$cfg = is_array( $settings['sheet_export'] ?? null ) ? $settings['sheet_export'] : [];
		if ( empty( $cfg['enabled'] ) || empty( $cfg['webhook_url'] ) ) {
			return;
		}
		$url = esc_url_raw( (string) $cfg['webhook_url'] );
		if ( '' === $url ) {
			return;
		}

		// Flatten to name => value for an easy spreadsheet row; keep labels too.
		$flat   = [];
		$labels = [];
		foreach ( $data as $name => $entry ) {
			$value = is_array( $entry ) ? ( $entry['value'] ?? '' ) : $entry;
			$flat[ $name ]   = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			$labels[ $name ] = is_array( $entry ) ? ( $entry['label'] ?? $name ) : $name;
		}

		$payload = [
			'submission_id'  => $extra['submission_id'] ?? 0,
			'form_id'        => $extra['form_id'] ?? 0,
			'payment_status' => $extra['status'] ?? '',
			'amount'         => $extra['amount'] ?? 0,
			'currency'       => $extra['currency'] ?? '',
			'submitted_at'   => current_time( 'mysql' ),
			'labels'         => $labels,
			'fields'         => $flat,
		];

		wp_remote_post( $url, [
			'timeout'     => 8,
			'blocking'    => false, // fire-and-forget; don't hold up the response
			'redirection' => 3,
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'body'        => wp_json_encode( $payload ),
		] );
	}
}
