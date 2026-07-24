<?php
defined( 'ABSPATH' ) || exit;

/**
 * Turns a stored form (schema + settings + slots) into front-end HTML: a
 * multi-step wizard with per-field conditional data, the capacity-aware session
 * picker, and a live pricing summary. All dynamic behaviour (step nav,
 * conditional show/hide, session min/max, price preview, AJAX submit) is driven
 * by assets/frontend.js reading the data-* attributes emitted here.
 */
class OFB_Renderer {

	/** Render the full form markup for the shortcode. */
	public static function render( array $form ): string {
		$schema   = $form['schema'];
		$settings = $form['settings'];
		$form_id  = (int) $form['id'];
		$steps    = $schema['steps'] ?? [];
		$multi    = count( $steps ) > 1;

		$pricing  = is_array( $settings['pricing'] ?? null ) ? $settings['pricing'] : [];
		$payments = is_array( $settings['payments'] ?? null ) ? $settings['payments'] : [];

		// Config the front-end script needs: pricing rules (for live preview) and
		// the field conditional map. Pricing here is non-secret rule data only.
		$config = [
			'formId'   => $form_id,
			'pricing'  => $pricing,
			'currency' => $payments['currency'] ?? 'aud',
		];

		ob_start();
		?>
		<form class="ofb-form" data-ofb-form="<?php echo esc_attr( $form_id ); ?>"
			data-ofb-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
			novalidate>
			<?php wp_nonce_field( OFB_Security::NONCE_SUBMIT, 'ofb_nonce' ); ?>

			<?php if ( $multi ) : ?>
				<ol class="ofb-progress" aria-hidden="true">
					<?php foreach ( $steps as $i => $step ) : ?>
						<li class="ofb-progress__item<?php echo 0 === $i ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr( $i ); ?>">
							<span class="ofb-progress__num"><?php echo esc_html( $i + 1 ); ?></span>
							<span class="ofb-progress__label"><?php echo esc_html( $step['title'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<div class="ofb-steps">
				<?php foreach ( $steps as $i => $step ) : ?>
					<section class="ofb-step<?php echo 0 === $i ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr( $i ); ?>">
						<?php if ( ! empty( $step['title'] ) ) : ?>
							<h3 class="ofb-step__title"><?php echo esc_html( $step['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( ! empty( $step['description'] ) ) : ?>
							<p class="ofb-step__desc"><?php echo esc_html( $step['description'] ); ?></p>
						<?php endif; ?>

						<?php foreach ( ( $step['fields'] ?? [] ) as $field ) : ?>
							<?php echo self::render_field( $field, $form_id, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* internally ?>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $pricing['enabled'] ) ) : ?>
				<div class="ofb-pricing-summary" aria-live="polite">
					<span class="ofb-pricing-summary__label"><?php esc_html_e( 'Total', 'open-form-builder' ); ?></span>
					<span class="ofb-pricing-summary__value" data-ofb-total>—</span>
				</div>
			<?php endif; ?>

			<div class="ofb-nav">
				<?php if ( $multi ) : ?>
					<button type="button" class="ofb-btn ofb-btn--prev" data-ofb-prev hidden><?php esc_html_e( 'Back', 'open-form-builder' ); ?></button>
					<button type="button" class="ofb-btn ofb-btn--next" data-ofb-next><?php esc_html_e( 'Next', 'open-form-builder' ); ?></button>
				<?php endif; ?>
				<button type="submit" class="ofb-btn ofb-btn--submit" data-ofb-submit<?php echo $multi ? ' hidden' : ''; ?>>
					<?php echo ! empty( $payments['enabled'] ) ? esc_html__( 'Continue to payment', 'open-form-builder' ) : esc_html__( 'Submit', 'open-form-builder' ); ?>
				</button>
			</div>

			<div class="ofb-message" role="alert" aria-live="assertive" hidden></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** Render one field, wrapped with conditional metadata for the JS engine. */
	private static function render_field( array $field, int $form_id, array $settings ): string {
		$type = $field['type'];
		$name = $field['name'];
		$id   = 'ofb-' . $form_id . '-' . ( $name ?: $field['id'] );

		$cond_attr = '';
		if ( ! empty( $field['conditional']['enabled'] ) ) {
			$cond_attr = ' data-ofb-conditional="' . esc_attr( wp_json_encode( $field['conditional'] ) ) . '"';
		}

		ob_start();
		// Wrapper carries the field name + conditional rules; the JS toggles it.
		echo '<div class="ofb-field ofb-field--' . esc_attr( $type ) . '" data-ofb-field="' . esc_attr( $name ) . '"' . $cond_attr . '>'; // phpcs:ignore

		if ( 'html' === $type ) {
			echo '<div class="ofb-html">' . wp_kses_post( $field['content'] ?? '' ) . '</div>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$required = ! empty( $field['required'] );
		if ( '' !== ( $field['label'] ?? '' ) ) {
			printf(
				'<label class="ofb-label" for="%s">%s%s</label>',
				esc_attr( $id ),
				esc_html( $field['label'] ),
				$required ? ' <span class="ofb-required" aria-hidden="true">*</span>' : ''
			);
		}

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea class="ofb-input" id="%s" name="%s" placeholder="%s"%s></textarea>',
					esc_attr( $id ), esc_attr( $name ), esc_attr( $field['placeholder'] ?? '' ), $required ? ' required' : ''
				);
				break;

			case 'select':
			case 'dropdown':
				printf( '<select class="ofb-input" id="%s" name="%s"%s>', esc_attr( $id ), esc_attr( $name ), $required ? ' required' : '' );
				if ( '' !== ( $field['placeholder'] ?? '' ) ) {
					printf( '<option value="">%s</option>', esc_html( $field['placeholder'] ) );
				}
				foreach ( ( $field['options'] ?? [] ) as $opt ) {
					printf( '<option value="%s">%s</option>', esc_attr( $opt['value'] ), esc_html( $opt['label'] ) );
				}
				echo '</select>';
				break;

			case 'radio':
			case 'checkbox':
				$input_type = ( 'radio' === $type ) ? 'radio' : 'checkbox';
				$brackets   = ( 'checkbox' === $type ) ? '[]' : '';
				echo '<div class="ofb-choices">';
				foreach ( ( $field['options'] ?? [] ) as $k => $opt ) {
					$opt_id = $id . '-' . $k;
					printf(
						'<label class="ofb-choice" for="%s"><input type="%s" id="%s" name="%s%s" value="%s"> <span>%s</span></label>',
						esc_attr( $opt_id ), esc_attr( $input_type ), esc_attr( $opt_id ),
						esc_attr( $name ), esc_attr( $brackets ), esc_attr( $opt['value'] ), esc_html( $opt['label'] )
					);
				}
				echo '</div>';
				break;

			case 'session_picker':
				echo self::render_session_picker( $field, $form_id, $settings, $name ); // phpcs:ignore
				break;

			default: // text, email, tel, number
				$input_type = in_array( $type, [ 'email', 'tel', 'number' ], true ) ? $type : 'text';
				printf(
					'<input class="ofb-input" type="%s" id="%s" name="%s" placeholder="%s"%s>',
					esc_attr( $input_type ), esc_attr( $id ), esc_attr( $name ),
					esc_attr( $field['placeholder'] ?? '' ), $required ? ' required' : ''
				);
				break;
		}

		if ( '' !== ( $field['help'] ?? '' ) ) {
			printf( '<p class="ofb-help">%s</p>', esc_html( $field['help'] ) );
		}
		echo '<p class="ofb-field-error" hidden></p>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Session picker: a tab per period (with its date window), and within each tab
	 * a card per teacher whose body is day columns of 30-minute time chips —
	 * modelled on a doctor-booking grid. Capacity comes from ofb_slots; a full
	 * chip renders disabled. min/max are emitted for the live counter/validation.
	 */
	private static function render_session_picker( array $field, int $form_id, array $settings, string $name ): string {
		$cfg = is_array( $field['config'] ?? null ) ? $field['config'] : [];
		$sp  = is_array( $settings['session_picker'] ?? null ) ? $settings['session_picker'] : [];
		$min = isset( $cfg['min'] ) ? (int) $cfg['min'] : (int) ( $sp['min'] ?? 4 );
		$max = array_key_exists( 'max', $cfg ) ? $cfg['max'] : ( $sp['max'] ?? null );

		$periods = self::resolve_periods( $sp, $cfg );
		$slots   = OFB_Slots::for_form( $form_id );

		// Index slots: tab => teacher => [slots].
		$by_tab_teacher = [];
		foreach ( $slots as $slot ) {
			$teacher = '' !== trim( $slot['teacher'] ) ? $slot['teacher'] : __( 'Available sessions', 'open-form-builder' );
			$by_tab_teacher[ $slot['tab'] ][ $teacher ][] = $slot;
		}

		ob_start();
		echo '<div class="ofb-session-picker" data-ofb-session data-min="' . esc_attr( $min ) . '"';
		if ( null !== $max ) {
			echo ' data-max="' . esc_attr( (int) $max ) . '"';
		}
		echo '>';

		// Tab buttons.
		echo '<div class="ofb-tabs" role="tablist">';
		foreach ( $periods as $i => $period ) {
			printf(
				'<button type="button" class="ofb-tab%s" data-ofb-tab="%s" role="tab">%s</button>',
				0 === $i ? ' is-active' : '', esc_attr( $period['key'] ), esc_html( $period['label'] )
			);
		}
		echo '</div>';

		// Tab panels.
		foreach ( $periods as $i => $period ) {
			printf(
				'<div class="ofb-tabpanel%s" data-ofb-tabpanel="%s" role="tabpanel"%s>',
				0 === $i ? ' is-active' : '', esc_attr( $period['key'] ), 0 === $i ? '' : ' hidden'
			);

			$range_text = self::format_ranges( $period['ranges'] ?? [] );
			if ( '' !== $range_text ) {
				echo '<p class="ofb-period-dates">' . esc_html( $range_text ) . '</p>';
			}

			$teachers = $by_tab_teacher[ $period['key'] ] ?? [];
			if ( empty( $teachers ) ) {
				echo '<p class="ofb-empty">' . esc_html__( 'No sessions available.', 'open-form-builder' ) . '</p>';
			} else {
				ksort( $teachers );
				foreach ( $teachers as $teacher => $teacher_slots ) {
					echo self::render_teacher_card( (string) $teacher, $teacher_slots, $name ); // phpcs:ignore
				}
			}
			echo '</div>';
		}

		echo '<p class="ofb-session-count" aria-live="polite"><span data-ofb-session-count>0</span> ' . esc_html__( 'selected', 'open-form-builder' ) . '</p>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/** One teacher card: day columns of time chips. */
	private static function render_teacher_card( string $teacher, array $slots, string $name ): string {
		// Group this teacher's slots by weekday, sorted by start time.
		$by_day = [];
		foreach ( $slots as $slot ) {
			$day = '' !== $slot['day'] ? $slot['day'] : OFB_Slots::clean_day( $slot['label'] );
			$by_day[ $day ][] = $slot;
		}
		foreach ( $by_day as &$list ) {
			usort( $list, fn( $a, $b ) => $a['start_min'] <=> $b['start_min'] );
		}
		unset( $list );

		// Collapse long columns behind "show all times" (chips beyond this are hidden by CSS).
		$collapse_after = 5;
		$max_in_col = 0;
		foreach ( $by_day as $list ) {
			$max_in_col = max( $max_in_col, count( $list ) );
		}
		$collapsible = $max_in_col > $collapse_after;

		ob_start();
		echo '<div class="ofb-teacher-card' . ( $collapsible ? ' is-collapsed' : '' ) . '" data-ofb-teacher>';
		echo '<div class="ofb-teacher-card__head">' . esc_html( $teacher ) . '</div>';
		echo '<div class="ofb-day-grid">';
		foreach ( OFB_Slots::DAYS as $day ) {
			if ( empty( $by_day[ $day ] ) ) {
				continue;
			}
			echo '<div class="ofb-day-col">';
			echo '<div class="ofb-day-col__head">' . esc_html( OFB_Slots::day_label( $day ) ) . '</div>';
			foreach ( $by_day[ $day ] as $slot ) {
				$full = OFB_Slots::is_full( $slot );
				$chip_text = '' !== $slot['time_label'] ? $slot['time_label'] : $slot['label'];
				$excl = self::format_exceptions( $slot['exceptions'] ?? [] );
				$note = '';
				if ( $full ) {
					$note = '<span class="ofb-chip__full">' . esc_html__( 'Full', 'open-form-builder' ) . '</span>';
				} elseif ( '' !== $excl ) {
					$note = '<span class="ofb-chip__excl">' . esc_html( $excl ) . '</span>';
				}
				printf(
					'<label class="ofb-chip%s"><input type="checkbox" name="%s[]" value="%s"%s><span class="ofb-chip__time">%s</span>%s</label>',
					$full ? ' is-full' : '',
					esc_attr( $name ),
					esc_attr( $slot['slot_key'] ),
					$full ? ' disabled' : '',
					esc_html( $chip_text ),
					$note
				);
			}
			echo '</div>';
		}
		echo '</div>';
		if ( $collapsible ) {
			echo '<button type="button" class="ofb-showtimes" data-ofb-showtimes>' . esc_html__( 'Show all times', 'open-form-builder' ) . '</button>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Resolve the periods (tabs + date ranges) for the picker. Prefers the
	 * per-form session_picker.periods; falls back to the field's legacy tabs.
	 */
	private static function resolve_periods( array $sp, array $cfg ): array {
		$periods = is_array( $sp['periods'] ?? null ) ? $sp['periods'] : [];
		if ( ! empty( $periods ) ) {
			return $periods;
		}
		$tabs = is_array( $cfg['tabs'] ?? null ) ? $cfg['tabs'] : [];
		return array_map( fn( $t ) => [
			'key'    => $t['key'] ?? '',
			'label'  => $t['label'] ?? ( $t['key'] ?? '' ),
			'ranges' => [],
		], $tabs );
	}

	/** "Sessions run 1 Aug 2026 – 20 Sep 2026" (joins multiple ranges). */
	private static function format_ranges( array $ranges ): string {
		$parts = [];
		foreach ( $ranges as $r ) {
			$start = $r['start'] ?? '';
			$end   = $r['end'] ?? '';
			if ( '' === $start && '' === $end ) {
				continue;
			}
			$fmt = fn( $d ) => $d ? date_i18n( 'j M Y', strtotime( $d ) ) : '…';
			$parts[] = $fmt( $start ) . ' – ' . $fmt( $end );
		}
		if ( empty( $parts ) ) {
			return '';
		}
		/* translators: %s: one or more date ranges */
		return sprintf( __( 'Sessions run %s', 'open-form-builder' ), implode( ', ', $parts ) );
	}

	/** "excl. 11 Aug, 18 Aug" for a slot's blackout dates (empty when none). */
	private static function format_exceptions( array $dates ): string {
		$dates = OFB_Slots::clean_exceptions( $dates );
		if ( empty( $dates ) ) {
			return '';
		}
		sort( $dates );
		$fmt = array_map( fn( $d ) => date_i18n( 'j M', strtotime( $d ) ), $dates );
		/* translators: %s: comma-separated dates the session does not run */
		return sprintf( __( 'excl. %s', 'open-form-builder' ), implode( ', ', $fmt ) );
	}
}
