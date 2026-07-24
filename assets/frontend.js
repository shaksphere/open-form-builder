/**
 * Open Form Builder — front-end engine.
 *
 * Drives every rendered [open_form]: multi-step navigation, per-field conditional
 * show/hide (mirrors the PHP evaluator in OFB_Schema), the capacity-aware session
 * picker with min/max enforcement, a live pricing preview (same rules as
 * OFB_Pricing), and an AJAX submit that either shows a message or redirects to
 * Stripe Checkout / the thank-you page.
 *
 * No dependencies — vanilla ES5-safe-ish (uses const/let, arrow fns; fine for
 * evergreen browsers WP 6.2 targets).
 */
( function () {
	'use strict';

	var DATA = window.OFB_DATA || { restUrl: '', i18n: {} };

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		var forms = document.querySelectorAll( '.ofb-form' );
		forms.forEach( function ( form ) { new OFBForm( form ); } );
	} );

	function OFBForm( el ) {
		this.el = el;
		this.config = parseJSON( el.getAttribute( 'data-ofb-config' ) ) || {};
		this.steps = Array.prototype.slice.call( el.querySelectorAll( '.ofb-step' ) );
		this.current = 0;
		this.init();
	}

	OFBForm.prototype.init = function () {
		var self = this;

		// Step nav.
		var next = this.el.querySelector( '[data-ofb-next]' );
		var prev = this.el.querySelector( '[data-ofb-prev]' );
		if ( next ) { next.addEventListener( 'click', function () { self.go( self.current + 1 ); } ); }
		if ( prev ) { prev.addEventListener( 'click', function () { self.go( self.current - 1 ); } ); }

		// React to any change for conditional logic, session counts, pricing.
		this.el.addEventListener( 'input', function () { self.refresh(); } );
		this.el.addEventListener( 'change', function () { self.refresh(); } );

		this.el.addEventListener( 'submit', function ( e ) { e.preventDefault(); self.submit(); } );

		// Session picker tabs.
		this.el.querySelectorAll( '[data-ofb-session]' ).forEach( function ( picker ) {
			picker.querySelectorAll( '[data-ofb-tab]' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', function () { self.switchTab( picker, tab.getAttribute( 'data-ofb-tab' ) ); } );
			} );
		} );

		// "Show all times" expands a collapsed teacher card.
		this.el.querySelectorAll( '[data-ofb-showtimes]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var card = btn.closest( '[data-ofb-teacher]' );
				if ( ! card ) { return; }
				var collapsed = card.classList.toggle( 'is-collapsed' );
				btn.textContent = collapsed ? 'Show all times' : 'Show fewer times';
			} );
		} );

		this.refresh();
		this.showStep();
	};

	// ---- Conditional logic ------------------------------------------------

	OFBForm.prototype.collectValues = function () {
		var values = {};
		this.el.querySelectorAll( '[data-ofb-field]' ).forEach( function ( wrap ) {
			var name = wrap.getAttribute( 'data-ofb-field' );
			if ( ! name ) { return; }
			var inputs = wrap.querySelectorAll( 'input, select, textarea' );
			var collected = [];
			inputs.forEach( function ( input ) {
				if ( ( input.type === 'checkbox' || input.type === 'radio' ) ) {
					if ( input.checked ) { collected.push( input.value ); }
				} else if ( input.value !== '' ) {
					collected.push( input.value );
				}
			} );
			values[ name ] = collected.length > 1 ? collected : ( collected[ 0 ] || '' );
		} );
		return values;
	};

	OFBForm.prototype.refresh = function () {
		var values = this.collectValues();
		this.applyConditionals( values );
		this.updateSessions();
		this.updatePricing();
	};

	OFBForm.prototype.applyConditionals = function ( values ) {
		this.el.querySelectorAll( '[data-ofb-conditional]' ).forEach( function ( wrap ) {
			var cond = parseJSON( wrap.getAttribute( 'data-ofb-conditional' ) );
			if ( ! cond ) { return; }
			var visible = isVisible( cond, values );
			wrap.hidden = ! visible;
			// Disable hidden inputs so they don't submit (server also re-checks).
			wrap.querySelectorAll( 'input, select, textarea' ).forEach( function ( i ) { i.disabled = ! visible; } );
		} );
	};

	function isVisible( cond, values ) {
		if ( ! cond.enabled || ! cond.rules || ! cond.rules.length ) { return true; }
		var results = cond.rules.map( function ( r ) { return ruleMatches( values[ r.field ], r.op, r.value ); } );
		var matched = cond.match === 'any'
			? results.indexOf( true ) !== -1
			: results.indexOf( false ) === -1;
		return cond.action === 'hide' ? ! matched : matched;
	}

	function ruleMatches( value, op, test ) {
		var arr = Array.isArray( value ) ? value : ( value === '' || value == null ? [] : [ value ] );
		var empty = arr.length === 0 || ( arr.length === 1 && String( arr[ 0 ] ).trim() === '' );
		var first = arr.length ? String( arr[ 0 ] ) : '';
		var has = function ( strict ) {
			return arr.some( function ( v ) {
				v = String( v );
				return strict ? v === test : ( test !== '' && v.indexOf( test ) !== -1 );
			} );
		};
		switch ( op ) {
			case 'empty': return empty;
			case 'not_empty': return ! empty;
			case 'is': return has( true );
			case 'is_not': return ! has( true );
			case 'contains': return has( false );
			case 'not_contains': return ! has( false );
			case 'gt': return isNum( first, test ) && parseFloat( first ) > parseFloat( test );
			case 'lt': return isNum( first, test ) && parseFloat( first ) < parseFloat( test );
			case 'gte': return isNum( first, test ) && parseFloat( first ) >= parseFloat( test );
			case 'lte': return isNum( first, test ) && parseFloat( first ) <= parseFloat( test );
		}
		return false;
	}
	function isNum( a, b ) { return a !== '' && b !== '' && ! isNaN( a ) && ! isNaN( b ); }

	// ---- Session picker ---------------------------------------------------

	OFBForm.prototype.switchTab = function ( picker, key ) {
		picker.querySelectorAll( '[data-ofb-tab]' ).forEach( function ( t ) {
			t.classList.toggle( 'is-active', t.getAttribute( 'data-ofb-tab' ) === key );
		} );
		picker.querySelectorAll( '[data-ofb-tabpanel]' ).forEach( function ( p ) {
			var on = p.getAttribute( 'data-ofb-tabpanel' ) === key;
			p.hidden = ! on; p.classList.toggle( 'is-active', on );
		} );
	};

	OFBForm.prototype.selectedSessions = function () {
		var keys = [];
		this.el.querySelectorAll( '[data-ofb-session] input[type="checkbox"]:checked' ).forEach( function ( cb ) {
			if ( ! cb.disabled ) { keys.push( cb.value ); }
		} );
		return keys;
	};

	OFBForm.prototype.updateSessions = function () {
		var count = this.selectedSessions().length;
		this.el.querySelectorAll( '[data-ofb-session-count]' ).forEach( function ( c ) { c.textContent = count; } );
		this.el.querySelectorAll( '[data-ofb-total]' ); // pricing handled separately
		var counter = this.el.querySelector( '[data-ofb-session]' );
		if ( counter ) {
			var max = counter.getAttribute( 'data-max' );
			if ( max ) {
				var maxN = parseInt( max, 10 );
				counter.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
					if ( ! cb.checked && ! cb.closest( '.ofb-slot' ).classList.contains( 'is-full' ) ) {
						cb.disabled = count >= maxN;
					}
				} );
			}
		}
	};

	// ---- Pricing preview (mirrors OFB_Pricing) ---------------------------

	OFBForm.prototype.updatePricing = function () {
		var totalEl = this.el.querySelector( '[data-ofb-total]' );
		if ( ! totalEl ) { return; }
		var p = this.config.pricing || {};
		if ( ! p.enabled ) { return; }
		var sessions = this.selectedSessions().length;
		var total = computeTotal( p, sessions );
		totalEl.textContent = formatMoney( total, this.config.currency );
	};

	function computeTotal( p, S ) {
		var base = num( p.base_price ), baseS = parseInt( p.base_sessions || 0, 10 );
		var extraPrice = num( p.extra_session_price ), block = Math.max( 1, parseInt( p.block_size || 1, 10 ) );
		var disc = ( p.block_discount && p.block_discount.type === 'percent' )
			? base * ( num( p.block_discount.value ) / 100 )
			: num( p.block_discount && p.block_discount.value );
		if ( S <= baseS ) { return round2( base ); }
		var extra = S - baseS;
		var fullBlocks = Math.floor( extra / block );
		var remainder = extra - ( fullBlocks * block );
		var total = base + fullBlocks * ( base - disc ) + remainder * extraPrice;
		return round2( Math.max( 0, total ) );
	}
	function num( v ) { v = parseFloat( v ); return isNaN( v ) ? 0 : v; }
	function round2( v ) { return Math.round( v * 100 ) / 100; }
	function formatMoney( v, currency ) {
		return '$' + v.toFixed( 2 ) + ( currency ? ' ' + String( currency ).toUpperCase() : '' );
	}

	// ---- Steps ------------------------------------------------------------

	OFBForm.prototype.go = function ( target ) {
		if ( target < 0 || target >= this.steps.length ) { return; }
		// Validate the current step before advancing forward.
		if ( target > this.current && ! this.validateStep( this.current ) ) { return; }
		this.current = target;
		this.showStep();
	};

	OFBForm.prototype.showStep = function () {
		var self = this;
		this.steps.forEach( function ( step, i ) {
			step.classList.toggle( 'is-active', i === self.current );
		} );
		this.el.querySelectorAll( '.ofb-progress__item' ).forEach( function ( item, i ) {
			item.classList.toggle( 'is-active', i <= self.current );
		} );
		var prev = this.el.querySelector( '[data-ofb-prev]' );
		var next = this.el.querySelector( '[data-ofb-next]' );
		var submit = this.el.querySelector( '[data-ofb-submit]' );
		var last = this.current === this.steps.length - 1;
		if ( prev ) { prev.hidden = this.current === 0; }
		if ( next ) { next.hidden = last; }
		if ( submit ) { submit.hidden = ! last && this.steps.length > 1; }
	};

	// ---- Validation -------------------------------------------------------

	OFBForm.prototype.validateStep = function ( index ) {
		var step = this.steps[ index ];
		var ok = true;
		var self = this;
		step.querySelectorAll( '[data-ofb-field]' ).forEach( function ( wrap ) {
			if ( wrap.hidden ) { return; }
			if ( ! self.validateField( wrap ) ) { ok = false; }
		} );
		return ok;
	};

	OFBForm.prototype.validateField = function ( wrap ) {
		var err = wrap.querySelector( '.ofb-field-error' );
		var msg = '';
		var required = wrap.querySelector( '[required]' );
		var session = wrap.querySelector( '[data-ofb-session]' );

		if ( session ) {
			var count = wrap.querySelectorAll( 'input[type="checkbox"]:checked' ).length;
			var min = parseInt( session.getAttribute( 'data-min' ) || '0', 10 );
			var max = session.getAttribute( 'data-max' );
			if ( min && count < min ) { msg = ( DATA.i18n.selectMin || 'Select at least %d.' ).replace( '%d', min ); }
			else if ( max && count > parseInt( max, 10 ) ) { msg = ( DATA.i18n.selectMax || 'Select at most %d.' ).replace( '%d', max ); }
		} else if ( required ) {
			var inputs = wrap.querySelectorAll( 'input, select, textarea' );
			var filled = false, isEmail = false, emailVal = '';
			inputs.forEach( function ( i ) {
				if ( i.type === 'checkbox' || i.type === 'radio' ) { if ( i.checked ) { filled = true; } }
				else if ( i.value.trim() !== '' ) { filled = true; if ( i.type === 'email' ) { isEmail = true; emailVal = i.value; } }
			} );
			if ( ! filled ) { msg = DATA.i18n.required || 'This field is required.'; }
			else if ( isEmail && ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( emailVal ) ) { msg = DATA.i18n.invalidEmail || 'Invalid email.'; }
		}

		if ( err ) { err.textContent = msg; err.hidden = ! msg; }
		wrap.classList.toggle( 'has-error', !! msg );
		return ! msg;
	};

	// ---- Submit -----------------------------------------------------------

	OFBForm.prototype.serialize = function () {
		var fields = {};
		this.el.querySelectorAll( '[data-ofb-field]' ).forEach( function ( wrap ) {
			if ( wrap.hidden ) { return; }
			var name = wrap.getAttribute( 'data-ofb-field' );
			if ( ! name ) { return; }
			wrap.querySelectorAll( 'input, select, textarea' ).forEach( function ( input ) {
				if ( input.disabled ) { return; }
				if ( input.type === 'checkbox' || input.type === 'radio' ) {
					if ( ! input.checked ) { return; }
					if ( ! fields[ name ] ) { fields[ name ] = []; }
					if ( Array.isArray( fields[ name ] ) ) { fields[ name ].push( input.value ); }
					else { fields[ name ] = [ fields[ name ], input.value ]; }
				} else {
					fields[ name ] = input.value;
				}
			} );
		} );
		return fields;
	};

	OFBForm.prototype.submit = function () {
		var self = this;
		// Validate all steps before sending.
		var allOk = true;
		this.steps.forEach( function ( s, i ) { if ( ! self.validateStep( i ) ) { allOk = false; } } );
		if ( ! allOk ) { this.message( DATA.i18n.error || 'Please fix the errors above.', false ); return; }

		var nonceEl = this.el.querySelector( '#ofb_nonce' );
		var submitBtn = this.el.querySelector( '[data-ofb-submit]' );
		if ( submitBtn ) { submitBtn.disabled = true; submitBtn.textContent = DATA.i18n.submitting || 'Submitting…'; }

		var payload = {
			form_id: parseInt( this.el.getAttribute( 'data-ofb-form' ), 10 ),
			ofb_nonce: nonceEl ? nonceEl.value : '',
			fields: this.serialize()
		};

		var headers = { 'Content-Type': 'application/json' };
		// Carry the REST cookie nonce so a logged-in visitor stays authenticated
		// in the REST context (otherwise the per-form nonce verifies as logged-out).
		if ( DATA.restNonce ) { headers[ 'X-WP-Nonce' ] = DATA.restNonce; }

		fetch( DATA.restUrl + 'submit', {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify( payload )
		} ).then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
		.then( function ( res ) {
			var body = res.body || {};
			if ( body.ok && body.redirect ) { window.location.href = body.redirect; return; }
			if ( body.ok ) { self.success( body.message ); return; }
			// Field-level errors from the server.
			if ( body.errors ) { self.showServerErrors( body.errors ); }
			self.message( body.message || DATA.i18n.error, false );
			if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = self.submitLabel(); }
		} ).catch( function () {
			self.message( DATA.i18n.error || 'Something went wrong.', false );
			if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = self.submitLabel(); }
		} );
	};

	OFBForm.prototype.submitLabel = function () {
		return ( this.config.pricing && this.config.pricing.enabled ) ? 'Continue to payment' : 'Submit';
	};

	OFBForm.prototype.showServerErrors = function ( errors ) {
		var self = this;
		Object.keys( errors ).forEach( function ( name ) {
			var wrap = self.el.querySelector( '[data-ofb-field="' + name + '"]' );
			if ( ! wrap ) { return; }
			var err = wrap.querySelector( '.ofb-field-error' );
			if ( err ) { err.textContent = errors[ name ]; err.hidden = false; }
			wrap.classList.add( 'has-error' );
		} );
	};

	OFBForm.prototype.success = function ( msg ) {
		this.el.querySelectorAll( '.ofb-steps, .ofb-nav, .ofb-progress, .ofb-pricing-summary' ).forEach( function ( n ) { n.remove(); } );
		this.message( msg, true );
	};

	OFBForm.prototype.message = function ( text, ok ) {
		var box = this.el.querySelector( '.ofb-message' );
		if ( ! box ) { return; }
		box.textContent = text;
		box.hidden = ! text;
		box.classList.toggle( 'is-success', !! ok );
		box.classList.toggle( 'is-error', ! ok );
	};

	function parseJSON( s ) { try { return JSON.parse( s ); } catch ( e ) { return null; } }
} )();
