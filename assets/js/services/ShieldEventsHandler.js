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
			'shown.bs.tab': {}
		}

		const container = document.querySelector( this._base_data.events_container_selector );
		if ( container ) {
			for ( const eventType in this.eventHandlers ) {
				container.addEventListener( eventType, ( evt ) => {
					const t = evt.target;
					for ( const selector in this.eventHandlers[ eventType ] ) {
						if ( t.closest( selector ) ) {
							if ( this.isSuppressEvent( eventType ) ) {
								evt.preventDefault();
							}
							this.eventHandlers[ eventType ][ selector ]( t.closest( selector ), evt );
							if ( this.isSuppressEvent( eventType ) ) {
								return false;
							}
						}
					}
				}, false );
			}
		}
	}

	addHandler( event, selector, callback ) {
		this.eventHandlers[ event ][ selector ] = callback;
	}

	add_Change( selector, callback ) {
		this.addHandler( 'change', selector, callback );
	}

	add_Click( selector, callback ) {
		this.addHandler( 'click', selector, callback );
	}

	add_Keypress( selector, callback ) {
		this.addHandler( 'keypress', selector, callback );
	}

	add_Keyup( selector, callback ) {
		this.addHandler( 'keyup', selector, callback );
	}

	add_Submit( selector, callback ) {
		this.addHandler( 'submit', selector, callback );
	}

	isSuppressEvent( event ) {
		return [ 'click', 'submit' ].includes( event );
	};
}