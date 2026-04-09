import { ObjectOps } from "../../util/ObjectOps";
import { AjaxService } from "../services/AjaxService";
import { DrillDownAsyncControllerBase } from "./DrillDownAsyncControllerBase";

export class ConfigureLandingController extends DrillDownAsyncControllerBase {

	canRun() {
		return true;
	}

	run() {
		this.layerRequests = {};
		this.selectedZone = null;
		this.searchTimeout = null;
		this.searchRequestKey = null;

		this.bindDrillDownHandlers();
		this.bindSaveHandlers();
		this.bindSearchHandlers();
		this.initializeCurrentRoot();
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

		const diagnosisIndex = this.getLayerIndexByKey( shell, 'diagnosis' );
		const drillCtrl = this.getDrillDownController();
		if ( diagnosisIndex < 0 || drillCtrl === null ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = shell;
		this.selectedZone = zone;
		this.cancelLayerRequest( 'diagnosis' );

		drillCtrl.drillTo( shell, diagnosisIndex );
		drillCtrl.updateLayerHeader(
			shell,
			diagnosisIndex,
			this.buildLoadingHeader( zone.header, this.getDiagnosisLoadingText() )
		);

		this.loadDiagnosisLayer();
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
		this.selectedZone = {
			...( this.selectedZone || {} ),
			...zoneSelection,
		};
		drillCtrl.updateLayerHeader( this.shellEl, 1, data.header || zoneSelection.header );
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
			this.replaceLayerBodyHtml( searchBody, '' );
			return;
		}

		this.replaceLayerBodyHtml( searchBody, this.buildLoadingMarkup( this.getSearchLoadingText() ) );
		this.searchTimeout = setTimeout( () => this.performSearch( query, searchBody ), 250 );
	}

	performSearch( query, searchBody ) {
		this.searchTimeout = null;
		const renderAction = this.buildRenderAction( this.rootEl?.dataset.configureSearchAction, {
			search: query,
		} );
		if ( ObjectOps.IsEmpty( renderAction ) ) {
			this.replaceLayerBodyHtml( searchBody, '' );
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
					this.replaceLayerBodyHtml( searchBody, this.buildSearchFailureMarkup() );
					return;
				}

				this.replaceLayerBodyHtml( searchBody, resp.data.render_output, true );
			} )
			.catch( () => {
				if ( this.searchRequestKey === requestKey ) {
					this.replaceLayerBodyHtml( searchBody, this.buildSearchFailureMarkup() );
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

	getSearchMinChars() {
		return this.parseInteger( this.rootEl?.dataset.configureSearchMinChars || 3 );
	}

	getSearchBody( root = this.rootEl ) {
		return root?.querySelector( '[data-configure-search-body="1"]' ) || null;
	}

	renderLayerFailure( body, layerKey ) {
		const message = this.rootEl?.dataset.configureLayerError || '';
		const retry = this.rootEl?.dataset.configureLayerRetry || '';

		this.replaceLayerBodyHtml(
			body,
			`<div class="configure-landing__empty-state"><div>${this.escapeHtml( message )}</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-configure-retry="${this.escapeHtml( layerKey )}">${this.escapeHtml( retry )}</button></div>`
		);
	}

	buildLoadingMarkup( message ) {
		return `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
	}

	buildSearchFailureMarkup() {
		const message = this.rootEl?.dataset.configureLayerError || '';
		return `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
	}

	reinitializeExpandLoader() {
		const app = globalThis.shieldAppMain || null;
		if ( !app || typeof app.getComponent !== 'function' ) {
			return;
		}

		app.getComponent( 'configure_expand_loader' )?.initializeCurrentRoot?.();
	}
}
