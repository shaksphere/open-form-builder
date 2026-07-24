/**
 * Submissions tab: read-only listing of entries for this form, with payment
 * status and a staff flag for over-capacity bookings.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Card, CardBody } from '@wordpress/components';
import { api } from '../api';

export default function SubmissionsTab( { formId } ) {
	const [ rows, setRows ] = useState( null );

	useEffect( () => {
		api.listSubmissions( formId ).then( setRows ).catch( () => setRows( [] ) );
	}, [ formId ] );

	if ( rows === null ) { return <Spinner />; }
	if ( rows.length === 0 ) { return <Card><CardBody>{ __( 'No submissions yet.', 'open-form-builder' ) }</CardBody></Card>; }

	return (
		<table className="ofb-submissions widefat striped">
			<thead>
				<tr>
					<th>{ __( 'ID', 'open-form-builder' ) }</th>
					<th>{ __( 'When', 'open-form-builder' ) }</th>
					<th>{ __( 'Status', 'open-form-builder' ) }</th>
					<th>{ __( 'Amount', 'open-form-builder' ) }</th>
					<th>{ __( 'Fields', 'open-form-builder' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r ) => (
					<tr key={ r.id }>
						<td>{ r.id }</td>
						<td>{ r.created_at }</td>
						<td>
							{ r.payment_status }
							{ r.flagged ? <strong className="ofb-flag"> ⚑ { __( 'over capacity', 'open-form-builder' ) }</strong> : null }
						</td>
						<td>{ r.amount_cents ? '$' + ( r.amount_cents / 100 ).toFixed( 2 ) + ' ' + ( r.currency || '' ).toUpperCase() : '—' }</td>
						<td>
							{ Object.keys( r.data || {} ).map( ( k ) => {
								const v = r.data[ k ].value;
								return <div key={ k }><strong>{ r.data[ k ].label }:</strong> { Array.isArray( v ) ? v.join( ', ' ) : String( v ) }</div>;
							} ) }
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
