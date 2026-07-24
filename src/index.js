/**
 * Open Form Builder — admin app entry. Mounts into #ofb-app and switches between
 * the form list and the editor. No router; a tiny bit of local state is enough.
 */
import { createRoot, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SnackbarList } from '@wordpress/components';
import FormList from './components/FormList';
import Editor from './components/Editor';
import './editor.scss';

function App() {
	const [ view, setView ] = useState( { name: 'list', id: 0 } );
	const [ notices, setNotices ] = useState( [] );

	function notify( content, status = 'success' ) {
		const id = Date.now();
		setNotices( ( n ) => [ ...n, { id, content, status } ] );
		setTimeout( () => setNotices( ( n ) => n.filter( ( x ) => x.id !== id ) ), 4000 );
	}

	return (
		<div className="ofb-admin">
			<h1 className="ofb-admin__title">{ __( 'Open Form Builder', 'open-form-builder' ) }</h1>

			{ view.name === 'list' && (
				<FormList
					onEdit={ ( id ) => setView( { name: 'editor', id } ) }
					onNew={ () => setView( { name: 'editor', id: 0 } ) }
					notify={ notify }
				/>
			) }

			{ view.name === 'editor' && (
				<Editor
					formId={ view.id }
					onBack={ () => setView( { name: 'list', id: 0 } ) }
					notify={ notify }
				/>
			) }

			<SnackbarList
				notices={ notices }
				className="ofb-admin__snackbars"
				onRemove={ ( id ) => setNotices( ( n ) => n.filter( ( x ) => x.id !== id ) ) }
			/>
		</div>
	);
}

const el = document.getElementById( 'ofb-app' );
if ( el ) {
	createRoot( el ).render( <App /> );
}
