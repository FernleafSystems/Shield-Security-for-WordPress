import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class MalaiFileScanQuery extends BaseService {

	init() {
		$( document ).on( 'submit', 'form#FileScanMalaiQuery', ( evt ) => {
			evt.preventDefault();

			let ready = true;
			let $form = $( evt.currentTarget );

			$( 'input[type=checkbox]', $form ).each( ( evt ) => {
				if ( !$( evt.currentTarget ).is( ':checked' ) ) {
					ready = ready && false;
				}
			} );

			if ( ready ) {
				( new AjaxService() )
				.send( $form.serialize() )
				.finally();
			}
			else {
				alert( 'Please check the box to agree.' );
			}

			return false;

		} );
	}
}