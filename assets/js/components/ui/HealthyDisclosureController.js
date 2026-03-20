import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { UiContentActivator } from "./UiContentActivator";

export class HealthyDisclosureController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		if ( this.hasBoundHandlers ) {
			return;
		}
		this.hasBoundHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-healthy-disclosure-toggle="1"]',
			( toggle ) => this.handleToggleClick( toggle ),
			false
		);
	}

	handleToggleClick( toggle ) {
		if ( !( toggle instanceof HTMLElement ) ) {
			return;
		}

		const body = toggle.nextElementSibling;
		if ( !( body instanceof HTMLElement ) || body.dataset.healthyDisclosureBody !== '1' ) {
			return;
		}

		const shouldOpen = !toggle.classList.contains( 'is-open' );
		toggle.classList.toggle( 'is-open', shouldOpen );
		body.classList.toggle( 'is-open', shouldOpen );
		toggle.setAttribute( 'aria-expanded', shouldOpen ? 'true' : 'false' );
		body.setAttribute( 'aria-hidden', shouldOpen ? 'false' : 'true' );
		if ( shouldOpen ) {
			UiContentActivator.activateCurrentSubtree( body );
		}
	}
}
