import { ShieldOverlay } from "./ShieldOverlay";
import { AjaxParseResponseService } from "./AjaxParseResponseService";
import { ObjectOps } from "./ObjectOps";
import qs from "qs";
import { Random } from "./Random";

export class AjaxService {

	bg( data ) {
		return this.send( data, false, true );
	}

	send( data, showOverlay = true, quiet = false ) {

		let reqData = ObjectOps.ObjClone( data );

		let url = typeof ajaxurl === 'undefined' ? reqData.ajaxurl : ajaxurl;
		delete reqData.ajaxurl;

		if ( showOverlay ) {
			ShieldOverlay.Show();
		}

		reqData[ 'shield_uniq' ] = Random.Int( 1000, 9999 );

		return fetch(
			url,
			this.constructFetchRequestData( reqData )
		)
		.then( raw => raw.text() )
		.then( respTEXT => AjaxParseResponseService.ParseIt( respTEXT ) )
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