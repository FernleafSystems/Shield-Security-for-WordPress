import { AjaxService } from "../services/AjaxService";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";

export class ScanItemMalaiForm {

	static initializeWithin( contextEl ) {
		if ( !( contextEl instanceof Element || contextEl instanceof Document ) ) {
			return;
		}

		contextEl.querySelectorAll( 'form[data-scan-item-malai-query-action]' ).forEach(
			( form ) => ScanItemMalaiForm.initializeForm( form )
		);
	}

	static initializeForm( form ) {
		if ( !( form instanceof HTMLFormElement ) || form.dataset.scanItemMalaiInitialized === '1' ) {
			return;
		}

		form.dataset.scanItemMalaiInitialized = '1';
		form.addEventListener( 'submit', ( evt ) => ScanItemMalaiForm.handleSubmit( form, evt ) );
	}

	static handleSubmit( form, evt ) {
		evt.preventDefault();

		if ( !ScanItemMalaiForm.isConfirmed( form ) ) {
			shieldServices.dialog().message( {
				message: 'Please check the box to agree.',
				launcher: form.querySelector( 'input[type=checkbox]' ),
			} );
			return false;
		}

		const actionData = ScanItemMalaiForm.parseActionData( form );
		if ( actionData === null ) {
			shieldServices.dialog().message( {
				message: 'Communications error with site.',
				launcher: form,
			} );
			return false;
		}

		( new AjaxService() )
		.send( ObjectOps.Merge( actionData, Forms.Serialize( form ) ) )
		.finally();

		return false;
	}

	static isConfirmed( form ) {
		let ready = true;

		form.querySelectorAll( 'input[type=checkbox]' ).forEach(
			( checkbox ) => {
				ready = ready && checkbox.checked;
			}
		);

		return ready;
	}

	static parseActionData( form ) {
		try {
			const parsed = JSON.parse( form.dataset.scanItemMalaiQueryAction || '' );
			return parsed && typeof parsed === 'object' && !Array.isArray( parsed ) ? parsed : null;
		}
		catch ( error ) {
			return null;
		}
	}
}
