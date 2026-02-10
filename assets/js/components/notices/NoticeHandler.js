import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { NoticeAllowTracking } from "./NoticeAllowTracking";
import { ObjectOps } from "../../util/ObjectOps";

export class NoticeHandler extends BaseComponent {

	init() {
		new NoticeAllowTracking( ObjectOps.ObjClone( this._base_data ) );

		shieldEventsHandler_Main.add_Click( 'a.shield_admin_notice_action', ( targetEl ) => {
			( new AjaxService() )
			.send( this._base_data.ajax[ targetEl.dataset.notice_action ] )
			.finally();
		} );

		shieldEventsHandler_Main.add_Click( '.shield-notice-container .notice-dismiss', ( targetEl ) => this.sendDismiss( targetEl ) );
	}

	sendDismiss( targetEl ) {
		const container = targetEl.closest( '.shield-notice-container' );
		( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.dismiss_admin_notice, container.dataset ) )
		.finally( () => container.remove() );
	}
}
