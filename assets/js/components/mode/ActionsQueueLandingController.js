import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { DrillDownAsyncControllerBase } from "./DrillDownAsyncControllerBase";
import { ShieldTableBase } from "../tables/ShieldTableBase";
import { confirmDialog } from "../ui/ShieldDialog";

export class ActionsQueueLandingController extends DrillDownAsyncControllerBase {

	canRun() {
		return true;
	}

	run() {
		this.layerRequests = {};
		this.selectedBucket = null;
		this.selectedGroup = null;
		this.defaultLayerState = {};

		this.bindModePanelHandlers();
		this.bindMaintenanceActionHandlers();
		this.bindOperatorContextActionHandlers();
		this.bindTableActionHandlers();
		this.bindDrillDownHandlers();
		this.initializeCurrentRoot();
	}

	bindDrillDownHandlers() {
		if ( this.hasBoundDrillDownHandlers ) {
			return;
		}
		this.hasBoundDrillDownHandlers = true;

		shieldEventsHandler_Main.add_Click(
			'[data-actions-landing="1"] [data-drill-target]',
			( item ) => this.handleDrillTargetClick( item ),
			false
		);
		shieldEventsHandler_Main.add_Click(
			'[data-actions-landing="1"] [data-actions-queue-retry]',
			( item ) => this.handleRetryClick( item ),
			false
		);
		document.addEventListener( 'shield:drill-back', ( evt ) => this.handleDrillBack( evt ) );
	}

	bindModePanelHandlers() {
		if ( this.hasBoundModePanelHandlers ) {
			return;
		}
		this.hasBoundModePanelHandlers = true;

		document.addEventListener( 'shield:mode-panel-opened', ( evt ) => this.handleModePanelOpened( evt ) );
		document.addEventListener( 'shield:mode-panel-closed', ( evt ) => this.handleModePanelClosed( evt ) );
	}

	bindMaintenanceActionHandlers() {
		if ( this.hasBoundMaintenanceActionHandlers ) {
			return;
		}
		this.hasBoundMaintenanceActionHandlers = true;

		document.addEventListener( 'click', ( evt ) => this.handleMaintenanceActionClick( evt ) );
	}

	bindOperatorContextActionHandlers() {
		if ( this.hasBoundOperatorContextActionHandlers ) {
			return;
		}
		this.hasBoundOperatorContextActionHandlers = true;

		document.addEventListener( 'click', ( evt ) => this.handleOperatorContextActionClick( evt ) );
	}

	bindTableActionHandlers() {
		if ( this.hasBoundTableActionHandlers ) {
			return;
		}
		this.hasBoundTableActionHandlers = true;

		document.addEventListener( 'shield:table-action-success', ( evt ) => this.handleTableActionSuccess( evt ) );
	}

	initializeCurrentRoot() {
		super.initializeCurrentRoot();
		this.defaultLayerState = this.captureDefaultLayerState( this.shellEl );
	}

	getRoot() {
		return document.querySelector( '[data-actions-landing="1"]' );
	}

	handleDrillTargetClick( item ) {
		const root = this.rootEl || this.getRoot();
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
			case 'groups':
				this.handleBucketSelection( item, shell );
				break;

			case 'detail':
				this.handleGroupSelection( item, shell );
				break;
		}
	}

	handleBucketSelection( item, shell ) {
		const bucket = this.readBucketSelection( item );
		if ( bucket.key.length < 1 ) {
			return;
		}

		const layerIndex = this.getLayerIndexByKey( shell, 'groups' );
		const drillCtrl = this.getDrillDownController();
		if ( layerIndex < 0 || drillCtrl === null ) {
			return;
		}

		this.selectedBucket = bucket;
		this.selectedGroup = null;
		this.resetGroupsLayerHeader( shell );
		this.resetDetailLayer( shell );
		drillCtrl.updateLayerHeader(
			shell,
			1,
			this.buildLoadingHeader( bucket.header, this.getGroupsLoadingText() ),
			{ announce: false }
		);
		drillCtrl.drillTo( shell, layerIndex, { sourceEl: item } );

		this.loadGroupsLayer();
	}

	handleGroupSelection( item, shell ) {
		const group = this.readGroupSelection( item );
		if ( group.key.length < 1 ) {
			return;
		}

		const bucket = this.readBucketSelection( item );
		if ( bucket.key.length > 0 ) {
			this.selectedBucket = bucket;
		}

		const layerIndex = this.getLayerIndexByKey( shell, 'detail' );
		const drillCtrl = this.getDrillDownController();
		if ( layerIndex < 0 || drillCtrl === null || this.selectedBucket === null ) {
			return;
		}

		this.selectedGroup = group;
		drillCtrl.updateLayerHeader(
			shell,
			2,
			this.buildLoadingHeader( group.header, this.getDetailLoadingText() ),
			{ announce: false }
		);
		drillCtrl.drillTo( shell, layerIndex, { sourceEl: item } );

		this.loadDetailLayer();
	}

	loadGroupsLayer( { showPlaceholder = true, includeSelectedGroup = false, includeLandingRefresh = false } = {} ) {
		if ( this.selectedBucket === null ) {
			return Promise.resolve( null );
		}

		const extraData = {
			bucket: this.selectedBucket.key,
		};
		if ( includeSelectedGroup && this.selectedGroup !== null ) {
			extraData.group = this.selectedGroup.key;
		}
		if ( includeLandingRefresh ) {
			extraData.include_landing_refresh = 1;
		}

		return this.loadLayerContent(
			'groups',
			this.buildRenderAction( this.rootEl?.dataset.actionsQueueGroupsAction, extraData ),
			showPlaceholder,
			this.getGroupsLoadingText(),
			( data ) => this.applyGroupsLayerResponse( data )
		);
	}

	loadDetailLayer( showPlaceholder = true ) {
		if ( this.selectedGroup === null || this.shellEl === null ) {
			return Promise.resolve( null );
		}

		const renderAction = this.selectedGroup.detail_render_action;
		const layer = this.getLayerByKey( this.shellEl, 'detail' );
		const body = layer?.querySelector( '.drill-layer__body' ) || null;
		if ( layer === null || body === null ) {
			return Promise.resolve( null );
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		this.layerRequests.detail = requestKey;

		if ( showPlaceholder ) {
			this.replaceLayerBodyHtml( body, this.buildLoadingMarkup( this.getDetailLoadingText() ) );
		}
		this.setLayerBusy( layer, true, this.getDetailLoadingText() );

		return ( new AjaxService() )
			.send( renderAction, false, true )
			.then( ( resp ) => {
				if ( this.layerRequests.detail !== requestKey ) {
					return null;
				}

				if ( !resp?.success || typeof resp?.data?.html !== 'string' ) {
					this.renderLayerFailure( body, 'detail' );
					this.announceLayerMessage( layer, this.getLayerFailureText( 'detail' ) );
					return null;
				}

				this.applyLayerHtml( body, this.buildDetailLayerHtml( resp.data.html ) );
				this.applyDetailLayerResponse();
				return resp.data;
			} )
			.catch( () => {
				if ( this.layerRequests.detail === requestKey ) {
					this.renderLayerFailure( body, 'detail' );
					this.announceLayerMessage( layer, this.getLayerFailureText( 'detail' ) );
				}
				return null;
			} )
			.finally( () => {
				if ( this.layerRequests.detail === requestKey ) {
					this.setLayerBusy( layer, false );
					delete this.layerRequests.detail;
				}
			} );
	}

	applyGroupsLayerResponse( data ) {
		const shouldAbortLayerRefresh = this.applyLandingRefresh( data.landing_refresh || null );
		if ( shouldAbortLayerRefresh ) {
			return;
		}

		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		this.selectedBucket = this.readBucketSelectionPayload( data.bucket_selection );
		drillCtrl.updateLayerHeader( this.shellEl, 1, data.header || this.selectedBucket.header );

		if ( !ObjectOps.IsEmpty( data.selected_group || {} ) ) {
			this.applySelectedGroupRefresh( data.selected_group );
		}
	}

	applyDetailLayerResponse() {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null || this.selectedGroup === null ) {
			return;
		}

		drillCtrl.updateLayerHeader( this.shellEl, 2, this.selectedGroup.header );
	}

	applySelectedGroupRefresh( selectedGroup ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		this.selectedGroup = this.readGroupSelectionPayload( selectedGroup );
		drillCtrl.updateLayerHeader( this.shellEl, 2, this.selectedGroup.header );
	}

	applyLandingRefresh( landingRefresh ) {
		if ( ObjectOps.IsEmpty( landingRefresh || {} ) || this.rootEl === null ) {
			return false;
		}

		this.updateOperatorRootStep( landingRefresh.root_step_json || '' );

		if ( landingRefresh.has_drilldown_content === false ) {
			const drilldown = this.rootEl.querySelector( '[data-actions-queue-section="drilldown"]' );
			if ( drilldown !== null ) {
				drilldown.remove();
			}
			this.selectedBucket = null;
			this.selectedGroup = null;
			this.cancelLayerRequest( 'groups' );
			this.cancelLayerRequest( 'detail' );
			this.initializeCurrentRoot();
			return true;
		}

		if ( this.shellEl !== null && typeof landingRefresh.buckets_html === 'string' ) {
			const bucketsLayer = this.getLayerByKey( this.shellEl, 'buckets' );
			const bucketsBody = bucketsLayer?.querySelector( '.drill-layer__body' ) || null;
			if ( bucketsBody !== null ) {
				this.applyLayerHtml( bucketsBody, landingRefresh.buckets_html );
			}
		}

		return false;
	}

	handleRetryClick( item ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );

		switch ( String( item.dataset.actionsQueueRetry || '' ).trim() ) {
			case 'groups':
				this.loadGroupsLayer();
				break;

			case 'detail':
				this.loadDetailLayer();
				break;
		}
	}

	handleDrillBack( evt ) {
		const root = this.rootEl || this.getRoot();
		const shell = evt.target;
		if ( root === null || !( shell instanceof HTMLElement ) || !root.contains( shell ) ) {
			return;
		}

		const layerIndex = parseInt( String( evt.detail?.layer_index ?? -1 ), 10 );
		if ( layerIndex <= 0 ) {
			this.selectedBucket = null;
			this.selectedGroup = null;
			this.cancelLayerRequest( 'groups' );
			this.cancelLayerRequest( 'detail' );
			return;
		}

		if ( layerIndex === 1 ) {
			this.selectedGroup = null;
			this.cancelLayerRequest( 'detail' );
		}
	}

	handleTableActionSuccess( evt ) {
		const root = this.rootEl || this.getRoot();
		const eventTarget = evt.target instanceof HTMLElement ? evt.target : null;
		if ( root === null || eventTarget === null || this.selectedBucket === null || !root.contains( eventTarget ) ) {
			return;
		}

		const detailContainer = eventTarget.closest( '[data-actions-queue-detail="1"]' );
		if ( detailContainer === null ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.pendingResultsDisplayOptions = this.captureResultsDisplayOptionsFromTarget( eventTarget );
		this.refreshAfterNestedAction( this.selectedGroup?.detail_shell === 'asset_cards' );
	}

	handleModePanelOpened( evt ) {
		if ( !this.isQueueAssetModeEvent( evt ) ) {
			return;
		}

		const shell = evt.target;
		this.setAssetHintVisible( shell, false );

		const panel = this.findModePanelByTarget( shell, String( evt.detail?.panel_target || '' ) );
		if ( panel === null ) {
			this.syncAssetHintVisibility( shell );
			return;
		}

		if ( this.isLazyAssetPanel( panel ) ) {
			if ( this.isLazyAssetPanelLoaded( panel ) ) {
				UiContentActivator.activateCurrentSubtree( panel );
				return;
			}
			this.loadLazyAssetPanel( panel );
			return;
		}

		UiContentActivator.activateCurrentSubtree( panel );
	}

	handleModePanelClosed( evt ) {
		if ( !this.isQueueAssetModeEvent( evt ) ) {
			return;
		}

		this.syncAssetHintVisibility( evt.target );
	}

	loadLazyAssetPanel( panel ) {
		if ( panel.dataset.actionsQueueAssetPanelLoading === '1'
			|| this.isLazyAssetPanelLoaded( panel ) ) {
			return;
		}

		const content = panel.querySelector( '[data-actions-queue-asset-panel-content="1"]' );
		const renderAction = this.parseJsonDataset( panel.dataset.actionsQueueAssetRenderAction );
		if ( content === null || ObjectOps.IsEmpty( renderAction ) ) {
			panel.dataset.actionsQueueAssetPanelLoaded = '1';
			UiContentActivator.activateCurrentSubtree( panel );
			return;
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		panel.dataset.actionsQueueAssetPanelLoading = '1';
		panel.dataset.actionsQueueAssetPanelRequest = requestKey;
		BootstrapTooltips.DisposeTooltipsWithin( content );
		content.innerHTML = this.buildLoadingMarkup( this.rootEl?.dataset.actionsPaneLoading || '' );

		( new AjaxService() )
			.send( renderAction, false, true )
			.then( ( resp ) => {
				if ( panel.dataset.actionsQueueAssetPanelRequest !== requestKey ) {
					return;
				}

				if ( resp.success && typeof resp?.data?.html === 'string' ) {
					this.replaceLayerBodyHtml( content, resp.data.html, true );
					panel.dataset.actionsQueueAssetPanelLoaded = '1';
					return;
				}

				panel.dataset.actionsQueueAssetPanelLoaded = '0';
				this.replaceLayerBodyHtml( content, `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>` );
			} )
			.catch( () => {
				if ( panel.dataset.actionsQueueAssetPanelRequest !== requestKey ) {
					return;
				}

				panel.dataset.actionsQueueAssetPanelLoaded = '0';
				this.replaceLayerBodyHtml( content, `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>` );
			} )
			.finally( () => {
				if ( panel.dataset.actionsQueueAssetPanelRequest === requestKey ) {
					delete panel.dataset.actionsQueueAssetPanelLoading;
					delete panel.dataset.actionsQueueAssetPanelRequest;
				}
			} );
	}

	syncAssetHintVisibility( shell ) {
		this.setAssetHintVisible(
			shell,
			shell.querySelector( '[data-mode-panel="1"].is-open' ) === null
		);
	}

	setAssetHintVisible( shell, isVisible ) {
		const hint = shell.querySelector( '[data-mode-landing-hint="1"]' );
		if ( hint === null ) {
			return;
		}
		hint.classList.toggle( 'd-none', !isVisible );
		hint.setAttribute( 'aria-hidden', isVisible ? 'false' : 'true' );
	}

	findModePanelByTarget( shell, target ) {
		return [ ...shell.querySelectorAll( '[data-mode-panel="1"]' ) ]
			.find( ( panel ) => this.getAssetPanelTarget( panel ) === target ) || null;
	}

	getAssetPanelTarget( panel ) {
		return ( panel.dataset.modePanelTargetDefault || panel.dataset.modePanelTarget || '' ).trim();
	}

	isQueueAssetModeEvent( evt ) {
		const root = this.rootEl || this.getRoot();
		const shell = evt?.target;
		return root !== null
			&& shell instanceof HTMLElement
			&& shell.dataset.mode === 'actions_queue_assets'
			&& root.contains( shell );
	}

	isLazyAssetPanel( panel ) {
		return panel.dataset.actionsQueueAssetPanelLazy === '1';
	}

	isLazyAssetPanelLoaded( panel ) {
		return panel.dataset.actionsQueueAssetPanelLoaded === '1';
	}

	handleMaintenanceActionClick( evt ) {
		const target = evt.target instanceof Element
			? evt.target.closest( '[data-actions-queue-maintenance-action]' )
			: null;
		if ( target === null ) {
			return;
		}

		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( target ) ) {
			return;
		}

		evt.preventDefault();
		this.rootEl = root;
		this.shellEl = this.getShell( root );
		BootstrapTooltips.HideAndDisposeTooltip( target );

		const actionData = this.parseJsonDataset( target.dataset.actionsQueueMaintenanceAction );
		if ( ObjectOps.IsEmpty( actionData ) ) {
			return;
		}

		( new AjaxService() )
			.send( actionData )
			.then( ( resp ) => {
				if ( !resp?.success ) {
					return;
				}

				return this.refreshAfterNestedAction( true );
			} )
			.catch( () => null );
	}

	async handleOperatorContextActionClick( evt ) {
		const target = evt.target instanceof Element
			? evt.target.closest( '[data-operator-context-action-ajax="1"]' )
			: null;
		if ( target === null ) {
			return;
		}

		const root = this.resolveOperatorContextRoot( target );
		if ( root === null ) {
			return;
		}

		evt.preventDefault();
		this.rootEl = root;
		this.shellEl = this.getShell( root );
		BootstrapTooltips.HideAndDisposeTooltip( target );

		const actionData = this.parseJsonDataset( target.dataset.operatorContextActionJson );
		if ( ObjectOps.IsEmpty( actionData ) ) {
			return;
		}

		const confirmText = String( target.dataset.operatorContextActionConfirm || '' ).trim();
		if ( confirmText.length > 0 ) {
			const confirmed = await confirmDialog( {
				title: shieldStrings.string( 'confirm_title' ),
				message: confirmText,
				confirmLabel: String( target.textContent || '' ).trim() || shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: target,
			} );
			if ( !confirmed ) {
				return;
			}
		}

		const busyTable = this.getCurrentDirectTable();
		this.setDirectTableBusy( busyTable, true );

		( new AjaxService() )
			.send( actionData )
			.then( ( resp ) => {
				if ( !resp?.success ) {
					this.setDirectTableBusy( busyTable, false );
					return null;
				}
				if ( resp?.data?.page_reload ) {
					return resp;
				}

				return this.refreshAfterNestedAction( true ).then( ( refreshResult ) => {
					if ( refreshResult === null ) {
						this.setDirectTableBusy( busyTable, false );
					}
					return refreshResult;
				} );
			} )
			.catch( () => {
				this.setDirectTableBusy( busyTable, false );
				return null;
			} );
	}

	refreshAfterNestedAction( reloadDetail ) {
		const openAssetPanelTarget = reloadDetail
			? this.getOpenAssetPanelTarget()
			: '';
		const resultsDisplayOptions = this.consumePendingResultsDisplayOptions();

		return this.loadGroupsLayer( {
			showPlaceholder: false,
			includeSelectedGroup: this.selectedGroup !== null,
			includeLandingRefresh: true,
		} ).then( ( data ) => {
			if ( data === null || data?.landing_refresh?.has_drilldown_content === false || !reloadDetail || this.selectedGroup === null ) {
				return data;
			}

			this.mergeSelectedGroupResultsDisplayOptions( resultsDisplayOptions );
			return this.loadDetailLayer( false ).then( ( detailData ) => {
				if ( openAssetPanelTarget.length > 0 ) {
					this.restoreOpenAssetPanel( openAssetPanelTarget );
				}
				return detailData || data;
			} );
		} );
	}

	refreshCurrentDetailLayer( restoreAssetPanel ) {
		const openAssetPanelTarget = restoreAssetPanel
			? this.getOpenAssetPanelTarget()
			: '';
		const resultsDisplayOptions = this.captureCurrentDetailResultsDisplayOptions();

		return this.loadGroupsLayer( {
			showPlaceholder: false,
			includeSelectedGroup: this.selectedGroup !== null,
			includeLandingRefresh: false,
		} ).then( ( data ) => {
			if ( data === null || this.selectedGroup === null ) {
				return data;
			}

			this.mergeSelectedGroupResultsDisplayOptions( resultsDisplayOptions );
			return this.loadDetailLayer( false ).then( ( detailData ) => {
				if ( openAssetPanelTarget.length > 0 ) {
					this.restoreOpenAssetPanel( openAssetPanelTarget );
				}
				return detailData || data;
			} );
		} );
	}

	getOpenAssetPanelTarget() {
		const assetShell = this.rootEl?.querySelector( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) || null;
		const openPanel = assetShell?.querySelector( '[data-mode-panel="1"].is-open' ) || null;
		return openPanel instanceof HTMLElement
			? this.getAssetPanelTarget( openPanel )
			: '';
	}

	restoreOpenAssetPanel( panelTarget ) {
		if ( panelTarget.length < 1 ) {
			return;
		}

		const trigger = [ ...( this.rootEl?.querySelectorAll( '[data-mode-tile="1"]' ) || [] ) ]
			.find( ( item ) => String( item.dataset.modePanelTarget || '' ).trim() === panelTarget ) || null;
		if ( trigger instanceof HTMLElement ) {
			trigger.click();
		}
	}

	resolveOperatorContextRoot( target ) {
		const root = this.rootEl || this.getRoot();
		const operatorShell = this.getOperatorShellForRoot( root );
		return root !== null && operatorShell !== null && operatorShell.contains( target )
			? root
			: null;
	}

	getOperatorShellForRoot( root = this.rootEl || this.getRoot() ) {
		const operatorShell = root?.closest( '[data-mode-shell="1"][data-operator-chrome="1"]' ) || null;
		return operatorShell instanceof HTMLElement
			? operatorShell
			: null;
	}

	getCurrentDirectTable() {
		const table = this.rootEl
			?.querySelector( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ) || null;
		return table instanceof HTMLTableElement
			? table
			: null;
	}

	setDirectTableBusy( tableEl, isBusy ) {
		return ShieldTableBase.setBusyForTableElement( tableEl, isBusy );
	}

	captureResultsDisplayOptionsFromTarget( target ) {
		const table = target instanceof Element
			? target.closest( '[data-scan-results-table="1"]' )
			: null;
		return table instanceof HTMLTableElement
			? this.parseResultsDisplayOptionsFromTable( table )
			: null;
	}

	captureCurrentDetailResultsDisplayOptions() {
		const detailRoot = this.rootEl?.querySelector( '[data-actions-queue-detail="1"]' ) || null;
		if ( !( detailRoot instanceof HTMLElement ) ) {
			return null;
		}

		const tables = detailRoot.querySelectorAll( '[data-scan-results-table="1"]' );
		for ( const table of tables ) {
			if ( table instanceof HTMLTableElement ) {
				const resultsDisplayOptions = this.parseResultsDisplayOptionsFromTable( table );
				if ( !ObjectOps.IsEmpty( resultsDisplayOptions || {} ) ) {
					return resultsDisplayOptions;
				}
			}
		}

		return null;
	}

	parseResultsDisplayOptionsFromTable( table ) {
		const resultsDisplayOptions = this.parseJsonDataset( table.dataset.resultsDisplayOptions || '{}' );
		return ObjectOps.IsEmpty( resultsDisplayOptions )
			? null
			: resultsDisplayOptions;
	}

	consumePendingResultsDisplayOptions() {
		const resultsDisplayOptions = this.pendingResultsDisplayOptions ?? this.captureCurrentDetailResultsDisplayOptions();
		delete this.pendingResultsDisplayOptions;
		return resultsDisplayOptions;
	}

	mergeSelectedGroupResultsDisplayOptions( resultsDisplayOptions ) {
		if ( this.selectedGroup === null || ObjectOps.IsEmpty( resultsDisplayOptions || {} ) ) {
			return;
		}

		const currentOptions = this.selectedGroup.detail_render_action?.results_display_options || {};
		if (
			this.isDefaultActiveOnlyResultsDisplayOptions( resultsDisplayOptions )
			&& !ObjectOps.IsEmpty( currentOptions )
		) {
			return;
		}

		this.selectedGroup = {
			...this.selectedGroup,
			detail_render_action: {
				...( this.selectedGroup.detail_render_action || {} ),
				results_display_options: resultsDisplayOptions,
			},
		};
	}

	isDefaultActiveOnlyResultsDisplayOptions( resultsDisplayOptions ) {
		return resultsDisplayOptions?.include_ignored === false
			&& resultsDisplayOptions?.include_repaired === false
			&& resultsDisplayOptions?.include_deleted === false
			&& resultsDisplayOptions?.ignored_only === false;
	}

	resetGroupsLayerHeader( shell ) {
		const defaultState = this.defaultLayerState.groups;
		const drillCtrl = this.getDrillDownController();
		if ( defaultState === undefined || drillCtrl === null ) {
			return;
		}

		drillCtrl.updateLayerHeader( shell, 1, defaultState.header );
	}

	resetDetailLayer( shell ) {
		const defaultState = this.defaultLayerState.detail;
		const drillCtrl = this.getDrillDownController();
		if ( defaultState === undefined || drillCtrl === null ) {
			return;
		}

		const detailLayer = this.getLayerByKey( shell, 'detail' );
		const detailBody = detailLayer?.querySelector( '.drill-layer__body' ) || null;
		if ( detailBody !== null ) {
			BootstrapTooltips.DisposeTooltipsWithin( detailBody );
			detailBody.innerHTML = '';
		}

		drillCtrl.updateLayerHeader( shell, 2, defaultState.header );
	}

	captureDefaultLayerState( shell ) {
		if ( shell === null ) {
			return {};
		}

		return {
			groups: this.readLayerState( shell, 'groups' ),
			detail: this.readLayerState( shell, 'detail' ),
		};
	}

	readLayerState( shell, layerKey ) {
		const layer = this.getLayerByKey( shell, layerKey );
		return {
			header: this.parseJsonDataset( layer?.dataset.drillLayerHeader || '{}' ),
		};
	}

	readBucketSelection( item ) {
		return this.readBucketSelectionPayload( this.parseJsonDataset( item.dataset.drillBucketSelection ) );
	}

	readGroupSelection( item ) {
		return this.readGroupSelectionPayload( this.parseJsonDataset( item.dataset.drillGroupSelection ) );
	}

	getGroupsLoadingText() {
		return this.rootEl?.dataset.actionsGroupsLoading || '';
	}

	getDetailLoadingText() {
		return this.rootEl?.dataset.actionsDetailLoading || '';
	}

	renderLayerFailure( body, layerKey ) {
		const message = this.rootEl?.dataset.actionsLayerError || '';
		const retry = this.rootEl?.dataset.actionsLayerRetry || '';

		this.replaceLayerBodyHtml(
			body,
			`<div class="actions-landing__empty-state"><div>${this.escapeHtml( message )}</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-actions-queue-retry="${this.escapeHtml( layerKey )}">${this.escapeHtml( retry )}</button></div>`
		);
	}

	getLayerFailureText( layerKey ) {
		void layerKey;
		return this.rootEl?.dataset.actionsLayerError || '';
	}

	buildLoadingMarkup( message ) {
		return `<div class="text-muted small" data-actions-queue-pane-placeholder="1">${this.escapeHtml( message )}</div>`;
	}

	readBucketSelectionPayload( selection ) {
		return {
			...this.readCountedSelectionPayload( selection ),
		};
	}

	readGroupSelectionPayload( selection ) {
		return {
			...this.readCountedSelectionPayload( selection ),
			detail_shell: selection.detail_shell,
			detail_render_action: selection.detail_render_action,
		};
	}

	buildDetailLayerHtml( detailHtml ) {
		return `<div class="actions-queue-detail" data-actions-queue-detail="1"><div class="actions-queue-detail__body">${detailHtml}</div></div>`;
	}

	getErrorMessage() {
		return this.rootEl?.dataset.actionsPaneError || '';
	}
}
