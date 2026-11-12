import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxParseResponseService } from "./AjaxParseResponseService";
import { Navigation } from "../../util/Navigation";
import { ObjectOps } from "../../util/ObjectOps";
import qs from "qs";
import { Random } from "../../util/Random";
import { announceGlobal } from "../ui/ShieldA11y";

export class AjaxService {

	static authRefreshPending = false;
	static authRefreshPendingPromise = null;
	static sessionChangedDialogOpen = false;

	bg( data ) {
		return this.sendWithPolicy( data, {
			showOverlay: false,
			quiet: true,
			isBackground: true,
		} );
	}

	send( data, showOverlay = false, quiet = false ) {
		return this.sendWithPolicy( data, {
			showOverlay,
			quiet,
			isBackground: false,
		} );
	}

	sendWithPolicy( data, { showOverlay, quiet, isBackground } ) {
		if ( AjaxService.authRefreshPending ) {
			return AjaxService.suspendForAuthRefresh();
		}

		if ( showOverlay ) {
			ShieldOverlay.Show();
		}

		return this
		.req( data, isBackground )
		.then( respJSON => {
			if ( !isBackground && this.isAuthRefreshResponse( respJSON ) ) {
				this.handleAuthRefresh( respJSON );
				return AjaxService.suspendForAuthRefresh();
			}

			// Background requests count too: every nonce on the page is dead, whichever request finds out. The
			// dialog carries the message, so the caller gets its failure without a second notice.
			if ( this.isSessionChangedResponse( respJSON ) && this.showSessionChangedDialog( respJSON ) ) {
				return respJSON;
			}

			if ( !quiet
				&& respJSON?.data?.show_toast !== false
				&& typeof respJSON?.data?.message === 'string'
				&& respJSON.data.message.length > 0 ) {
				if ( typeof shieldServices === 'undefined' || typeof shieldServices.notification !== 'function' ) {
					this.showFallbackMessage( respJSON.data.message, respJSON.success );
				}
				else {
					shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
				}
			}
			return respJSON;
		} )
		.then( respJSON => {
			if ( !isBackground && respJSON?.data?.page_reload ) {
				setTimeout( () => Navigation.RedirectOrReload( respJSON, null ), 2000 );
			}
			else if ( showOverlay ) {
				ShieldOverlay.Hide();
			}
			return respJSON;
		} )
		.catch( error => {
			console.log( error );
			if ( !quiet ) {
				this.showFallbackMessage(
					'Something went wrong with the request - it was either blocked or there was an error.',
					false
				);
			}
			if ( showOverlay ) {
				ShieldOverlay.Hide();
			}
			return error;
		} );
	}

	showFallbackMessage( message, success ) {
		announceGlobal( message, {
			politeness: success ? 'polite' : 'assertive',
		} );
		const logger = success ? console.info : console.warn;
		logger.call( console, message );
	}

	req( data, isBackground = false ) {
		if ( data === null || ObjectOps.IsEmpty( data ) ) {
			throw new Error( 'Empty or null Ajax data.' );
		}

		let url = typeof ajaxurl === 'undefined' ? data.ajaxurl : ajaxurl;

		let reqData = ObjectOps.ObjClone( data );
		reqData[ 'shield_uniq' ] = Random.Int( 1000, 9999 );

		delete reqData.ajaxurl;

		return fetch( url, this.constructFetchRequestData( reqData, 'POST', isBackground ) )
		.then( raw => raw.text().then( respTEXT => {
			const respJSON = AjaxParseResponseService.ParseIt( respTEXT );
			if ( respJSON === null
				|| typeof respJSON !== 'object'
				|| Array.isArray( respJSON )
				|| ObjectOps.IsEmpty( respJSON ) ) {
				throw new Error(
					raw.ok ? 'Invalid AJAX response.' : `AJAX request failed with HTTP ${ raw.status }.`
				);
			}
			return respJSON;
		} ) );
	}

	constructFetchRequestData( core, method = 'POST', isBackground = false ) {
		core.apto_wrap_response = 1;
		const headers = {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With': 'XMLHttpRequest',
		};
		if ( !isBackground && this.shouldRequestAuthRefresh( core ) ) {
			headers[ 'X-Shield-Auth-Refresh' ] = '1';
		}
		return {
			method: method,
			body: ( new URLSearchParams( qs.stringify( core ) ) ).toString(),
			headers,
		};
	};

	shouldRequestAuthRefresh( core ) {
		return core?.action === 'shield_action'
			&& document?.body?.classList?.contains( 'wp-admin' );
	}

	isAuthRefreshResponse( respJSON ) {
		return !!( respJSON?.data?.auth_refresh_required );
	}

	handleAuthRefresh( respJSON ) {
		if ( AjaxService.authRefreshPending ) {
			return;
		}

		AjaxService.authRefreshPending = true;
		ShieldOverlay.Show();
		Navigation.RedirectOrReload( respJSON, null );
	}

	isSessionChangedResponse( respJSON ) {
		return !!( respJSON?.data?.session_changed );
	}

	/**
	 * The page's nonces belong to a login session that has since changed, so nothing on it will work until it's
	 * reloaded. Says so in a dialog and reloads when the dialog is closed. Requests aren't held back meanwhile:
	 * the reload can be cancelled (unsaved changes), and the page must carry on if it is.
	 *
	 * @return {boolean} whether the dialog is showing. It isn't when the page has no dialog, or another dialog
	 * is open; the response is then reported like any other failure.
	 */
	showSessionChangedDialog( respJSON ) {
		if ( AjaxService.sessionChangedDialogOpen ) {
			return true;
		}
		if ( typeof shieldServices === 'undefined' || typeof shieldServices.dialog !== 'function' ) {
			return false;
		}
		const dialog = shieldServices.dialog();
		if ( dialog.isOpen() ) {
			return false;
		}

		AjaxService.sessionChangedDialogOpen = true;
		dialog.message( {
			title: respJSON.data.dialog.title,
			message: respJSON.data.message,
			confirmLabel: respJSON.data.dialog.confirm_label,
			showTitle: true,
		} ).finally( () => {
			AjaxService.sessionChangedDialogOpen = false;
			window.location.reload();
		} );
		return true;
	}

	static suspendForAuthRefresh() {
		if ( AjaxService.authRefreshPendingPromise === null ) {
			AjaxService.authRefreshPendingPromise = new Promise( () => {} );
		}
		return AjaxService.authRefreshPendingPromise;
	}
}
