import $ from "jquery";
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { NoticeAllowTracking } from "./NoticeAllowTracking";
import { ObjectOps } from "./ObjectOps";

export class NoticeHandler extends BaseService {

	init() {
		new NoticeAllowTracking( ObjectOps.ObjClone( this._base_data ) );

		$( document ).on( 'click', 'a.shield_admin_notice_action', ( evt ) => {
			evt.preventDefault();
			( new AjaxService() )
			.send( this._base_data.ajax[ evt.currentTarget.dataset.notice_action ] )
			.finally();
			return false;
		} );

		$( document ).on(
			'click', '.shield-notice-container .shield-notice-dismiss, .shield-notice-container .notice-dismiss',
			( evt ) => {
				const container = evt.currentTarget.closest( '.shield-notice-container' );
				( new AjaxService() )
				.bg( ObjectOps.Merge( this._base_data.ajax.dismiss_admin_notice, container.dataset ) )
				.then( ( evt ) => {
					$( container ).fadeOut( 500, () => container.remove() );
				} )
				.finally();
			}
		);
	}
}