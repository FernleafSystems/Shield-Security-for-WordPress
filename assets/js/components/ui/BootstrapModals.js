import { Modal } from "bootstrap";
import { focusElement } from "./ShieldA11y";

export class BootstrapModals {

	static openerElements = new WeakMap();

	static Show( modalEl, options = {} ) {
		if ( modalEl ) {
			BootstrapModals.normalizeModalAccessibility( modalEl );
			const relatedTarget = BootstrapModals.captureOpener( modalEl );
			Modal.getOrCreateInstance( modalEl, options ).show( relatedTarget );
		}
	}

	static Hide( modalEl ) {
		if ( modalEl ) {
			Modal.getOrCreateInstance( modalEl ).hide();
		}
	}

	static normalizeModalAccessibility( modalEl ) {
		if ( !modalEl.hasAttribute( 'tabindex' ) ) {
			modalEl.setAttribute( 'tabindex', '-1' );
		}

		const currentLabel = BootstrapModals.getReferencedLabel( modalEl );
		if ( currentLabel !== null ) {
			return;
		}

		const titleEl = modalEl.querySelector( '.modal-title' );
		if ( titleEl instanceof HTMLElement && ( titleEl.textContent || '' ).trim().length > 0 ) {
			if ( titleEl.id.length === 0 ) {
				titleEl.id = `${ modalEl.id || 'ShieldModal' }Label`;
			}
			modalEl.setAttribute( 'aria-labelledby', titleEl.id );
			return;
		}

		modalEl.removeAttribute( 'aria-labelledby' );
	}

	static getReferencedLabel( modalEl ) {
		const labelId = modalEl.getAttribute( 'aria-labelledby' ) || '';
		if ( labelId.length < 1 ) {
			return null;
		}

		const labelEl = modalEl.ownerDocument.getElementById( labelId );
		return labelEl instanceof HTMLElement && ( labelEl.textContent || '' ).trim().length > 0
			? labelEl
			: null;
	}

	static captureOpener( modalEl ) {
		if ( modalEl.classList.contains( 'show' ) ) {
			return null;
		}

		const activeEl = modalEl.ownerDocument.activeElement;
		const opener = activeEl instanceof HTMLElement && !modalEl.contains( activeEl ) ? activeEl : null;
		if ( opener === null ) {
			return null;
		}

		BootstrapModals.openerElements.set( modalEl, opener );
		modalEl.addEventListener( 'hidden.bs.modal', () => {
			const previousOpener = BootstrapModals.openerElements.get( modalEl );
			BootstrapModals.openerElements.delete( modalEl );
			focusElement( previousOpener );
		}, { once: true } );

		return opener;
	}
}
