import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { OffCanvasService } from "../ui/OffCanvasService";
import { sendEncodedOptionsSave } from "./OptionsSaveRequest";

export class OptionsFormSubmit extends BaseComponent {

	static profileMutationRoot( form ) {
		const pane = form.closest( '[data-import-export-pane="profile"]' );
		return pane instanceof HTMLElement ? pane : form;
	}

	static beginProfileMutation( form ) {
		if ( !( form instanceof HTMLFormElement ) || !form.matches( 'form.import-export-profile-options-form' ) ) {
			return true;
		}
		if ( form.dataset.importExportProfileMutationBusy === '1' ) {
			return false;
		}

		form.dataset.importExportProfileMutationBusy = '1';
		form.setAttribute( 'aria-busy', 'true' );
		OptionsFormSubmit.profileMutationRoot( form ).querySelectorAll( [
			'[data-import-export-profile-sync-toggle]',
			'[data-import-export-profile-group-sync-toggle]',
			'[data-import-export-profile-copy-from-master]',
			'button[type="submit"]',
		].join( ',' ) ).forEach( ( control ) => {
			if ( control instanceof HTMLButtonElement && !control.disabled ) {
				control.dataset.importExportProfileMutationDisabled = '1';
				control.disabled = true;
			}
		} );

		return true;
	}

	static endProfileMutation( form ) {
		if ( !( form instanceof HTMLFormElement ) || !form.matches( 'form.import-export-profile-options-form' ) ) {
			return;
		}

		OptionsFormSubmit.profileMutationRoot( form )
			.querySelectorAll( '[data-import-export-profile-mutation-disabled="1"]' ).forEach( ( control ) => {
			if ( control instanceof HTMLButtonElement ) {
				control.disabled = false;
				delete control.dataset.importExportProfileMutationDisabled;
			}
		} );
		delete form.dataset.importExportProfileMutationBusy;
		form.removeAttribute( 'aria-busy' );
	}

	init() {
		shieldEventsHandler_Main.add_Submit( 'form.options_form_for', ( targetEl ) => this.#submitOptionsForm( targetEl ) );
	}

	#submitOptionsForm( form ) {
		let passwordsReady = true;
		form.querySelectorAll( 'input[type=password]' ).forEach( ( passwordField ) => {

			if ( passwordField.value && passwordField.value.length > 0 ) {

				const confirmPass = form.querySelector( '#' + passwordField.id + '_confirm' );
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
			this.#sendForm( form );
		}
	};

	#sendForm( form ) {
		const actionKey = form.dataset.optionsSaveAction;
		if ( !actionKey || !( actionKey in this._base_data.ajax ) ) {
			return;
		}
		const formData = Forms.Serialize( form );
		if ( !OptionsFormSubmit.beginProfileMutation( form ) ) {
			return;
		}

		let request;
		try {
			request = sendEncodedOptionsSave( this._base_data.ajax[ actionKey ], formData );
		}
		catch ( error ) {
			OptionsFormSubmit.endProfileMutation( form );
			throw error;
		}

		request
		.then( ( resp ) => {
			setTimeout( () => {
				if ( form.dataset[ 'context' ] === 'expansion' && !resp.data.page_reload ) {
					form.dispatchEvent( new CustomEvent( 'shield:expansion-form-saved', {
						bubbles: true
					} ) );
				}
				else if ( form.dataset[ 'context' ] === 'offcanvas' && !resp.data.page_reload ) {
					OffCanvasService.CloseCanvas();
				}
				else if ( form.dataset[ 'context' ] === 'import_export_profile' && !resp.data.page_reload ) {
					form.dispatchEvent( new CustomEvent( 'shield:import-export-profile-saved', {
						bubbles: true
					} ) );
				}
				else {
					window.location.reload();
				}
			}, 1000 );
		} )
		.catch( () => null )
		.finally( () => OptionsFormSubmit.endProfileMutation( form ) );
	};
}
