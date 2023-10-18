import $ from 'jquery';
import { NoticeBase } from "./NoticeBase";
import { AjaxService } from "./AjaxService";

export class NoticeAllowTracking extends NoticeBase {

	init() {
		$( document ).on( 'click', 'a#icwpButtonPluginTrackingAgree', ( evt ) => this.setAgreement( evt, true ) );
		$( document ).on( 'click', 'a#icwpButtonPluginTrackingDisagree', ( evt ) => this.setAgreement( evt, false ) );
	}

	setAgreement( evt, agree ) {
		let reqData = this._base_data.ajax.set_plugin_tracking;
		reqData[ 'agree' ] = agree;
		reqData[ 'notice_id' ] = this.getNoticeID();
		( new AjaxService() )
		.send( reqData )
		.then( ( resp ) => {
			let container = $( evt.currentTarget ).closest( 'div.shield-notice-container' );
			container.fadeOut( 500, () => container.remove() );
		} );
	}

	getNoticeID() {
		return 'allow-tracking'
	};
}