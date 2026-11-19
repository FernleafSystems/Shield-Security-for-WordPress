import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldOverlay } from "../ui/ShieldOverlay";
import { focusElement } from "../ui/ShieldA11y";

export class SecurityAdmin extends BaseComponent {

	init() {
		this.hasCheckInPlace = false;
		this.isWarningShown = false;

		if ( this._base_data ) {
			this.timeoutInterval = 500 * this._base_data.vars.time_remaining;
			this.initializeRestrictedPageOverlay();
			this.restrictWPOptions();

			if ( this._base_data.flags.run_checks ) {
				this.scheduleSecAdminCheck();
			}

			shieldEventsHandler_Main.add_Click( '#SecAdminRemoveConfirmEmail', async ( targetEl ) => {
				const dialog = shieldServices.dialog();
				const confirmed = await dialog.confirm( {
					message: this._base_data.strings.confirm_disable,
					confirmLabel: dialog.resolveConfirmLabel( targetEl ),
					danger: true,
					launcher: targetEl,
				} );
				if ( confirmed ) {
					( new AjaxService() )
						.send( this._base_data.ajax.req_email_remove )
						.finally();
				}
			} );

			shieldEventsHandler_Main.add_Click( '[data-shield-secadmin-unlock]', ( link, evt ) => {
				const template = document.getElementById( 'ShieldSecAdminUnlock' );
				if ( template instanceof HTMLTemplateElement ) {
					evt.preventDefault();
					this.openUnlockDialog( template, link );
				}
			}, false );
		}
	}

	/**
	 * The PIN dialog opened from an Unlock link on WP admin pages outside Shield.
	 */
	openUnlockDialog( template, launcher ) {
		const fragment = template.content.cloneNode( true );
		const form = fragment.querySelector( 'form' );

		const handle = shieldServices.dialog().content( {
			title: template.dataset.title,
			message: template.dataset.message,
			iconClass: template.dataset.iconClass,
			content: form,
			launcher,
		} );
		if ( handle !== null ) {
			form.querySelector( '[data-shield-secadmin-unlock-cancel]' ).addEventListener( 'click', () => handle.close() );
			this.bindPinForm( form, handle );
		}
	}

	/**
	 * The same PIN dialog shown in place on Shield's restricted page, where it can't be closed.
	 */
	initializeRestrictedPageOverlay() {
		const overlayEl = document.getElementById( 'SecurityAdminOverlay' );
		if ( overlayEl ) {
			this.bindPinForm( overlayEl.querySelector( 'form' ), null );
		}
	}

	/**
	 * A wrong PIN stays put to retry, unless the server reloads the page (e.g. the IP is now blocked).
	 * A dialog (handle) is busy, so can't be closed, only while a request is in flight.
	 * While busy the PIN is read-only rather than disabled, so keyboard focus stays on it; with Unlock disabled,
	 * Enter can't resubmit.
	 */
	bindPinForm( form, handle ) {
		const pinInput = form.querySelector( '[name="sec_admin_key"]' );
		const message = form.querySelector( '.shield-secadmin-unlock__message' );
		const unlockButton = form.querySelector( 'button[type="submit"]' );
		const buttons = form.querySelectorAll( 'button' );

		const surface = form.closest( '.shield-accessible-dialog__surface' );
		const clearShake = () => surface.classList.remove( 'shield-secadmin-unlock--rejected' );
		const setBusy = ( busy ) => {
			handle?.setBusy( busy );
			pinInput.readOnly = busy;
			buttons.forEach( ( button ) => button.disabled = busy );
		};
		const showMessage = ( text, isError ) => {
			message.textContent = text;
			message.classList.toggle( 'shield-secadmin-unlock__message--ok', !isError );
		};
		const reject = ( text ) => {
			showMessage( text, true );
			pinInput.value = '';
			pinInput.setAttribute( 'aria-invalid', 'true' );
			pinInput.setAttribute( 'aria-describedby', message.id );
			clearShake();
			void surface.offsetWidth; // restarts the shake on each retry
			surface.classList.add( 'shield-secadmin-unlock--rejected' );
			surface.addEventListener( 'animationend', clearShake, { once: true } );
			focusElement( pinInput );
		};

		handle?.closed.then( clearShake );

		form.addEventListener( 'submit', async ( evt ) => {
			evt.preventDefault();
			if ( pinInput.value.length < 1 ) {
				focusElement( pinInput );
				return;
			}
			setBusy( true );
			showMessage( '', true );
			pinInput.removeAttribute( 'aria-invalid' );
			pinInput.removeAttribute( 'aria-describedby' );
			const resp = await ( new AjaxService() ).send(
				ObjectOps.Merge( this._base_data.ajax.sec_admin_login, { sec_admin_key: pinInput.value } ),
				false,
				true
			);
			setBusy( false );
			const text = typeof resp?.data?.message === 'string' ? resp.data.message : '';
			if ( resp?.data?.session_changed ) {
				// Not a PIN problem: hand over to the session-changed dialog, which this dialog may be blocking.
				if ( handle !== null ) {
					handle.close();
					await handle.closed;
				}
				( new AjaxService() ).showSessionChangedDialog( resp );
			}
			else if ( resp?.data?.page_reload ) {
				// AjaxService reloads the page shortly. Cancel stays usable in case the reload is cancelled
				// (e.g. WordPress asks about unsaved changes and the user stays).
				showMessage( text, resp.success !== true );
				pinInput.disabled = unlockButton.disabled = true;
			}
			else {
				reject( text || shieldStrings.string( 'request_failed' ) );
			}
		} );

		// The form is drawn with Unlock disabled, so pressing Enter on a PIN typed before this script was listening
		// doesn't send the form the ordinary way and lose it. It can be sent now.
		unlockButton.disabled = false;

		// The field asks for focus itself (autofocus), so it normally has it as soon as it's shown, without waiting
		// for this script. This is for when the browser didn't grant that and nothing has focus; focus that has
		// since been moved elsewhere is left where it was put.
		if ( document.activeElement === null || document.activeElement === document.body ) {
			focusElement( pinInput );
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
				ShieldOverlay.Hide();
				shieldServices.dialog().message( {
					title: this._base_data.strings.timeout_title,
					message: this._base_data.strings.timeout_message,
					confirmLabel: this._base_data.strings.timeout_reload,
					showTitle: true,
				} ).finally( () => window.location.reload() );
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
				Array.from( document.getElementsByName( element ) )
				.filter( ( inputEl ) => inputEl instanceof HTMLInputElement )
				.forEach( ( inputEl ) => {
					inputEl.disabled = true;
					inputEl.closest( 'tr' )?.classList.add( 'restricted-option-row' );
					this.appendRestrictedOptionNotice( inputEl.closest( 'td' ) );
				} );
			} );
		}
	};

	appendRestrictedOptionNotice( tableCell ) {
		if ( tableCell !== null && tableCell.querySelector( '.shield-restricted-option' ) === null ) {
			tableCell.insertAdjacentHTML( 'beforeend', this.getRestrictedOptionNoticeHtml() );
		}
	};

	getRestrictedOptionNoticeHtml() {
		return '<div style="clear:both"></div><div class="shield-restricted-option">' +
			'<span class="dashicons dashicons-lock"></span>' +
			this._base_data.strings.editing_restricted +
			' ' + this._base_data.strings.unlock_link +
			'</div>';
	};
}
