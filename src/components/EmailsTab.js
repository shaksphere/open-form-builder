/**
 * Emails tab: confirmation + receipt templates (with {tag} support) and the
 * CF7-style conditional send-to routing based on one field's value.
 */
import { __ } from '@wordpress/i18n';
import {
	Card, CardBody, CardHeader, ToggleControl, TextControl, TextareaControl,
	SelectControl, Flex, FlexItem, Button,
} from '@wordpress/components';

export default function EmailsTab( { form, onChange } ) {
	const settings = form.settings;
	const emails = settings.emails;
	function setEmails( partial ) { onChange( { ...settings, emails: { ...emails, ...partial } } ); }

	const fieldNames = [];
	( form.schema.steps || [] ).forEach( ( s ) => s.fields.forEach( ( f ) => {
		if ( f.name && f.type !== 'html' ) { fieldNames.push( { value: f.name, label: f.label || f.name } ); }
	} ) );

	return (
		<div>
			<MailCard
				title={ __( 'Confirmation email', 'open-form-builder' ) }
				help={ __( 'Sent to the resolved recipient (routing below, or the address/tag here).', 'open-form-builder' ) }
				mail={ emails.confirmation }
				showTo
				onChange={ ( m ) => setEmails( { confirmation: m } ) }
			/>

			<MailCard
				title={ __( 'Receipt email', 'open-form-builder' ) }
				help={ __( 'Sent to the submitter. Leave "to" blank to use the first email field, or use a {tag}.', 'open-form-builder' ) }
				mail={ emails.receipt }
				showTo
				onChange={ ( m ) => setEmails( { receipt: m } ) }
			/>

			<Card>
				<CardHeader><strong>{ __( 'Conditional send-to routing', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<p>{ __( 'Override the confirmation recipient based on the value of one field.', 'open-form-builder' ) }</p>
					<SelectControl
						label={ __( 'Routing field', 'open-form-builder' ) }
						value={ emails.routing.field }
						options={ [ { value: '', label: __( '— none —', 'open-form-builder' ) }, ...fieldNames ] }
						onChange={ ( field ) => setEmails( { routing: { ...emails.routing, field } } ) }
					/>
					{ emails.routing.field && (
						<>
							{ ( emails.routing.map || [] ).map( ( row, i ) => (
								<Flex key={ i } className="ofb-options__row">
									<FlexItem isBlock>
										<TextControl placeholder={ __( 'when value equals…', 'open-form-builder' ) } value={ row.value } onChange={ ( value ) => setEmails( { routing: { ...emails.routing, map: emails.routing.map.map( ( r, j ) => ( j === i ? { ...r, value } : r ) ) } } ) } __nextHasNoMarginBottom />
									</FlexItem>
									<FlexItem isBlock>
										<TextControl placeholder={ __( 'send to email', 'open-form-builder' ) } value={ row.email } onChange={ ( email ) => setEmails( { routing: { ...emails.routing, map: emails.routing.map.map( ( r, j ) => ( j === i ? { ...r, email } : r ) ) } } ) } __nextHasNoMarginBottom />
									</FlexItem>
									<FlexItem>
										<Button isDestructive variant="tertiary" onClick={ () => setEmails( { routing: { ...emails.routing, map: emails.routing.map.filter( ( _, j ) => j !== i ) } } ) }>×</Button>
									</FlexItem>
								</Flex>
							) ) }
							<Button variant="secondary" onClick={ () => setEmails( { routing: { ...emails.routing, map: [ ...( emails.routing.map || [] ), { value: '', email: '' } ] } } ) }>
								{ __( 'Add routing rule', 'open-form-builder' ) }
							</Button>
							<TextControl
								label={ __( 'Default address (no rule matched)', 'open-form-builder' ) }
								value={ emails.routing.default }
								onChange={ ( d ) => setEmails( { routing: { ...emails.routing, default: d } } ) }
							/>
						</>
					) }
				</CardBody>
			</Card>

			<p className="ofb-help">{ __( 'Tags: {field_name} for any field, plus {all_fields}, {amount}, {submission_id}, {site_name}.', 'open-form-builder' ) }</p>
		</div>
	);
}

function MailCard( { title, help, mail, onChange, showTo } ) {
	function set( partial ) { onChange( { ...mail, ...partial } ); }
	return (
		<Card className="ofb-mail-card">
			<CardHeader>
				<strong>{ title }</strong>
				<ToggleControl checked={ !! mail.enabled } onChange={ ( enabled ) => set( { enabled } ) } label={ __( 'Enabled', 'open-form-builder' ) } />
			</CardHeader>
			{ mail.enabled && (
				<CardBody>
					<p className="ofb-help">{ help }</p>
					{ showTo && <TextControl label={ __( 'To', 'open-form-builder' ) } value={ mail.to || '' } onChange={ ( to ) => set( { to } ) } /> }
					<TextControl label={ __( 'Subject', 'open-form-builder' ) } value={ mail.subject || '' } onChange={ ( subject ) => set( { subject } ) } />
					<TextareaControl label={ __( 'Body', 'open-form-builder' ) } value={ mail.body || '' } onChange={ ( body ) => set( { body } ) } rows={ 6 } />
				</CardBody>
			) }
		</Card>
	);
}
