/**
 * Form list: existing forms with shortcode, plus create-new and CF7 import.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, Spinner, ClipboardButton, Modal, TextareaControl } from '@wordpress/components';
import { api } from '../api';

export default function FormList( { onEdit, onNew, notify } ) {
	const [ forms, setForms ] = useState( null );
	const [ importing, setImporting ] = useState( false );

	useEffect( () => { load(); }, [] );

	function load() {
		api.listForms().then( setForms ).catch( () => setForms( [] ) );
	}

	function remove( id ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this form? Submissions are kept.', 'open-form-builder' ) ) ) {
			return;
		}
		api.deleteForm( id ).then( () => { notify( __( 'Form deleted.', 'open-form-builder' ) ); load(); } );
	}

	if ( forms === null ) {
		return <Spinner />;
	}

	return (
		<div className="ofb-list">
			<div className="ofb-list__actions">
				<Button variant="primary" onClick={ onNew }>{ __( 'Add new form', 'open-form-builder' ) }</Button>
				<Button variant="secondary" onClick={ () => setImporting( true ) }>{ __( 'Import from CF7', 'open-form-builder' ) }</Button>
			</div>

			{ forms.length === 0 && <p>{ __( 'No forms yet. Create your first one.', 'open-form-builder' ) }</p> }

			{ forms.map( ( f ) => (
				<Card key={ f.id } className="ofb-list__item">
					<CardBody>
						<div className="ofb-list__row">
							<div>
								<strong>{ f.name }</strong>
								<div className="ofb-list__shortcode">
									<code>{ `[open_form id="${ f.id }"]` }</code>
									<ClipboardButton
										text={ `[open_form id="${ f.id }"]` }
										variant="tertiary"
										onCopy={ () => notify( __( 'Shortcode copied.', 'open-form-builder' ) ) }
									>
										{ __( 'Copy', 'open-form-builder' ) }
									</ClipboardButton>
								</div>
							</div>
							<div className="ofb-list__buttons">
								<Button variant="secondary" onClick={ () => onEdit( f.id ) }>{ __( 'Edit', 'open-form-builder' ) }</Button>
								<Button isDestructive variant="tertiary" onClick={ () => remove( f.id ) }>{ __( 'Delete', 'open-form-builder' ) }</Button>
							</div>
						</div>
					</CardBody>
				</Card>
			) ) }

			{ importing && <ImportModal onClose={ () => setImporting( false ) } onEdit={ onEdit } notify={ notify } /> }
		</div>
	);
}

function ImportModal( { onClose, onEdit, notify } ) {
	const [ source, setSource ] = useState( '' );
	const [ mail, setMail ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	function run() {
		setBusy( true );
		api.importCf7( source, mail )
			.then( ( draft ) => api.createForm( {
				name: draft.name,
				schema: draft.schema,
				settings: draft.settings,
				slots: [],
			} ) )
			.then( ( created ) => {
				notify( __( 'CF7 form imported. Review and add any conditional logic.', 'open-form-builder' ) );
				onClose();
				onEdit( created.id );
			} )
			.catch( () => { notify( __( 'Import failed.', 'open-form-builder' ), 'error' ); setBusy( false ); } );
	}

	return (
		<Modal title={ __( 'Import a Contact Form 7 form', 'open-form-builder' ) } onRequestClose={ onClose }>
			<p>{ __( 'Paste the CF7 form template (fields) and, optionally, the mail body. Conditional logic is not imported — add it after.', 'open-form-builder' ) }</p>
			<TextareaControl
				label={ __( 'CF7 form template', 'open-form-builder' ) }
				value={ source }
				onChange={ setSource }
				rows={ 8 }
			/>
			<TextareaControl
				label={ __( 'CF7 mail body (optional)', 'open-form-builder' ) }
				value={ mail }
				onChange={ setMail }
				rows={ 5 }
			/>
			<div className="ofb-modal__actions">
				<Button variant="primary" onClick={ run } isBusy={ busy } disabled={ busy || ! source.trim() }>
					{ __( 'Import', 'open-form-builder' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'open-form-builder' ) }</Button>
			</div>
		</Modal>
	);
}
