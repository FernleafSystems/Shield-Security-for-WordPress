/**
 * Tracks unsaved edits per option row in Configure option forms.
 *
 * A row is unsaved when any control differs from the value it was rendered with. The rendered
 * value comes from the control's own defaultValue / defaultChecked / defaultSelected, so it
 * holds however the form was injected or re-rendered, and form.reset() returns rows to clean.
 */
export class OptionDirtyState {

	static FORM_SELECTOR = 'form.options_form_for--modern';

	/**
	 * @param {Element} target any element inside an option row
	 */
	static refresh( target ) {
		const item = OptionDirtyState.itemFor( target );
		if ( item === null ) {
			return;
		}

		const isDirty = OptionDirtyState.serialize( item, false ) !== OptionDirtyState.serialize( item, true );
		item.classList.toggle( 'is-dirty', isDirty );
		item.querySelector( '[data-option-unsaved="1"]' )?.toggleAttribute( 'hidden', !isDirty );

		item.closest( 'form' )?.dispatchEvent( new CustomEvent( 'shield:options-dirty-change', { bubbles: true } ) );
	}

	/**
	 * @param {Element} target
	 * @return {HTMLElement|null}
	 */
	static itemFor( target ) {
		const item = target instanceof Element
			? target.closest( `${OptionDirtyState.FORM_SELECTOR} .shield-option-item` )
			: null;
		return item instanceof HTMLElement ? item : null;
	}

	/**
	 * @param {HTMLElement} item
	 * @param {boolean} rendered true for the values the controls were rendered with
	 * @return {string}
	 */
	static serialize( item, rendered ) {
		// A panel slider's range input has no name (a hidden field carries its value), but a hidden field's
		// value is also its default, so the range itself is what shows whether it has moved.
		const controls = item.querySelectorAll( '.shield-option-control-col [name], .shield-option-control-col input[type="range"]' );
		return JSON.stringify( [ ...controls ].map( ( control ) => {
			if ( control instanceof HTMLInputElement && ( control.type === 'checkbox' || control.type === 'radio' ) ) {
				return rendered ? control.defaultChecked : control.checked;
			}
			if ( control instanceof HTMLSelectElement ) {
				const values = [ ...control.options ].filter( ( opt ) => rendered ? opt.defaultSelected : opt.selected ).map( ( opt ) => opt.value );
				// A single select rendered without a "selected" option shows its first option.
				return rendered && values.length === 0 && !control.multiple && control.options.length > 0
					? [ control.options[ 0 ].value ]
					: values;
			}
			if ( control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement ) {
				return rendered ? control.defaultValue : control.value;
			}
			return '';
		} ) );
	}
}
