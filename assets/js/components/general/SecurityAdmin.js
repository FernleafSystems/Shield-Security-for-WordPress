import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
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

			shieldEventsHandler_Main.add_Submit( 'form#SecurityAdminForm', ( form ) => {
				const btn = form.querySelector( 'button[type="submit"]' );
				btn.disabled = true;
				( new AjaxService() )
				.send(
					ObjectOps.Merge( this._base_data.ajax.sec_admin_login, { form_params: Forms.Serialize( form ) } )
				)
				.then( ( resp ) => {
					if ( !resp?.data?.page_reload ) {
						btn.disabled = false;
						const pinInput = form.querySelector( '[name="sec_admin_key"]' );
						pinInput.value = '';
						focusElement( pinInput );
					}
				} )
				.finally();
			} );

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
	 * A wrong PIN keeps the dialog open to retry, unless the server reloads the page (e.g. the IP is now blocked).
	 * The dialog is busy (can't be closed) only while a request is in flight.
	 */
	openUnlockDialog( template, launcher ) {
		const fragment = template.content.cloneNode( true );
		const form = fragment.querySelector( 'form' );
		const footer = fragment.querySelector( '.shield-accessible-dialog__footer' );
		const pinInput = form.querySelector( '[name="sec_admin_key"]' );
		const message = form.querySelector( '.shield-secadmin-unlock__message' );
		const unlockButton = footer.querySelector( 'button[type="submit"]' );
		const controls = [ pinInput, ...footer.querySelectorAll( 'button' ) ];

		const handle = shieldServices.dialog().content( {
			title: template.dataset.title,
			message: template.dataset.message,
			iconClass: 'dashicons dashicons-lock',
			content: form,
			footer,
			launcher,
		} );
		if ( handle === null ) {
			return;
		}

		const surface = form.closest( '.shield-accessible-dialog__surface' );
		const clearShake = () => surface.classList.remove( 'shield-secadmin-unlock--rejected' );
		const setBusy = ( busy ) => {
			handle.setBusy( busy );
			controls.forEach( ( control ) => control.disabled = busy );
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

		handle.closed.then( clearShake );
		footer.querySelector( '[data-shield-secadmin-unlock-cancel]' ).addEventListener( 'click', () => handle.close() );

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
				// Not a PIN problem: hand over to the session-changed dialog, which this dialog was blocking.
				handle.close();
				await handle.closed;
				( new AjaxService() ).showSessionChangedDialog( resp );
			}
			else if ( resp?.data?.page_reload ) {
				// AjaxService reloads the page shortly. Cancel stays usable in case the reload is cancelled
				// (e.g. WordPress asks about unsaved changes and the user stays).
				showMessage( text, resp.success !== true );
				pinInput.disabled = unlockButton.disabled = true;
			}
			else {
				reject( text || this._base_data.strings.unlock_failed );
			}
		} );

		focusElement( pinInput );
	}

	initializeRestrictedPageOverlay() {
		const overlayEl = document.getElementById( 'SecurityAdminOverlay' );
		if ( overlayEl ) {
			const pinInput = overlayEl.querySelector( '#sec_admin_key' );
			window.setTimeout( () => {
				pinInput?.focus();
			}, 0 );
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
