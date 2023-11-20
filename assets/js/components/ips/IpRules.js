import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class IpRules extends BaseAutoExecComponent {

	run() {
		this.handleIpRuleOffcanvasForm();
		this.handleIpRuleForm();
	}

	handleIpRuleOffcanvasForm() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_form_create_ip_rule', () => {
			OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas ).finally();
		} );
	}

	handleIpRuleForm() {
		shieldEventsHandler_Main.add_Submit( '#IpRuleAddForm', ( targetEl ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge(
				this._base_data.ajax.add_form_submit,
				{ 'form_data': Object.fromEntries( new FormData( targetEl ) ) }
			) )
			.then( () => OffCanvasService.CloseCanvas() )
			.finally();
		} );
	}
}