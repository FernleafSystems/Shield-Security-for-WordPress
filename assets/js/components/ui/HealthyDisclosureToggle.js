import { UiContentActivator } from "./UiContentActivator";

export class HealthyDisclosureToggle {

	constructor( getRoot ) {
		this.getRoot = typeof getRoot === 'function' ? getRoot : () => null;
	}

	bind() {
		if ( this.hasBound ) {
			return;
		}
		this.hasBound = true;

		document.addEventListener( 'click', ( evt ) => {
			const toggle = evt.target instanceof Element
				? evt.target.closest( '[data-healthy-disclosure-toggle="1"]' )
				: null;
			if ( toggle === null ) {
				return;
			}

			const root = this.getRoot();
			if ( root !== null && !root.contains( toggle ) ) {
				return;
			}

			toggle.classList.toggle( 'is-open' );
			const body = toggle.nextElementSibling;
			if ( body === null || body.getAttribute( 'data-healthy-disclosure-body' ) !== '1' ) {
				return;
			}

			body.classList.toggle( 'is-open' );
			if ( body.classList.contains( 'is-open' ) ) {
				UiContentActivator.activateCurrentSubtree( body );
			}
		} );
	}
}
