import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";

export class DebugTools extends BaseAutoExecComponent {

	run() {
		shieldEventsHandler_Main.add_Click( 'a.tool_purge_provider_ips', () => {
			( new AjaxService() )
			.send( this._base_data.ajax.tool_purge_provider_ips )
			.finally( () => {} );
		} );
	}
}