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
					shieldServices.dialog().message( {
						message: 'Form not submitted due to error: security admin PIN and confirm PIN do not match.',
						launcher: confirmPass,
					} );
					passwordsReady = false;
				}
			}
		} );

		if ( passwordsReady ) {
			this.#sendForm();
		}
	};

	#sendForm() {
		const actionKey = this.form.dataset.optionsSaveAction;
		if ( !actionKey || !( actionKey in this._base_data.ajax ) ) {
			return;
		}
		sendEncodedOptionsSave( this._base_data.ajax[ actionKey ], Forms.Serialize( this.form ) )
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
				else if ( this.form.dataset[ 'context' ] === 'import_export_profile' && !resp.data.page_reload ) {
					this.form.dispatchEvent( new CustomEvent( 'shield:import-export-profile-saved', {
						bubbles: true
					} ) );
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
