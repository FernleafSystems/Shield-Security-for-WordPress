import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { OffCanvasService } from "../ui/OffCanvasService";
import { sendEncodedOptionsSave } from "./OptionsSaveRequest";

export class OptionsFormSubmit extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form.options_form_for', ( targetEl ) => this.#submitOptionsForm( targetEl ) );
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
			this.#sendForm();
		}
	};

	#sendForm() {
		sendEncodedOptionsSave( this._base_data.ajax.form_save, Forms.Serialize( this.form ) )
		.then( ( resp ) => {
			setTimeout( () => {
				if ( this.form.dataset[ 'context' ] === 'expansion' && !resp.data.page_reload ) {
					this.form.dispatchEvent( new CustomEvent( 'shield:expansion-form-saved', {
						bubbles: true
					} ) );
				}
				else if ( this.form.dataset[ 'context' ] === 'offcanvas' && !resp.data.page_reload ) {
					OffCanvasService.CloseCanvas();
				}
				else {
					window.location.reload();
				}
			}, 1000 );
		} )
		.catch( () => null )
		.finally();
	};
}
