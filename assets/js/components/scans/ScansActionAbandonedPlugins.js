import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { ObjectOps } from "../../util/ObjectOps";

export class ScansActionAbandonedPlugins extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Click( 'div.scan-results-plugin-section button.standalone-action.ignore', ( targetEl ) => {
			let reqData = ObjectOps.ObjClone( this._base_data.ajax.results_action );
			reqData.sub_action = targetEl.dataset[ 'action' ];
			reqData.rids = targetEl.dataset[ 'rid' ];

			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.results_action, {
					sub_action: targetEl.dataset[ 'action' ],
					rids: [ targetEl.dataset[ 'rid' ] ],
				} )
			)
			.then( () => window.location.reload() )
			.finally();
		} );
	}
}