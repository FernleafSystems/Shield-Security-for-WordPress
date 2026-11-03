import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

export class Blockdown extends BaseAutoExecComponent {

	run() {
		this.handleForms();

		// Lock down waits for all four confirmations, and for your own IP to be allowed in.
		shieldEventsHandler_Main.add_Change( '[data-lockdown-form="1"] input[type="checkbox"]', ( input ) => {
			const form = input.closest( 'form' );
			form instanceof HTMLFormElement && this.syncLockdownReady( form );
		} );
	}

	/**
	 * @param {HTMLFormElement} form
	 */
	syncLockdownReady( form ) {
		const message = form.querySelector( '[data-lockdown-message="1"]' );
		const submit = form.querySelector( '[data-lockdown-submit="1"]' );
		if ( !( message instanceof HTMLElement ) || !( submit instanceof HTMLButtonElement ) ) {
			return;
		}
		const confirms = [ ...form.querySelectorAll( '[data-lockdown-confirm="1"]' ) ];
		const allConfirmed = confirms.every( ( box ) => box instanceof HTMLInputElement && box.checked );
		const addMe = form.querySelector( '[data-lockdown-whitelist-me="1"]' );
		const ipAllowed = message.dataset.ipBypassed === '1' || ( addMe instanceof HTMLInputElement && addMe.checked );
		const ready = allConfirmed && ipAllowed;
		submit.disabled = !ready;
		const text = ready ? message.dataset.ready : ( ipAllowed ? message.dataset.needs : message.dataset.needsIp );
		if ( message.textContent !== text ) {
			message.textContent = text || '';
		}
	}

	handleForms() {
		shieldEventsHandler_Main.add_Submit( '#FormBlockdown', ( form ) => {
			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.blockdown_form_submit, { 'form_data': Forms.Serialize( form ) } )
			)
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( '#FormBlockdownDisable', ( form ) => {
			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.blockdown_disable_form_submit, { 'form_data': Forms.Serialize( form ) } )
			)
			.finally();
		} );
	}
}