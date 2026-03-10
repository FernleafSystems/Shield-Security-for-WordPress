import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ObjectOps } from "../../util/ObjectOps";
import { OptionsFormSubmit } from "./OptionsFormSubmit";
import { AjaxService } from "../services/AjaxService";
import { Tooltip } from 'bootstrap';

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
				const descriptionTarget = ( targetEl.getAttribute( 'aria-controls' ) || '' ).trim();
				const toToggle = descriptionTarget.length > 0
					? document.getElementById( descriptionTarget )
					: null;
				if ( toToggle ) {
					const isHidden = toToggle.classList.toggle( 'hidden' );
					targetEl.setAttribute( 'aria-expanded', isHidden ? 'false' : 'true' );
					toToggle.setAttribute( 'aria-hidden', isHidden ? 'true' : 'false' );

					const item = targetEl.closest( '.shield-option-item' );
					if ( item ) {
						item.classList.toggle( 'shield-option-item-expanded', !isHidden );
					}

					const tip = Tooltip.getInstance( targetEl );
					if ( tip ) {
						tip.hide();
					}
				}
			},
			false
		);

		shieldEventsHandler_Main.add_Change(
			'form.options_form_for .form-switch .form-check-input',
			( targetEl ) => {
				const stateLabel = targetEl.closest( '.form-switch' )?.querySelector( '.shield-option-switch-state' );
				if ( stateLabel ) {
					stateLabel.textContent = targetEl.checked ? 'Enabled' : 'Disabled';
					stateLabel.classList.toggle( 'on', targetEl.checked );
				}
			},
			false
		);
	}
}
