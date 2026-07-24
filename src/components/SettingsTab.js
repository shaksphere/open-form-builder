/**
 * Per-form settings: payments, redirects, Google Sheet export, success message
 * and custom JS. (Site-wide Stripe API keys live on the plugin Settings page.)
 */
import { __ } from '@wordpress/i18n';
import {
	Card, CardBody, CardHeader, ToggleControl, TextControl, TextareaControl, ExternalLink,
} from '@wordpress/components';
import { api } from '../api';

export default function SettingsTab( { settings, onChange } ) {
	function set( partial ) { onChange( { ...settings, ...partial } ); }
	const pay = settings.payments;
	const sheet = settings.sheet_export;

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
