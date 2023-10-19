import $ from 'jquery';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";

export class MalaiFileScanQuery extends BaseService {

	init() {
		$( document ).on( 'submit', 'form#FileScanMalaiQuery', ( evt ) => {
			evt.preventDefault();

			let ready = true;

			evt.currentTarget.querySelectorAll( 'input[type=checkbox]' ).forEach(
				( checkbox ) => {
					ready = ready && checkbox.checked;
				}
			);

			if ( ready ) {
				( new AjaxService() )
				.send( ObjectOps.Merge( this._base_data.ajax.malai_file_query, Forms.Serialize( evt.target ) ) )
				.finally();
			}
			else {
				alert( 'Please check the box to agree.' );
			}

			return false;
		} );
	}
}