import $ from 'jquery';
import { Base64 } from 'js-base64';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import { ToasterService } from "./ToasterService";
import { ShieldServicesPlugin } from "./ShieldServicesPlugin";
import { OffCanvasService } from "./OffCanvasService";

export class OptionsFormSubmit extends BaseService {

	init() {
		$( document ).on( "submit", 'form.icwpOptionsForm', ( evt ) => this.#submitOptionsForm( evt ) );
	}

	#submitOptionsForm( evt ) {
		evt.preventDefault();

		this.$form = $( evt.currentTarget );

		let $passwordsReady = true;
		$( 'input[type=password]', this.$form ).each( ( index, passwordField ) => {
			let $pass = $( passwordField );
			let $confirm = $( '#' + $pass.attr( 'id' ) + '_confirm', this.$form );
			if ( typeof $confirm.attr( 'id' ) !== 'undefined' ) {
				if ( $pass.val() && !$confirm.val() ) {
					$confirm.addClass( 'is-invalid' );
					alert( 'Form not submitted due to error: password confirmation field not provided.' );
					$passwordsReady = false;
				}
			}
		} );

		if ( $passwordsReady ) {
			this.#sendForm( false );
		}

		return false;
	};

	/**
	 * First try with base64 and failover to lz-string upon abject failure.
	 * This works around mod_security rules that even unpack b64 encoded params and look
	 * for patterns within them.
	 */
	#sendForm( obscure = false ) {

		let formData = this.$form.serialize();
		if ( obscure ) {
			formData = 'icwp-' + Base64.encode( formData );
		}

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.form_save, {
				'form_params': Base64.encode( formData ),
				'enc_params': obscure ? 'obscure' : 'b64',
			} )
		)
		.then( ( resp ) => {
			setTimeout( () => {
				if ( this.$form.data( 'context' ) !== 'offcanvas' || resp.data.page_reload ) {
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