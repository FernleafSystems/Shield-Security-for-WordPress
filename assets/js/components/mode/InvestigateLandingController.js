import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { LiveTrafficPoller } from "../general/LiveTrafficPoller";
import { UiContentActivator } from "../ui/UiContentActivator";
import { InvestigateInlineTabs } from "./InvestigateInlineTabs";

export class InvestigateLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		this.livePanelPoller = null;
		this.inlineTabs = new InvestigateInlineTabs();
		this.bindHandlers();
		this.initializeCurrentRoot();
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
		shieldEventsHandler_Main.add_Click(
			'[data-investigate-landing="1"] [data-investigate-change-subject="1"]',
			( changeLink, evt ) => this.handleChangeSubjectClick( changeLink, evt ),
			false
		);

		document.addEventListener( 'shield:mode-panel-opened', ( evt ) => this.handleModePanelOpened( evt ) );
		document.addEventListener( 'shield:mode-panel-closed', ( evt ) => this.handleModePanelClosed( evt ) );
	}

	handleAutoSubmitChange( input ) {
		if ( input === null ) {
			return;
		}
		if ( typeof input.matches === 'function' && input.matches( 'select[data-investigate-select2="1"]' ) ) {
			return;
		}

		const form = input.closest( 'form[data-investigate-panel-form="1"]' );
		if ( form === null ) {
			return;
		}
		form.requestSubmit();
	}

	handleChangeSubjectClick( changeLink, evt ) {
		const panel = this.findPanelFromElement( changeLink );
		if ( panel === null ) {
			return;
		}

		const renderActionData = this.parsePanelRenderActionData( panel );
		if ( renderActionData === null ) {
			return;
		}

		evt.preventDefault();
		this.loadPanelBodyFromRenderAction( panel, renderActionData ).finally();
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

		this.renderPanelLoadingMarkup( panel );
		this.setPanelLoadingState( panel, true );
		return ( new AjaxService() )
		.send( reqData, false, true )
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
		const innerShell = parsed.querySelector( '[data-inner-page-body-shell="1"]' )
			|| parsed.querySelector( '.inner-page-body-shell' );
		return innerShell ? innerShell.innerHTML : '';
	}

	applyRenderOutputToPanel( panel, renderOutput ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent === null ) {
			return false;
		}

		const panelBodyHtml = this.extractInnerPageBodyHtml( renderOutput );
		if ( panelBodyHtml.length < 1 ) {
			return false;
		}

		panelContent.innerHTML = panelBodyHtml;
		this.syncPanelChrome( panel, true );
		UiContentActivator.activateWithin( panelContent );
		return true;
	}

	renderPanelLoadingMarkup( panel ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			panelContent.innerHTML = this.buildPanelLoadingMarkup();
		}
		this.syncPanelChrome( panel, true );
	}

	handlePanelLoadFailure( panel ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			panelContent.innerHTML = this.buildInlineErrorMarkup();
		}
		this.syncPanelChrome( panel, true );
		this.setPanelLoadedState( panel, false );
	}

	setPanelLoadingState( panel, isLoading ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			panelContent.classList.toggle( 'investigate-panel-is-loading', isLoading );
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

		this.setLandingHintVisible( false );

		const panel = this.findPanelByTarget( evt.detail?.panel_target || '' );
		if ( panel === null ) {
			this.syncLandingHintVisibilityFromPanelState();
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
			this.syncPanelChrome( panel );
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
		this.syncLandingHintVisibilityFromPanelState();
		this.stopLivePanelPoller();
	}

	isInvestigateModeEvent( evt ) {
		return evt?.detail?.mode === 'investigate';
	}

	findPanelByTarget( target ) {
		const root = this.getRoot();
		if ( root === null || typeof target !== 'string' || target.length < 1 ) {
			return null;
		}
		return root.querySelector( `[data-investigate-panel="${target}"]` );
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

	buildPanelLoadingMarkup() {
		const placeholder = document.querySelector( '.shield_loading_placeholder_investigate_panel' );
		if ( placeholder instanceof HTMLElement ) {
			const clone = placeholder.cloneNode( true );
			clone.classList.remove( 'd-none' );
			return clone.outerHTML;
		}

		const message = this.rootEl?.dataset?.investigatePanelLoading || 'Loading investigation panel...';
		return `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
	}

	syncInlineTabsForAllPanels() {
		if ( this.rootEl === null ) {
			return;
		}
		this.inlineTabs.initializeWithin( this.rootEl );
	}

	syncPanelHeadersForAllPanels() {
		if ( this.rootEl === null ) {
			return;
		}
		this.rootEl.querySelectorAll( '[data-investigate-panel]' ).forEach( ( panel ) => {
			this.syncPanelHeader( panel, true );
		} );
	}

	syncPanelChrome( panel, clearHeaderIfMissing = false ) {
		this.syncPanelHeader( panel, clearHeaderIfMissing );
		this.inlineTabs.initializeWithin( panel );
	}

	syncPanelHeader( panel, clearIfMissing = false ) {
		const panelHeader = this.getPanelHeaderContainer( panel );
		if ( panelHeader === null ) {
			return;
		}

		const panelContent = this.getPanelContentContainer( panel );
		const subjectHeader = panelContent === null
			? null
			: ( panelContent.querySelector( '[data-investigate-subject-header="1"]' )
				|| panelContent.querySelector( '.investigate-subject-header' ) );

		if ( subjectHeader !== null ) {
			panelHeader.innerHTML = '';
			panelHeader.appendChild( subjectHeader );
			return;
		}

		if ( clearIfMissing ) {
			panelHeader.innerHTML = '';
		}
	}

	getPanelContentContainer( panel ) {
		return panel.querySelector( '[data-investigate-panel-content="1"]' )
			|| panel.querySelector( '[data-mode-panel-body]' );
	}

	getPanelHeaderContainer( panel ) {
		return panel.querySelector( '[data-investigate-panel-header="1"]' );
	}

	getLandingHintElement() {
		if ( this.rootEl === null ) {
			return null;
		}
		return this.rootEl.querySelector( '[data-mode-landing-hint="1"]' );
	}

	setLandingHintVisible( isVisible ) {
		const hint = this.getLandingHintElement();
		if ( hint === null ) {
			return;
		}

		hint.classList.toggle( 'd-none', !isVisible );
		hint.setAttribute( 'aria-hidden', isVisible ? 'false' : 'true' );
	}

	syncLandingHintVisibilityFromPanelState() {
		if ( this.modeShellEl === null ) {
			return;
		}
		this.setLandingHintVisible(
			this.modeShellEl.querySelector( '[data-mode-panel="1"].is-open' ) === null
		);
	}

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		this.modeShellEl = this.rootEl ? this.rootEl.closest( '[data-mode-shell="1"]' ) : null;
		if ( this.rootEl === null ) {
			this.stopLivePanelPoller();
			return;
		}

		UiContentActivator.activateWithin( this.rootEl );
		this.syncPanelHeadersForAllPanels();
		this.syncInlineTabsForAllPanels();
		this.syncLandingHintVisibilityFromPanelState();
		this.syncLivePanelPolling();
	}

	getRoot() {
		return document.querySelector( '[data-investigate-landing="1"]' );
	}

}
