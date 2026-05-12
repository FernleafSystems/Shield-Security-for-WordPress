import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxParseResponseService } from "./AjaxParseResponseService";
import { Navigation } from "../../util/Navigation";
import { ObjectOps } from "../../util/ObjectOps";
import qs from "qs";
import { Random } from "../../util/Random";
import { RestService } from "./RestService";
import { announceGlobal } from "../ui/ShieldA11y";

export class AjaxService {

	static authRefreshPending = false;
	static authRefreshPendingPromise = null;

	bg( data ) {
		return this.send( data, false, true );
	}

	send( data, showOverlay = false, quiet = false ) {
		if ( AjaxService.authRefreshPending ) {
			return AjaxService.suspendForAuthRefresh();
		}

		if ( showOverlay ) {
			ShieldOverlay.Show();
		}

		return this
		.req( data )
		.then( respJSON => {
			if ( this.isAuthRefreshResponse( respJSON ) ) {
				this.handleAuthRefresh( respJSON );
				return AjaxService.suspendForAuthRefresh();
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
			if ( respJSON?.data?.page_reload ) {
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
	};

	showFallbackMessage( message, success ) {
		announceGlobal( message, {
			politeness: success ? 'polite' : 'assertive',
		} );
		const logger = success ? console.info : console.warn;
		logger.call( console, message );
	}

	req( data ) {
		if ( data === null || ObjectOps.IsEmpty( data ) ) {
			throw new Error( 'Empty or null Ajax data.' );
		}

		/* const isRest = '_rest_url' in data; */
		const isRest = false;

		if ( isRest ) {
			return ( new RestService() ).req( data );
		}
		else {
			let url = typeof ajaxurl === 'undefined' ? data.ajaxurl : ajaxurl;

			let reqData = ObjectOps.ObjClone( data );
			reqData[ 'shield_uniq' ] = Random.Int( 1000, 9999 );

			delete reqData._wpnonce;
			delete reqData._rest_url;
			delete reqData.ajaxurl;

			return fetch( url, this.constructFetchRequestData( reqData ) )
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
	}

	constructFetchRequestData( core, method = 'POST' ) {
		core.apto_wrap_response = 1;
		const headers = {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With': 'XMLHttpRequest',
		};
		if ( this.shouldRequestAuthRefresh( core ) ) {
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

	static suspendForAuthRefresh() {
		if ( AjaxService.authRefreshPendingPromise === null ) {
			AjaxService.authRefreshPendingPromise = new Promise( () => {} );
		}
		return AjaxService.authRefreshPendingPromise;
	}
}
