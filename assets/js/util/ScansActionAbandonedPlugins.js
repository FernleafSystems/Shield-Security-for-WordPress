import $ from 'jquery';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";

export class ScansActionAbandonedPlugins extends BaseService {

	init() {
		$( document ).on( 'click', 'div.scan-results-plugin-section button.standalone-action.ignore',
			( evt ) => this.handleAction( evt ) )
	}

	handleAction( evt ) {
		let item = $( evt.currentTarget );
		let reqData = ObjectOps.ObjClone( this._base_data.ajax.results_action );
		reqData.sub_action = item.data( 'action' );
		reqData.rids = [ item.data( 'rid' ) ];

		( new AjaxService() )
		.send( reqData )
		.finally();
	}
}