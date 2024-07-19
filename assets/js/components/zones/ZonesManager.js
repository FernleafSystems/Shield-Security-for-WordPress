import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class ZonesManager extends BaseAutoExecComponent {

	run() {
		shieldEventsHandler_Main.add_Click( '.zone_component_action', ( button ) => this.componentAction( button ) );
	}

	componentAction( button ) {
		const data = button.dataset;
		if ( data.zone_component_action.startsWith( 'offcanvas_' ) ) {
			OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax[ data.zone_component_action ], data ) ).finally();
		}
	}
}