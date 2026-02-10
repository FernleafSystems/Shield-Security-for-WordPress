import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { DateRangePicker } from "vanillajs-datepicker";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";
import { OffCanvasService } from "../ui/OffCanvasService";

export class ReportingHandler extends BaseAutoExecComponent {

	canRun() {
		return this._base_data.flags.can_run_report;
	}

	run() {
		shieldEventsHandler_Main.add_Click( 'a.offcanvas_report_create_form', () => {
			OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas )
							.then( () => this.postRender() )
							.finally();
		} );

		this.requestRunning = false;

		shieldEventsHandler_Main.add_Submit( 'form.form_create_report', ( form ) => {
			if ( this.requestRunning ) {
				return false;
			}
			const buttonSubmit = form.querySelector( 'button[type=submit]' );
			const missing = this.getMissingRequiredFields( form );

			if ( missing.length > 0 ) {
				this.showValidationError( missing );
				return false;
			}

			this.requestRunning = true;
			if ( buttonSubmit ) {
				buttonSubmit.setAttribute( 'disabled', 'disabled' );
			}
			let willReload = false;

			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.create_report, { form_params: Forms.Serialize( form ) } )
			)
			.then( ( resp ) => {
				willReload = !!( resp && resp.data && resp.data.page_reload );
			} )
			.finally( () => {
				this.requestRunning = false;
				if ( buttonSubmit && !willReload ) {
					buttonSubmit.removeAttribute( 'disabled' );
				}
			} );
			return false;
		} );
	}

	postRender() {
		let form = OffCanvasService.offCanvasEl.querySelector( 'form.form_create_report' );
		if ( !form ) {
			return;
		}
		new DateRangePicker( form.querySelector( '.input-daterange' ), {
			format: 'yyyy-mm-dd',
			minDate: new Date( this._base_data.vars.earliest_date ),
			maxDate: new Date( this._base_data.vars.latest_date ),
			weekStart: 1,
		} );
	}

	getMissingRequiredFields( form ) {
		const fields = [];
		const startDate = form.querySelector( 'input[name=start_date]' );
		const endDate = form.querySelector( 'input[name=end_date]' );
		const title = form.querySelector( 'input[name=title]' );

		if ( startDate && startDate.value.trim().length === 0 ) {
			fields.push( this._base_data.strings.start_date );
		}
		if ( endDate && endDate.value.trim().length === 0 ) {
			fields.push( this._base_data.strings.end_date );
		}
		if ( title && title.value.trim().length === 0 ) {
			fields.push( this._base_data.strings.title );
		}

		return fields;
	}

	showValidationError( missingFields ) {
		const msg = this._base_data.strings.required_fields.replace( '%s', missingFields.join( ', ' ) );
		if ( typeof shieldServices === 'undefined' ) {
			alert( msg );
		}
		else {
			shieldServices.notification().showMessage( msg, false );
		}
	}
}
