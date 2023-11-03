import { Base64 } from 'js-base64';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";
import { ToasterService } from "./ToasterService";

export class OptionsFormSubmit extends BaseService {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form.icwpOptionsForm', ( targetEl ) => this.#submitOptionsForm( targetEl ) );
	}

	#submitOptionsForm( form ) {
		this.form = form;

		let passwordsReady = true;
		this.form.querySelectorAll( 'input[type=password]' ).forEach( ( passwordField ) => {

			if ( passwordField.value && passwordField.value.length > 0 ) {

				const confirmPass = this.form.querySelector( '#' + passwordField.id + '_confirm' );
				if ( confirmPass && ( confirmPass.value.length === 0 || passwordField.value !== confirmPass.value ) ) {
					confirmPass.classList.add( 'is-invalid' );
					alert( 'Form not submitted due to error: security admin PIN and confirm PIN do not match.' );
					passwordsReady = false;
				}
			}
		} );

		if ( passwordsReady ) {
			this.#sendForm( false );
		}
	};

	/**
	 * First try with base64 and failover to lz-string upon abject failure.
	 * This works around mod_security rules that even unpack b64 encoded params and look
	 * for patterns within them.
	 */
	#sendForm( obscure = false ) {

		let formData = Base64.encode( JSON.stringify( Forms.Serialize( this.form ) ) );

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.form_save, {
				form_params: obscure ? 'icwp-' + formData : formData,
				form_enc: obscure ? [ 'obscure', 'b64', 'json' ] : [ 'b64', 'json' ],
			} )
		)
		.then( ( resp ) => {
			setTimeout( () => {
				if ( this.form.dataset[ 'context' ] !== 'offcanvas' || resp.data.page_reload ) {
					window.location.reload();
				}
				else {
					OffCanvasService.CloseCanvas();
				}
			}, 1000 );
		} )
		.catch( ( error ) => {
			if ( obscure ) {
				( new ToasterService() ).showMessage( 'Alternative failed.', false );
			}
			else {
				( new ToasterService() ).showMessage( 'The request was blocked. Retrying an alternative...', false );
				this.#sendForm( true );
			}
		} )
		.finally();
	};
}