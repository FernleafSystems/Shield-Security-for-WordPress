import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";

export class ScansActionAbandonedPlugins extends BaseService {

	init() {
		shieldEventsHandler_Main.add_Click( 'div.scan-results-plugin-section button.standalone-action.ignore', ( targetEl ) => {
			let reqData = ObjectOps.ObjClone( this._base_data.ajax.results_action );
			reqData.sub_action = targetEl.dataset[ 'action' ];
			reqData.rids = targetEl.dataset[ 'rid' ];

			( new AjaxService() )
			.send( reqData )
			.finally();
		} );
	}
}