import $ from 'jquery';
import { Modal } from "bootstrap";
import { SecurityAdminBase } from "./SecurityAdminBase";

export class SecurityAdmin extends SecurityAdminBase {

	showRestrictedPageModal() {
		const modalEl = document.getElementById( 'SecurityAdminOverlay' );
		if ( modalEl ) {
			modalEl.addEventListener( 'shown.bs.modal', () => {
				modalEl.querySelector( '#sec_admin_key' )?.focus();
			}, { once: true } );

			( new Modal( modalEl, {
				backdrop: 'static',
				keyboard: false
			} ) ).show();
		}
	}

	handleSecAdminCheck( resp ) {
		if ( resp.data.success ) {
			const left = resp.data.time_remaining;
			this.timeoutInterval = Math.abs( Math.max( 3, ( left / 2 ) ) * 1000 );

			if ( !this.isWarningShown && left < 20 && left > 8 ) {
				this.isWarningShown = true;
				shieldServices.notification().showMessage( this._base_data.strings.nearly, false );
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
			shieldServices.notification().showMessage( this._base_data.strings.expired, resp.success );
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
					'<div style="clear:both"></div><div class="shield-restricted-option">' +
					'<span class="dashicons dashicons-lock"></span>' +
					this._base_data.strings.editing_restricted +
					' ' + this._base_data.strings.unlock_link +
					'</div>'
				);
			} );
		}
	};
}
