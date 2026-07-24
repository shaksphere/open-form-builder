/**
 * Starter templates for the 5 most common form use-cases. Each builds a full,
 * ready-to-tweak form (schema + settings + slots) so a new user gets a working,
 * on-brand-looking form in one click instead of a blank canvas. Picking one just
 * pre-fills the (unsaved) editor state — nothing is written until "Save form".
 */
import { __ } from '@wordpress/i18n';
import { uid, defaultSettings } from './defaults';

function field( type, overrides = {} ) {
	return {
		id: uid( 'fld' ),
		type,
		name: uid( 'field' ),
		label: '',
		placeholder: '',
		help: '',
		required: false,
		conditional: { enabled: false, action: 'show', match: 'all', rules: [] },
		...overrides,
	};
}

function choiceField( type, name, label, options, layout = 'list', overrides = {} ) {
	return field( type, {
		name,
		label,
		options: options.map( ( o ) => ( { image: '', price: 0, ...o } ) ),
		config: { layout },
		required: true,
		...overrides,
	} );
}

function step( title, description, fields ) {
	return { id: uid( 'step' ), title, description, fields };
}

function theme( primary, extra = {} ) {
	return { primary, text: '#111827', background: '#ffffff', radius: 10, ...extra };
}

function settingsWith( partial ) {
	const base = defaultSettings();
	return { ...base, ...partial };
}

// ---------------------------------------------------------------- 1. Contact

function contactTemplate() {
	return {
		name: __( 'Contact Form', 'open-form-builder' ),
		schema: {
			version: 1,
			steps: [
				step( __( 'Get in touch', 'open-form-builder' ), '', [
					field( 'text', { name: 'name', label: __( 'Full name', 'open-form-builder' ), required: true } ),
					field( 'email', { name: 'email', label: __( 'Email', 'open-form-builder' ), required: true } ),
					field( 'tel', { name: 'phone', label: __( 'Phone (optional)', 'open-form-builder' ) } ),
					field( 'textarea', { name: 'message', label: __( 'How can we help?', 'open-form-builder' ), required: true } ),
				] ),
			],
		},
		settings: settingsWith( {
			emails: {
				confirmation: { enabled: true, to: '', subject: __( 'New enquiry from {name}', 'open-form-builder' ), body: __( 'You have a new enquiry.\n\n{all_fields}', 'open-form-builder' ) },
				receipt: { enabled: true, to: '{email}', subject: __( 'Thanks for getting in touch', 'open-form-builder' ), body: __( 'Hi {name},\n\nThanks for reaching out — we\'ll be in touch shortly.\n\n{site_name}', 'open-form-builder' ) },
				routing: { field: '', map: [], default: '' },
			},
			messages: { success: __( 'Thanks! We\'ll be in touch shortly.', 'open-form-builder' ) },
			theme: theme( '#475569' ),
		} ),
		slots: [],
	};
}

// --------------------------------------------------------- 2. Lesson booking

function lessonBookingTemplate() {
	return {
		name: __( 'Lesson & Session Booking', 'open-form-builder' ),
		schema: {
			version: 1,
			steps: [
				step( __( 'Parent Information', 'open-form-builder' ), '', [
					field( 'text', { name: 'parent_name', label: __( 'Parent name', 'open-form-builder' ), required: true } ),
					field( 'tel', { name: 'mobile', label: __( 'Mobile number', 'open-form-builder' ), required: true } ),
					field( 'email', { name: 'email', label: __( 'Email address', 'open-form-builder' ), required: true } ),
				] ),
				step( __( 'Student Information', 'open-form-builder' ), '', [
					field( 'text', { name: 'student_name', label: __( 'Student name', 'open-form-builder' ), required: true } ),
				] ),
				step( __( 'Select Lesson Times', 'open-form-builder' ), __( 'Choose 4 or more sessions.', 'open-form-builder' ), [
					field( 'session_picker', {
						name: 'sessions',
						label: __( 'Choose your sessions', 'open-form-builder' ),
						required: true,
						config: { min: 4, max: null, tabs: [ { key: 'term', label: __( 'School Term', 'open-form-builder' ) }, { key: 'holiday', label: __( 'School Holiday', 'open-form-builder' ) } ] },
					} ),
				] ),
			],
		},
		settings: settingsWith( {
			pricing: { enabled: true, mode: 'sessions', base_fee: 0, base_price: 80, base_sessions: 4, extra_session_price: 25, block_size: 4, block_discount: { type: 'amount', value: 20 } },
			session_picker: {
				min: 4, max: null,
				periods: [
					{ key: 'term', label: __( 'School Term Availability', 'open-form-builder' ), ranges: [ { start: '', end: '' } ] },
					{ key: 'holiday', label: __( 'School Holiday Availability', 'open-form-builder' ), ranges: [ { start: '', end: '' } ] },
				],
			},
			payments: { enabled: true, currency: 'aud', product_label: __( 'Sessions', 'open-form-builder' ) },
			emails: {
				confirmation: { enabled: true, to: '{email}', subject: __( 'Your booking is confirmed — {amount}', 'open-form-builder' ), body: __( 'Hi {parent_name},\n\nThanks for booking {student_name} in. Total paid: {amount}.\n\n{all_fields}', 'open-form-builder' ) },
				receipt: { enabled: false, to: '', subject: '', body: '' },
				routing: { field: '', map: [], default: '' },
			},
			messages: { success: __( 'Thank you! Your booking was received.', 'open-form-builder' ) },
			theme: theme( '#4f46e5' ),
		} ),
		slots: [
			{ slot_key: uid( 'slot' ), tab: 'term', teacher: __( 'Ms. Ramlawie', 'open-form-builder' ), day: 'mon', time_label: '5:00-5:30 pm', capacity: 4, booked_count: 0, exceptions: [] },
			{ slot_key: uid( 'slot' ), tab: 'term', teacher: __( 'Ms. Ramlawie', 'open-form-builder' ), day: 'mon', time_label: '5:30-6:00 pm', capacity: 4, booked_count: 0, exceptions: [] },
			{ slot_key: uid( 'slot' ), tab: 'term', teacher: __( 'Ms. Ramlawie', 'open-form-builder' ), day: 'wed', time_label: '5:00-5:30 pm', capacity: 4, booked_count: 0, exceptions: [] },
			{ slot_key: uid( 'slot' ), tab: 'term', teacher: __( 'Mr. Mallat', 'open-form-builder' ), day: 'tue', time_label: '4:30-5:00 pm', capacity: 4, booked_count: 0, exceptions: [] },
			{ slot_key: uid( 'slot' ), tab: 'term', teacher: __( 'Mr. Mallat', 'open-form-builder' ), day: 'thu', time_label: '4:30-5:00 pm', capacity: 4, booked_count: 0, exceptions: [] },
		],
	};
}

// ------------------------------------------------------- 3. Course enrolment

function courseTemplate() {
	return {
		name: __( 'Course & Certification Enrolment', 'open-form-builder' ),
		schema: {
			version: 1,
			steps: [
				step( __( 'Your details', 'open-form-builder' ), '', [
					field( 'text', { name: 'name', label: __( 'Full name', 'open-form-builder' ), required: true } ),
					field( 'email', { name: 'email', label: __( 'Email', 'open-form-builder' ), required: true } ),
					field( 'tel', { name: 'phone', label: __( 'Phone', 'open-form-builder' ), required: true } ),
				] ),
				step( __( 'Choose your course(s)', 'open-form-builder' ), __( 'Select one or more courses. Prices shown are per course.', 'open-form-builder' ), [
					choiceField( 'checkbox', 'courses', __( 'Courses', 'open-form-builder' ), [
						{ label: __( 'White Card Induction', 'open-form-builder' ), value: 'white-card', price: 95 },
						{ label: __( 'First Aid Certificate', 'open-form-builder' ), value: 'first-aid', price: 150 },
						{ label: __( 'Working at Heights', 'open-form-builder' ), value: 'heights', price: 220 },
						{ label: __( 'Confined Space Entry', 'open-form-builder' ), value: 'confined-space', price: 280 },
						{ label: __( 'Forklift License', 'open-form-builder' ), value: 'forklift', price: 350 },
					], 'cards' ),
					field( 'date', { name: 'preferred_date', label: __( 'Preferred start date', 'open-form-builder' ) } ),
					field( 'textarea', { name: 'notes', label: __( 'Anything we should know?', 'open-form-builder' ) } ),
				] ),
			],
		},
		settings: settingsWith( {
			pricing: { enabled: true, mode: 'options', base_fee: 0, base_price: 80, base_sessions: 4, extra_session_price: 25, block_size: 4, block_discount: { type: 'amount', value: 20 } },
			payments: { enabled: true, currency: 'aud', product_label: __( 'Course enrolment', 'open-form-builder' ) },
			emails: {
				confirmation: { enabled: true, to: '{email}', subject: __( 'You\'re enrolled — {amount}', 'open-form-builder' ), body: __( 'Hi {name},\n\nThanks for enrolling. Total paid: {amount}.\n\n{all_fields}', 'open-form-builder' ) },
				receipt: { enabled: false, to: '', subject: '', body: '' },
				routing: { field: '', map: [], default: '' },
			},
			messages: { success: __( 'You\'re enrolled! Check your email for details.', 'open-form-builder' ) },
			theme: theme( '#7c3aed' ),
		} ),
		slots: [],
	};
}

// -------------------------------------------------------- 4. Service booking

function serviceBookingTemplate() {
	return {
		name: __( 'Service Booking (Home Services)', 'open-form-builder' ),
		schema: {
			version: 1,
			steps: [
				step( __( 'Your details', 'open-form-builder' ), '', [
					field( 'text', { name: 'name', label: __( 'Full name', 'open-form-builder' ), required: true } ),
					field( 'email', { name: 'email', label: __( 'Email', 'open-form-builder' ), required: true } ),
					field( 'tel', { name: 'phone', label: __( 'Phone', 'open-form-builder' ), required: true } ),
					field( 'text', { name: 'address', label: __( 'Service address', 'open-form-builder' ), required: true } ),
				] ),
				step( __( 'Choose your service', 'open-form-builder' ), '', [
					choiceField( 'radio', 'service', __( 'Service', 'open-form-builder' ), [
						{ label: __( 'Carpet Steam Clean', 'open-form-builder' ), value: 'carpet', price: 120 },
						{ label: __( 'Upholstery Clean', 'open-form-builder' ), value: 'upholstery', price: 90 },
						{ label: __( 'End of Lease Clean', 'open-form-builder' ), value: 'end-of-lease', price: 350 },
						{ label: __( 'Regular House Clean', 'open-form-builder' ), value: 'house-clean', price: 150 },
					], 'cards' ),
					field( 'number', { name: 'rooms', label: __( 'Number of rooms', 'open-form-builder' ), config: { unit_price: 15 } } ),
					choiceField( 'checkbox', 'addons', __( 'Add-ons', 'open-form-builder' ), [
						{ label: __( 'Stain protection', 'open-form-builder' ), value: 'stain', price: 40 },
						{ label: __( 'Deodorising', 'open-form-builder' ), value: 'deodor', price: 25 },
						{ label: __( 'Extra bathroom', 'open-form-builder' ), value: 'bathroom', price: 20 },
					], 'list', { required: false } ),
					field( 'date', { name: 'preferred_date', label: __( 'Preferred date', 'open-form-builder' ) } ),
					field( 'time', { name: 'preferred_time', label: __( 'Preferred time', 'open-form-builder' ) } ),
				] ),
			],
		},
		settings: settingsWith( {
			pricing: { enabled: true, mode: 'options', base_fee: 30, base_price: 80, base_sessions: 4, extra_session_price: 25, block_size: 4, block_discount: { type: 'amount', value: 20 } },
			payments: { enabled: true, currency: 'aud', product_label: __( 'Cleaning service', 'open-form-builder' ) },
			emails: {
				confirmation: { enabled: true, to: '{email}', subject: __( 'Booking confirmed — {amount}', 'open-form-builder' ), body: __( 'Hi {name},\n\nYour booking is confirmed. Total paid: {amount}.\n\n{all_fields}', 'open-form-builder' ) },
				receipt: { enabled: false, to: '', subject: '', body: '' },
				routing: { field: '', map: [], default: '' },
			},
			messages: { success: __( 'Booking confirmed! We\'ll see you then.', 'open-form-builder' ) },
			theme: theme( '#0d9488' ),
		} ),
		slots: [],
	};
}

// -------------------------------------------------------- 5. Quote calculator

function quoteTemplate() {
	return {
		name: __( 'Quote / Estimate Calculator', 'open-form-builder' ),
		schema: {
			version: 1,
			steps: [
				step( __( 'Your details', 'open-form-builder' ), '', [
					field( 'text', { name: 'name', label: __( 'Full name', 'open-form-builder' ), required: true } ),
					field( 'email', { name: 'email', label: __( 'Email', 'open-form-builder' ), required: true } ),
					field( 'tel', { name: 'phone', label: __( 'Phone', 'open-form-builder' ) } ),
					field( 'date', { name: 'preferred_date', label: __( 'Preferred date', 'open-form-builder' ) } ),
					field( 'time', { name: 'preferred_time', label: __( 'Preferred time', 'open-form-builder' ) } ),
				] ),
				step( __( 'Job details', 'open-form-builder' ), '', [
					choiceField( 'radio', 'service', __( 'Service needed', 'open-form-builder' ), [
						{ label: __( 'Repair', 'open-form-builder' ), value: 'repair', price: 150 },
						{ label: __( 'New installation', 'open-form-builder' ), value: 'install', price: 600 },
						{ label: __( 'Diagnostic only', 'open-form-builder' ), value: 'diagnostic', price: 90 },
					] ),
					field( 'number', { name: 'units', label: __( 'Number of units', 'open-form-builder' ), config: { unit_price: 50 } } ),
					choiceField( 'checkbox', 'addons', __( 'Add-ons', 'open-form-builder' ), [
						{ label: __( 'After-hours visit', 'open-form-builder' ), value: 'after_hours', price: 80 },
						{ label: __( 'Filter replacement', 'open-form-builder' ), value: 'filter', price: 40 },
					], 'list', { required: false } ),
					field( 'textarea', { name: 'notes', label: __( 'Describe the issue', 'open-form-builder' ) } ),
				] ),
			],
		},
		settings: settingsWith( {
			pricing: { enabled: true, mode: 'options', base_fee: 30, base_price: 80, base_sessions: 4, extra_session_price: 25, block_size: 4, block_discount: { type: 'amount', value: 20 } },
			// Payments off on purpose — this is a quote, not a checkout: the total
			// still computes live and lands in the confirmation email as {amount}.
			payments: { enabled: false, currency: 'aud', product_label: __( 'Service', 'open-form-builder' ) },
			emails: {
				confirmation: { enabled: true, to: '', subject: __( 'New quote request — est. {amount}', 'open-form-builder' ), body: __( 'New quote request, estimated total {amount}.\n\n{all_fields}', 'open-form-builder' ) },
				receipt: { enabled: true, to: '{email}', subject: __( 'Your estimate — {amount}', 'open-form-builder' ), body: __( 'Hi {name},\n\nThanks for the request. Your estimated total is {amount} — we\'ll confirm shortly.\n\n{site_name}', 'open-form-builder' ) },
				routing: { field: '', map: [], default: '' },
			},
			messages: { success: __( 'Thanks! We\'ll email your confirmed quote shortly.', 'open-form-builder' ) },
			theme: theme( '#d97706' ),
		} ),
		slots: [],
	};
}

export const TEMPLATES = [
	{ key: 'contact', emoji: '✉️', name: __( 'Contact Form', 'open-form-builder' ), description: __( 'A simple enquiry form. No payment.', 'open-form-builder' ), accent: '#475569', build: contactTemplate },
	{ key: 'lessons', emoji: '📅', name: __( 'Lesson & Session Booking', 'open-form-builder' ), description: __( 'Sell recurring lesson slots or one-off sessions with live capacity.', 'open-form-builder' ), accent: '#4f46e5', build: lessonBookingTemplate },
	{ key: 'courses', emoji: '🎓', name: __( 'Course & Certification Enrolment', 'open-form-builder' ), description: __( 'Courses shown as image cards; select one or more, priced individually.', 'open-form-builder' ), accent: '#7c3aed', build: courseTemplate },
	{ key: 'services', emoji: '🧹', name: __( 'Service Booking (Home Services)', 'open-form-builder' ), description: __( 'Priced services, add-ons and a quantity — cleaning, trades, and more.', 'open-form-builder' ), accent: '#0d9488', build: serviceBookingTemplate },
	{ key: 'quote', emoji: '🧮', name: __( 'Quote / Estimate Calculator', 'open-form-builder' ), description: __( 'Compute a live estimate and email it — no payment collected.', 'open-form-builder' ), accent: '#d97706', build: quoteTemplate },
];
