import { Base64 } from 'js-base64';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ToasterService } from "../toast/ToasterService";

export function sendEncodedOptionsSave( baseAction, formData ) {

	const send = ( obscure = false ) => {
		const encoded = Base64.encode( JSON.stringify( formData ) );

		return ( new AjaxService() )
			.send(
				ObjectOps.Merge( baseAction, {
					form_params: obscure ? 'icwp-' + encoded : encoded,
					form_enc: obscure ? [ 'obscure', 'b64', 'json' ] : [ 'b64', 'json' ],
				} )
			)
			.catch( ( error ) => {
				if ( obscure ) {
					( new ToasterService() ).showMessage( 'Alternative failed.', false );
					return Promise.reject( error );
				}

				( new ToasterService() ).showMessage( 'The request was blocked. Retrying an alternative...', false );
				return send( true );
			} );
	};

	// Anything on the page that reflects settings (the setup guide, for one) can refresh itself on this. A save
	// that reloads the page needs no notice.
	return send( false ).then( ( resp ) => {
		if ( resp?.success && !resp?.data?.page_reload ) {
			document.dispatchEvent( new CustomEvent( 'shield:options-saved' ) );
		}
		return resp;
	} );
}
