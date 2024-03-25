import { BaseService } from "./BaseService";

/**
 * Takes event delegation to the max.
 *
 * Here we add a single event listener and then attach all the possible callbacks to it based on the selector for the
 * clicked element.
 */
export class ShieldEventsHandler extends BaseService {

	init() {
		this.eventHandlers = {
			click: {},
			change: {},
			keypress: {},
			keyup: {},
			submit: {},
			'shown.bs.tab': {},
			'hidden.bs.offcanvas': {}
		}

		const container = document.querySelector( this._base_data.events_container_selector );
		if ( container ) {
			for ( const eventType in this.eventHandlers ) {
				container.addEventListener( eventType, ( evt ) => {
					const t = evt.target;
					for ( const selector in this.eventHandlers[ eventType ] ) {
						if ( t.closest( selector ) ) {

							const theHandler = this.eventHandlers[ eventType ][ selector ];
							if ( theHandler.suppress ) {
								evt.preventDefault();
							}
							theHandler.callback( t.closest( selector ), evt );
							if ( theHandler.suppress ) {
								return false;
							}
						}
					}
				}, false );
			}
		}
	}

	addHandler( event, selector, callback, suppress = null ) {
		this.eventHandlers[ event ][ selector ] = {
			callback: callback,
			suppress: suppress === null ? this.isSuppressEvent( event ) : suppress
		};
	}

	add_Change( selector, callback, suppress = null ) {
		this.addHandler( 'change', selector, callback, suppress );
	}

	add_Click( selector, callback, suppress = null ) {
		this.addHandler( 'click', selector, callback, suppress );
	}

	add_Keypress( selector, callback, suppress = null ) {
		this.addHandler( 'keypress', selector, callback, suppress );
	}

	add_Keyup( selector, callback, suppress = null ) {
		this.addHandler( 'keyup', selector, callback, suppress );
	}

	add_Submit( selector, callback, suppress = null ) {
		this.addHandler( 'submit', selector, callback, suppress );
	}

	isSuppressEvent( event ) {
		return [ 'click', 'submit' ].includes( event );
	};
}