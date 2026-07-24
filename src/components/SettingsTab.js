/**
 * Per-form settings: payments, redirects, Google Sheet export, success message
 * and custom JS. (Site-wide Stripe API keys live on the plugin Settings page.)
 */
import { __ } from '@wordpress/i18n';
import {
	Card, CardBody, CardHeader, ToggleControl, TextControl, TextareaControl, SelectControl, ExternalLink,
} from '@wordpress/components';
import { api } from '../api';

export default function SettingsTab( { form, onChange } ) {
	const settings = form.settings;
	function set( partial ) { onChange( { ...settings, ...partial } ); }
	const pay = settings.payments;
	const sheet = settings.sheet_export;
	const marketing = settings.marketing || { enabled: false, provider: 'mailchimp', list_id: '', email_field: '', name_field: '', tags: '', double_optin: false };
	function setMarketing( partial ) { set( { marketing: { ...marketing, ...partial } } ); }

	const fieldNames = [];
	( form.schema.steps || [] ).forEach( ( s ) => s.fields.forEach( ( f ) => {
		if ( f.name && f.type !== 'html' ) { fieldNames.push( { value: f.name, label: f.label || f.name } ); }
	} ) );

	return (
		<div>
			<Card>
				<CardHeader><strong>{ __( 'Payments', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( 'Collect payment via Stripe Checkout', 'open-form-builder' ) }
						checked={ !! pay.enabled }
						onChange={ ( enabled ) => set( { payments: { ...pay, enabled } } ) }
					/>
					{ pay.enabled && (
						<>
							<TextControl label={ __( 'Currency (ISO, e.g. aud)', 'open-form-builder' ) } value={ pay.currency } onChange={ ( currency ) => set( { payments: { ...pay, currency } } ) } />
							<TextControl label={ __( 'Line item label', 'open-form-builder' ) } value={ pay.product_label } onChange={ ( product_label ) => set( { payments: { ...pay, product_label } } ) } />
							<p className="ofb-help">
								{ __( 'Stripe API keys are set once for the whole site on the ', 'open-form-builder' ) }
								<ExternalLink href={ api.admin.settingsUrl }>{ __( 'Settings page', 'open-form-builder' ) }</ExternalLink>.
							</p>
						</>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader><strong>{ __( 'After submission', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Thank-you / redirect URL', 'open-form-builder' ) }
						value={ settings.redirects.thank_you_url }
						onChange={ ( v ) => set( { redirects: { ...settings.redirects, thank_you_url: v } } ) }
						help={ __( 'Used after payment success, or after submit for non-payment forms.', 'open-form-builder' ) }
					/>
					<TextControl
						label={ __( 'Success message (when no redirect)', 'open-form-builder' ) }
						value={ settings.messages.success }
						onChange={ ( v ) => set( { messages: { ...settings.messages, success: v } } ) }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader><strong>{ __( 'Google Sheet export', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( 'POST each submission to a Google Apps Script web app', 'open-form-builder' ) }
						checked={ !! sheet.enabled }
						onChange={ ( enabled ) => set( { sheet_export: { ...sheet, enabled } } ) }
					/>
					{ sheet.enabled && (
						<TextControl
							label={ __( 'Apps Script web-app URL', 'open-form-builder' ) }
							value={ sheet.webhook_url }
							onChange={ ( webhook_url ) => set( { sheet_export: { ...sheet, webhook_url } } ) }
							help={ __( 'See the Apps Script snippet in the plugin docs (docs/apps-script.md).', 'open-form-builder' ) }
						/>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader><strong>{ __( 'Email marketing', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( 'Subscribe submitters to Mailchimp / MailerLite', 'open-form-builder' ) }
						checked={ !! marketing.enabled }
						onChange={ ( enabled ) => setMarketing( { enabled } ) }
					/>
					{ marketing.enabled && (
						<>
							<SelectControl
								label={ __( 'Provider', 'open-form-builder' ) }
								value={ marketing.provider }
								options={ [ { value: 'mailchimp', label: 'Mailchimp' }, { value: 'mailerlite', label: 'MailerLite' } ] }
								onChange={ ( provider ) => setMarketing( { provider } ) }
							/>
							<TextControl
								label={ marketing.provider === 'mailerlite' ? __( 'Group ID', 'open-form-builder' ) : __( 'Audience (List) ID', 'open-form-builder' ) }
								value={ marketing.list_id }
								onChange={ ( list_id ) => setMarketing( { list_id } ) }
							/>
							<SelectControl
								label={ __( 'Email field', 'open-form-builder' ) }
								value={ marketing.email_field }
								options={ [ { value: '', label: __( '— first email field —', 'open-form-builder' ) }, ...fieldNames ] }
								onChange={ ( email_field ) => setMarketing( { email_field } ) }
							/>
							<SelectControl
								label={ __( 'Name field (optional)', 'open-form-builder' ) }
								value={ marketing.name_field }
								options={ [ { value: '', label: __( '— none —', 'open-form-builder' ) }, ...fieldNames ] }
								onChange={ ( name_field ) => setMarketing( { name_field } ) }
							/>
							<TextControl
								label={ __( 'Tags (comma-separated, optional)', 'open-form-builder' ) }
								value={ marketing.tags }
								onChange={ ( tags ) => setMarketing( { tags } ) }
							/>
							{ marketing.provider === 'mailchimp' && (
								<ToggleControl
									label={ __( 'Double opt-in (send Mailchimp confirmation email)', 'open-form-builder' ) }
									checked={ !! marketing.double_optin }
									onChange={ ( double_optin ) => setMarketing( { double_optin } ) }
								/>
							) }
							<p className="ofb-help">
								{ __( 'Add your provider API key once on the ', 'open-form-builder' ) }
								<ExternalLink href={ api.admin.settingsUrl }>{ __( 'Settings page', 'open-form-builder' ) }</ExternalLink>.
								{ __( ' People are synced only after a completed (paid or free) submission.', 'open-form-builder' ) }
							</p>
						</>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader><strong>{ __( 'Custom JavaScript', 'open-form-builder' ) }</strong></CardHeader>
				<CardBody>
					<TextareaControl
						label={ __( 'Runs on pages where this form appears', 'open-form-builder' ) }
						value={ settings.custom_js }
						onChange={ ( custom_js ) => set( { custom_js } ) }
						rows={ 6 }
						help={ __( 'Only saved for users who can edit unfiltered HTML.', 'open-form-builder' ) }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
