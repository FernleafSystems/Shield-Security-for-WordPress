import { Modal } from "bootstrap";
import { focusElement } from "./ShieldA11y";

export class BootstrapModals {

	static openerElements = new WeakMap();

	static Show( modalEl, options = {} ) {
		if ( modalEl ) {
			if ( !BootstrapModals.normalizeModalAccessibility( modalEl ) ) {
				return false;
			}
			const relatedTarget = BootstrapModals.captureOpener( modalEl );
			Modal.getOrCreateInstance( modalEl, options ).show( relatedTarget );
			return true;
		}
		return false;
	}

	static Hide( modalEl ) {
		if ( modalEl ) {
			Modal.getOrCreateInstance( modalEl ).hide();
		}
	}

	static normalizeModalAccessibility( modalEl ) {
		modalEl.setAttribute( 'role', 'dialog' );
		if ( !modalEl.hasAttribute( 'tabindex' ) ) {
			modalEl.setAttribute( 'tabindex', '-1' );
		}
		BootstrapModals.normalizeDescription( modalEl );

		const currentLabel = BootstrapModals.getReferencedLabel( modalEl );
		if ( currentLabel !== null ) {
			return true;
		}

		const titleEl = modalEl.querySelector( '.modal-title[id]' );
		if ( titleEl instanceof HTMLElement && ( titleEl.textContent || '' ).trim().length > 0 ) {
			modalEl.setAttribute( 'aria-labelledby', titleEl.id );
			return true;
		}

		modalEl.removeAttribute( 'aria-labelledby' );
		return false;
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

	static normalizeDescription( modalEl ) {
		const descriptionId = modalEl.getAttribute( 'aria-describedby' ) || '';
		if ( descriptionId.length < 1 ) {
			return;
		}

		const descriptionEl = modalEl.ownerDocument.getElementById( descriptionId );
		if ( !( descriptionEl instanceof HTMLElement ) || ( descriptionEl.textContent || '' ).trim().length < 1 ) {
			modalEl.removeAttribute( 'aria-describedby' );
		}
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
