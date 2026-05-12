import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { DrillDownAsyncControllerBase } from "./DrillDownAsyncControllerBase";

export class ConfigureLandingController extends DrillDownAsyncControllerBase {

	canRun() {
		return true;
	}

	run() {
		this.batchRequestData = this._base_data?.ajax?.batch_requests || {};
		this.diagnosisCache = new Map();
		this.preloadGeneration = 0;
		this.lastConfigureRoot = null;
		this.layerRequests = {};
		this.selectedZone = null;
		this.searchTimeout = null;
		this.searchRequestKey = null;

		this.bindDrillDownHandlers();
		this.bindSaveHandlers();
		this.bindSearchHandlers();
		this.initializeCurrentRoot();
	}

	initializeCurrentRoot() {
		super.initializeCurrentRoot();

		if ( this.rootEl !== this.lastConfigureRoot ) {
			this.resetDiagnosisCacheForRoot( this.rootEl );
		}

		if ( this.rootEl === null || this.shellEl === null ) {
			return;
		}

		this.setSearchState( 'idle', this.rootEl );
		this.seedDiagnosisCacheFromCurrentLayer();
		this.preloadDiagnosisLayers();
	}

	bindDrillDownHandlers() {
		if ( this.hasBoundDrillHandlers ) {
			return;
		}
		this.hasBoundDrillHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-configure-landing="1"] [data-drill-target="diagnosis"]',
			( item ) => this.handleZoneSelectionClick( item ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-configure-landing="1"] [data-configure-retry]',
			( item ) => this.handleRetryClick( item ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-configure-landing="1"] [data-configure-search-result="1"]',
			( item, evt ) => this.handleSearchResultClick( item, evt ),
			false
		);
	}

	bindSaveHandlers() {
		if ( this.hasBoundSaveHandlers ) {
			return;
		}
		this.hasBoundSaveHandlers = true;

		document.addEventListener( 'shield:expansion-form-saved', ( evt ) => this.handleSettingsSaved( evt ) );
	}

	bindSearchHandlers() {
		if ( this.hasBoundSearchHandlers ) {
			return;
		}
		this.hasBoundSearchHandlers = true;

		const searchInput = this.getRoot()?.querySelector( '[data-configure-search-input="1"]' ) || null;
		if ( searchInput !== null ) {
			searchInput.addEventListener( 'input', () => this.handleSearchInput( searchInput ) );
		}
	}

	getRoot() {
		return document.querySelector( '[data-configure-landing="1"]' );
	}

	handleZoneSelectionClick( item ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		const shell = this.getShell( root );
		if ( shell === null ) {
			return;
		}

		const zone = this.readZoneSelection( item );
		if ( zone.key.length < 1 ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = shell;
		this.openDiagnosisSelection( zone, {
			sourceEl: item,
		} );
	}

	handleSearchResultClick( item, evt ) {
		if ( this.shouldAllowDefaultSearchResultClick( item, evt ) ) {
			return;
		}

		evt.preventDefault();

		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		const zone = this.readZoneSelection( item );
		if ( zone.key.length < 1 ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.cancelPendingSearch();
		this.clearSearchUi( root );
		this.openDiagnosisSelection( zone, {
			sourceEl: item,
			focusRequestJson: String( item.dataset.configureFocusRequest || '' ),
			historyUrl: item instanceof HTMLAnchorElement ? item.href : '',
		} );
	}

	loadDiagnosisLayer( { showPlaceholder = true, includeLandingRefresh = false } = {} ) {
		if ( this.selectedZone === null ) {
			return Promise.resolve( null );
		}

		const extraData = {
			zone: this.selectedZone.key,
		};
		if ( includeLandingRefresh ) {
			extraData.include_landing_refresh = 1;
		}

		return this.loadLayerContent(
			'diagnosis',
			this.buildRenderAction( this.rootEl?.dataset.configureDiagnosisAction, extraData ),
			showPlaceholder,
			this.getDiagnosisLoadingText(),
			( data ) => this.applyDiagnosisLayerResponse( data )
		);
	}

	applyDiagnosisLayerResponse( data ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		const zoneSelection = this.readSelectionPayload( data.zone_selection );
		this.storeDiagnosisCacheEntry( data );
		this.selectedZone = {
			...( this.selectedZone || {} ),
			...zoneSelection,
		};
		drillCtrl.updateLayerHeader( this.shellEl, 1, data.header );
		this.applyLandingRefresh( data.landing_refresh || null );
		this.reinitializeExpandLoader();
	}

	applyLandingRefresh( landingRefresh ) {
		if ( ObjectOps.IsEmpty( landingRefresh || {} ) || this.rootEl === null ) {
			return;
		}

		this.updateOperatorRootStep( landingRefresh.root_step_json || '' );

		if ( this.shellEl !== null && typeof landingRefresh.zones_html === 'string' ) {
			const zonesLayer = this.getLayerByKey( this.shellEl, 'zones' );
			const zonesBody = zonesLayer?.querySelector( '.drill-layer__body' ) || null;
			if ( zonesBody !== null ) {
				this.applyLayerHtml( zonesBody, landingRefresh.zones_html );
			}
		}

		this.restartDiagnosisPreload();
	}

	handleRetryClick( item ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );

		if ( String( item.dataset.configureRetry || '' ).trim() === 'diagnosis' ) {
			this.loadDiagnosisLayer();
		}
	}

	handleSettingsSaved( evt ) {
		const root = this.rootEl || this.getRoot();
		const target = evt.target instanceof HTMLElement ? evt.target : null;
		if ( root === null || target === null || this.selectedZone === null || !root.contains( target ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.loadDiagnosisLayer( {
			showPlaceholder: false,
			includeLandingRefresh: true,
		} );
	}

	handleSearchInput( input ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( input ) ) {
			return;
		}

		const searchBody = this.getSearchBody( root );
		if ( searchBody === null ) {
			return;
		}

		this.rootEl = root;
		const query = String( input.value || '' ).trim();
		this.cancelPendingSearch();

		if ( query.length < this.getSearchMinChars() ) {
			this.clearSearchResults( root, searchBody );
			return;
		}

		this.renderSearchLoading( root, searchBody );
		this.searchTimeout = setTimeout( () => this.performSearch( query, searchBody ), this.getSearchDebounceMs() );
	}

	performSearch( query, searchBody ) {
		this.searchTimeout = null;
		const renderAction = this.buildRenderAction( this.rootEl?.dataset.configureSearchAction, {
			search: query,
		} );
		if ( ObjectOps.IsEmpty( renderAction ) ) {
			this.clearSearchResults( this.rootEl, searchBody );
			return;
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		this.searchRequestKey = requestKey;

		( new AjaxService() )
			.send( renderAction, false, true )
			.then( ( resp ) => {
				if ( this.searchRequestKey !== requestKey ) {
					return;
				}

				if ( !resp.success || typeof resp?.data?.render_output !== 'string' ) {
					this.renderSearchFailure( this.rootEl, searchBody );
					return;
				}

				this.replaceLayerBodyHtml( searchBody, resp.data.render_output, true );
				this.setSearchState( 'ready', this.rootEl );
			} )
			.catch( () => {
				if ( this.searchRequestKey === requestKey ) {
					this.renderSearchFailure( this.rootEl, searchBody );
				}
			} )
			.finally( () => {
				if ( this.searchRequestKey === requestKey ) {
					this.searchRequestKey = null;
				}
			} );
	}

	cancelPendingSearch() {
		if ( this.searchTimeout ) {
			clearTimeout( this.searchTimeout );
			this.searchTimeout = null;
		}
		this.searchRequestKey = `cancelled-${Date.now()}`;
	}

	readZoneSelection( item ) {
		return this.readSelectionPayload( this.parseJsonDataset( item.dataset.drillZoneSelection ) );
	}

	getDiagnosisLoadingText() {
		return this.rootEl?.dataset.configureDiagnosisLoading || '';
	}

	getSearchLoadingText() {
		return this.rootEl?.dataset.configureSearchLoading || '';
	}

	getSearchDebounceMs() {
		return 350;
	}

	getSearchMinChars() {
		return this.parseInteger( this.rootEl?.dataset.configureSearchMinChars || 3 );
	}

	getSearchInput( root = this.rootEl ) {
		return root?.querySelector( '[data-configure-search-input="1"]' ) || null;
	}

	getSearchBody( root = this.rootEl ) {
		return root?.querySelector( '[data-configure-search-body="1"]' ) || null;
	}

	getSearchDock( root = this.rootEl ) {
		return root?.querySelector( '[data-configure-search-dock="1"]' ) || null;
	}

	renderLayerFailure( body, layerKey ) {
		const message = this.rootEl?.dataset.configureLayerError || '';
		const retry = this.rootEl?.dataset.configureLayerRetry || '';

		this.replaceLayerBodyHtml(
			body,
			`<div class="configure-landing__empty-state"><div>${this.escapeHtml( message )}</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-configure-retry="${this.escapeHtml( layerKey )}">${this.escapeHtml( retry )}</button></div>`
		);
	}

	getLayerFailureText( layerKey ) {
		void layerKey;
		return this.rootEl?.dataset.configureLayerError || '';
	}

	buildLoadingMarkup( message ) {
		return `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
	}

	buildSearchFailureMarkup() {
		const message = this.rootEl?.dataset.configureLayerError || '';
		return this.buildSearchStatusMarkup( message );
	}

	renderSearchLoading( root, searchBody ) {
		this.replaceLayerBodyHtml(
			searchBody,
			this.buildSearchStatusMarkup( this.getSearchLoadingText(), true )
		);
		this.setSearchState( 'loading', root );
	}

	renderSearchFailure( root, searchBody ) {
		this.replaceLayerBodyHtml( searchBody, this.buildSearchFailureMarkup() );
		this.setSearchState( 'error', root );
	}

	clearSearchResults( root, searchBody ) {
		this.replaceLayerBodyHtml( searchBody, '' );
		this.setSearchState( 'idle', root );
	}

	clearSearchUi( root = this.rootEl ) {
		const input = this.getSearchInput( root );
		if ( input !== null ) {
			input.value = '';
		}

		const searchBody = this.getSearchBody( root );
		if ( searchBody !== null ) {
			this.clearSearchResults( root, searchBody );
		}
	}

	setSearchState( state, root = this.rootEl ) {
		const dock = this.getSearchDock( root );
		if ( dock !== null ) {
			dock.dataset.configureSearchState = state;
		}

		const searchBody = this.getSearchBody( root );
		if ( searchBody !== null ) {
			searchBody.setAttribute( 'aria-busy', state === 'loading' ? 'true' : 'false' );
		}
	}

	buildSearchStatusMarkup( message, includeSpinner = false ) {
		const spinnerMarkup = includeSpinner
			? `<span class="configure-search-results__spinner">${this.buildSearchSpinnerMarkup()}</span>`
			: '';

		return `<div class="configure-search-results configure-search-results--status" data-configure-search-results="1"><div class="configure-search-results__status">${spinnerMarkup}<span class="configure-search-results__status-text">${this.escapeHtml( message )}</span></div></div>`;
	}

	buildSearchSpinnerMarkup() {
		const spinner = document.getElementById( 'ShieldWaitSpinner' ).cloneNode( true );
		spinner.id = '';
		spinner.classList.remove( 'd-none' );
		spinner.querySelector( '.spinner-border' )?.classList.remove( 'm-5' );
		return spinner.outerHTML;
	}

	shouldAllowDefaultSearchResultClick( item, evt ) {
		if ( !( item instanceof HTMLAnchorElement ) ) {
			return true;
		}
		if ( item.target === '_blank' || item.hasAttribute( 'download' ) ) {
			return true;
		}

		const mouseEvt = evt instanceof MouseEvent ? evt : null;
		if ( mouseEvt === null ) {
			return false;
		}

		return mouseEvt.button !== 0
			|| mouseEvt.metaKey
			|| mouseEvt.ctrlKey
			|| mouseEvt.shiftKey
			|| mouseEvt.altKey;
	}

	openDiagnosisSelection( zone, { sourceEl = null, focusRequestJson = '', historyUrl = '' } = {} ) {
		const shell = this.shellEl;
		const diagnosisIndex = this.getLayerIndexByKey( shell, 'diagnosis' );
		const drillCtrl = this.getDrillDownController();
		if ( diagnosisIndex < 0 || drillCtrl === null || shell === null ) {
			return Promise.resolve( null );
		}

		this.selectedZone = zone;
		this.cancelLayerRequest( 'diagnosis' );
		drillCtrl.updateLayerHeader(
			shell,
			diagnosisIndex,
			this.buildLoadingHeader( zone.header, this.getDiagnosisLoadingText() ),
			{ announce: false }
		);
		drillCtrl.drillTo( shell, diagnosisIndex, { sourceEl } );

		if ( historyUrl.length > 0 ) {
			this.replaceHistoryUrl( historyUrl );
		}

		const cachedDiagnosis = this.readDiagnosisCacheEntry( zone.key );
		if ( cachedDiagnosis !== null ) {
			this.applyDiagnosisCacheEntry( cachedDiagnosis );
			this.applyFocusRequest( focusRequestJson );
			return Promise.resolve( cachedDiagnosis );
		}

		return this.loadDiagnosisLayer().then( ( data ) => {
			if ( data !== null ) {
				this.applyFocusRequest( focusRequestJson );
			}
			return data;
		} );
	}

	applyFocusRequest( focusRequestJson = '' ) {
		if ( String( focusRequestJson || '' ).trim().length < 2 ) {
			return;
		}

		const app = globalThis.shieldAppMain || null;
		if ( !app || typeof app.getComponent !== 'function' ) {
			return;
		}

		app.getComponent( 'configure_expand_loader' )?.applyFocusRequestFromJson?.( focusRequestJson );
	}

	replaceHistoryUrl( nextUrl = '' ) {
		if ( typeof nextUrl !== 'string' || nextUrl.length < 1 ) {
			return;
		}

		window.history.replaceState( window.history.state || {}, '', nextUrl );
	}

	resetDiagnosisCacheForRoot( root ) {
		this.lastConfigureRoot = root;
		this.diagnosisCache = new Map();
		this.preloadGeneration++;
		this.selectedZone = null;
	}

	seedDiagnosisCacheFromCurrentLayer() {
		const diagnosisBody = this.getDiagnosisLayerBody();
		const diagnosis = diagnosisBody?.querySelector( '[data-configure-diagnosis="1"]' ) || null;
		if ( !( diagnosis instanceof HTMLElement ) ) {
			return;
		}

		const zoneKey = String( diagnosis.dataset.configureZone || '' ).trim();
		const zoneSelection = this.getZoneSelectionByKey( zoneKey );
		if ( zoneSelection === null ) {
			return;
		}

		const diagnosisHeader = this.parseJsonDataset(
			this.getLayerByKey( this.shellEl, 'diagnosis' )?.dataset.drillLayerHeader || '{}'
		);
		const html = diagnosisBody?.innerHTML || '';
		if ( html.trim().length < 1 ) {
			return;
		}

		this.storeDiagnosisCacheEntry( {
			html,
			header: diagnosisHeader,
			zone_selection: zoneSelection,
		} );
		this.selectedZone = {
			...( this.selectedZone || {} ),
			...zoneSelection,
		};
	}

	preloadDiagnosisLayers() {
		const root = this.rootEl;
		if ( root === null
			|| ObjectOps.IsEmpty( this.batchRequestData )
			|| String( root.dataset.configureDiagnosisAction || '' ).trim().length < 1 ) {
			return;
		}

		const zoneSelections = this.getZoneSelections().filter(
			( zoneSelection ) => this.readDiagnosisCacheEntry( zoneSelection.key ) === null
		);
		if ( zoneSelections.length < 1 ) {
			return;
		}

		const generation = this.preloadGeneration;
		const batch = new AjaxBatchService( this.batchRequestData );

		zoneSelections.forEach( ( zoneSelection ) => {
			const renderAction = this.buildRenderAction( root.dataset.configureDiagnosisAction, {
				zone: zoneSelection.key,
			} );
			if ( ObjectOps.IsEmpty( renderAction ) ) {
				return;
			}

			batch.add( {
				id: `configure-diagnosis-${zoneSelection.key}`,
				request: renderAction,
				onSuccess: ( result ) => this.handleDiagnosisBatchSuccess( result, generation, root ),
			} );
		} );

		batch.flush();
	}

	handleDiagnosisBatchSuccess( result, generation, root ) {
		if ( generation !== this.preloadGeneration || root !== this.lastConfigureRoot ) {
			return;
		}

		this.storeDiagnosisCacheEntry( result?.data || {}, generation );
	}

	restartDiagnosisPreload() {
		const selectedZoneKey = this.selectedZone?.key || '';
		const selectedZoneCache = selectedZoneKey.length > 0
			? this.readDiagnosisCacheEntry( selectedZoneKey )
			: null;

		this.preloadGeneration++;
		this.diagnosisCache = new Map();

		if ( selectedZoneCache !== null ) {
			this.diagnosisCache.set( selectedZoneKey, {
				...selectedZoneCache,
				generation: this.preloadGeneration,
			} );
		}

		this.preloadDiagnosisLayers();
	}

	storeDiagnosisCacheEntry( data, generation = this.preloadGeneration ) {
		const cacheEntry = this.buildDiagnosisCacheEntry( data );
		if ( cacheEntry === null ) {
			return;
		}

		this.diagnosisCache.set( cacheEntry.key, {
			...cacheEntry,
			generation,
		} );
	}

	buildDiagnosisCacheEntry( data ) {
		const zoneSelection = this.readSelectionPayload( data?.zone_selection );
		const html = typeof data?.html === 'string' ? data.html : '';
		if ( zoneSelection.key.length < 1 || html.trim().length < 1 ) {
			return null;
		}

		return {
			key: zoneSelection.key,
			html,
			header: data?.header,
			zone_selection: zoneSelection,
		};
	}

	readDiagnosisCacheEntry( zoneKey ) {
		const cacheEntry = this.diagnosisCache.get( String( zoneKey || '' ).trim() ) || null;
		if ( cacheEntry === null || cacheEntry.generation !== this.preloadGeneration ) {
			return null;
		}

		return cacheEntry;
	}

	applyDiagnosisCacheEntry( cacheEntry ) {
		const drillCtrl = this.getDrillDownController();
		const diagnosisBody = this.getDiagnosisLayerBody();
		if ( this.shellEl === null || diagnosisBody === null || drillCtrl === null ) {
			return;
		}

		const zoneSelection = this.readSelectionPayload( cacheEntry.zone_selection );
		this.selectedZone = {
			...( this.selectedZone || {} ),
			...zoneSelection,
		};
		this.applyLayerHtml( diagnosisBody, cacheEntry.html );
		drillCtrl.updateLayerHeader( this.shellEl, 1, cacheEntry.header );
		this.reinitializeExpandLoader();
	}

	getDiagnosisLayerBody() {
		const diagnosisLayer = this.getLayerByKey( this.shellEl, 'diagnosis' );
		return diagnosisLayer?.querySelector( '.drill-layer__body' ) || null;
	}

	getZoneSelections() {
		return Array.from(
			this.rootEl?.querySelectorAll( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ) || []
		).map( ( item ) => this.readZoneSelection( item ) ).filter(
			( zoneSelection ) => zoneSelection.key.length > 0
		);
	}

	getZoneSelectionByKey( zoneKey ) {
		return this.getZoneSelections().find(
			( zoneSelection ) => zoneSelection.key === zoneKey
		) || null;
	}

	reinitializeExpandLoader() {
		const app = globalThis.shieldAppMain || null;
		if ( !app || typeof app.getComponent !== 'function' ) {
			return;
		}

		app.getComponent( 'configure_expand_loader' )?.initializeCurrentRoot?.();
	}
}
