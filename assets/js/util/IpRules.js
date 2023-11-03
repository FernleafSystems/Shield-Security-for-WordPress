import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class IpRules extends BaseService {

	init() {
		this.exec();
	}

	run() {
		this.handleIpRuleOffcanvasForm();
		this.handleIpDelete();
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
			.finally();
		} );
	}

	handleIpDelete() {
		shieldEventsHandler_Main.add_Click( 'td.ip_linked a.ip_delete', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				( new AjaxService() )
				.send( ObjectOps.Merge( this._base_data.ajax.delete, { rid: targetEl.dataset[ 'rid' ] } ) )
				.finally();
			}
		} );
	}
}