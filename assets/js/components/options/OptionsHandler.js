import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OptionsFormSubmit } from "./OptionsFormSubmit";
import { AjaxService } from "../services/AjaxService";

export class OptionsHandler extends BaseAutoExecComponent {

	run() {
		new OptionsFormSubmit( this._base_data );

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

		shieldEventsHandler_Main.add_Click(
			'form.options_form_for .option-description-expander',
			( targetEl ) => {
				const toToggle = document.querySelector(
					'.option-description.option-description-' + targetEl.dataset.option_description_key
				);
				if ( toToggle ) {
					toToggle.classList.toggle( 'hidden' )
				}
			},
			false
		);
	}
}