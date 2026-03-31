import { Tab } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { LiveTrafficPoller } from "../general/LiveTrafficPoller";
import { UiContentActivator } from "../ui/UiContentActivator";
import { InvestigateInlineTabs } from "./InvestigateInlineTabs";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { getActiveLayerIndex, getLayersForShell } from "./DrillDownShared";

export class InvestigateLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		this.livePanelPoller = null;
		this.inlineTabs = new InvestigateInlineTabs();
		this.subjectTiles = new Map();
		this.defaultPanelHeader = this.buildEmptyHeader();
		this.panelRequestKey = '';
		this.batchRequestData = this._base_data?.ajax?.batch_requests || {};
		this.genericPanelCache = new Map();
		this.genericPanelPending = new Map();
		this.preloadedRoots = new WeakSet();
		this.preloadingRoots = new WeakSet();
		this.lastInvestigateRoot = null;

		this.bindHandlers();
		this.initializeCurrentRoot();
	}

	bindHandlers() {
		if ( this.hasBoundHandlers ) {
			return;
		}
		this.hasBoundHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-investigate-landing="1"] [data-drill-target="panel"]',
			( subjectTile, evt ) => this.handleSubjectSelectionClick( subjectTile, evt ),
			false
		);
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

		document.addEventListener( 'shield:drill-back', ( evt ) => this.handleDrillBack( evt ) );
		document.addEventListener( 'click', ( evt ) => this.handlePivotClick( evt ) );
	}

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		if ( this.rootEl !== this.lastInvestigateRoot ) {
			this.genericPanelCache = new Map();
			this.genericPanelPending = new Map();
			this.lastInvestigateRoot = this.rootEl;
		}
		this.shellEl = this.getShell( this.rootEl );
		this.panelEl = this.getPanel( this.rootEl );
		this.subjectTiles = this.collectSubjectTiles( this.rootEl );
		this.defaultPanelHeader = this.readLayerHeader( this.shellEl, 'panel' );

		if ( this.rootEl === null || this.shellEl === null || this.panelEl === null ) {
			this.stopLivePanelPoller();
			return;
		}

		this.seedGenericPanelCacheFromPanel( this.panelEl );
		UiContentActivator.activateCurrentWithinRoot( this.rootEl );
		this.preloadGenericPanels();

		if ( this.isPanelLoaded( this.panelEl ) ) {
			this.syncPanelChrome( this.panelEl, true );
			this.finalizePanelRequestState(
				this.buildSelectionHeader( this.getCurrentSelection( this.panelEl ) ),
				''
			);
			this.syncLivePanelPolling();
			this.activatePanelHash( window.location.hash );
			return;
		}

		this.stopLivePanelPoller();
	}

	handleSubjectSelectionClick( subjectTile, evt ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( subjectTile ) ) {
			return;
		}

		const selection = this.readSubjectSelection( subjectTile );
		if ( selection.key.length < 1 ) {
			return;
		}

		evt.preventDefault();
		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.panelEl = this.getPanel( root );

		this.openSubjectSelection( selection, {
			requestData: {
				...selection.render_action,
			},
			historyUrl: this.buildLandingUrlForSelection( selection, selection.render_action ),
			hash: '',
		} ).finally();
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
		evt.preventDefault();
		this.resetCurrentSelection( changeLink ).finally();
	}

	handlePanelFormSubmit( form, evt ) {
		const panel = this.findPanelFromElement( form );
		if ( panel === null ) {
			return;
		}

		const selection = this.getCurrentSelection( panel );
		if ( selection === null ) {
			return;
		}

		evt.preventDefault();

		const reqData = {
			...selection.render_action,
		};
		( new FormData( form ) ).forEach( ( value, key ) => {
			reqData[ key ] = typeof value === 'string' ? value : '';
		} );

		this.loadPanelBodyFromRenderAction(
			panel,
			reqData,
			{
				selection,
				historyUrl: this.buildLandingUrlForSelection( selection, reqData ),
				hash: window.location.hash,
			}
		).finally();
	}

	handlePivotClick( evt ) {
		if ( evt.defaultPrevented
			|| evt.button !== 0
			|| evt.metaKey
			|| evt.ctrlKey
			|| evt.shiftKey
			|| evt.altKey ) {
			return;
		}

		const link = evt.target instanceof Element
			? evt.target.closest( 'a[href]' )
			: null;
		if ( link === null
			|| link.target === '_blank'
			|| link.hasAttribute( 'download' ) ) {
			return;
		}

		const root = this.rootEl || this.getRoot();
		const panel = this.panelEl || this.getPanel( root );
		if ( root === null || panel === null || !panel.contains( link ) ) {
			return;
		}

		const pivot = this.resolvePivotSelection( link.href );
		if ( pivot === null ) {
			return;
		}

		evt.preventDefault();
		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.panelEl = panel;

		this.openSubjectSelection( pivot.selection, {
			requestData: pivot.requestData,
			historyUrl: pivot.historyUrl,
			hash: pivot.hash,
		} ).finally();
	}

	handleDrillBack( evt ) {
		const root = this.rootEl || this.getRoot();
		const shell = evt.target;
		if ( root === null || !( shell instanceof HTMLElement ) || !root.contains( shell ) ) {
			return;
		}

		const layerIndex = parseInt( String( evt.detail?.layer_index ?? -1 ), 10 );
		if ( layerIndex > 0 ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.panelEl = this.getPanel( root );
		this.cancelPanelRequest();
		this.stopLivePanelPoller();
		this.resetLandingToIdle();
	}

	openSubjectSelection( selection, { requestData, historyUrl, hash = '' } = {} ) {
		if ( this.shellEl === null || this.panelEl === null ) {
			return Promise.resolve();
		}

		const drillCtrl = this.getDrillDownController();
		const panelLayerIndex = this.getLayerIndexByKey( this.shellEl, 'panel' );
		if ( drillCtrl === null || panelLayerIndex < 0 ) {
			return Promise.resolve();
		}

		this.stopLivePanelPoller();
		this.applySelectionToPanel( this.panelEl, selection );
		drillCtrl.updateLayerHeader(
			this.shellEl,
			1,
			this.buildLoadingHeader( selection.header, this.getPanelLoadingText() )
		);
		drillCtrl.drillTo( this.shellEl, panelLayerIndex );

		return this.loadPanelBodyFromRenderAction(
			this.panelEl,
			requestData,
			{
				selection,
				historyUrl,
				hash,
			}
		);
	}

	loadPanelBodyFromRenderAction( panel, reqData, { selection = null, historyUrl = '', hash = '' } = {} ) {
		if ( !reqData || typeof reqData !== 'object' || Object.keys( reqData ).length < 1 ) {
			return Promise.resolve();
		}

		const nextSelection = selection || this.getCurrentSelection( panel );
		if ( nextSelection !== null && this.canReuseGenericPanel( nextSelection, reqData ) ) {
			return this.loadGenericPanelBody( panel, nextSelection, { historyUrl, hash } );
		}

		return this.sendPanelRequest(
			panel,
			reqData,
			nextSelection,
			{
				historyUrl,
				hash,
				cacheGenericSelection: nextSelection !== null && this.canCacheGenericPanel( nextSelection, reqData ),
			}
		);
	}

	sendPanelRequest( panel, reqData, nextSelection, { historyUrl = '', hash = '', cacheGenericSelection = false } = {} ) {
		const requestKey = `${Date.now()}-${Math.random()}`;
		this.panelRequestKey = requestKey;

		this.renderPanelLoadingMarkup( panel );
		this.setPanelLoadingState( panel, true );

		return ( new AjaxService() )
			.send( reqData, false, true )
			.then( ( resp ) => {
				if ( this.panelRequestKey !== requestKey ) {
					return;
				}

				const panelBodyHtml = ( resp && resp.success && resp.data && typeof resp.data.render_output === 'string' )
					? resp.data.render_output
					: '';
				if ( cacheGenericSelection ) {
					this.cacheGenericPanelHtml( nextSelection?.key || '', panelBodyHtml );
				}
				if ( this.finalizeSuccessfulPanelLoad( panel, panelBodyHtml, nextSelection, historyUrl, hash ) ) {
					return;
				}

				this.handlePanelLoadFailure( panel, nextSelection, historyUrl );
			} )
			.catch( () => {
				if ( this.panelRequestKey === requestKey ) {
					this.handlePanelLoadFailure( panel, nextSelection, historyUrl );
				}
			} )
			.finally( () => {
				if ( this.panelRequestKey === requestKey ) {
					this.setPanelLoadingState( panel, false );
					this.panelRequestKey = '';
				}
			} );
	}

	loadGenericPanelBody( panel, selection, { historyUrl = '', hash = '' } = {} ) {
		const cachedHtml = this.readCachedGenericPanelHtml( selection.key );
		if ( cachedHtml.length > 0 ) {
			this.cancelPanelRequest();
			this.setPanelLoadingState( panel, false );
			this.finalizeSuccessfulPanelLoad( panel, cachedHtml, selection, historyUrl, hash );
			return Promise.resolve();
		}

		const pending = this.genericPanelPending.get( selection.key ) || null;
		if ( pending instanceof Promise ) {
			const requestKey = `${Date.now()}-${Math.random()}`;
			this.panelRequestKey = requestKey;
			this.renderPanelLoadingMarkup( panel );
			this.setPanelLoadingState( panel, true );

			return pending
				.then( ( panelBodyHtml ) => {
					if ( this.panelRequestKey !== requestKey ) {
						return;
					}

					if ( this.finalizeSuccessfulPanelLoad( panel, panelBodyHtml, selection, historyUrl, hash ) ) {
						return;
					}

					this.handlePanelLoadFailure( panel, selection, historyUrl );
				} )
				.catch( () => {
					if ( this.panelRequestKey !== requestKey ) {
						return;
					}

					this.setPanelLoadingState( panel, false );
					this.panelRequestKey = '';
					return this.sendPanelRequest(
						panel,
						{
							...selection.render_action,
						},
						selection,
						{
							historyUrl,
							hash,
							cacheGenericSelection: true,
						}
					);
				} )
				.finally( () => {
					if ( this.panelRequestKey === requestKey ) {
						this.setPanelLoadingState( panel, false );
						this.panelRequestKey = '';
					}
				} );
		}

		return this.sendPanelRequest(
			panel,
			{
				...selection.render_action,
			},
			selection,
			{
				historyUrl,
				hash,
				cacheGenericSelection: true,
			}
		);
	}

	findPanelFromElement( el ) {
		return el.closest( '[data-investigate-panel="1"]' );
	}

	getCurrentSelection( panel = this.panelEl ) {
		if ( !( panel instanceof HTMLElement ) ) {
			return null;
		}

		const subjectKey = String( panel.dataset.investigatePanelSubject || '' ).trim();
		return this.subjectTiles.get( subjectKey ) || null;
	}

	applySelectionToPanel( panel, selection ) {
		panel.dataset.investigatePanelSubject = selection.key;
		panel.dataset.investigatePanelLive = selection.is_live ? '1' : '0';
		panel.dataset.investigatePanelLoaded = '0';
		panel.dataset.investigateRenderAction = JSON.stringify( selection.render_action );
		this.clearPanelChrome( panel );
	}

	resetLandingToIdle() {
		if ( this.shellEl === null || this.panelEl === null ) {
			return;
		}

		this.panelEl.dataset.investigatePanelSubject = '';
		this.panelEl.dataset.investigatePanelLive = '0';
		this.panelEl.dataset.investigatePanelLoaded = '0';
		delete this.panelEl.dataset.investigateRenderAction;
		this.clearPanelChrome( this.panelEl );
		this.replaceHistoryUrl( this.buildLandingUrlForSelection( null, {} ) );

		const drillCtrl = this.getDrillDownController();
		if ( drillCtrl !== null ) {
			drillCtrl.updateLayerHeader( this.shellEl, 1, this.defaultPanelHeader );
		}
	}

	clearPanelChrome( panel ) {
		const panelHeader = this.getPanelHeaderContainer( panel );
		const panelTabs = panel.querySelector( '[data-investigate-panel-tabs="1"]' );
		const panelContent = this.getPanelContentContainer( panel );

		if ( panelHeader !== null ) {
			panelHeader.innerHTML = '';
		}
		if ( panelTabs !== null ) {
			panelTabs.innerHTML = '';
		}
		if ( panelContent !== null ) {
			BootstrapTooltips.DisposeTooltipsWithin( panelContent );
			panelContent.innerHTML = '';
		}
	}

	cancelPanelRequest() {
		if ( this.panelRequestKey.length > 0 ) {
			this.panelRequestKey = `cancelled-${Date.now()}`;
		}
	}

	readSubjectSelection( subjectTile ) {
		const header = JSON.parse( subjectTile.dataset.investigateHeader );
		const renderAction = JSON.parse( subjectTile.dataset.investigateRenderAction );

		return {
			key: String( subjectTile.dataset.investigateSubject || '' ).trim(),
			header,
			render_action: renderAction,
			lookup_key: String( subjectTile.dataset.investigateLookupKey || '' ).trim(),
			is_live: subjectTile.dataset.investigateIsLive === '1',
		};
	}

	resolvePivotSelection( href ) {
		let url;
		try {
			url = new URL( href, window.location.href );
		}
		catch ( e ) {
			return null;
		}

		if ( url.origin !== window.location.origin || !url.searchParams.has( 'page' ) ) {
			return null;
		}

		const currentUrl = new URL( window.location.href );
		const page = String( url.searchParams.get( 'page' ) || '' ).trim();
		const currentPage = String( currentUrl.searchParams.get( 'page' ) || '' ).trim();
		const nav = String( url.searchParams.get( 'nav' ) || '' ).trim();
		const subnav = String( url.searchParams.get( 'nav_sub' ) || '' ).trim();
		if ( page.length < 1
			|| currentPage.length < 1
			|| page !== currentPage
			|| nav.length < 1
			|| subnav.length < 1 ) {
			return null;
		}

		const selection = [ ...this.subjectTiles.values() ]
			.find( ( candidate ) => {
				const route = this.getSelectionRoute( candidate );
				return route.nav === nav && route.subnav === subnav;
			} ) || null;
		if ( selection === null ) {
			return null;
		}

		const requestData = {
			...selection.render_action,
		};
		if ( selection.lookup_key.length > 0 ) {
			const lookupValue = String( url.searchParams.get( selection.lookup_key ) || '' ).trim();
			if ( lookupValue.length > 0 ) {
				requestData[ selection.lookup_key ] = lookupValue;
			}
		}

		return {
			selection,
			requestData,
			historyUrl: this.buildLandingUrlForSelection( selection, requestData, url.hash ),
			hash: url.hash,
		};
	}

	collectSubjectTiles( root ) {
		const tiles = new Map();
		if ( root === null ) {
			return tiles;
		}

		root.querySelectorAll( '[data-investigate-subject][data-investigate-render-action]' ).forEach( ( item ) => {
			const selection = this.readSubjectSelection( item );
			if ( selection.key.length > 0 ) {
				tiles.set( selection.key, selection );
			}
		} );

		return tiles;
	}

	buildEmptyHeader() {
		return {
			compact_back_label: '',
			active_back_label: '',
			breadcrumb_label: '',
			title: '',
			meta: '',
			summary: '',
			icon_class: '',
			badge: '',
			badge_status: 'neutral',
			color_key: 'investigate',
		};
	}

	buildLoadingHeader( header, loadingText ) {
		return {
			...( header && typeof header === 'object' ? header : {} ),
			summary: String( loadingText || '' ).trim(),
		};
	}

	buildFailureHeader( header ) {
		return {
			...( header && typeof header === 'object' ? header : {} ),
			summary: this.getPanelErrorText(),
		};
	}

	buildSelectionHeader( selection ) {
		if ( selection === null ) {
			return null;
		}

		return selection.header && typeof selection.header === 'object'
			? selection.header
			: null;
	}

	getRoot() {
		return document.querySelector( '[data-investigate-landing="1"]' );
	}

	resolveRootContext( contextEl = null ) {
		if ( contextEl instanceof Element ) {
			if ( contextEl.matches( '[data-investigate-landing="1"]' ) ) {
				return contextEl;
			}

			const nestedRoot = contextEl.querySelector?.( '[data-investigate-landing="1"]' ) || null;
			if ( nestedRoot instanceof HTMLElement ) {
				return nestedRoot;
			}

			return contextEl.closest( '[data-investigate-landing="1"]' );
		}
		return this.rootEl || this.getRoot();
	}

	getShell( root = this.rootEl ) {
		return root?.querySelector( '[data-drill-shell="1"]' ) || null;
	}

	getPanel( root = this.rootEl ) {
		return root?.querySelector( '[data-investigate-panel="1"]' ) || null;
	}

	getLayerByKey( shell, layerKey ) {
		return getLayersForShell( shell )
			.find( ( layer ) => String( layer.dataset.drillLayerKey || '' ).trim() === layerKey ) || null;
	}

	getLayerIndexByKey( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		if ( layer === null ) {
			return -1;
		}

		const parsed = parseInt( String( layer.dataset.drillLayer || '-1' ), 10 );
		return Number.isNaN( parsed ) ? -1 : parsed;
	}

	getDrillDownController() {
		return window.shieldAppMain?.components?.drill_down || null;
	}

	getPanelLoadingText() {
		return String( this.rootEl?.dataset?.investigatePanelLoading ?? '' ).trim();
	}

	readLayerHeader( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		if ( !( layer instanceof HTMLElement ) ) {
			return this.buildEmptyHeader();
		}

		try {
			return JSON.parse( layer.dataset.drillLayerHeader || '{}' );
		}
		catch ( e ) {
			return this.buildEmptyHeader();
		}
	}

	updatePanelLayerHeader( header ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}
		drillCtrl.updateLayerHeader( this.shellEl, 1, header );
	}

	finalizePanelRequestState( header, historyUrl ) {
		if ( header === null || typeof header !== 'object' ) {
			return;
		}

		if ( historyUrl.length > 0 ) {
			this.replaceHistoryUrl( historyUrl );
		}

		this.updatePanelLayerHeader( header );
	}

	resetCurrentSelection( contextEl = null ) {
		const root = this.resolveRootContext( contextEl );
		if ( root === null ) {
			return Promise.resolve();
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.panelEl = this.getPanel( root );
		if ( this.panelEl === null ) {
			return Promise.resolve();
		}

		const selection = this.getCurrentSelection( this.panelEl );
		if ( selection === null || selection.lookup_key.length < 1 ) {
			return Promise.resolve();
		}

		return this.loadPanelBodyFromRenderAction(
			this.panelEl,
			{
				...selection.render_action,
			},
			{
				selection,
				historyUrl: this.buildLandingUrlForSelection( selection, selection.render_action ),
				hash: '',
			}
		);
	}

	replaceHistoryUrl( nextUrl ) {
		if ( typeof nextUrl !== 'string' || nextUrl.length < 1 ) {
			return;
		}
		window.history.replaceState( window.history.state || {}, '', nextUrl );
	}

	buildLandingUrlForSelection( selection, reqData = {}, hash = '' ) {
		const url = new URL( window.location.href );
		this.collectKnownLookupKeys().forEach( ( lookupKey ) => {
			url.searchParams.delete( lookupKey );
		} );
		url.searchParams.set( 'nav', 'activity' );
		url.searchParams.set( 'nav_sub', 'overview' );

		if ( selection !== null && selection.key.length > 0 ) {
			url.searchParams.set( 'subject', selection.key );
			if ( selection.lookup_key.length > 0 ) {
				const lookupValue = String( reqData?.[ selection.lookup_key ] || '' ).trim();
				if ( lookupValue.length > 0 ) {
					url.searchParams.set( selection.lookup_key, lookupValue );
				}
				else {
					url.searchParams.delete( selection.lookup_key );
				}
			}
		}
		else {
			url.searchParams.delete( 'subject' );
		}

		url.hash = typeof hash === 'string' ? hash : '';
		return url.toString();
	}

	collectKnownLookupKeys() {
		return Array.from( new Set(
			[ ...this.subjectTiles.values() ]
				.map( ( selection ) => selection.lookup_key )
				.filter( ( lookupKey ) => lookupKey.length > 0 )
		) );
	}

	getSelectionRoute( selection ) {
		return {
			nav: String( selection?.render_action?.nav || '' ).trim(),
			subnav: String( selection?.render_action?.nav_sub || '' ).trim(),
		};
	}

	applyPanelBodyToPanel( panel, panelBodyHtml ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent === null ) {
			return false;
		}

		if ( panelBodyHtml.length < 1 ) {
			return false;
		}

		BootstrapTooltips.DisposeTooltipsWithin( panelContent );
		panelContent.innerHTML = panelBodyHtml;
		this.syncPanelChrome( panel, true );
		UiContentActivator.activateCurrentSubtree( panelContent );
		return true;
	}

	finalizeSuccessfulPanelLoad( panel, panelBodyHtml, nextSelection, historyUrl, hash ) {
		if ( !this.applyPanelBodyToPanel( panel, panelBodyHtml ) ) {
			return false;
		}

		this.setPanelLoadedState( panel, true );
		this.syncPanelLivePolling( panel );
		this.finalizePanelRequestState(
			this.buildSelectionHeader( nextSelection ),
			historyUrl
		);
		this.activatePanelHash( hash );
		return true;
	}

	renderPanelLoadingMarkup( panel ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			BootstrapTooltips.DisposeTooltipsWithin( panelContent );
			panelContent.innerHTML = this.buildPanelLoadingMarkup();
		}
		this.syncPanelChrome( panel, true );
	}

	handlePanelLoadFailure( panel, selection = null, historyUrl = '' ) {
		const panelContent = this.getPanelContentContainer( panel );
		if ( panelContent !== null ) {
			BootstrapTooltips.DisposeTooltipsWithin( panelContent );
			panelContent.innerHTML = this.buildInlineErrorMarkup();
		}
		this.syncPanelChrome( panel, true );
		this.setPanelLoadedState( panel, false );
		this.stopLivePanelPoller();
		this.finalizePanelRequestState(
			selection === null
				? null
				: this.buildFailureHeader( selection.header ),
			historyUrl
		);
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

	syncLivePanelPolling() {
		if ( this.panelEl === null || this.shellEl === null ) {
			this.stopLivePanelPoller();
			return;
		}

		if ( this.isLivePanel( this.panelEl )
			&& this.isPanelLoaded( this.panelEl )
			&& getActiveLayerIndex( getLayersForShell( this.shellEl ) ) === 1 ) {
			this.startLivePanelPoller( this.panelEl );
			return;
		}

		this.stopLivePanelPoller();
	}

	syncPanelLivePolling( panel ) {
		if ( this.isLivePanel( panel ) && this.isPanelLoaded( panel ) ) {
			this.startLivePanelPoller( panel );
			return;
		}
		this.stopLivePanelPoller();
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

	buildInlineErrorMarkup() {
		const message = this.getPanelErrorText();
		return '<div class="alert alert-warning mb-0">'
			   + this.escapeHtml( message )
			   + '</div>';
	}

	getPanelErrorText() {
		return String( this.rootEl?.dataset?.investigatePanelError ?? '' ).trim();
	}

	buildPanelLoadingMarkup() {
		const placeholder = document.querySelector( '.shield_loading_placeholder_investigate_panel' );
		if ( placeholder instanceof HTMLElement ) {
			const clone = placeholder.cloneNode( true );
			clone.classList.remove( 'd-none' );
			return clone.outerHTML;
		}

		return `<div class="text-muted small">${this.escapeHtml( this.getPanelLoadingText() )}</div>`;
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
		return panel.querySelector( '[data-investigate-panel-content="1"]' ) || null;
	}

	getPanelHeaderContainer( panel ) {
		return panel.querySelector( '[data-investigate-panel-header="1"]' );
	}

	preloadGenericPanels() {
		const root = this.rootEl;
		if ( root === null
			|| Object.keys( this.batchRequestData || {} ).length < 1
			|| this.preloadingRoots.has( root )
			|| this.preloadedRoots.has( root ) ) {
			return;
		}

		const preloadableSelections = [ ...this.subjectTiles.values() ].filter(
			( selection ) => this.canPreloadSelection( selection )
				&& !this.genericPanelCache.has( selection.key )
				&& !this.genericPanelPending.has( selection.key )
		);
		if ( preloadableSelections.length < 1 ) {
			this.preloadedRoots.add( root );
			return;
		}

		const batch = new AjaxBatchService( this.batchRequestData );
		let queuedRequests = 0;

		preloadableSelections.forEach( ( selection ) => {
			const trackedPromise = new Promise( ( resolve, reject ) => {
				batch.add( {
					id: this.buildGenericBatchId( selection.key ),
					request: {
						...selection.render_action,
					},
					onSuccess: ( result ) => {
						if ( this.lastInvestigateRoot !== root ) {
							reject( new Error( 'Investigate preload root changed.' ) );
							return;
						}

						const panelBodyHtml = this.extractBatchPanelHtml( result );
						if ( panelBodyHtml.length < 1 ) {
							reject( new Error( 'Investigate preload returned no panel HTML.' ) );
							return;
						}

						this.cacheGenericPanelHtml( selection.key, panelBodyHtml );
						resolve( panelBodyHtml );
					},
					onError: ( result ) => {
						reject( new Error( String( result?.error || result?.data?.message || 'Investigate preload failed.' ) ) );
					},
				} );
			} ).finally( () => {
				if ( this.genericPanelPending.get( selection.key ) === trackedPromise ) {
					this.genericPanelPending.delete( selection.key );
				}
			} );

			this.genericPanelPending.set( selection.key, trackedPromise );
			queuedRequests++;
		} );

		if ( queuedRequests < 1 ) {
			this.preloadedRoots.add( root );
			return;
		}

		this.preloadingRoots.add( root );
		batch.flush()
			.finally( () => {
				this.preloadingRoots.delete( root );
				this.preloadedRoots.add( root );
			} );
	}

	canPreloadSelection( selection ) {
		return selection !== null
			&& selection.key.length > 0
			&& !selection.is_live
			&& selection.render_action
			&& typeof selection.render_action === 'object'
			&& Object.keys( selection.render_action ).length > 0;
	}

	canReuseGenericPanel( selection, reqData ) {
		return this.canCacheGenericPanel( selection, reqData );
	}

	canCacheGenericPanel( selection, reqData ) {
		return selection !== null
			&& selection.key.length > 0
			&& !selection.is_live
			&& selection.render_action
			&& typeof reqData === 'object'
			&& !this.selectionHasLookupValue( selection, reqData );
	}

	selectionHasLookupValue( selection, reqData ) {
		if ( selection.lookup_key.length < 1 ) {
			return false;
		}

		return String( reqData?.[ selection.lookup_key ] || '' ).trim().length > 0;
	}

	seedGenericPanelCacheFromPanel( panel ) {
		if ( !( panel instanceof HTMLElement ) || !this.isPanelLoaded( panel ) ) {
			return;
		}

		const selection = this.getCurrentSelection( panel );
		if ( selection === null || !this.canCacheGenericPanel( selection, this.buildRequestDataFromCurrentUrl( selection ) ) ) {
			return;
		}

		const panelContent = this.getPanelContentContainer( panel );
		const panelBodyHtml = typeof panelContent?.innerHTML === 'string'
			? panelContent.innerHTML.trim()
			: '';
		if ( panelBodyHtml.length > 0 ) {
			this.cacheGenericPanelHtml( selection.key, panelBodyHtml );
		}
	}

	buildRequestDataFromCurrentUrl( selection ) {
		const requestData = {
			...selection.render_action,
		};
		if ( selection.lookup_key.length < 1 ) {
			return requestData;
		}

		let currentUrl;
		try {
			currentUrl = new URL( window.location.href );
		}
		catch ( e ) {
			return requestData;
		}

		const lookupValue = String( currentUrl.searchParams.get( selection.lookup_key ) || '' ).trim();
		if ( lookupValue.length > 0 ) {
			requestData[ selection.lookup_key ] = lookupValue;
		}
		return requestData;
	}

	buildGenericBatchId( subjectKey ) {
		return `investigate-generic-${subjectKey}`;
	}

	extractBatchPanelHtml( result ) {
		return typeof result?.data?.render_output === 'string'
			? result.data.render_output
			: '';
	}

	cacheGenericPanelHtml( subjectKey, panelBodyHtml ) {
		if ( typeof subjectKey !== 'string' || subjectKey.trim().length < 1 ) {
			return;
		}

		const normalizedHtml = typeof panelBodyHtml === 'string' ? panelBodyHtml.trim() : '';
		if ( normalizedHtml.length > 0 ) {
			this.genericPanelCache.set( subjectKey.trim(), normalizedHtml );
		}
	}

	readCachedGenericPanelHtml( subjectKey ) {
		const cachedHtml = this.genericPanelCache.get( String( subjectKey || '' ).trim() ) || '';
		return typeof cachedHtml === 'string' ? cachedHtml : '';
	}

	activatePanelHash( hash ) {
		if ( this.panelEl === null || typeof hash !== 'string' || hash.length < 2 ) {
			return;
		}

		const hashTarget = this.panelEl.querySelector( hash );
		if ( hashTarget instanceof HTMLElement
			&& ( hashTarget.dataset.bsToggle === 'tab' || hashTarget.classList.contains( 'nav-link' ) ) ) {
			Tab.getOrCreateInstance( hashTarget ).show();
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
}
