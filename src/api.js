/**
 * Thin REST client for the builder. Wraps @wordpress/api-fetch with the plugin's
 * REST root + cookie nonce (provided by PHP in window.OFB_ADMIN).
 */
import apiFetch from '@wordpress/api-fetch';

const ADMIN = window.OFB_ADMIN || { restUrl: '', nonce: '' };

apiFetch.use( apiFetch.createNonceMiddleware( ADMIN.nonce ) );

function url( path ) {
	return ADMIN.restUrl.replace( /\/$/, '' ) + path;
}

export const api = {
	admin: ADMIN,

	listForms() {
		return apiFetch( { url: url( '/forms' ) } );
	},
	getForm( id ) {
		return apiFetch( { url: url( `/forms/${ id }` ) } );
	},
	createForm( body ) {
		return apiFetch( { url: url( '/forms' ), method: 'POST', data: body } );
	},
	updateForm( id, body ) {
		return apiFetch( { url: url( `/forms/${ id }` ), method: 'POST', data: body } );
	},
	deleteForm( id ) {
		return apiFetch( { url: url( `/forms/${ id }` ), method: 'DELETE' } );
	},
	listSubmissions( id ) {
		return apiFetch( { url: url( `/forms/${ id }/submissions` ) } );
	},
	importCf7( source, mail ) {
		return apiFetch( { url: url( '/import-cf7' ), method: 'POST', data: { source, mail } } );
	},
};
