/**
 * Factories + constants shared across the builder. The shapes here mirror
 * OFB_Schema / OFB_Security on the PHP side exactly.
 */

let counter = 0;
export function uid( prefix ) {
	counter += 1;
	return `${ prefix }_${ Date.now().toString( 36 ) }${ counter }`;
}

export const FIELD_TYPES = [
	{ value: 'text', label: 'Text' },
	{ value: 'email', label: 'Email' },
	{ value: 'tel', label: 'Phone' },
	{ value: 'number', label: 'Number' },
	{ value: 'date', label: 'Date' },
	{ value: 'time', label: 'Time' },
	{ value: 'textarea', label: 'Text area' },
	{ value: 'select', label: 'Select' },
	{ value: 'dropdown', label: 'Dropdown' },
	{ value: 'radio', label: 'Radio buttons' },
	{ value: 'checkbox', label: 'Checkboxes' },
	{ value: 'html', label: 'HTML content' },
	{ value: 'session_picker', label: 'Session picker' },
];

export const CHOICE_TYPES = [ 'select', 'dropdown', 'radio', 'checkbox' ];

export const OPERATORS = [
	{ value: 'is', label: 'is' },
	{ value: 'is_not', label: 'is not' },
	{ value: 'contains', label: 'contains' },
	{ value: 'not_contains', label: 'does not contain' },
	{ value: 'gt', label: 'greater than' },
	{ value: 'lt', label: 'less than' },
	{ value: 'gte', label: 'greater or equal' },
	{ value: 'lte', label: 'less or equal' },
	{ value: 'empty', label: 'is empty' },
	{ value: 'not_empty', label: 'is not empty' },
];

export function newField( type = 'text' ) {
	const field = {
		id: uid( 'fld' ),
		type,
		name: uid( 'field' ),
		label: 'New field',
		placeholder: '',
		help: '',
		required: false,
		conditional: { enabled: false, action: 'show', match: 'all', rules: [] },
	};
	if ( CHOICE_TYPES.includes( type ) ) {
		field.options = [ { label: 'Option 1', value: 'option-1', price: 0 } ];
	}
	if ( type === 'number' ) {
		field.config = { unit_price: 0 };
	}
	if ( type === 'html' ) {
		field.content = '<p>Your content here.</p>';
	}
	if ( type === 'session_picker' ) {
		field.name = 'sessions';
		field.label = 'Choose your sessions';
		field.config = {
			min: 4,
			max: null,
			tabs: [
				{ key: 'term', label: 'School Term' },
				{ key: 'holiday', label: 'School Holiday' },
			],
		};
	}
	return field;
}

export function newStep( index ) {
	return { id: uid( 'step' ), title: `Step ${ index }`, description: '', fields: [] };
}

export function blankForm() {
	return {
		id: 0,
		name: 'Untitled form',
		schema: { version: 1, steps: [ newStep( 1 ) ] },
		settings: defaultSettings(),
		slots: [],
	};
}

export function defaultSettings() {
	return {
		pricing: {
			enabled: false,
			mode: 'sessions',
			base_fee: 0,
			base_price: 80,
			base_sessions: 4,
			extra_session_price: 25,
			block_size: 4,
			block_discount: { type: 'amount', value: 20 },
		},
		session_picker: {
			min: 4,
			max: null,
			periods: [
				{ key: 'term', label: 'School Term Availability', ranges: [ { start: '', end: '' } ] },
				{ key: 'holiday', label: 'School Holiday Availability', ranges: [ { start: '', end: '' } ] },
			],
		},
		payments: { enabled: false, currency: 'aud', product_label: 'Sessions' },
		emails: {
			confirmation: { enabled: false, to: '', subject: '', body: '' },
			receipt: { enabled: false, to: '', subject: '', body: '' },
			routing: { field: '', map: [], default: '' },
		},
		marketing: {
			enabled: false,
			provider: 'mailchimp',
			list_id: '',
			email_field: '',
			name_field: '',
			tags: '',
			double_optin: false,
		},
		sheet_export: { enabled: false, webhook_url: '' },
		redirects: { thank_you_url: '' },
		messages: { success: 'Thank you. Your submission was received.' },
		custom_js: '',
	};
}
