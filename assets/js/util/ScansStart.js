import $ from 'jquery';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";
import { ScansCheck } from "./ScansCheck";

export class ScansStart extends BaseService {

	init() {
		$( document ).on( "submit", 'form#StartScans', ( evt ) => this.startScans( evt ) );
	}

	startScans( evt ) {
		evt.preventDefault();

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.start, { form_params: Forms.Serialize( evt.currentTarget ) } )
		)
		.then( ( resp ) => {

			if ( resp.success ) {
				if ( resp.data.page_reload ) {
					this.#loadResultsPage();
				}
				else if ( resp.data.scans_running ) {
					setTimeout( () => ( new ScansCheck( this._base_data ) ).check(), 1000 );
				}
			}
			else {
				let msg = 'Communications error with site.';
				if ( resp.data.message !== undefined ) {
					msg = resp.data.message;
				}
				alert( msg );
			}
		} )
		.catch( ( error ) =>
			alert( 'Scan failed because the site killed the request. ' +
				'Likely your webhost imposes a maximum time limit for processes, and this limit was reached.' ) )
		.finally();

		return false;
	};

	#loadResultsPage() {
		window.location.href = this._base_data.hrefs.results;
	};
}