import $ from 'jquery';
import { BaseService } from "./BaseService";
import { ToasterService } from "./ToasterService";
import { ShieldOverlay } from "./ShieldOverlay";
import { ObjectOps } from "./ObjectOps";
import { AjaxService } from "./AjaxService";

export class SecurityAdmin extends BaseService {

	init() {
		this.hasCheckInPlace = false;
		this.isWarningShown = false;

		if ( this._base_data ) {
			this.timeoutInterval = 500 * this._base_data.vars.time_remaining;

			this.restrictWPOptions();

			$( document ).on( 'submit', 'form#SecurityAdminForm', ( evt ) => {
				evt.preventDefault();
				( new AjaxService() )
				.send(
					ObjectOps.Merge( this._base_data.ajax.sec_admin_login, { 'form_params': $( evt.currentTarget ).serialize() } )
				)
				.finally();
				return false;
			} );

			if ( this._base_data.flags.run_checks ) {
				this.scheduleSecAdminCheck();
			}

			$( document ).on( 'click', '#SecAdminRemoveConfirmEmail',
				( evt ) => {
					evt.preventDefault();
					if ( confirm( this._base_data.strings.confirm_disable ) ) {
						( new AjaxService() )
						.send( this._base_data.ajax.req_email_remove )
						.finally();
					}
					return false;
				}
			);

			$( document ).on( 'click', '#SecAdminDialog a', ( evt ) => this.#performSecAdminDialogLogin( evt ) );
		}
	}

	handleSecAdminCheck( resp ) {
		if ( resp.data.success ) {
			const left = resp.data.time_remaining;
			this.timeoutInterval = Math.abs( Math.max( 3, ( left / 2 ) ) * 1000 );

			if ( !this.isWarningShown && left < 20 && left > 8 ) {
				this.isWarningShown = true;
				( new ToasterService() ).showMessage( this._base_data.strings.nearly, false );
			}

			this.hasCheckInPlace = false;
			this.scheduleSecAdminCheck();
		}
		else {
			ShieldOverlay.Show();
			setTimeout( () => {
				alert( this._base_data.strings.confirm )
				window.location.reload();
			}, 1500 );
			( new ToasterService() ).showMessage( this._base_data.strings.expired, resp.success );
		}
	};

	scheduleSecAdminCheck() {
		if ( !this.hasCheckInPlace ) {
			this.hasCheckInPlace = true;
			setTimeout(
				() => ( new AjaxService() )
				.send( this._base_data.ajax.sec_admin_check, false )
				.then( ( resp ) => this.handleSecAdminCheck( resp ) )
				.finally(),
				this.timeoutInterval
			);
		}
	};

	restrictWPOptions() {
		if ( this._base_data.flags.restrict_options ) {
			this._base_data.vars.wp_options_to_restrict.forEach( ( element ) => {
				let $element = $( 'input[name=' + element + ']' );
				$element.prop( 'disabled', true );
				$element.parents( 'tr' ).addClass( 'restricted-option-row' );
				$element.parents( 'td' ).append(
					'<div style="clear:both"></div><div class="restricted-option">' +
					'<span class="dashicons dashicons-lock"></span>' +
					this._base_data.strings.editing_restricted +
					' ' + this._base_data.strings.unlock_link +
					'</div>' );
			} );
		}
	};

	#performSecAdminDialogLogin( evt ) {
		evt.preventDefault();

		let pinInput = document.getElementById( 'SecAdminPinInput' );
		this._base_data.ajax.sec_admin_login.sec_admin_key = pinInput.value;

		let input = document.getElementById( 'SecAdminPinInputContainer' );
		input.innerHTML = '<div class="spinner"></div>';

		( new AjaxService() )
		.send( this._base_data.ajax.sec_admin_login, false )
		.then( ( resp ) => {
			if ( resp.success ) {
				location.reload();
			}
			if ( resp.data ) {
				input.innerHTML = resp.data.html;
				location.reload();
			}
			else {
				input.innerHTML = 'There was an unknown error';
			}
		} )
		.finally();
	};
}