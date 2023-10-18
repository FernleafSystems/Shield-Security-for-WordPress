import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class LicenseHandler extends BaseService {

	init() {
		document.querySelectorAll( '.license-action' ).forEach( ( element, idx ) => {
			element.addEventListener( 'click', () => ( new AjaxService() )
			.send( this._base_data.ajax[ element.dataset[ 'action' ] ] )
			.finally() );
		} );
	}
}