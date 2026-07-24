/**
 * Edits a single field: its label/name/validation, type-specific config
 * (choices, HTML content, session-picker tabs + min/max) and conditional logic.
 */
import { __ } from '@wordpress/i18n';
import {
	Card, CardBody, CardHeader, TextControl, TextareaControl, ToggleControl,
	Button, SelectControl, Flex, FlexItem, __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { CHOICE_TYPES, OPERATORS, uid } from '../defaults';

export default function FieldEditor( { field, allFields, onChange, onRemove } ) {
	function set( partial ) { onChange( { ...field, ...partial } ); }

	const isChoice = CHOICE_TYPES.includes( field.type );

	return (
		<Card className="ofb-field-editor">
			<CardHeader>
				<strong>{ __( 'Field', 'open-form-builder' ) }: { field.type }</strong>
				<Button isDestructive variant="tertiary" onClick={ onRemove }>{ __( 'Delete', 'open-form-builder' ) }</Button>
			</CardHeader>
			<CardBody>
				{ field.type !== 'html' && (
					<>
						<TextControl
							label={ __( 'Label', 'open-form-builder' ) }
							value={ field.label }
							onChange={ ( label ) => set( { label } ) }
						/>
						<TextControl
							label={ __( 'Field name (machine key, used in {tags})', 'open-form-builder' ) }
							value={ field.name }
							onChange={ ( name ) => set( { name } ) }
							help={ __( 'Lowercase letters, numbers, dashes/underscores.', 'open-form-builder' ) }
						/>
					</>
				) }

				{ field.type === 'html' && (
					<TextareaControl
						label={ __( 'HTML content', 'open-form-builder' ) }
						value={ field.content || '' }
						onChange={ ( content ) => set( { content } ) }
						rows={ 6 }
					/>
				) }

				{ ![ 'html', 'session_picker' ].includes( field.type ) && (
					<>
						<TextControl
							label={ __( 'Placeholder', 'open-form-builder' ) }
							value={ field.placeholder || '' }
							onChange={ ( placeholder ) => set( { placeholder } ) }
						/>
						<TextControl
							label={ __( 'Help text', 'open-form-builder' ) }
							value={ field.help || '' }
							onChange={ ( help ) => set( { help } ) }
						/>
					</>
				) }

				{ field.type !== 'html' && (
					<ToggleControl
						label={ __( 'Required', 'open-form-builder' ) }
						checked={ !! field.required }
						onChange={ ( required ) => set( { required } ) }
					/>
				) }

				{ isChoice && <OptionsEditor field={ field } set={ set } /> }
				{ field.type === 'number' && <NumberConfig field={ field } set={ set } /> }
				{ field.type === 'session_picker' && <SessionConfig field={ field } set={ set } /> }

				<ConditionalEditor field={ field } allFields={ allFields } set={ set } />
			</CardBody>
		</Card>
	);
}

function OptionsEditor( { field, set } ) {
	const options = field.options || [];
	function update( i, partial ) {
		set( { options: options.map( ( o, j ) => ( j === i ? { ...o, ...partial } : o ) ) } );
	}
	return (
		<div className="ofb-options">
			<p className="ofb-subhead">{ __( 'Options', 'open-form-builder' ) }</p>
			{ options.map( ( opt, i ) => (
				<Flex key={ i } className="ofb-options__row">
					<FlexItem isBlock>
						<TextControl placeholder={ __( 'Label', 'open-form-builder' ) } value={ opt.label } onChange={ ( label ) => update( i, { label } ) } __nextHasNoMarginBottom />
					</FlexItem>
					<FlexItem isBlock>
						<TextControl placeholder={ __( 'Value', 'open-form-builder' ) } value={ opt.value } onChange={ ( value ) => update( i, { value } ) } __nextHasNoMarginBottom />
					</FlexItem>
					<FlexItem>
						<NumberControl placeholder={ __( 'Price', 'open-form-builder' ) } value={ opt.price == null ? 0 : opt.price } min={ 0 } onChange={ ( v ) => update( i, { price: Number( v ) || 0 } ) } __nextHasNoMarginBottom />
					</FlexItem>
					<FlexItem>
						<Button isDestructive variant="tertiary" onClick={ () => set( { options: options.filter( ( _, j ) => j !== i ) } ) }>×</Button>
					</FlexItem>
				</Flex>
			) ) }
			<Button variant="secondary" onClick={ () => set( { options: [ ...options, { label: '', value: '', price: 0 } ] } ) }>
				{ __( 'Add option', 'open-form-builder' ) }
			</Button>
			<p className="ofb-help">{ __( 'Price is only charged when Pricing → model is “By selected options”.', 'open-form-builder' ) }</p>
		</div>
	);
}

function NumberConfig( { field, set } ) {
	const cfg = field.config || { unit_price: 0 };
	return (
		<div className="ofb-number-config">
			<p className="ofb-subhead">{ __( 'Number pricing', 'open-form-builder' ) }</p>
			<NumberControl
				label={ __( 'Unit price ($) — multiplies the entered number into the total (0 = off)', 'open-form-builder' ) }
				value={ cfg.unit_price || 0 }
				min={ 0 }
				onChange={ ( v ) => set( { config: { ...cfg, unit_price: Number( v ) || 0 } } ) }
			/>
			<p className="ofb-help">{ __( 'e.g. “Number of rooms” × $30. Only applied in “By selected options” pricing.', 'open-form-builder' ) }</p>
		</div>
	);
}

function SessionConfig( { field, set } ) {
	const cfg = field.config || { min: 4, max: null, tabs: [] };
	function setCfg( partial ) { set( { config: { ...cfg, ...partial } } ); }
	const tabs = cfg.tabs || [];
	return (
		<div className="ofb-session-config">
			<p className="ofb-subhead">{ __( 'Session picker', 'open-form-builder' ) }</p>
			<Flex>
				<FlexItem isBlock>
					<NumberControl label={ __( 'Minimum', 'open-form-builder' ) } value={ cfg.min } min={ 0 } onChange={ ( v ) => setCfg( { min: parseInt( v || 0, 10 ) } ) } />
				</FlexItem>
				<FlexItem isBlock>
					<NumberControl
						label={ __( 'Maximum (blank = none)', 'open-form-builder' ) }
						value={ cfg.max == null ? '' : cfg.max }
						min={ 0 }
						onChange={ ( v ) => setCfg( { max: v === '' || v == null ? null : parseInt( v, 10 ) } ) }
					/>
				</FlexItem>
			</Flex>
			<p className="ofb-subhead">{ __( 'Tabs', 'open-form-builder' ) }</p>
			{ tabs.map( ( tab, i ) => (
				<Flex key={ i } className="ofb-options__row">
					<FlexItem isBlock>
						<TextControl placeholder={ __( 'Key', 'open-form-builder' ) } value={ tab.key } onChange={ ( key ) => setCfg( { tabs: tabs.map( ( t, j ) => ( j === i ? { ...t, key } : t ) ) } ) } __nextHasNoMarginBottom />
					</FlexItem>
					<FlexItem isBlock>
						<TextControl placeholder={ __( 'Label', 'open-form-builder' ) } value={ tab.label } onChange={ ( label ) => setCfg( { tabs: tabs.map( ( t, j ) => ( j === i ? { ...t, label } : t ) ) } ) } __nextHasNoMarginBottom />
					</FlexItem>
					<FlexItem>
						<Button isDestructive variant="tertiary" onClick={ () => setCfg( { tabs: tabs.filter( ( _, j ) => j !== i ) } ) }>×</Button>
					</FlexItem>
				</Flex>
			) ) }
			<Button variant="secondary" onClick={ () => setCfg( { tabs: [ ...tabs, { key: uid( 'tab' ), label: '' } ] } ) }>
				{ __( 'Add tab', 'open-form-builder' ) }
			</Button>
			<p className="ofb-help">{ __( 'Define the actual sessions (slots) and capacities in the Sessions tab.', 'open-form-builder' ) }</p>
		</div>
	);
}

function ConditionalEditor( { field, allFields, set } ) {
	const cond = field.conditional || { enabled: false, action: 'show', match: 'all', rules: [] };
	function setCond( partial ) { set( { conditional: { ...cond, ...partial } } ); }
	const fieldOptions = allFields
		.filter( ( f ) => f.name && f.name !== field.name && f.type !== 'html' )
		.map( ( f ) => ( { value: f.name, label: f.label || f.name } ) );

	return (
		<div className="ofb-conditional">
			<p className="ofb-subhead">{ __( 'Conditional logic', 'open-form-builder' ) }</p>
			<ToggleControl
				label={ __( 'Show/hide this field based on other answers', 'open-form-builder' ) }
				checked={ !! cond.enabled }
				onChange={ ( enabled ) => setCond( { enabled } ) }
			/>
			{ cond.enabled && (
				<>
					<Flex>
						<FlexItem isBlock>
							<SelectControl
								label={ __( 'Action', 'open-form-builder' ) }
								value={ cond.action }
								options={ [ { value: 'show', label: __( 'Show', 'open-form-builder' ) }, { value: 'hide', label: __( 'Hide', 'open-form-builder' ) } ] }
								onChange={ ( action ) => setCond( { action } ) }
								__nextHasNoMarginBottom
							/>
						</FlexItem>
						<FlexItem isBlock>
							<SelectControl
								label={ __( 'Match', 'open-form-builder' ) }
								value={ cond.match }
								options={ [ { value: 'all', label: __( 'All rules', 'open-form-builder' ) }, { value: 'any', label: __( 'Any rule', 'open-form-builder' ) } ] }
								onChange={ ( match ) => setCond( { match } ) }
								__nextHasNoMarginBottom
							/>
						</FlexItem>
					</Flex>
					{ ( cond.rules || [] ).map( ( rule, i ) => (
						<Flex key={ i } className="ofb-options__row">
							<FlexItem isBlock>
								<SelectControl
									value={ rule.field }
									options={ [ { value: '', label: __( '— field —', 'open-form-builder' ) }, ...fieldOptions ] }
									onChange={ ( v ) => setCond( { rules: cond.rules.map( ( r, j ) => ( j === i ? { ...r, field: v } : r ) ) } ) }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem isBlock>
								<SelectControl
									value={ rule.op }
									options={ OPERATORS }
									onChange={ ( v ) => setCond( { rules: cond.rules.map( ( r, j ) => ( j === i ? { ...r, op: v } : r ) ) } ) }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem isBlock>
								<TextControl
									value={ rule.value }
									placeholder={ __( 'value', 'open-form-builder' ) }
									onChange={ ( v ) => setCond( { rules: cond.rules.map( ( r, j ) => ( j === i ? { ...r, value: v } : r ) ) } ) }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<Button isDestructive variant="tertiary" onClick={ () => setCond( { rules: cond.rules.filter( ( _, j ) => j !== i ) } ) }>×</Button>
							</FlexItem>
						</Flex>
					) ) }
					<Button variant="secondary" onClick={ () => setCond( { rules: [ ...( cond.rules || [] ), { field: '', op: 'is', value: '' } ] } ) }>
						{ __( 'Add rule', 'open-form-builder' ) }
					</Button>
				</>
			) }
		</div>
	);
}
