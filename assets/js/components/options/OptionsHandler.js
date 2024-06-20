import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";
import { OptionsFormSubmit } from "./OptionsFormSubmit";
import { AjaxService } from "../services/AjaxService";

export class OptionsHandler extends BaseAutoExecComponent {

	run() {
		new OptionsFormSubmit( this._base_data );

		shieldEventsHandler_Main.add_Click( '.offcanvas_form_mod_cfg', ( targetEl ) => {
			const data = targetEl.dataset;
			if ( typeof data.config_item !== 'undefined' && data.config_item.length > 0 ) {
				OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, data ) ).finally();
			}
		} );

		shieldEventsHandler_Main.add_Click(
			'form.options_form_for .toggle-importexport-inclusion > input[type=checkbox]',
			( targetEl ) => {
				( new AjaxService() )
				.bg(
					ObjectOps.Merge( this._base_data.ajax.xfer_include_toggle, {
						key: targetEl.dataset.key,
						status: targetEl.checked ? 'include' : 'exclude'
					} )
				)
				.then( respJSON => {
					shieldServices.notification().showMessage( respJSON.data.message, respJSON.success );
					return respJSON;
				} )
				.finally();
			},
			false
		);
	}
}