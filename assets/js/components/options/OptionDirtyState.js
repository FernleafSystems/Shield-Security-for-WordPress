/**
 * Tracks unsaved edits per option row in Configure option forms, and restores defaults.
 *
 * A row's starting state is captured the first time the user focuses one of its controls,
 * because forms are injected after page load and may be re-rendered after each save.
 */
export class OptionDirtyState {

	static FORM_SELECTOR = 'form.options_form_for--modern';

	/**
	 * @param {Element} target any element inside an option row
	 */
	static captureInitial( target ) {
		const item = OptionDirtyState.itemFor( target );
		if ( item !== null && item.dataset.optionInitialState === undefined ) {
			item.dataset.optionInitialState = OptionDirtyState.serialize( item );
		}
	}

	/**
	 * @param {Element} target any element inside an option row
	 */
	static refresh( target ) {
		const item = OptionDirtyState.itemFor( target );
		if ( item === null ) {
			return;
		}

		const current = OptionDirtyState.serialize( item );
		const initial = item.dataset.optionInitialState;
		const isDirty = initial !== undefined && initial !== current;
		item.classList.toggle( 'is-dirty', isDirty );
		item.querySelector( '[data-option-unsaved="1"]' )?.toggleAttribute( 'hidden', !isDirty );

		const reset = item.querySelector( '[data-option-reset]' );
		if ( reset instanceof HTMLElement ) {
			reset.hidden = OptionDirtyState.matchesDefault( item, reset.dataset.defaultValue ?? '' );
		}

		const form = item.closest( 'form' );
		form?.dispatchEvent( new CustomEvent( 'shield:options-dirty-change', {
			bubbles: true,
			detail: {
				dirtyCount: form.querySelectorAll( '.shield-option-item.is-dirty' ).length,
			},
		} ) );
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

		OptionDirtyState.captureInitial( button );
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
	 * @return {string}
	 */
	static serialize( item ) {
		return JSON.stringify( [ ...item.querySelectorAll( '.shield-option-control-col [name]' ) ].map( ( control ) => {
			if ( control instanceof HTMLInputElement && ( control.type === 'checkbox' || control.type === 'radio' ) ) {
				return control.checked;
			}
			return 'value' in control ? control.value : '';
		} ) );
	}
}
