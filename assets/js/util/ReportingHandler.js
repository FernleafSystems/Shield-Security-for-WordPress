import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { DateRangePicker } from "vanillajs-datepicker";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";

export class ReportingHandler extends BaseService {

	init() {
		this.exec();
	}

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
			this.requestRunning = true;

			const buttonSubmit = form.querySelector( 'button[type=submit]' );
			buttonSubmit.setAttribute( 'disabled', 'disabled' );

			if ( form.querySelector( 'input[name=start_date]' ).value.length === 0 ) {
				alert( 'Please provide a start date' );
			}
			if ( form.querySelector( 'input[name=end_date]' ).value.length === 0 ) {
				alert( 'Please provide an end date' );
			}
			if ( form.querySelector( 'input[name=title]' ).value.length === 0 ) {
				alert( 'Please provide a title for the report' );
			}
			else {
				( new AjaxService() )
				.send(
					ObjectOps.Merge( this._base_data.ajax.create_report, { form_params: Forms.Serialize( form ) } )
				)
				.catch( () => {
					buttonSubmit.removeAttribute( 'disabled' );
				} )
				.finally( () => this.requestRunning = false );
			}
		} );
	}

	postRender() {
		let form = OffCanvasService.offCanvasEl.querySelector( 'form.form_create_report' );
		new DateRangePicker( form.querySelector( '.input-daterange' ), {
			format: 'yyyy-mm-dd',
			minDate: new Date( this._base_data.vars.earliest_date ),
			maxDate: new Date( this._base_data.vars.latest_date ),
			weekStart: 1,
		} );
	}
}