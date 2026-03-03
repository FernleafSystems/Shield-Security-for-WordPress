import $ from 'jquery';
import 'select2';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { InvestigationTable } from "../tables/InvestigationTable";

export class InvestigateLandingController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-investigate-landing="1"]' ) !== null;
	}

	run() {
		this.initializeSelect2Within( document.querySelector( '[data-investigate-landing="1"]' ) );
		this.bindHandlers();
	}

	bindHandlers() {
		if ( this.hasBoundHandlers ) {
			return;
		}
		this.hasBoundHandlers = true;

		shieldEventsHandler_Main.add_Submit(
			'[data-investigate-landing="1"] form[data-investigate-panel-form="1"]',
			( form, evt ) => this.handlePanelFormSubmit( form, evt ),
			false
		);
		shieldEventsHandler_Main.add_Change(
			'[data-investigate-landing="1"] [data-investigate-auto-submit="1"]',
			( input ) => this.handleAutoSubmitChange( input ),
			false
		);
	}

	handleAutoSubmitChange( input ) {
		if ( input === null ) {
			return;
		}

		const form = input.closest( 'form[data-investigate-panel-form="1"]' );
		if ( form === null ) {
			return;
		}
		form.requestSubmit();
	}

	handlePanelFormSubmit( form, evt ) {
		const panel = this.findPanelFromElement( form );
		if ( panel === null ) {
			return;
		}

		const renderActionData = this.parsePanelRenderActionData( panel );
		if ( renderActionData === null ) {
			return;
		}

		evt.preventDefault();

		const reqData = { ...renderActionData };
		( new FormData( form ) ).forEach( ( value, key ) => {
			reqData[ key ] = typeof value === 'string' ? value : '';
		} );

		this.loadPanelBodyFromRenderAction( panel, reqData );
	}

	loadPanelBodyFromRenderAction( panel, reqData ) {
		const panelBody = panel.querySelector( '[data-mode-panel-body]' );
		if ( panelBody === null ) {
			return;
		}

		panelBody.classList.add( 'investigate-panel-is-loading' );
		( new AjaxService() )
		.send( reqData, true, true )
		.then( ( resp ) => {
			const renderOutput = ( resp && resp.success && resp.data && typeof resp.data.render_output === 'string' )
				? resp.data.render_output
				: '';
			const panelBodyHtml = this.extractInnerPageBodyHtml( renderOutput );

			panelBody.innerHTML = panelBodyHtml.length > 0
				? panelBodyHtml
				: this.buildInlineErrorMarkup();

			this.initializeSelect2Within( panelBody );
			new InvestigationTable();
		} )
		.catch( () => {
			panelBody.innerHTML = this.buildInlineErrorMarkup();
		} )
		.finally( () => {
			panelBody.classList.remove( 'investigate-panel-is-loading' );
		} );
	}

	initializeSelect2Within( contextEl ) {
		if ( contextEl === null || !$.fn.select2 ) {
			return;
		}

		contextEl.querySelectorAll( 'select[data-investigate-select2="1"]' ).forEach( ( selectEl ) => {
			const $select = $( selectEl );
			if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
				return;
			}

			const firstOption = selectEl.querySelector( 'option[value=""]' );
			const placeholder = firstOption ? ( firstOption.textContent || '' ) : '';

			$select.select2( {
				width: '100%',
				placeholder,
			} );
		} );
	}

	findPanelFromElement( el ) {
		return el.closest( '[data-investigate-panel]' );
	}

	parsePanelRenderActionData( panel ) {
		const rawJson = panel.dataset.investigateRenderAction || '';
		if ( rawJson.length === 0 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawJson );
			return ( parsed && typeof parsed === 'object' ) ? parsed : null;
		}
		catch ( e ) {
			return null;
		}
	}

	extractInnerPageBodyHtml( renderOutput ) {
		if ( typeof renderOutput !== 'string' || renderOutput.length === 0 ) {
			return '';
		}

		const parsed = ( new DOMParser() ).parseFromString( renderOutput, 'text/html' );
		const innerShell = parsed.querySelector( '.inner-page-body-shell' );
		return innerShell ? innerShell.innerHTML : '';
	}

	buildInlineErrorMarkup() {
		return '<div class="alert alert-warning mb-0">'
			   + 'Unable to load this investigation panel. Please try again.'
			   + '</div>';
	}
}

