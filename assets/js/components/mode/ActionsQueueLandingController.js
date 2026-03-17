import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { getLayersForShell } from "./DrillDownShared";

export class ActionsQueueLandingController extends BaseAutoExecComponent {

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

	bindTableActionHandlers() {
		if ( this.hasBoundTableActionHandlers ) {
			return;
		}
		this.hasBoundTableActionHandlers = true;

		document.addEventListener( 'shield:table-action-success', ( evt ) => this.handleTableActionSuccess( evt ) );
	}

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		this.shellEl = this.getShell( this.rootEl );
		this.defaultLayerState = this.captureDefaultLayerState( this.shellEl );
	}

	getRoot() {
		return document.querySelector( '[data-actions-landing="1"]' );
	}

	getShell( root = this.rootEl ) {
		return root?.querySelector( '[data-drill-shell="1"]' ) || null;
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
		this.resetGroupsLayerStrip( shell );
		this.resetDetailLayer( shell );
		drillCtrl.updateStripText( shell, 0, bucket.strip_text );
		drillCtrl.updateStripBadge( shell, 0, bucket.strip_badge, bucket.status );
		drillCtrl.drillTo( shell, layerIndex );
		drillCtrl.updateLayerContext(
			shell,
			1,
			this.buildLoadingContext( bucket.context, this.getGroupsLoadingText() )
		);

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
		drillCtrl.updateStripText( shell, 1, group.strip_text );
		drillCtrl.updateStripBadge( shell, 1, group.strip_badge, group.status );
		drillCtrl.drillTo( shell, layerIndex );
		drillCtrl.updateLayerContext(
			shell,
			2,
			this.buildLoadingContext( group.context, this.getDetailLoadingText() )
		);

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
		if ( this.selectedBucket === null || this.selectedGroup === null ) {
			return Promise.resolve( null );
		}

		return this.loadLayerContent(
			'detail',
			this.buildRenderAction( this.rootEl?.dataset.actionsQueueDetailAction, {
				bucket: this.selectedBucket.key,
				group: this.selectedGroup.key,
			} ),
			showPlaceholder,
			this.getDetailLoadingText(),
			( data ) => this.applyDetailLayerResponse( data )
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

	applyGroupsLayerResponse( data ) {
		const queueIsEmpty = this.applyLandingRefresh( data.landing_refresh || null );
		if ( queueIsEmpty ) {
			return;
		}

		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		this.selectedBucket = this.readBucketSelectionPayload( data.render_data?.bucket_selection );
		drillCtrl.updateStripText( this.shellEl, 0, this.selectedBucket.strip_text );
		drillCtrl.updateStripBadge(
			this.shellEl,
			0,
			this.selectedBucket.strip_badge,
			this.selectedBucket.status
		);
		drillCtrl.updateLayerContext( this.shellEl, 1, this.selectedBucket.context );

		if ( !ObjectOps.IsEmpty( data.selected_group || {} ) ) {
			this.applySelectedGroupRefresh( data.selected_group );
		}
	}

	applyDetailLayerResponse( data ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		this.selectedGroup = this.readGroupSelectionPayload( data.render_data?.group_selection );
		drillCtrl.updateStripText( this.shellEl, 1, this.selectedGroup.strip_text );
		drillCtrl.updateStripBadge(
			this.shellEl,
			1,
			this.selectedGroup.strip_badge,
			this.selectedGroup.status
		);
		drillCtrl.updateLayerContext( this.shellEl, 2, this.selectedGroup.context );
	}

	applySelectedGroupRefresh( selectedGroup ) {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null ) {
			return;
		}

		this.selectedGroup = this.readGroupSelectionPayload( selectedGroup );
		drillCtrl.updateStripText( this.shellEl, 1, this.selectedGroup.strip_text );
		drillCtrl.updateStripBadge( this.shellEl, 1, this.selectedGroup.strip_badge, this.selectedGroup.status );
		drillCtrl.updateLayerContext( this.shellEl, 2, this.selectedGroup.context );
	}

	applyLandingRefresh( landingRefresh ) {
		if ( ObjectOps.IsEmpty( landingRefresh || {} ) || this.rootEl === null ) {
			return false;
		}

		const severityStrip = this.rootEl.querySelector( '[data-actions-queue-section="severity-strip"]' );
		if ( severityStrip !== null && typeof landingRefresh.severity_strip_html === 'string' ) {
			severityStrip.outerHTML = landingRefresh.severity_strip_html;
		}

		if ( landingRefresh.queue_is_empty ) {
			const drilldown = this.rootEl.querySelector( '[data-actions-queue-section="drilldown"]' );
			if ( drilldown !== null && typeof landingRefresh.all_clear_html === 'string' ) {
				drilldown.outerHTML = landingRefresh.all_clear_html;
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
					BootstrapTooltips.DisposeTooltipsWithin( content );
					content.innerHTML = resp.data.html;
					panel.dataset.actionsQueueAssetPanelLoaded = '1';
					UiContentActivator.activateCurrentSubtree( panel );
					return;
				}

				panel.dataset.actionsQueueAssetPanelLoaded = '0';
				BootstrapTooltips.DisposeTooltipsWithin( content );
				content.innerHTML = `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>`;
			} )
			.catch( () => {
				if ( panel.dataset.actionsQueueAssetPanelRequest !== requestKey ) {
					return;
				}

				panel.dataset.actionsQueueAssetPanelLoaded = '0';
				BootstrapTooltips.DisposeTooltipsWithin( content );
				content.innerHTML = `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>`;
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

	refreshAfterNestedAction( reloadDetail ) {
		const openAssetPanelTarget = reloadDetail
			? this.getOpenAssetPanelTarget()
			: '';

		return this.loadGroupsLayer( {
			showPlaceholder: false,
			includeSelectedGroup: this.selectedGroup !== null,
			includeLandingRefresh: true,
		} ).then( ( data ) => {
			if ( data === null || data?.landing_refresh?.queue_is_empty || !reloadDetail || this.selectedGroup === null ) {
				return data;
			}

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

	cancelLayerRequest( layerKey ) {
		if ( this.layerRequests[ layerKey ] !== undefined ) {
			this.layerRequests[ layerKey ] = `cancelled-${Date.now()}`;
		}
	}

	resetGroupsLayerStrip( shell ) {
		const defaultState = this.defaultLayerState.groups;
		const drillCtrl = this.getDrillDownController();
		if ( defaultState === undefined || drillCtrl === null ) {
			return;
		}

		drillCtrl.updateStripText( shell, 1, defaultState.text );
		drillCtrl.updateStripBadge( shell, 1, defaultState.badge, defaultState.status );
		drillCtrl.updateLayerContext( shell, 1, defaultState.context );
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

		drillCtrl.updateStripText( shell, 2, defaultState.text );
		drillCtrl.updateStripBadge( shell, 2, defaultState.badge, defaultState.status );
		drillCtrl.updateLayerContext( shell, 2, defaultState.context );
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
		const strip = layer?.querySelector( '[data-drill-strip="1"]' ) || null;
		const title = strip?.querySelector( '.drill-strip__title' ) || null;
		const badge = strip?.querySelector( '.shield-badge' ) || null;

		return {
			text: title?.textContent || '',
			badge: badge?.textContent || '',
			status: this.readBadgeStatus( badge ),
			context: this.parseJsonDataset( layer?.dataset.drillLayerContext || '{}' ),
		};
	}

	readBadgeStatus( badge ) {
		if ( !( badge instanceof HTMLElement ) ) {
			return 'neutral';
		}

		const statusClass = [ ...badge.classList ]
			.find( ( className ) => className.startsWith( 'badge-' ) );

		return statusClass ? statusClass.replace( 'badge-', '' ) : 'neutral';
	}

	readBucketSelection( item ) {
		return this.readBucketSelectionPayload( this.parseJsonDataset( item.dataset.drillBucketSelection ) );
	}

	readGroupSelection( item ) {
		return this.readGroupSelectionPayload( this.parseJsonDataset( item.dataset.drillGroupSelection ) );
	}

	buildLoadingContext( context, loadingText ) {
		return {
			path: Array.isArray( context.path ) ? context.path : [],
			focus: String( context.focus || '' ).trim(),
			next_step: loadingText,
		};
	}

	getGroupsLoadingText() {
		return this.rootEl?.dataset.actionsGroupsLoading || '';
	}

	getDetailLoadingText() {
		return this.rootEl?.dataset.actionsDetailLoading || '';
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
		const message = this.rootEl?.dataset.actionsLayerError || '';
		const retry = this.rootEl?.dataset.actionsLayerRetry || '';

		BootstrapTooltips.DisposeTooltipsWithin( body );
		body.innerHTML = `<div class="actions-landing__empty-state"><div>${this.escapeHtml( message )}</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-actions-queue-retry="${this.escapeHtml( layerKey )}">${this.escapeHtml( retry )}</button></div>`;
	}

	buildLoadingMarkup( message ) {
		return `<div class="text-muted small" data-actions-queue-pane-placeholder="1">${this.escapeHtml( message )}</div>`;
	}

	parseJsonDataset( value = '{}' ) {
		try {
			return JSON.parse( value );
		}
		catch ( e ) {
			return {};
		}
	}

	readBucketSelectionPayload( selection ) {
		return {
			key: String( selection?.key || '' ).trim(),
			label: String( selection?.label || '' ).trim(),
			status: String( selection?.status || 'neutral' ).trim(),
			item_count: this.parseInteger( selection?.item_count ?? 0 ),
			strip_text: String( selection?.strip_text || '' ).trim(),
			strip_badge: String( selection?.strip_badge || '' ).trim(),
			context: selection?.context || {},
		};
	}

	readGroupSelectionPayload( selection ) {
		return {
			key: String( selection?.key || '' ).trim(),
			label: String( selection?.label || '' ).trim(),
			status: String( selection?.status || 'neutral' ).trim(),
			item_count: this.parseInteger( selection?.item_count ?? 0 ),
			detail_shell: String( selection?.detail_shell || 'direct_table' ).trim(),
			strip_text: String( selection?.strip_text || '' ).trim(),
			strip_badge: String( selection?.strip_badge || '' ).trim(),
			context: selection?.context || {},
		};
	}

	parseInteger( value ) {
		const parsed = parseInt( String( value ?? '0' ), 10 );
		return Number.isNaN( parsed ) ? 0 : parsed;
	}

	getErrorMessage() {
		return this.rootEl?.dataset.actionsPaneError || '';
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
