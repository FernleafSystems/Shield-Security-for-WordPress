import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { BootstrapModals } from "../ui/BootstrapModals";

export class ProUpsell extends BaseAutoExecComponent {

	canRun() {
		return document.getElementById( 'shield-pro-upsell-template' ) !== null;
	}

	run() {
		shieldEventsHandler_Main.add_Click( '[data-pro-upsell="1"]', () => this.launch(), false );
	}

	launch() {
		const template = document.getElementById( 'shield-pro-upsell-template' );
		const modal = document.getElementById( 'ShieldModalContainer' );
		const content = modal?.querySelector( '.modal-content' ) || null;
		if ( !( template instanceof HTMLTemplateElement ) || modal === null || content === null ) {
			return;
		}

		content.replaceChildren( template.content.cloneNode( true ) );
		modal.classList.add( 'shield-modal--pro-upsell' );
		const cleanupProUpsellModalState = () => modal.classList.remove( 'shield-modal--pro-upsell' );
		modal.addEventListener( 'hidden.bs.modal', cleanupProUpsellModalState, { once: true } );
		if ( !BootstrapModals.Show( modal ) ) {
			modal.removeEventListener( 'hidden.bs.modal', cleanupProUpsellModalState );
			cleanupProUpsellModalState();
		}
	}
}
