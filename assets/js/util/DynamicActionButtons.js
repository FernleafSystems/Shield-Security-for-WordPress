import $ from 'jquery';
import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class DynamicActionButtons extends BaseService {

	init() {
		$( document ).on( 'click', 'a.shield_dynamic_action_button', ( evt ) => {
			evt.preventDefault()
			let data = evt.currentTarget.dataset;
			if ( !( data[ 'confirm' ] ?? false ) || confirm( 'Are you sure?' ) ) {
				delete data[ 'confirm' ];
				( new AjaxService() ).send( data );
			}
			return false;
		} );
	}
}