/**
 * The form editor shell: loads/creates a form, holds the whole form object in
 * state, exposes a single `patch` helper to children, and saves to REST.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, TabPanel, Spinner, TextControl, Flex, FlexItem } from '@wordpress/components';
import { api } from '../api';
import { blankForm } from '../defaults';
import BuildTab from './BuildTab';
import SessionsTab from './SessionsTab';
import PricingTab from './PricingTab';
import EmailsTab from './EmailsTab';
import SettingsTab from './SettingsTab';
import SubmissionsTab from './SubmissionsTab';

export default function Editor( { formId, initialForm, onBack, notify } ) {
	const [ form, setForm ] = useState( formId ? null : ( initialForm ? { id: 0, ...initialForm } : blankForm() ) );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		if ( formId ) {
			api.getForm( formId ).then( setForm ).catch( () => { notify( __( 'Could not load form.', 'open-form-builder' ), 'error' ); onBack(); } );
		}
	}, [ formId ] );

	if ( ! form ) {
		return <Spinner />;
	}

	// Generic immutable patch: patch({name}) or patch over a nested path helper.
	function update( partial ) {
		setForm( ( f ) => ( { ...f, ...partial } ) );
	}
	function updateSchema( schema ) { update( { schema } ); }
	function updateSettings( settings ) { update( { settings } ); }
	function updateSlots( slots ) { update( { slots } ); }

	function save() {
		setSaving( true );
		const body = {
			name: form.name,
			schema: form.schema,
			settings: form.settings,
			slots: form.slots,
		};
		const req = form.id ? api.updateForm( form.id, body ) : api.createForm( body );
		req.then( ( saved ) => {
			setForm( saved );
			notify( __( 'Form saved.', 'open-form-builder' ) );
		} ).catch( () => {
			notify( __( 'Save failed.', 'open-form-builder' ), 'error' );
		} ).finally( () => setSaving( false ) );
	}

	const tabs = [
		{ name: 'build', title: __( 'Build', 'open-form-builder' ) },
		{ name: 'sessions', title: __( 'Sessions', 'open-form-builder' ) },
		{ name: 'pricing', title: __( 'Pricing', 'open-form-builder' ) },
		{ name: 'emails', title: __( 'Emails', 'open-form-builder' ) },
		{ name: 'settings', title: __( 'Settings', 'open-form-builder' ) },
	];
	if ( form.id ) {
		tabs.push( { name: 'submissions', title: __( 'Submissions', 'open-form-builder' ) } );
	}

	return (
		<div className="ofb-editor">
			<Flex className="ofb-editor__bar">
				<FlexItem isBlock>
					<TextControl
						label={ __( 'Form name', 'open-form-builder' ) }
						value={ form.name }
						onChange={ ( name ) => update( { name } ) }
						__nextHasNoMarginBottom
					/>
				</FlexItem>
				<FlexItem>
					<Button variant="tertiary" onClick={ onBack }>{ __( '← All forms', 'open-form-builder' ) }</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
						{ __( 'Save form', 'open-form-builder' ) }
					</Button>
				</FlexItem>
			</Flex>

			<TabPanel className="ofb-editor__tabs" tabs={ tabs }>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'build':
							return <BuildTab schema={ form.schema } onChange={ updateSchema } />;
						case 'sessions':
							return <SessionsTab form={ form } onChangeSlots={ updateSlots } onChangeSettings={ updateSettings } />;
						case 'pricing':
							return <PricingTab settings={ form.settings } onChange={ updateSettings } />;
						case 'emails':
							return <EmailsTab form={ form } onChange={ updateSettings } />;
						case 'settings':
							return <SettingsTab form={ form } onChange={ updateSettings } />;
						case 'submissions':
							return <SubmissionsTab formId={ form.id } />;
						default:
							return null;
					}
				} }
			</TabPanel>
		</div>
	);
}
