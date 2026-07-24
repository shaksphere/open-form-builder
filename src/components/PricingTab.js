/**
 * Pricing tab: the per-form rules engine inputs + a live preview that mirrors
 * OFB_Pricing exactly, so the admin can confirm targets while configuring.
 */
import { __ } from '@wordpress/i18n';
import {
	Card, CardBody, ToggleControl, SelectControl, Flex, FlexItem,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

function computeTotal( p, S ) {
	const base = Number( p.base_price ) || 0;
	const baseS = parseInt( p.base_sessions || 0, 10 );
	const extraPrice = Number( p.extra_session_price ) || 0;
	const block = Math.max( 1, parseInt( p.block_size || 1, 10 ) );
	const disc = p.block_discount && p.block_discount.type === 'percent'
		? base * ( ( Number( p.block_discount.value ) || 0 ) / 100 )
		: ( p.block_discount ? Number( p.block_discount.value ) || 0 : 0 );
	if ( S <= baseS ) { return base; }
	const extra = S - baseS;
	const fullBlocks = Math.floor( extra / block );
	const remainder = extra - fullBlocks * block;
	return Math.max( 0, base + fullBlocks * ( base - disc ) + remainder * extraPrice );
}

export default function PricingTab( { settings, onChange } ) {
	const p = settings.pricing;
	function setP( partial ) { onChange( { ...settings, pricing: { ...p, ...partial } } ); }
	function setDiscount( partial ) { setP( { block_discount: { ...p.block_discount, ...partial } } ); }

	const previewPoints = [ p.base_sessions, p.base_sessions + 2, p.base_sessions + 4, p.base_sessions + 8, p.base_sessions + 12 ];

	const mode = p.mode || 'sessions';

	return (
		<Card>
			<CardBody>
				<ToggleControl
					label={ __( 'Enable pricing (show a live total / charge via Stripe)', 'open-form-builder' ) }
					checked={ !! p.enabled }
					onChange={ ( enabled ) => setP( { enabled } ) }
				/>
				{ p.enabled && (
					<SelectControl
						label={ __( 'Pricing model', 'open-form-builder' ) }
						value={ mode }
						options={ [
							{ value: 'sessions', label: __( 'By sessions (count-based tiers + block discounts)', 'open-form-builder' ) },
							{ value: 'options', label: __( 'By selected options (priced choices + quantities)', 'open-form-builder' ) },
						] }
						onChange={ ( v ) => setP( { mode: v } ) }
						help={ __( 'Sessions: for the session picker. Options: courses, services, add-ons, quotes.', 'open-form-builder' ) }
					/>
				) }
				{ p.enabled && mode === 'options' && (
					<>
						<Flex>
							<FlexItem isBlock><NumberControl label={ __( 'Base fee ($) — added to every order', 'open-form-builder' ) } value={ p.base_fee || 0 } min={ 0 } onChange={ ( v ) => setP( { base_fee: Number( v ) || 0 } ) } /></FlexItem>
							<FlexItem isBlock />
						</Flex>
						<p className="ofb-help">
							{ __( 'The total is the base fee + the price of each selected option + each number field × its unit price. Set option prices on each field in the Build tab, and number unit prices under “Number pricing”.', 'open-form-builder' ) }
						</p>
					</>
				) }
				{ p.enabled && mode === 'sessions' && (
					<>
						<Flex>
							<FlexItem isBlock><NumberControl label={ __( 'Base price ($)', 'open-form-builder' ) } value={ p.base_price } min={ 0 } onChange={ ( v ) => setP( { base_price: Number( v ) || 0 } ) } /></FlexItem>
							<FlexItem isBlock><NumberControl label={ __( 'Base sessions', 'open-form-builder' ) } value={ p.base_sessions } min={ 0 } onChange={ ( v ) => setP( { base_sessions: parseInt( v || 0, 10 ) } ) } /></FlexItem>
						</Flex>
						<Flex>
							<FlexItem isBlock><NumberControl label={ __( 'Extra session price ($)', 'open-form-builder' ) } value={ p.extra_session_price } min={ 0 } onChange={ ( v ) => setP( { extra_session_price: Number( v ) || 0 } ) } /></FlexItem>
							<FlexItem isBlock><NumberControl label={ __( 'Block size', 'open-form-builder' ) } value={ p.block_size } min={ 1 } onChange={ ( v ) => setP( { block_size: parseInt( v || 1, 10 ) } ) } /></FlexItem>
						</Flex>
						<Flex>
							<FlexItem isBlock>
								<SelectControl
									label={ __( 'Per-block discount type', 'open-form-builder' ) }
									value={ p.block_discount.type }
									options={ [ { value: 'amount', label: __( '$ amount', 'open-form-builder' ) }, { value: 'percent', label: __( '% of base price', 'open-form-builder' ) } ] }
									onChange={ ( type ) => setDiscount( { type } ) }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem isBlock><NumberControl label={ __( 'Discount value', 'open-form-builder' ) } value={ p.block_discount.value } min={ 0 } onChange={ ( v ) => setDiscount( { value: Number( v ) || 0 } ) } /></FlexItem>
						</Flex>

						<p className="ofb-subhead">{ __( 'Live preview', 'open-form-builder' ) }</p>
						<table className="ofb-price-preview">
							<thead><tr><th>{ __( 'Sessions', 'open-form-builder' ) }</th><th>{ __( 'Total', 'open-form-builder' ) }</th></tr></thead>
							<tbody>
								{ [ ...new Set( previewPoints ) ].map( ( s ) => (
									<tr key={ s }><td>{ s }</td><td>${ computeTotal( p, s ).toFixed( 2 ) }</td></tr>
								) ) }
							</tbody>
						</table>
					</>
				) }
			</CardBody>
		</Card>
	);
}
