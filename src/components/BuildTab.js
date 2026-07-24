/**
 * Build tab: steps (multi-step wizard) each holding a draggable list of fields,
 * a field-type palette, and a side panel to edit the selected field.
 *
 * Drag-and-drop uses native HTML5 DnD: a dragged field carries its step+index;
 * dropping on another field reorders/moves; dropping on a step's empty area
 * appends. Up/down buttons remain as an accessible fallback.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, SelectControl, Flex, FlexItem, TextControl, TextareaControl } from '@wordpress/components';
import { FIELD_TYPES, newField, newStep } from '../defaults';
import FieldEditor from './FieldEditor';

export default function BuildTab( { schema, onChange } ) {
	const [ selected, setSelected ] = useState( null ); // { step, index }
	const [ addType, setAddType ] = useState( 'text' );

	const steps = schema.steps || [];

	function setSteps( next ) {
		onChange( { ...schema, steps: next } );
	}

	function patchStep( si, partial ) {
		setSteps( steps.map( ( s, i ) => ( i === si ? { ...s, ...partial } : s ) ) );
	}

	function addStep() {
		setSteps( [ ...steps, newStep( steps.length + 1 ) ] );
	}

	function removeStep( si ) {
		if ( steps.length <= 1 ) { return; }
		setSteps( steps.filter( ( _, i ) => i !== si ) );
		setSelected( null );
	}

	function addField( si ) {
		const field = newField( addType );
		const next = steps.map( ( s, i ) => ( i === si ? { ...s, fields: [ ...s.fields, field ] } : s ) );
		setSteps( next );
		setSelected( { step: si, index: next[ si ].fields.length - 1 } );
	}

	function updateField( si, fi, field ) {
		setSteps( steps.map( ( s, i ) => (
			i === si ? { ...s, fields: s.fields.map( ( f, j ) => ( j === fi ? field : f ) ) } : s
		) ) );
	}

	function removeField( si, fi ) {
		setSteps( steps.map( ( s, i ) => (
			i === si ? { ...s, fields: s.fields.filter( ( _, j ) => j !== fi ) } : s
		) ) );
		setSelected( null );
	}

	// Move a field from (fromStep,fromIndex) to (toStep, toIndex). toIndex may be
	// equal to the target list length (append).
	function moveField( from, to ) {
		const next = steps.map( ( s ) => ( { ...s, fields: [ ...s.fields ] } ) );
		const [ moved ] = next[ from.step ].fields.splice( from.index, 1 );
		if ( ! moved ) { return; }
		let toIndex = to.index;
		if ( from.step === to.step && from.index < to.index ) { toIndex -= 1; }
		next[ to.step ].fields.splice( toIndex, 0, moved );
		setSteps( next );
		setSelected( { step: to.step, index: toIndex } );
	}

	const sel = selected && steps[ selected.step ] && steps[ selected.step ].fields[ selected.index ];
	const allFields = steps.flatMap( ( s ) => s.fields );

	return (
		<div className="ofb-build">
			<div className="ofb-build__canvas">
				{ steps.map( ( step, si ) => (
					<StepColumn
						key={ step.id }
						step={ step }
						si={ si }
						selected={ selected }
						onSelect={ setSelected }
						onPatchStep={ ( p ) => patchStep( si, p ) }
						onRemoveStep={ () => removeStep( si ) }
						onMoveField={ moveField }
						canRemoveStep={ steps.length > 1 }
					>
						<Flex className="ofb-build__add">
							<FlexItem isBlock>
								<SelectControl
									value={ addType }
									options={ FIELD_TYPES }
									onChange={ setAddType }
									__nextHasNoMarginBottom
								/>
							</FlexItem>
							<FlexItem>
								<Button variant="secondary" onClick={ () => addField( si ) }>{ __( 'Add field', 'open-form-builder' ) }</Button>
							</FlexItem>
						</Flex>
					</StepColumn>
				) ) }
				<Button variant="tertiary" onClick={ addStep } className="ofb-build__add-step">
					{ __( '+ Add step', 'open-form-builder' ) }
				</Button>
			</div>

			<div className="ofb-build__inspector">
				{ sel ? (
					<FieldEditor
						field={ sel }
						allFields={ allFields }
						onChange={ ( f ) => updateField( selected.step, selected.index, f ) }
						onRemove={ () => removeField( selected.step, selected.index ) }
					/>
				) : (
					<Card><CardBody>{ __( 'Select a field to edit it, or add a new one.', 'open-form-builder' ) }</CardBody></Card>
				) }
			</div>
		</div>
	);
}

function StepColumn( { step, si, selected, onSelect, onPatchStep, onRemoveStep, onMoveField, canRemoveStep, children } ) {
	function onDropField( e, toIndex ) {
		e.preventDefault();
		e.stopPropagation();
		const data = e.dataTransfer.getData( 'text/plain' );
		if ( ! data ) { return; }
		const from = JSON.parse( data );
		onMoveField( from, { step: si, index: toIndex } );
	}

	return (
		<Card className="ofb-step-col">
			<CardBody>
				<TextControl
					value={ step.title }
					onChange={ ( title ) => onPatchStep( { title } ) }
					placeholder={ __( 'Step title', 'open-form-builder' ) }
					__nextHasNoMarginBottom
				/>
				<TextareaControl
					value={ step.description }
					onChange={ ( description ) => onPatchStep( { description } ) }
					placeholder={ __( 'Step description (optional)', 'open-form-builder' ) }
					rows={ 2 }
				/>

				<ul
					className="ofb-field-list"
					onDragOver={ ( e ) => e.preventDefault() }
					onDrop={ ( e ) => onDropField( e, step.fields.length ) }
				>
					{ step.fields.map( ( field, fi ) => (
						<li
							key={ field.id }
							className={ 'ofb-field-row' + ( selected && selected.step === si && selected.index === fi ? ' is-selected' : '' ) }
							draggable
							onDragStart={ ( e ) => e.dataTransfer.setData( 'text/plain', JSON.stringify( { step: si, index: fi } ) ) }
							onDragOver={ ( e ) => e.preventDefault() }
							onDrop={ ( e ) => onDropField( e, fi ) }
							onClick={ () => onSelect( { step: si, index: fi } ) }
						>
							<span className="ofb-field-row__handle" aria-hidden="true">⠿</span>
							<span className="ofb-field-row__label">{ field.label || field.name }</span>
							<span className="ofb-field-row__type">{ field.type }</span>
						</li>
					) ) }
					{ step.fields.length === 0 && (
						<li className="ofb-field-row ofb-field-row--empty">{ __( 'Drop fields here', 'open-form-builder' ) }</li>
					) }
				</ul>

				{ children }

				{ canRemoveStep && (
					<Button isDestructive variant="link" onClick={ onRemoveStep } className="ofb-step-col__remove">
						{ __( 'Remove step', 'open-form-builder' ) }
					</Button>
				) }
			</CardBody>
		</Card>
	);
}
