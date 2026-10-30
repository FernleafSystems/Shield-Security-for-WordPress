/**
 * Behaviour for Configure panels. A slider is a native range input that moves between the setting's
 * stops: as it moves, this copies the chosen stop's value into the setting's hidden field and shows the
 * stop's name beside the track. It also opens and closes "More options". Panels are injected after page
 * load, so everything is delegated.
 */
export class ConfigurePanelControls {

	static PANEL_SELECTOR = 'form.options_form_for--panel';

	constructor() {
		// Runs before unsaved-change tracking sees the event, so the hidden field is already up to date.
		const onRange = ( evt ) => {
			const range = evt.target;
			if ( range instanceof HTMLInputElement && range.classList.contains( 'shield-range__input' ) ) {
				ConfigurePanelControls.syncRange( range );
			}
		};
		document.addEventListener( 'input', onRange );
		document.addEventListener( 'change', onRange );

		// Discarding changes resets the form, which fires no input events.
		document.addEventListener( 'reset', ( evt ) => {
			const form = evt.target;
			if ( form instanceof HTMLFormElement && form.matches( ConfigurePanelControls.PANEL_SELECTOR ) ) {
				window.setTimeout( () => form.querySelectorAll( '.shield-range__input' ).forEach(
					( range ) => range instanceof HTMLInputElement && ConfigurePanelControls.syncRange( range )
				) );
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
	 * Opens a panel's "More options" when a field inside it is the target of a search.
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
	 * @param {HTMLInputElement} range
	 */
	static syncRange( range ) {
		let stops = [];
		try {
			stops = JSON.parse( range.dataset.stops || '[]' );
		}
		catch {
			return;
		}
		const index = Math.min( Math.max( parseInt( range.value, 10 ) || 0, 0 ), stops.length - 1 );
		const stop = stops[ index ];
		if ( !stop ) {
			return;
		}

		const wrap = range.closest( '[data-panel-range="1"]' );
		const hidden = wrap?.querySelector( 'input[type="hidden"]' );
		if ( hidden instanceof HTMLInputElement ) {
			hidden.value = String( stop.value );
		}
		const output = wrap?.querySelector( '[data-range-value="1"]' );
		if ( output instanceof HTMLElement ) {
			output.textContent = stop.label;
			output.classList.toggle( 'is-off', stop.value === '0' || stop.value === 'disabled' );
		}
		range.setAttribute( 'aria-valuetext', stop.label );
		const last = Math.max( stops.length - 1, 1 );
		range.style.setProperty( '--shield-range-pos', `${( index / last ) * 100}%` );
	}
}
