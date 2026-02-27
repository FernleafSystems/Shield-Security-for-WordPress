import { SecurityAdminBase } from "./SecurityAdminBase";
import { BootstrapModals } from "../ui/BootstrapModals";

export class SecurityAdmin extends SecurityAdminBase {

	showRestrictedPageModal() {
		const modalEl = document.getElementById( 'SecurityAdminOverlay' );
		if ( modalEl ) {
			modalEl.addEventListener( 'shown.bs.modal', () => {
				modalEl.querySelector( '#sec_admin_key' )?.focus();
			}, { once: true } );

			BootstrapModals.Show( modalEl, {
				backdrop: 'static',
				keyboard: false
			} );
		}
	}
}
