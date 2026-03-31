import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { Forms } from "../../util/Forms";
import { LineChartRenderer } from "./LineChartRenderer";
import { ObjectOps } from "../../util/ObjectOps";

export class ReportsTrendsController extends BaseAutoExecComponent {

	canRun() {
		return !!this._base_data?.ajax?.render_chart;
	}

	run() {
		this.renderer = null;
		this.bindHandlers();
		this.updateAllForms();
	}

	bindHandlers() {
		if ( this.handlersBound ) {
			return;
		}
		this.handlersBound = true;

		shieldEventsHandler_Main.add_Change(
			'[data-reports-trends-form="1"] input[name="event_keys[]"]',
			( input ) => this.updateFormState( input.closest( 'form' ) ),
			false
		);

		shieldEventsHandler_Main.add_Submit(
			'[data-reports-trends-form="1"]',
			( form ) => this.submit( form ),
			true
		);
	}

	updateAllForms() {
		document.querySelectorAll( '[data-reports-trends-form="1"]' )
				.forEach( ( form ) => this.updateFormState( form ) );
	}

	updateFormState( form ) {
		if ( !form ) {
			return;
		}

		const selectedCount = this.getSelectedEvents( form ).length;
		const submit = form.querySelector( '[data-reports-chart-submit="1"]' );
		const note = form.querySelector( '[data-reports-chart-selection-note="1"]' );

		if ( submit ) {
			submit.disabled = selectedCount < 1;
		}
		if ( note ) {
			note.textContent = this.buildSelectionNote( selectedCount );
		}
	}

	submit( form ) {
		const selectedEvents = this.getSelectedEvents( form );
		if ( selectedEvents.length < 1 ) {
			this.showError( form, this._base_data.strings.select_events_error );
			this.updateFormState( form );
			return false;
		}

		const submit = form.querySelector( '[data-reports-chart-submit="1"]' );
		if ( submit ) {
			submit.disabled = true;
		}

		this.showLoading( form );

		( new AjaxService() )
		.bg(
			ObjectOps.Merge(
				this._base_data.ajax.render_chart,
				{ form_params: Forms.Serialize( form ) }
			)
		)
		.then( ( resp ) => this.handleResponse( form, resp ) )
		.finally( () => this.updateFormState( form ) );

		return false;
	}

	handleResponse( form, resp = {} ) {
		if ( !resp?.success ) {
			this.showError( form, resp?.data?.message || this._base_data.strings.chart_error );
			return;
		}

		const chart = resp?.data?.chart || {};
		this.hideFeedback( form );

		const results = this.getScopedElement( form, '[data-reports-chart-results="1"]' );
		const empty = this.getScopedElement( form, '[data-reports-chart-empty="1"]' );
		const period = this.getScopedElement( form, '[data-reports-chart-period="1"]' );
		const output = this.getScopedElement( form, '[data-reports-chart-output="1"]' );
		const legend = this.getScopedElement( form, '[data-reports-chart-legend="1"]' );

		if ( empty ) {
			empty.classList.add( 'd-none' );
		}
		if ( results ) {
			results.classList.remove( 'd-none' );
		}
		if ( period ) {
			period.textContent = chart.period_label || '';
		}

		if ( !this.renderer || this.renderer.outputEl !== output ) {
			this.renderer = new LineChartRenderer( output, legend );
		}
		this.renderer.render( chart );
	}

	showLoading( form ) {
		this.hideFeedback( form );
		const results = this.getScopedElement( form, '[data-reports-chart-results="1"]' );
		const empty = this.getScopedElement( form, '[data-reports-chart-empty="1"]' );
		if ( results ) {
			results.classList.add( 'd-none' );
		}
		if ( empty ) {
			empty.classList.remove( 'd-none' );
			empty.textContent = this._base_data.strings.loading;
		}
	}

	showError( form, message ) {
		const results = this.getScopedElement( form, '[data-reports-chart-results="1"]' );
		const error = this.getScopedElement( form, '[data-reports-chart-error="1"]' );
		const empty = this.getScopedElement( form, '[data-reports-chart-empty="1"]' );

		if ( results ) {
			results.classList.add( 'd-none' );
		}
		if ( empty ) {
			empty.classList.add( 'd-none' );
		}
		if ( error ) {
			error.classList.remove( 'd-none' );
			error.textContent = message || this._base_data.strings.chart_error;
		}
	}

	hideFeedback( form ) {
		const error = this.getScopedElement( form, '[data-reports-chart-error="1"]' );
		const empty = this.getScopedElement( form, '[data-reports-chart-empty="1"]' );

		if ( error ) {
			error.classList.add( 'd-none' );
			error.textContent = '';
		}
		if ( empty ) {
			empty.classList.add( 'd-none' );
		}
	}

	getSelectedEvents( form ) {
		return Array.from( form.querySelectorAll( 'input[name="event_keys[]"]:checked' ) )
					.map( ( input ) => input.value )
					.filter( ( value ) => String( value ).trim().length > 0 );
	}

	getScopedElement( form, selector ) {
		return form.closest( '[data-reports-trends="1"]' )?.querySelector( selector ) || null;
	}

	buildSelectionNote( selectedCount ) {
		if ( selectedCount < 1 ) {
			return this._base_data.strings.selection_none;
		}

		if ( selectedCount === 1 ) {
			return this._base_data.strings.selection_one;
		}

		return this._base_data.strings.selection_many.replace( '%s', selectedCount.toLocaleString() );
	}
}
