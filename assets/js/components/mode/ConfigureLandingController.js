import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { getLayersForShell, updateOperatorRootStep } from "./DrillDownShared";

export class ConfigureLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		this.layerRequests = {};
		this.selectedZone = null;
		this.defaultLayerState = {};
		this.diagnosisStale = false;

		this.bindDrillDownHandlers();
		this.bindSaveHandlers();
		this.initializeCurrentRoot();
		this.bindHealthyZoneToggle();
	}

	bindDrillDownHandlers() {
		if ( this.hasBoundDrillHandlers ) {
			return;
		}
		this.hasBoundDrillHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-configure-landing="1"] [data-drill-target]',
			( item ) => this.handleDrillTargetClick( item ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-configure-landing="1"] [data-configure-retry]',
			( item ) => this.handleRetryClick( item ),
			false
		);
		document.addEventListener( 'shield:drill-back', ( evt ) => this.handleDrillBack( evt ) );
	}

	bindSaveHandlers() {
		if ( this.hasBoundSaveHandlers ) {
			return;
		}
		this.hasBoundSaveHandlers = true;

		document.addEventListener( 'shield:expansion-form-saved', ( evt ) => this.handleSettingsSaved( evt ) );
	}

	bindHealthyZoneToggle() {
		if ( this.hasBoundHealthyToggle ) {
			return;
		}
		this.hasBoundHealthyToggle = true;

		document.addEventListener( 'click', ( evt ) => {
			// Shared collapse contract: toggle + immediately-following content panel.
			const toggle = evt.target instanceof Element
				? evt.target.closest( '[data-configure-healthy-toggle="1"], [data-configure-healthy-settings-toggle="1"]' )
				: null;
			if ( toggle === null ) {
				return;
			}

			const root = this.rootEl || this.getConfigureRoot();
			if ( root === null || !root.contains( toggle ) ) {
				return;
			}

			toggle.classList.toggle( 'is-open' );
			const body = toggle.nextElementSibling;
			if ( body !== null ) {
				body.classList.toggle( 'is-open' );
			}
		} );
	}

	initializeCurrentRoot() {
		this.rootEl = this.getConfigureRoot();
		this.shellEl = this.getShell( this.rootEl );
		this.defaultLayerState = this.captureDefaultLayerState( this.shellEl );
	}

	getConfigureRoot() {
		return document.querySelector( '[data-configure-landing="1"]' );
	}

	getShell( root = this.rootEl ) {
		return root?.querySelector( '[data-drill-shell="1"]' ) || null;
	}

	handleDrillTargetClick( item ) {
		const root = this.rootEl || this.getConfigureRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		const shell = this.getShell( root );
		if ( shell === null ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = shell;

		switch ( String( item.dataset.drillTarget || '' ).trim() ) {
			case 'diagnosis':
				this.handleZoneSelection( item, shell );
				break;

			case 'editor':
				this.handleEditorSelection( item, shell );
				break;
		}
	}

	handleZoneSelection( item, shell ) {
		const zone = this.readZoneSelection( item );
		if ( zone.key.length < 1 ) {
			return;
		}

		const diagnosisIndex = this.getLayerIndexByKey( shell, 'diagnosis' );
		const drillCtrl = this.getDrillDownController();
		if ( diagnosisIndex < 0 || drillCtrl === null ) {
			return;
		}

		this.selectedZone = zone;
		this.diagnosisStale = false;
		this.cancelLayerRequest( 'diagnosis' );
		this.cancelLayerRequest( 'editor' );
		this.resetEditorLayer( shell );
		drillCtrl.drillTo( shell, diagnosisIndex );
		drillCtrl.updateLayerHeader(
			shell,
			1,
			this.buildLoadingHeader( zone.header, this.getDiagnosisLoadingText() )
		);

		this.loadDiagnosisLayer();
	}

	handleEditorSelection( item, shell ) {
		const editorSelection = this.readEditorSelection( item );
		if ( editorSelection.key.length < 1 ) {
			return;
		}

		const editorIndex = this.getLayerIndexByKey( shell, 'editor' );
		const drillCtrl = this.getDrillDownController();
		if ( editorIndex < 0 || drillCtrl === null ) {
			return;
		}

		this.selectedZone = {
			...( this.selectedZone || {} ),
			key: editorSelection.key,
			label: editorSelection.label,
			status: editorSelection.status,
			editor_selection: editorSelection,
		};
		this.cancelLayerRequest( 'editor' );
		drillCtrl.drillTo( shell, editorIndex );
		drillCtrl.updateLayerHeader(
			shell,
			2,
			this.buildLoadingHeader( editorSelection.header, this.getEditorLoadingText() )
		);

		this.loadEditorLayer();
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

	loadEditorLayer( showPlaceholder = true ) {
		if ( this.selectedZone === null ) {
			return Promise.resolve( null );
		}

		return this.loadLayerContent(
			'editor',
			this.buildRenderAction( this.rootEl?.dataset.configureEditorAction, {
				zone: this.selectedZone.key,
			} ),
			showPlaceholder,
			this.getEditorLoadingText(),
			( data ) => this.applyEditorLayerResponse( data )
		);
	}

	loadLayerContent( layerKey, renderAction, showPlaceholder, loadingText, onSuccess ) {
		if ( this.shellEl === null || ObjectOps.IsEmpty( renderAction ) ) {
			return Promise.resolve( null );
		}

		const layer = this.getLayerByKey( this.shellEl, layerKey );
		const body = layer?.querySelector( '.drill-layer__body' ) || null;
		if ( layer === null || body === null ) {
			return Promise.resolve( null );
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		this.layerRequests[ layerKey ] = requestKey;

		if ( showPlaceholder ) {
			BootstrapTooltips.DisposeTooltipsWithin( body );
			body.innerHTML = this.buildLoadingMarkup( loadingText );
		}

		return ( new AjaxService() )
			.send( renderAction, false, true )
			.then( ( resp ) => {
				if ( this.layerRequests[ layerKey ] !== requestKey ) {
					return null;
				}

				if ( !resp.success || typeof resp?.data?.html !== 'string' ) {
					this.renderLayerFailure( body, layerKey );
					return null;
				}

				this.applyLayerHtml( body, resp.data.html );
				onSuccess( resp.data );
				return resp.data;
			} )
			.catch( () => {
				if ( this.layerRequests[ layerKey ] === requestKey ) {
					this.renderLayerFailure( body, layerKey );
				}
				return null;
			} )
			.finally( () => {
				if ( this.layerRequests[ layerKey ] === requestKey ) {
					delete this.layerRequests[ layerKey ];
				}
			} );
	}

	applyDiagnosisLayerResponse( data ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		const zoneSelection = this.readSelectionPayload( data.zone_selection );
		const editorSelection = this.readSelectionPayload( data.editor_selection );
		this.selectedZone = {
			...( this.selectedZone || {} ),
			...zoneSelection,
			editor_selection: editorSelection,
		};
		drillCtrl.updateLayerHeader( this.shellEl, 1, data.header || zoneSelection.header );
		this.applyLandingRefresh( data.landing_refresh || null );
		this.reinitializeExpandLoader();
	}

	applyEditorLayerResponse( data ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		const editorSelection = this.readSelectionPayload( data.editor_selection );
		if ( editorSelection.key.length > 0 ) {
			this.selectedZone = {
				...( this.selectedZone || {} ),
				editor_selection: editorSelection,
			};
		}
		drillCtrl.updateLayerHeader( this.shellEl, 2, data.header || editorSelection.header );
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

	updateOperatorRootStep( rootStepJson ) {
		updateOperatorRootStep( this.rootEl, rootStepJson );
	}

	handleRetryClick( item ) {
		const root = this.rootEl || this.getConfigureRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );

		switch ( String( item.dataset.configureRetry || '' ).trim() ) {
			case 'diagnosis':
				this.loadDiagnosisLayer();
				break;

			case 'editor':
				this.loadEditorLayer();
				break;
		}
	}

	handleDrillBack( evt ) {
		const root = this.rootEl || this.getConfigureRoot();
		const shell = evt.target;
		if ( root === null || !( shell instanceof HTMLElement ) || !root.contains( shell ) ) {
			return;
		}

		const layerIndex = this.parseInteger( evt.detail?.layer_index );
		if ( layerIndex <= 0 ) {
			this.cancelLayerRequest( 'editor' );
			this.resetEditorLayer( shell );
			if ( this.diagnosisStale && this.selectedZone !== null ) {
				this.loadDiagnosisLayer( {
					showPlaceholder: false,
					includeLandingRefresh: true,
				} ).then( ( data ) => {
					if ( data !== null ) {
						this.diagnosisStale = false;
					}
				} );
			}
			return;
		}

		if ( layerIndex === 1 ) {
			this.cancelLayerRequest( 'editor' );
			if ( this.diagnosisStale ) {
				this.loadDiagnosisLayer( { showPlaceholder: false } ).then( ( data ) => {
					if ( data !== null ) {
						this.diagnosisStale = false;
					}
				} );
			}
		}
	}

	handleSettingsSaved( evt ) {
		const root = this.rootEl || this.getConfigureRoot();
		const target = evt.target instanceof HTMLElement ? evt.target : null;
		if ( root === null || target === null || this.selectedZone === null || !root.contains( target ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.diagnosisStale = true;
		this.loadDiagnosisLayer( {
			showPlaceholder: false,
			includeLandingRefresh: true,
		} ).then( ( data ) => {
			if ( data !== null ) {
				this.diagnosisStale = false;
			}
		} );
	}

	cancelLayerRequest( layerKey ) {
		if ( this.layerRequests[ layerKey ] !== undefined ) {
			this.layerRequests[ layerKey ] = `cancelled-${Date.now()}`;
		}
	}

	resetEditorLayer( shell ) {
		const defaultState = this.defaultLayerState.editor;
		const drillCtrl = this.getDrillDownController();
		if ( defaultState === undefined || drillCtrl === null ) {
			return;
		}

		const editorLayer = this.getLayerByKey( shell, 'editor' );
		const editorBody = editorLayer?.querySelector( '.drill-layer__body' ) || null;
		if ( editorBody !== null ) {
			BootstrapTooltips.DisposeTooltipsWithin( editorBody );
			editorBody.innerHTML = '';
		}

		drillCtrl.updateLayerHeader( shell, 2, defaultState.header );
	}

	captureDefaultLayerState( shell ) {
		if ( shell === null ) {
			return {};
		}

		return {
			diagnosis: this.readLayerState( shell, 'diagnosis' ),
			editor: this.readLayerState( shell, 'editor' ),
		};
	}

	readLayerState( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		return {
			header: this.parseJsonDataset( layer?.dataset.drillLayerHeader || '{}' ),
		};
	}

	readZoneSelection( item ) {
		return this.readSelectionPayload( this.parseJsonDataset( item.dataset.drillZoneSelection ) );
	}

	readEditorSelection( item ) {
		return this.readSelectionPayload( this.parseJsonDataset( item.dataset.drillEditorSelection ) );
	}

	readSelectionPayload( selection ) {
		return {
			key: String( selection?.key || '' ).trim(),
			label: String( selection?.label || '' ).trim(),
			status: String( selection?.status || 'neutral' ).trim(),
			icon_class: String( selection?.icon_class || '' ).trim(),
			header: selection?.header || {},
		};
	}

	buildLoadingHeader( header, loadingText ) {
		return {
			...( header && typeof header === 'object' ? header : {} ),
			summary: String( loadingText || '' ).trim(),
		};
	}

	getDiagnosisLoadingText() {
		return this.rootEl?.dataset.configureDiagnosisLoading || '';
	}

	getEditorLoadingText() {
		return this.rootEl?.dataset.configureEditorLoading || '';
	}

	getLayerByKey( shell, layerKey ) {
		return getLayersForShell( shell )
			.find( ( layer ) => String( layer.dataset.drillLayerKey || '' ).trim() === layerKey ) || null;
	}

	getLayerIndexByKey( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		return layer === null ? -1 : this.parseInteger( layer.dataset.drillLayer );
	}

	getDrillDownController() {
		return window.shieldAppMain?.components?.drill_down || null;
	}

	buildRenderAction( source, extraData ) {
		const action = this.parseJsonDataset( source );
		if ( ObjectOps.IsEmpty( action ) ) {
			return {};
		}

		return {
			...action,
			...extraData,
		};
	}

	applyLayerHtml( body, html ) {
		BootstrapTooltips.DisposeTooltipsWithin( body );
		body.innerHTML = html;
		UiContentActivator.activateCurrentSubtree( body );
	}

	renderLayerFailure( body, layerKey ) {
		const message = this.rootEl?.dataset.configureLayerError || '';
		const retry = this.rootEl?.dataset.configureLayerRetry || '';

		BootstrapTooltips.DisposeTooltipsWithin( body );
		body.innerHTML = `<div class="configure-landing__empty-state"><div>${this.escapeHtml( message )}</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-configure-retry="${this.escapeHtml( layerKey )}">${this.escapeHtml( retry )}</button></div>`;
	}

	buildLoadingMarkup( message ) {
		return `<div class="text-muted small">${this.escapeHtml( message )}</div>`;
	}

	parseJsonDataset( value = '{}' ) {
		try {
			return JSON.parse( value );
		}
		catch ( e ) {
			return {};
		}
	}

	parseInteger( value ) {
		const parsed = parseInt( String( value ?? '0' ), 10 );
		return Number.isNaN( parsed ) ? 0 : parsed;
	}

	reinitializeExpandLoader() {
		const app = globalThis.shieldAppMain || null;
		if ( !app || typeof app.getComponent !== 'function' ) {
			return;
		}

		app.getComponent( 'configure_expand_loader' )?.initializeCurrentRoot?.();
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
