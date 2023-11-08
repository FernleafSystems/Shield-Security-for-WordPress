import { ShieldOverlay } from "../ui/ShieldOverlay";
import { AjaxParseResponseService } from "./AjaxParseResponseService";
import { ObjectOps } from "../../util/ObjectOps";
import qs from "qs";
import { Random } from "../../util/Random";

export class AjaxService {

	bg( data ) {
		return this.send( data, false, true );
	}

	send( data, showOverlay = true, quiet = false ) {

		if ( showOverlay ) {
			ShieldOverlay.Show();
		}

		return this
		.req( data )
		.then( respJSON => {
			if ( !quiet && respJSON.data.message.length > 0 ) {

				if ( typeof shieldServices === 'undefined' ) {
					alert( respJSON.data.message );
				}
				else {
					shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
				}
			}
			return respJSON;
		} )
		.then( respJSON => {
			if ( respJSON.data.page_reload ) {
				setTimeout( () => location.reload(), 2000 );
			}
			else if ( showOverlay ) {
				ShieldOverlay.Hide();
			}
			return respJSON;
		} )
		.catch( error => {
			console.log( error );
			if ( !quiet ) {
				alert( 'Something went wrong with the request - it was either blocked or there was an error.' );
			}
			if ( showOverlay ) {
				ShieldOverlay.Hide();
			}
			return error;
		} );
	};

	req( data ) {
		/* const isRest = '_rest_url' in data; */
		const isRest = false;
		let url = isRest ? data._rest_url : ( typeof ajaxurl === 'undefined' ? data.ajaxurl : ajaxurl );

		let reqData = ObjectOps.ObjClone( data );
		delete reqData.ajaxurl;
		delete reqData._rest_url;
		reqData[ 'shield_uniq' ] = Random.Int( 1000, 9999 );

		if ( isRest ) {
			const headers = {
				"Content-Type": "application/json",
				"X-WP-Nonce": reqData._wpnonce,
			};

			delete reqData.action;
			delete reqData.ex;
			delete reqData.exnonce;
			delete reqData.shield_uniq;
			delete reqData._wpnonce;

			return fetch( url, {
				method: 'POST',
				body: JSON.stringify( { payload: reqData } ),
				cache: "no-cache",
				credentials: "same-origin",
				headers: headers,
				redirect: "follow",
				referrerPolicy: "no-referrer",
			} ).then( ( resp ) => resp.json() );
		}
		else {
			delete reqData._wpnonce;
			return fetch( url, this.constructFetchRequestData( reqData ) )
			.then( raw => raw.text() )
			.then( respTEXT => AjaxParseResponseService.ParseIt( respTEXT ) );
		}
	}

	constructFetchRequestData( core, method = 'POST' ) {
		core.apto_wrap_response = 1;
		return {
			method: method,
			body: ( new URLSearchParams( qs.stringify( core ) ) ).toString(),
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
		};
	};

	constructFetchRequestDataOld( core, method = 'POST' ) {
		core.apto_wrap_response = 1;
		return {
			method: method,
			body: ( new URLSearchParams( core ) ).toString(),
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
		};
	};
}