/**
 * Sessions tab: define the periods (tabs + custom date ranges) and the slots
 * (teacher + day + time + capacity) for the session picker.
 *
 * Slots are stored structured (day/time_label); the server rebuilds the display
 * label and derives the sort time. booked_count is read-only — depletion is
 * driven by paid submissions.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card, CardBody, CardHeader, TextControl, Button, Flex, FlexItem, Notice,
	CheckboxControl, __experimentalNumberControl as NumberControl, SelectControl,
} from '@wordpress/components';
import { uid } from '../defaults';

const DAYS = [
	{ value: 'mon', label: 'Monday' }, { value: 'tue', label: 'Tuesday' },
	{ value: 'wed', label: 'Wednesday' }, { value: 'thu', label: 'Thursday' },
	{ value: 'fri', label: 'Friday' }, { value: 'sat', label: 'Saturday' },
	{ value: 'sun', label: 'Sunday' },
];

// JS getDay() index for each weekday key (Sunday = 0).
const DAY_INDEX = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };

/** All dates a slot would run: its weekday across its period's date ranges. */
function slotDates( periods, slot ) {
	const period = ( periods || [] ).find( ( p ) => p.key === slot.tab );
	if ( ! period ) { return []; }
	const wanted = DAY_INDEX[ slot.day ];
	if ( wanted === undefined ) { return []; }
	const out = [];
	( period.ranges || [] ).forEach( ( r ) => {
		if ( ! r.start || ! r.end ) { return; }
		const start = new Date( r.start + 'T00:00:00' );
		const end = new Date( r.end + 'T00:00:00' );
		for ( let d = new Date( start ); d <= end; d.setDate( d.getDate() + 1 ) ) {
			if ( d.getDay() === wanted ) {
				out.push( d.toISOString().slice( 0, 10 ) );
			}
		}
	} );
	return out;
}

function fmtDate( iso ) {
	const d = new Date( iso + 'T00:00:00' );
	return d.toLocaleDateString( undefined, { day: 'numeric', month: 'short' } );
}

export default function SessionsTab( { form, onChangeSlots, onChangeSettings } ) {
	const slots = form.slots || [];
	const sp = form.settings.session_picker || { min: 4, max: null, periods: [] };
	const periods = sp.periods && sp.periods.length
		? sp.periods
		: [ { key: 'term', label: 'School Term Availability', ranges: [ { start: '', end: '' } ] } ];

	function setPeriods( next ) {
		onChangeSettings( { ...form.settings, session_picker: { ...sp, periods: next } } );
	}

	// ---- slots ----
	function update( i, partial ) {
		onChangeSlots( slots.map( ( s, j ) => ( j === i ? { ...s, ...partial } : s ) ) );
	}
	function add() {
		onChangeSlots( [ ...slots, {
			slot_key: uid( 'slot' ),
			tab: periods[ 0 ].key,
			teacher: '',
			day: 'mon',
			time_label: '',
			capacity: 1,
			booked_count: 0,
			exceptions: [],
		} ] );
	}

	return (
		<div>
			<PeriodsEditor periods={ periods } setPeriods={ setPeriods } />

			<Card>
				<CardHeader><strong>{ __( 'Slots', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<p>{ __( 'Each slot is one teacher available at a weekday + 30-minute time, with its own capacity. A full slot renders disabled. Bookings are consumed only on successful payment (first-pay-wins).', 'open-form-builder' ) }</p>

					{ slots.length === 0 && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'No slots yet. Add teacher availability below (or import from the spreadsheet).', 'open-form-builder' ) }
						</Notice>
					) }

					{ slots.map( ( slot, i ) => (
						<div key={ slot.slot_key || i } className="ofb-slot-row">
							<Flex align="flex-end" wrap>
								<FlexItem isBlock>
									<TextControl label={ __( 'Teacher', 'open-form-builder' ) } value={ slot.teacher || '' } onChange={ ( teacher ) => update( i, { teacher } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem>
									<SelectControl label={ __( 'Day', 'open-form-builder' ) } value={ slot.day || 'mon' } options={ DAYS } onChange={ ( day ) => update( i, { day } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem isBlock>
									<TextControl label={ __( 'Time (e.g. 5:00-5:30 pm)', 'open-form-builder' ) } value={ slot.time_label || '' } onChange={ ( time_label ) => update( i, { time_label } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem>
									<SelectControl label={ __( 'Period', 'open-form-builder' ) } value={ slot.tab } options={ periods.map( ( p ) => ( { value: p.key, label: p.label || p.key } ) ) } onChange={ ( tab ) => update( i, { tab } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem>
									<NumberControl label={ __( 'Capacity', 'open-form-builder' ) } value={ slot.capacity } min={ 0 } onChange={ ( v ) => update( i, { capacity: parseInt( v || 0, 10 ) } ) } />
								</FlexItem>
								<FlexItem>
									<span className="ofb-slot-booked">{ __( 'Booked', 'open-form-builder' ) }: { slot.booked_count || 0 }</span>
								</FlexItem>
								<FlexItem>
									<Button isDestructive variant="tertiary" onClick={ () => onChangeSlots( slots.filter( ( _, j ) => j !== i ) ) }>×</Button>
								</FlexItem>
							</Flex>
							<SlotExceptions slot={ slot } periods={ periods } onChange={ ( exceptions ) => update( i, { exceptions } ) } />
						</div>
					) ) }

					<Button variant="secondary" onClick={ add }>{ __( 'Add slot', 'open-form-builder' ) }</Button>
				</CardBody>
			</Card>
		</div>
	);
}

/**
 * Per-slot blackout manager: lists every date this slot would run (its weekday
 * across its period's date ranges) and lets the admin disable specific ones —
 * e.g. the teacher is away that week. Stored as ISO dates in slot.exceptions.
 */
function SlotExceptions( { slot, periods, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const dates = slotDates( periods, slot );
	const disabled = slot.exceptions || [];

	if ( dates.length === 0 ) {
		return (
			<p className="ofb-slot-exceptions__hint">
				{ __( 'Set this period’s date range to manage unavailable weeks.', 'open-form-builder' ) }
			</p>
		);
	}

	function toggle( iso, off ) {
		const next = off ? [ ...disabled, iso ] : disabled.filter( ( d ) => d !== iso );
		onChange( Array.from( new Set( next ) ) );
	}

	return (
		<div className="ofb-slot-exceptions">
			<Button variant="link" onClick={ () => setOpen( ! open ) }>
				{ open
					? __( 'Hide unavailable weeks', 'open-form-builder' )
					: sprintf(
						/* translators: 1: disabled count, 2: total occurrences */
						__( 'Unavailable weeks (%1$d of %2$d disabled)', 'open-form-builder' ),
						disabled.length, dates.length
					) }
			</Button>
			{ open && (
				<div className="ofb-slot-exceptions__grid">
					{ dates.map( ( iso ) => (
						<CheckboxControl
							key={ iso }
							label={ fmtDate( iso ) }
							checked={ disabled.includes( iso ) }
							onChange={ ( off ) => toggle( iso, off ) }
							__nextHasNoMarginBottom
						/>
					) ) }
				</div>
			) }
		</div>
	);
}

function PeriodsEditor( { periods, setPeriods } ) {
	function patch( i, partial ) {
		setPeriods( periods.map( ( p, j ) => ( j === i ? { ...p, ...partial } : p ) ) );
	}
	function patchRange( pi, ri, partial ) {
		patch( pi, { ranges: periods[ pi ].ranges.map( ( r, j ) => ( j === ri ? { ...r, ...partial } : r ) ) } );
	}

	return (
		<Card style={ { marginBottom: '1rem' } }>
			<CardHeader><strong>{ __( 'Periods (tabs & date windows)', 'open-form-builder' ) }</strong></CardHeader>
			<CardBody>
				<p>{ __( 'Each period becomes a tab. Set the custom date range(s) it runs between — these do not have to follow the official school calendar.', 'open-form-builder' ) }</p>
				{ periods.map( ( period, pi ) => (
					<div key={ period.key } className="ofb-period">
						<Flex align="flex-end">
							<FlexItem isBlock>
								<TextControl label={ __( 'Tab label', 'open-form-builder' ) } value={ period.label } onChange={ ( label ) => patch( pi, { label } ) } __nextHasNoMarginBottom />
							</FlexItem>
							<FlexItem>
								<TextControl label={ __( 'Key', 'open-form-builder' ) } value={ period.key } disabled help={ __( 'matches slot period', 'open-form-builder' ) } __nextHasNoMarginBottom />
							</FlexItem>
							<FlexItem>
								<Button isDestructive variant="tertiary" onClick={ () => setPeriods( periods.filter( ( _, j ) => j !== pi ) ) }>{ __( 'Remove', 'open-form-builder' ) }</Button>
							</FlexItem>
						</Flex>
						{ ( period.ranges || [] ).map( ( range, ri ) => (
							<Flex key={ ri } align="flex-end" className="ofb-range-row">
								<FlexItem isBlock>
									<TextControl type="date" label={ __( 'Start', 'open-form-builder' ) } value={ range.start || '' } onChange={ ( start ) => patchRange( pi, ri, { start } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem isBlock>
									<TextControl type="date" label={ __( 'End', 'open-form-builder' ) } value={ range.end || '' } onChange={ ( end ) => patchRange( pi, ri, { end } ) } __nextHasNoMarginBottom />
								</FlexItem>
								<FlexItem>
									<Button isDestructive variant="tertiary" onClick={ () => patch( pi, { ranges: period.ranges.filter( ( _, j ) => j !== ri ) } ) }>×</Button>
								</FlexItem>
							</Flex>
						) ) }
						<Button variant="link" onClick={ () => patch( pi, { ranges: [ ...( period.ranges || [] ), { start: '', end: '' } ] } ) }>
							{ __( '+ Add date range', 'open-form-builder' ) }
						</Button>
					</div>
				) ) }
				<Button variant="secondary" onClick={ () => setPeriods( [ ...periods, { key: uid( 'period' ), label: '', ranges: [ { start: '', end: '' } ] } ] ) }>
					{ __( 'Add period', 'open-form-builder' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
