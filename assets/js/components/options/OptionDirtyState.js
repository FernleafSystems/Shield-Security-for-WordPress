/**
 * Tracks unsaved edits per option row in Configure option forms, and restores defaults.
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

		const reset = item.querySelector( '[data-option-reset]' );
		if ( reset instanceof HTMLElement ) {
			reset.hidden = OptionDirtyState.matchesDefault( item, reset.dataset.defaultValue ?? '' );
		}

		item.closest( 'form' )?.dispatchEvent( new CustomEvent( 'shield:options-dirty-change', { bubbles: true } ) );
	}

	/**
	 * @param {HTMLElement} button the "Reset to default" button
	 */
	static resetToDefault( button ) {
		const item = OptionDirtyState.itemFor( button );
		const control = item === null ? null : OptionDirtyState.primaryControl( item );
		if ( control === null ) {
			return;
		}

		const defaultValue = button.dataset.defaultValue ?? '';
		if ( control instanceof HTMLInputElement && control.type === 'checkbox' ) {
			control.checked = defaultValue === 'Y';
		}
		else {
			control.value = defaultValue;
		}
		control.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		control.focus();
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
	 * @return {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement|null}
	 */
	static primaryControl( item ) {
		const key = item.dataset.optionKey || '';
		const control = key.length > 0 ? item.querySelector( `[name="${key}"]` ) : null;
		return control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement
			? control
			: null;
	}

	/**
	 * @param {HTMLElement} item
	 * @param {string} defaultValue
	 */
	static matchesDefault( item, defaultValue ) {
		const control = OptionDirtyState.primaryControl( item );
		if ( control === null ) {
			return true;
		}
		if ( control instanceof HTMLInputElement && control.type === 'checkbox' ) {
			return control.checked === ( defaultValue === 'Y' );
		}
		return control.value === defaultValue;
	}

	/**
	 * @param {HTMLElement} item
	 * @param {boolean} rendered true for the values the controls were rendered with
	 * @return {string}
	 */
	static serialize( item, rendered ) {
		return JSON.stringify( [ ...item.querySelectorAll( '.shield-option-control-col [name]' ) ].map( ( control ) => {
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
