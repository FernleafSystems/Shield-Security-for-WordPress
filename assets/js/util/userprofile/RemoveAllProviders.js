import $ from 'jquery';
import { BaseService } from "../BaseService";
import { AjaxService } from "../AjaxService";

export class RemoveAllProviders extends BaseService {

	init() {
		$( document ).on( 'click', 'button#ShieldMfaRemoveAll', ( evt ) => {
			if ( confirm( this._base_data.strings.are_you_sure ) ) {
				this._base_data.ajax.mfa_remove_all.user_id = $( evt.currentTarget ).data( 'user_id' );

				( new AjaxService() )
				.send( this._base_data.ajax.mfa_remove_all )
				.finally();
			}
		} );
	}
}