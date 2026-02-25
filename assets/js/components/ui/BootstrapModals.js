import { Modal } from "bootstrap";

export class BootstrapModals {

	static Show( modalEl, options = {} ) {
		if ( modalEl ) {
			Modal.getOrCreateInstance( modalEl, options ).show();
		}
	}
}
