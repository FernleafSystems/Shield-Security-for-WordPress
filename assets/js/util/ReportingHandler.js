import $ from "jquery";
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

	run() {
		if ( this._base_data.flags.can_run_report ) {
			$( document ).on( 'click', 'a.offcanvas_report_create_form', ( evt ) => {
				evt.preventDefault();
				OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas )
								.then( () => this.postRender() )
								.finally();
				return false;
			} );
		}
	}

	postRender() {
		let form = OffCanvasService.offCanvasEl.querySelector( 'form' );
		let $form = $( form );

		new DateRangePicker( form.querySelector( '.input-daterange' ), {
			format: 'yyyy-mm-dd',
			minDate: new Date( this._base_data.vars.earliest_date ),
			maxDate: new Date( this._base_data.vars.latest_date ),
			weekStart: 1,
		} );

		let requestRunning = false;

		$form.on( "submit", ( evt ) => {
			evt.preventDefault();
			if ( requestRunning ) {
				return false;
			}
			requestRunning = true;

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
				.finally();
			}

			return false;
		} );
	}
}