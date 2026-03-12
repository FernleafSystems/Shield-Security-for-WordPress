import { Modal } from "bootstrap";

export class BootstrapModals {

	static Show( modalEl, options = {} ) {
		if ( modalEl ) {
			BootstrapModals.normalizeModalAccessibility( modalEl );
			Modal.getOrCreateInstance( modalEl, options ).show();
		}
	}

	static Hide( modalEl ) {
		if ( modalEl ) {
			Modal.getOrCreateInstance( modalEl ).hide();
		}
	}

	static normalizeModalAccessibility( modalEl ) {
		modalEl.setAttribute( 'aria-modal', 'true' );

		const titleEl = modalEl.querySelector( '.modal-title' );
		if ( titleEl ) {
			if ( titleEl.id.length === 0 ) {
				titleEl.id = `${ modalEl.id || 'ShieldModal' }Label`;
			}
			modalEl.setAttribute( 'aria-labelledby', titleEl.id );
		}
	}
}
