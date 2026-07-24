/**
 * Shown before a blank editor: pick one of the built-in use-case templates (a
 * ready-made schema + settings + slots + branding) or start from scratch.
 * Picking a template only pre-fills the editor's local state — nothing is
 * written until the user hits "Save form".
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { TEMPLATES } from '../templates';

export default function TemplatePicker( { onPick, onBack } ) {
	return (
		<div className="ofb-templates">
			<div className="ofb-templates__head">
				<div>
					<h2>{ __( 'Start with a template', 'open-form-builder' ) }</h2>
					<p>{ __( 'Pick the closest match — every field, price and colour is yours to edit afterwards.', 'open-form-builder' ) }</p>
				</div>
				<Button variant="tertiary" onClick={ onBack }>{ __( '← All forms', 'open-form-builder' ) }</Button>
			</div>

			<div className="ofb-templates__grid">
				{ TEMPLATES.map( ( t ) => (
					<button
						type="button"
						key={ t.key }
						className="ofb-template-card"
						style={ { '--ofb-tpl-accent': t.accent } }
						onClick={ () => onPick( t.build() ) }
					>
						<span className="ofb-template-card__icon" aria-hidden="true">{ t.emoji }</span>
						<span className="ofb-template-card__name">{ t.name }</span>
						<span className="ofb-template-card__desc">{ t.description }</span>
					</button>
				) ) }

				<button type="button" className="ofb-template-card ofb-template-card--blank" onClick={ () => onPick( null ) }>
					<span className="ofb-template-card__icon" aria-hidden="true">＋</span>
					<span className="ofb-template-card__name">{ __( 'Start from scratch', 'open-form-builder' ) }</span>
					<span className="ofb-template-card__desc">{ __( 'A blank single-step form.', 'open-form-builder' ) }</span>
				</button>
			</div>
		</div>
	);
}
