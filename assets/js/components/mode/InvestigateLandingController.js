import $ from 'jquery';
import 'select2';
import { Tab } from 'bootstrap';
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
		this.syncInlineTabsForAllPanels();
		this.syncLandingHintVisibilityFromPanelState();
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
		shieldEventsHandler_Main.add_Click(
			'[data-investigate-landing="1"] [data-investigate-panel-tab="1"]',
			( tabButton, evt ) => this.handleInlineTabClick( tabButton, evt ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-investigate-landing="1"] [data-investigate-change-subject="1"]',
			( changeLink, evt ) => this.handleChangeSubjectClick( changeLink, evt ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'[data-investigate-landing="1"] [data-investigate-source-tab="1"]',
			( sourceTab ) => this.handleSourceTabShown( sourceTab ),
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
			const shouldAutoSubmit = selectEl.dataset.investigateAutoSubmit === '1';
			if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
				if ( shouldAutoSubmit ) {
					this.bindSelect2AutoSubmit( selectEl, $select );
				}
				return;
			}

			const firstOption = selectEl.querySelector( 'option[value=""]' );
			const placeholder = firstOption ? ( firstOption.textContent || '' ) : '';
			const ajaxContract = this.parseSelect2AjaxConfig( selectEl.dataset.investigateSelect2Ajax || '' );
			const select2Config = {
				width: '100%',
				placeholder,
			};

			if ( ajaxContract !== null ) {
				select2Config.minimumInputLength = ajaxContract.minimumInputLength;
				select2Config.ajax = this.buildSelect2AjaxConfig( ajaxContract );
			}

			$select.select2( select2Config );

			if ( shouldAutoSubmit ) {
				this.bindSelect2AutoSubmit( selectEl, $select );
			}
		} );
	}

	bindSelect2AutoSubmit( selectEl, $select ) {
		$select.off( 'select2:select.investigateAutoSubmit select2:clear.investigateAutoSubmit' );
		$select.on( 'select2:select.investigateAutoSubmit select2:clear.investigateAutoSubmit', () => {
			const form = selectEl.closest( 'form[data-investigate-panel-form="1"]' );
			if ( form !== null ) {
				form.requestSubmit();
			}
		} );
	}

	parseSelect2AjaxConfig( rawJson ) {
		if ( typeof rawJson !== 'string' || rawJson.trim().length < 1 ) {
			return null;
		}

		const parsed = this.parseJsonObject( rawJson );
		if ( parsed === null ) {
			return null;
		}

		const action = ( parsed.action && typeof parsed.action === 'object' ) ? parsed.action : null;
		const subject = typeof parsed.subject === 'string' ? parsed.subject.trim() : '';
		const ajaxUrl = typeof action?.ajaxurl === 'string' ? action.ajaxurl : '';
		if ( action === null || subject.length < 1 || ajaxUrl.length < 1 ) {
			return null;
		}

		return {
			action,
			subject,
			ajaxUrl,
			minimumInputLength: Math.max( 1, Number.isInteger( parsed.minimum_input_length ) ? parsed.minimum_input_length : 2 ),
			delayMs: Math.max( 0, Number.isInteger( parsed.delay_ms ) ? parsed.delay_ms : 700 ),
		};
	}

	buildSelect2AjaxConfig( ajaxContract ) {
		return {
			type: 'POST',
			delay: ajaxContract.delayMs,
			url: ajaxContract.ajaxUrl,
			dataType: 'json',
			data: ( params ) => this.buildSelect2Query( ajaxContract, params?.term || '' ),
			processResults: ( response ) => {
				return {
					results: Array.isArray( response?.data?.results ) ? response.data.results : [],
				};
			},
		};
	}

	buildSelect2Query( ajaxContract, term ) {
		return {
			...ajaxContract.action,
			subject: ajaxContract.subject,
			search: typeof term === 'string' ? term : '',
		};
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
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent === null ) {
			return false;
		}

		const panelBodyHtml = this.extractInnerPageBodyHtml( renderOutput );
		if ( panelBodyHtml.length < 1 ) {
			return false;
		}

		panelContent.innerHTML = panelBodyHtml;
		this.initializeSelect2Within( panelContent );
		new InvestigationTable();
		this.rebuildInlineTabs( panel );
		return true;
	}

	handlePanelLoadFailure( panel ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			panelContent.innerHTML = this.buildInlineErrorMarkup();
		}
		this.clearInlineTabs( panel );
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
			this.rebuildInlineTabs( panel );
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
		this.syncLandingHintVisibilityFromPanelState();
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

	syncInlineTabsForAllPanels() {
		if ( this.rootEl === null ) {
			return;
		}
		this.rootEl.querySelectorAll( '[data-investigate-panel]' ).forEach( ( panel ) => {
			this.rebuildInlineTabs( panel );
		} );
	}

	getPanelContentContainer( panel ) {
		return panel.querySelector( '[data-investigate-panel-content="1"]' )
			|| panel.querySelector( '[data-mode-panel-body]' );
	}

	getPanelTabsContainer( panel ) {
		return panel.querySelector( '[data-investigate-panel-tabs="1"]' );
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

	clearInlineTabs( panel ) {
		const tabsContainer = this.getPanelTabsContainer( panel );
		if ( tabsContainer !== null ) {
			tabsContainer.innerHTML = '';
		}
	}

	rebuildInlineTabs( panel ) {
		const tabsContainer = this.getPanelTabsContainer( panel );
		if ( tabsContainer === null ) {
			return;
		}

		const sourceTabs = this.collectSourceTabs( panel );
		if ( sourceTabs.length < 1 ) {
			this.clearInlineTabs( panel );
			return;
		}

		const fragment = document.createDocumentFragment();
		sourceTabs.forEach( ( sourceTab, index ) => {
			if ( sourceTab.id.length < 1 ) {
				sourceTab.id = this.buildSourceTabID( panel, index );
			}

			const targetSelector = this.getTabTargetSelector( sourceTab );
			if ( targetSelector.length < 1 ) {
				return;
			}

			const tabButton = document.createElement( 'button' );
			tabButton.type = 'button';
			tabButton.className = 'investigate-panel__tab';
			tabButton.dataset.investigatePanelTab = '1';
			tabButton.dataset.sourceTabId = sourceTab.id;
			tabButton.textContent = sourceTab.textContent.trim();

			if ( sourceTab.classList.contains( 'active' ) || sourceTab.getAttribute( 'aria-selected' ) === 'true' ) {
				tabButton.classList.add( 'is-active' );
			}

			fragment.appendChild( tabButton );
		} );

		tabsContainer.innerHTML = '';
		tabsContainer.appendChild( fragment );
		this.syncInlineActiveTab( panel );
	}

	collectSourceTabs( panel ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent === null ) {
			return [];
		}

		return Array.from( panelContent.querySelectorAll( '.shield-options-rail [data-bs-toggle="tab"]' ) ).filter( ( sourceTab ) => {
			const targetSelector = this.getTabTargetSelector( sourceTab );
			if ( targetSelector.length < 1 || panelContent.querySelector( targetSelector ) === null ) {
				delete sourceTab.dataset.investigateSourceTab;
				return false;
			}

			sourceTab.dataset.investigateSourceTab = '1';
			return true;
		} );
	}

	getTabTargetSelector( sourceTab ) {
		const targetSelector = ( sourceTab.dataset.bsTarget || sourceTab.getAttribute( 'href' ) || '' ).trim();
		return targetSelector.startsWith( '#' ) ? targetSelector : '';
	}

	buildSourceTabID( panel, index ) {
		const panelKey = ( panel.dataset.investigatePanel || 'panel' ).toLowerCase().replace( /[^a-z0-9_-]/g, '-' );
		return `investigate-inline-source-tab-${panelKey}-${index}`;
	}

	handleInlineTabClick( tabButton, evt ) {
		const sourceTabID = ( tabButton.dataset.sourceTabId || '' ).trim();
		if ( sourceTabID.length < 1 ) {
			return;
		}

		const sourceTab = document.getElementById( sourceTabID );
		if ( sourceTab === null ) {
			return;
		}

		evt.preventDefault();
		Tab.getOrCreateInstance( sourceTab ).show();
	}

	handleSourceTabShown( sourceTab ) {
		const panel = this.findPanelFromElement( sourceTab );
		if ( panel === null ) {
			return;
		}
		this.syncInlineActiveTab( panel );
	}

	syncInlineActiveTab( panel ) {
		const tabsContainer = this.getPanelTabsContainer( panel );
		if ( tabsContainer === null ) {
			return;
		}

		const activeSourceTab = panel.querySelector( '[data-investigate-source-tab="1"].active' )
			|| panel.querySelector( '[data-investigate-source-tab="1"][aria-selected="true"]' );
		const activeSourceTabID = activeSourceTab !== null ? activeSourceTab.id : '';
		tabsContainer.querySelectorAll( '[data-investigate-panel-tab="1"]' ).forEach( ( inlineTab ) => {
			inlineTab.classList.toggle( 'is-active', activeSourceTabID.length > 0 && inlineTab.dataset.sourceTabId === activeSourceTabID );
		} );
	}
}
