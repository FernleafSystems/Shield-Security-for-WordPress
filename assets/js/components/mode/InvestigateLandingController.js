import $ from 'jquery';
import 'select2';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { LiveTrafficPoller } from "../general/LiveTrafficPoller";
import { InvestigationTable } from "../tables/InvestigationTable";

export class InvestigateLandingController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-investigate-landing="1"]' ) !== null;
	}

	run() {
		this.rootEl = document.querySelector( '[data-investigate-landing="1"]' );
		this.modeShellEl = this.rootEl ? this.rootEl.closest( '[data-mode-shell="1"]' ) : null;
		this.livePanelPoller = null;

		this.initializeSelect2Within( this.rootEl );
		this.bindHandlers();
		this.preloadInactivePanels();
		this.syncLivePanelPolling();
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

		if ( this.modeShellEl ) {
			this.modeShellEl.addEventListener(
				'shield:mode-panel-opened',
				( evt ) => this.handleModePanelOpened( evt )
			);
			this.modeShellEl.addEventListener(
				'shield:mode-panel-closed',
				( evt ) => this.handleModePanelClosed( evt )
			);
		}
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

		this.loadPanelBodyFromRenderAction( panel, reqData ).finally();
	}

	loadPanelBodyFromRenderAction( panel, reqData ) {
		if ( !reqData || typeof reqData !== 'object' || Object.keys( reqData ).length < 1 ) {
			return Promise.resolve();
		}

		this.setPanelLoadingState( panel, true );
		return ( new AjaxService() )
		.send( reqData, true, true )
		.then( ( resp ) => {
			const renderOutput = ( resp && resp.success && resp.data && typeof resp.data.render_output === 'string' )
				? resp.data.render_output
				: '';
			if ( this.applyRenderOutputToPanel( panel, renderOutput ) ) {
				this.setPanelLoadedState( panel, true );
				return;
			}
			this.handlePanelLoadFailure( panel );
		} )
		.catch( () => {
			this.handlePanelLoadFailure( panel );
		} )
		.finally( () => {
			this.setPanelLoadingState( panel, false );
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

	preloadInactivePanels() {
		const batchRequestData = this.parseBatchRequestData();
		if ( batchRequestData === null ) {
			return;
		}

		const panels = this.collectBatchLoadablePanels();
		if ( panels.length < 1 ) {
			return;
		}

		const batch = new AjaxBatchService( batchRequestData );
		const queuedPanels = [];

		panels.forEach( ( panel, index ) => {
			const request = this.parsePanelRenderActionData( panel );
			if ( request === null ) {
				return;
			}

			queuedPanels.push( panel );
			this.setPanelLoadingState( panel, true );

			batch.add( {
				id: this.buildPanelBatchID( panel, index ),
				request,
				onSuccess: ( result ) => {
					const renderOutput = typeof result?.data?.render_output === 'string'
						? result.data.render_output
						: '';
					if ( this.applyRenderOutputToPanel( panel, renderOutput ) ) {
						this.setPanelLoadedState( panel, true );
					}
					else {
						this.handlePanelLoadFailure( panel );
					}
				},
				onError: () => this.handlePanelLoadFailure( panel ),
			} );
		} );

		if ( queuedPanels.length < 1 ) {
			return;
		}

		batch.flush()
		.catch( () => {
			queuedPanels.forEach( ( panel ) => this.handlePanelLoadFailure( panel ) );
		} )
		.finally( () => {
			queuedPanels.forEach( ( panel ) => this.setPanelLoadingState( panel, false ) );
		} );
	}

	collectBatchLoadablePanels() {
		if ( this.rootEl === null ) {
			return [];
		}
		return Array.from( this.rootEl.querySelectorAll( '[data-investigate-panel]' ) ).filter( ( panel ) => {
			return !this.isPanelLoaded( panel )
				   && !this.isLivePanel( panel )
				   && this.parsePanelRenderActionData( panel ) !== null;
		} );
	}

	parseBatchRequestData() {
		if ( this.rootEl === null ) {
			return null;
		}
		const rawJson = this.rootEl.dataset.investigateBatchAction || '';
		if ( rawJson.length < 1 ) {
			return null;
		}
		return this.parseJsonObject( rawJson );
	}

	buildPanelBatchID( panel, index ) {
		const panelKey = panel.dataset.investigatePanel || `panel-${index}`;
		return `investigate-panel-${panelKey}-${index}`;
	}

	findPanelFromElement( el ) {
		return el.closest( '[data-investigate-panel]' );
	}

	parsePanelRenderActionData( panel ) {
		const rawJson = panel.dataset.investigateRenderAction || '';
		if ( rawJson.length === 0 ) {
			return null;
		}
		return this.parseJsonObject( rawJson );
	}

	parseJsonObject( rawJson ) {
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

	applyRenderOutputToPanel( panel, renderOutput ) {
		const panelBody = panel.querySelector( '[data-mode-panel-body]' );
		if ( panelBody === null ) {
			return false;
		}

		const panelBodyHtml = this.extractInnerPageBodyHtml( renderOutput );
		if ( panelBodyHtml.length < 1 ) {
			return false;
		}

		panelBody.innerHTML = panelBodyHtml;
		this.initializeSelect2Within( panelBody );
		new InvestigationTable();
		return true;
	}

	handlePanelLoadFailure( panel ) {
		const panelBody = panel.querySelector( '[data-mode-panel-body]' );
		if ( panelBody !== null ) {
			panelBody.innerHTML = this.buildInlineErrorMarkup();
		}
		this.setPanelLoadedState( panel, false );
	}

	setPanelLoadingState( panel, isLoading ) {
		const panelBody = panel.querySelector( '[data-mode-panel-body]' );
		if ( panelBody !== null ) {
			panelBody.classList.toggle( 'investigate-panel-is-loading', isLoading );
		}
	}

	setPanelLoadedState( panel, isLoaded ) {
		panel.dataset.investigatePanelLoaded = isLoaded ? '1' : '0';
	}

	isPanelLoaded( panel ) {
		return panel.dataset.investigatePanelLoaded === '1';
	}

	isLivePanel( panel ) {
		return panel.dataset.investigatePanelLive === '1';
	}

	handleModePanelOpened( evt ) {
		if ( !this.isInvestigateModeEvent( evt ) ) {
			return;
		}

		const panel = this.findPanelByTarget( evt.detail?.panel_target || '' );
		if ( panel === null ) {
			this.stopLivePanelPoller();
			return;
		}
		if ( !this.isLivePanel( panel ) ) {
			this.stopLivePanelPoller();
		}

		const afterLoad = () => {
			if ( this.isLivePanel( panel ) ) {
				this.startLivePanelPoller( panel );
			}
			else {
				this.stopLivePanelPoller();
			}
		};

		if ( this.isPanelLoaded( panel ) ) {
			afterLoad();
			return;
		}

		const renderActionData = this.parsePanelRenderActionData( panel );
		if ( renderActionData === null ) {
			this.handlePanelLoadFailure( panel );
			afterLoad();
			return;
		}

		this.loadPanelBodyFromRenderAction( panel, renderActionData )
		.finally( () => afterLoad() );
	}

	handleModePanelClosed( evt ) {
		if ( !this.isInvestigateModeEvent( evt ) ) {
			return;
		}
		this.stopLivePanelPoller();
	}

	isInvestigateModeEvent( evt ) {
		return evt?.detail?.mode === 'investigate';
	}

	findPanelByTarget( target ) {
		if ( this.rootEl === null || typeof target !== 'string' || target.length < 1 ) {
			return null;
		}
		return this.rootEl.querySelector( `[data-investigate-panel="${target}"]` );
	}

	syncLivePanelPolling() {
		const activePanel = this.modeShellEl
			? this.modeShellEl.querySelector( '[data-mode-panel="1"].is-open' )
			: null;

		if ( activePanel && this.isLivePanel( activePanel ) ) {
			const renderActionData = this.parsePanelRenderActionData( activePanel );
			if ( !this.isPanelLoaded( activePanel ) && renderActionData !== null ) {
				this.loadPanelBodyFromRenderAction( activePanel, renderActionData )
				.finally( () => this.startLivePanelPoller( activePanel ) );
			}
			else {
				this.startLivePanelPoller( activePanel );
			}
		}
		else {
			this.stopLivePanelPoller();
		}
	}

	startLivePanelPoller( panel ) {
		const requestData = this.getLiveRenderRequestData();
		if ( requestData === null ) {
			return;
		}

		if ( this.livePanelPoller === null ) {
			this.livePanelPoller = new LiveTrafficPoller( {
				requestData,
				onSuccess: ( resp ) => this.renderLivePanelLogs( panel, resp ),
				onFailure: ( resp ) => this.handleLivePanelPollingFailure( panel, resp ),
			} );
		}

		this.focusLivePanelOutput( panel );
		this.livePanelPoller.start();
	}

	stopLivePanelPoller() {
		if ( this.livePanelPoller ) {
			this.livePanelPoller.stop();
		}
	}

	getLiveRenderRequestData() {
		const liveRenderData = window?.shield_vars_main?.comps?.traffic?.ajax?.render_live || null;
		return ( liveRenderData && typeof liveRenderData === 'object' ) ? liveRenderData : null;
	}

	renderLivePanelLogs( panel, resp ) {
		const output = panel.querySelector( '.live_logs .output' );
		if ( output && typeof resp?.data?.html === 'string' ) {
			output.innerHTML = resp.data.html;
		}
	}

	handleLivePanelPollingFailure( panel, resp ) {
		const output = panel.querySelector( '.live_logs .output' );
		if ( output === null ) {
			return;
		}

		const message = this.extractResponseMessage( resp );
		if ( message.length > 0 ) {
			output.innerHTML = `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
		}
	}

	extractResponseMessage( resp ) {
		if ( typeof resp?.data?.message === 'string' && resp.data.message.length > 0 ) {
			return resp.data.message;
		}
		if ( typeof resp?.error === 'string' && resp.error.length > 0 ) {
			return resp.error;
		}
		return '';
	}

	focusLivePanelOutput( panel ) {
		const output = panel.querySelector( '.live_logs .output' );
		if ( output ) {
			output.focus();
		}
	}

	escapeHtml( text = '' ) {
		return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
	}

	buildInlineErrorMarkup() {
		const message = this.rootEl?.dataset?.investigatePanelError || 'Unable to load this investigation panel. Please try again.';
		return '<div class="alert alert-warning mb-0">'
			   + this.escapeHtml( message )
			   + '</div>';
	}
}
