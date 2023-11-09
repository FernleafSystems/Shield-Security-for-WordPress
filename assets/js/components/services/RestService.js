import { ObjectOps } from "../../util/ObjectOps";
import { Random } from "../../util/Random";

export class RestService {

	req( data ) {
		const reqData = ObjectOps.ObjClone( data );
		delete reqData.action;
		delete reqData.ajaxurl;
		delete reqData.ex;
		delete reqData.exnonce;
		delete reqData.shield_uniq;
		delete reqData._rest_url;
		delete reqData._wpnonce;
		reqData[ 'shield_uniq' ] = Random.Int( 1000, 9999 );

		return fetch( data._rest_url, {
			method: 'POST',
			body: JSON.stringify( { payload: reqData } ),
			cache: "no-cache",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": data._wpnonce,
			},
			redirect: "follow",
			referrerPolicy: "no-referrer",
		} ).then( ( resp ) => resp.json() );
	}
}