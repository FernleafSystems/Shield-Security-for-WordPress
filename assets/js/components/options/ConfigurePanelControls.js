/**
 * Behaviour for Configure panels. The controls are plain form fields, so this only keeps what they show
 * in step: a slider's fill and value, settings that fade while the switch above them is off, and the
 * "More options" section. Panels are injected after page load, so everything is delegated.
 */
export class ConfigurePanelControls {

	static PANEL_SELECTOR = 'form.options_form_for--panel';

	constructor() {
		document.addEventListener( 'change', ( evt ) => {
			const control = evt.target;
			if ( !( control instanceof HTMLInputElement ) || control.closest( ConfigurePanelControls.PANEL_SELECTOR ) === null ) {
				return;
			}
			if ( control.classList.contains( 'shield-slider__input' ) ) {
				ConfigurePanelControls.syncSlider( control.closest( '[data-panel-slider="1"]' ) );
			}
			ConfigurePanelControls.syncChildren( control );
		} );

		// Discarding changes resets the form, which fires no change events.
		document.addEventListener( 'reset', ( evt ) => {
			const form = evt.target;
			if ( form instanceof HTMLFormElement && form.matches( ConfigurePanelControls.PANEL_SELECTOR ) ) {
				window.setTimeout( () => ConfigurePanelControls.syncForm( form ) );
			}
		} );

		shieldEventsHandler_Main.add_Click( '[data-panel-more="1"]', ( button ) => {
			const body = document.getElementById( button.getAttribute( 'aria-controls' ) || '' );
			if ( body instanceof HTMLElement ) {
				ConfigurePanelControls.setMoreOpen( button, body, body.hidden );
			}
		} );
	}

	/**
	 * Opens a panel's "More options" when a field inside it is the target of a search or "Show me".
	 * @param {Element} field
	 */
	static revealMoreOptions( field ) {
		const body = field instanceof Element ? field.closest( '.shield-panel-more__body' ) : null;
		if ( body instanceof HTMLElement && body.hidden ) {
			const button = body.parentElement?.querySelector( '[data-panel-more="1"]' );
			button instanceof HTMLElement && ConfigurePanelControls.setMoreOpen( button, body, true );
		}
	}

	static setMoreOpen( button, body, isOpen ) {
		body.hidden = !isOpen;
		button.setAttribute( 'aria-expanded', String( isOpen ) );
		button.classList.toggle( 'is-open', isOpen );
	}

	/**
	 * @param {HTMLFormElement} form
	 */
	static syncForm( form ) {
		form.querySelectorAll( '[data-panel-slider="1"]' ).forEach( ( slider ) => ConfigurePanelControls.syncSlider( slider ) );
		form.querySelectorAll( '[data-panel-children-of]' ).forEach( ( children ) => {
			const parentKey = children.getAttribute( 'data-panel-children-of' ) || '';
			const parent = form.querySelector( `[name="${CSS.escape( parentKey )}"]` );
			parent instanceof HTMLInputElement && ConfigurePanelControls.syncChildren( parent );
		} );
	}

	/**
	 * @param {Element|null} slider
	 */
	static syncSlider( slider ) {
		if ( !( slider instanceof HTMLElement ) ) {
			return;
		}
		const checked = slider.querySelector( '.shield-slider__input:checked' );
		if ( !( checked instanceof HTMLInputElement ) ) {
			return;
		}
		slider.style.setProperty( '--shield-slider-pos', `${checked.dataset.stopPos || 0}%` );
		const value = slider.closest( '.shield-option-item' )?.querySelector( '[data-slider-value="1"]' );
		if ( value instanceof HTMLElement ) {
			value.textContent = checked.dataset.stopLabel || '';
			value.classList.toggle( 'is-off', checked.value === '0' || checked.value === 'disabled' );
		}
	}

	/**
	 * Fades the settings beneath a switch (or an "Off"-able slider) while it is off.
	 * @param {HTMLInputElement} control
	 */
	static syncChildren( control ) {
		const form = control.form;
		const children = form?.querySelector( `[data-panel-children-of="${CSS.escape( control.name )}"]` );
		if ( !( children instanceof HTMLElement ) ) {
			return;
		}
		let isOn;
		if ( control.type === 'radio' ) {
			const checked = form.querySelector( `[name="${CSS.escape( control.name )}"]:checked` );
			isOn = checked instanceof HTMLInputElement && checked.value !== '0' && checked.value !== 'disabled';
		}
		else {
			const toggle = form.querySelector( `input[type="checkbox"][name="${CSS.escape( control.name )}"]` );
			isOn = toggle instanceof HTMLInputElement ? toggle.checked : true;
		}
		children.classList.toggle( 'is-off', !isOn );
	}
}
