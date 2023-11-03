import { NoticeBase } from "./NoticeBase";
import { AjaxService } from "./AjaxService";

export class NoticeAllowTracking extends NoticeBase {

	init() {
		shieldEventsHandler_Main.add_Click( 'a#icwpButtonPluginTrackingAgree', ( targetEl ) => this.setAgreement( targetEl, true ) );
		shieldEventsHandler_Main.add_Click( 'a#icwpButtonPluginTrackingDisagree', ( targetEl ) => this.setAgreement( targetEl, false ) );
	}

	setAgreement( targetEl, agree ) {
		let reqData = this._base_data.ajax.set_plugin_tracking;
		reqData[ 'agree' ] = agree;
		reqData[ 'notice_id' ] = this.getNoticeID();
		( new AjaxService() )
		.send( reqData )
		.then( () => {
			targetEl.closest( 'div.shield-notice-container' ).remove();
		} );
	}

	getNoticeID() {
		return 'allow-tracking'
	};
}