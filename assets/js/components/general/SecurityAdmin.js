import { Modal } from "bootstrap";
import { SecurityAdminBase } from "./SecurityAdminBase";

export class SecurityAdmin extends SecurityAdminBase {

	showRestrictedPageModal() {
		const modalEl = document.getElementById( 'SecurityAdminOverlay' );
		if ( modalEl ) {
			modalEl.addEventListener( 'shown.bs.modal', () => {
				modalEl.querySelector( '#sec_admin_key' )?.focus();
			}, { once: true } );

			( new Modal( modalEl, {
				backdrop: 'static',
				keyboard: false
			} ) ).show();
		}
	}
}
