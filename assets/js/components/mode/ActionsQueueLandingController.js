import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { DrillDownAsyncControllerBase } from "./DrillDownAsyncControllerBase";
import { announceStatus, focusElement } from "../ui/ShieldA11y";
import { getActiveLayerIndex, getLayerForShell, getLayersForShell } from "./DrillDownShared";

import { openProtectionEnableDialog } from '../scans/ProtectionEnableDialog';

export class ActionsQueueLandingController extends DrillDownAsyncControllerBase {

	canRun() {
		return true;
	}

	run() {
		this.layerRequests = {};
		this.selectedBucket = null;
		this.selectedGroup = null;
		this.refreshRequest = null;

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
			'[data-actions-landing="1"] [data-enable-protection]',
			async ( item ) => {
				if ( !( item instanceof HTMLElement ) ) return;
				const isFile = item.closest( '[data-actions-queue-asset-cards="1"]' ) !== null;
				if ( await openProtectionEnableDialog( JSON.parse( item.dataset.enableProtection ), item ) ) {
					await this.refreshAfterProtectionEnabled( item, isFile );
				}
			}, false
		);

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

		// Results opened from the queue sit over it: the close button, the backdrop and Escape return to the queue.
		shieldEventsHandler_Main.add_Click(
			'[data-actions-landing="1"] [data-results-overlay-close="1"]',
			( item ) => this.closeResultsOverlay( item.closest( '[data-drill-shell="1"]' ) ),
			false
		);
		document.addEventListener( 'click', ( evt ) => {
			// The backdrop belongs to the shell itself, so a click on it targets the shell.
			const shell = evt.target;
			if ( shell instanceof HTMLElement && shell.dataset.resultsOverlay === '1' ) {
				this.closeResultsOverlay( shell );
			}
		} );
		document.addEventListener( 'keydown', ( evt ) => {
			if ( evt.key !== 'Escape' || evt.defaultPrevented ) {
				return;
			}
			const shell = document.querySelector( '[data-actions-landing="1"] [data-drill-shell="1"][data-results-overlay="1"]' );
			const panel = shell?.querySelector( '[data-drill-layer-key="detail"]' );
			// Escape in a dialog opened from the results (a file viewer, a confirmation) closes only that dialog.
			const target = evt.target;
			if ( panel instanceof HTMLElement && ( target === document.body || ( target instanceof Node && panel.contains( target ) ) ) ) {
				evt.preventDefault();
				this.closeResultsOverlay( shell );
			}
		} );
	}

	/**
	 * Shows the results layer as a large panel over the queue rather than in its place, so the queue
	 * stays where it was underneath.
	 * @param {HTMLElement|null} shell
	 * @param {boolean} isOpen
	 */
	setResultsOverlay( shell, isOpen ) {
		if ( !( shell instanceof HTMLElement ) ) {
			return;
		}
		if ( isOpen ) {
			shell.dataset.resultsOverlay = '1';
		}
		else {
			delete shell.dataset.resultsOverlay;
		}
		const detail = shell.querySelector( '[data-drill-layer-key="detail"]' );
		if ( detail instanceof HTMLElement ) {
			detail.setAttribute( 'role', isOpen ? 'dialog' : 'region' );
			isOpen ? detail.setAttribute( 'aria-modal', 'true' ) : detail.removeAttribute( 'aria-modal' );

			if ( isOpen && detail.querySelector( ':scope > [data-results-overlay-close="1"]' ) === null ) {
				const close = document.createElement( 'button' );
				close.type = 'button';
				close.className = 'drill-layer__overlay-close';
				close.dataset.resultsOverlayClose = '1';
				close.setAttribute( 'aria-label', this.rootEl?.dataset.actionsResultsClose || '' );
				close.title = close.getAttribute( 'aria-label' );
				close.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
				detail.prepend( close );
			}
		}
		document.documentElement.classList.toggle( 'shield-results-overlay-open', isOpen );
	}

	/**
	 * @param {Element|null} shell
	 */
	closeResultsOverlay( shell ) {
		if ( shell instanceof HTMLElement && shell.dataset.resultsOverlay === '1' ) {
			this.getDrillDownController()?.drillTo( shell, 0 );
		}
	}

	async refreshAfterProtectionEnabled( launcher, isFile ) {
		this.adoptLandingBucket( launcher );
		await ( isFile
			? this.refreshAfterNestedAction( true )
			: this.refreshQueue() );

		// A user may have moved elsewhere while the closed dialog's refresh was pending.
		const focused = document.activeElement;
		if ( focused instanceof HTMLElement && focused !== document.body
			&& focused !== launcher && focused.isConnected ) {
			return;
		}
		const layers = getLayersForShell( this.shellEl );
		const activeLayer = getLayerForShell( this.shellEl, getActiveLayerIndex( layers ) );
		if ( !focusElement( activeLayer ) ) {
			focusElement( document.getElementById( 'PageContainer-Apto' ) );
		}
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
		document.addEventListener( 'shield:scan-results-changed', ( evt ) => this.handleScanResultsChanged( evt ) );
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

		if ( item.dataset.drillTarget === 'detail' ) {
			this.handleGroupSelection( item, shell );
		}
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

		// A group on the landing opens its results over the queue.
		this.setResultsOverlay( shell, item.closest( '[data-actions-queue-sections="1"]' ) !== null );

		drillCtrl.updateLayerHeader(
			shell,
			layerIndex,
			this.buildLoadingHeader( group.header, this.getDetailLoadingText() ),
			{ announce: false }
		);
		drillCtrl.drillTo( shell, layerIndex, { sourceEl: item } );

		this.loadDetailLayer();
	}

	/**
	 * After a change, the queue refreshes in place: its lanes, summary and sidebar counts, and the open group's
	 * heading. Only the latest refresh is applied.
	 * @return {Promise<Object|null>}
	 */
	refreshQueue( { includeSelectedGroup = false } = {} ) {
		const action = this.buildRenderAction( this.rootEl?.dataset.actionsQueueRefreshAction, {
			bucket: this.selectedBucket?.key || '',
			...( includeSelectedGroup && this.selectedGroup !== null ? { group: this.selectedGroup.key } : {} ),
		} );
		if ( this.selectedBucket === null || ObjectOps.IsEmpty( action ) ) {
			return Promise.resolve( null );
		}

		const requestKey = `${Date.now()}-${Math.random()}`;
		this.refreshRequest = requestKey;
		return ( new AjaxService() )
			.send( action, false, true )
			.then( ( resp ) => {
				if ( this.refreshRequest !== requestKey || resp?.success !== true ) {
					return null;
				}
				this.applyRefreshResponse( resp.data );
				return resp.data;
			} )
			.catch( () => null )
			.finally( () => {
				if ( this.refreshRequest === requestKey ) {
					this.refreshRequest = null;
				}
			} );
	}

	loadDetailLayer( showPlaceholder = true ) {
		if ( this.selectedGroup === null ) {
			return Promise.resolve( null );
		}

		return this.loadLayerContent(
			'detail',
			this.selectedGroup.detail_render_action,
			showPlaceholder,
			this.getDetailLoadingText(),
			() => this.applyDetailLayerResponse()
		);
	}

	/**
	 * @param {{landing_refresh:Object, selected_group?:Object}} data
	 */
	applyRefreshResponse( data ) {
		if ( this.applyLandingRefresh( data.landing_refresh ) ) {
			return;
		}
		if ( data.selected_group !== undefined ) {
			this.selectedGroup = this.readGroupSelectionPayload( data.selected_group );
			this.applyDetailLayerResponse();
		}
	}

	applyDetailLayerResponse() {
		const drillCtrl = this.getDrillDownController();
		if ( this.shellEl === null || drillCtrl === null || this.selectedGroup === null ) {
			return;
		}

		drillCtrl.updateLayerHeader( this.shellEl, this.getLayerIndexByKey( this.shellEl, 'detail' ), this.selectedGroup.header );
	}

	applyLandingRefresh( landingRefresh ) {
		if ( this.rootEl === null ) {
			return false;
		}

		this.updateOperatorRootStep( landingRefresh.root_step_json );
		this.updateSidebarCounts( landingRefresh );

		if ( landingRefresh.has_drilldown_content === false ) {
			const drilldown = this.rootEl.querySelector( '[data-actions-queue-section="drilldown"]' );
			if ( drilldown !== null ) {
				drilldown.remove();
			}
			this.selectedBucket = null;
			this.selectedGroup = null;
			this.refreshRequest = null;
			this.cancelLayerRequest( 'detail' );
			this.initializeCurrentRoot();
			return true;
		}

		if ( this.shellEl !== null ) {
			const bucketsLayer = this.getLayerByKey( this.shellEl, 'buckets' );
			const bucketsBody = bucketsLayer?.querySelector( '.drill-layer__body' ) || null;
			if ( bucketsBody !== null ) {
				// The landing is re-rendered after actions; keep the sections or tab that were open.
				const sectionsState = this.captureSectionsState( bucketsBody );
				this.applyLayerHtml( bucketsBody, landingRefresh.buckets_html );
				this.restoreSectionsState( bucketsBody, sectionsState );
			}
		}

		return false;
	}

	/**
	 * The sidebar's Actions Queue item shows the queue's counts and colour; they follow the queue as it changes.
	 * @param {{nav_status:string,nav_badges_html:string}} landingRefresh
	 */
	updateSidebarCounts( landingRefresh ) {
		const item = document.querySelector( '#NavSideBar a[data-mode="actions"]' );
		if ( !( item instanceof HTMLElement ) ) {
			return;
		}
		item.dataset.queueStatus = landingRefresh.nav_status;
		item.querySelector( '.mode-badges' )?.remove();
		item.querySelector( '.mode-label' ).insertAdjacentHTML( 'afterend', landingRefresh.nav_badges_html );
	}

	/**
	 * Which landing sections and folded boxes were open, and which tab was chosen.
	 * @param {Element} container
	 * @return {{open:string[],tab:string,boxes:string[]}}
	 */
	captureSectionsState( container ) {
		const sectionKey = ( el ) => {
			const section = el.closest( '[data-actions-queue-section-key]' );
			return section instanceof HTMLElement ? section.dataset.actionsQueueSectionKey || '' : '';
		};
		const tab = container.querySelector( '[data-actions-queue-tab][aria-selected="true"]' );
		return {
			open: [ ...container.querySelectorAll( '[data-actions-queue-section-toggle="1"][aria-expanded="true"]' ) ].map( sectionKey ),
			tab: tab instanceof HTMLElement ? tab.dataset.actionsQueueTab || '' : '',
			boxes: [ ...container.querySelectorAll( '[data-actions-queue-box-toggle][aria-expanded="true"]' ) ]
			.map( ( toggle ) => toggle instanceof HTMLElement ? toggle.dataset.actionsQueueBoxToggle || '' : '' ),
		};
	}

	/**
	 * Opens again the sections that were open, where they still exist, and selects the same tab. If none of
	 * them is left, such as when a section's last item was ignored, the landing stays as rendered.
	 * @param {Element} container
	 * @param {{open:string[],tab:string,boxes:string[]}} state
	 */
	restoreSectionsState( container, state ) {
		const root = container.querySelector( '[data-actions-queue-sections="1"]' );
		if ( !( root instanceof HTMLElement ) ) {
			return;
		}
		const sections = [ ...root.querySelectorAll( '[data-actions-queue-section-key]' ) ]
		.filter( ( section ) => section instanceof HTMLElement );
		const keys = sections.map( ( section ) => section.dataset.actionsQueueSectionKey || '' );

		if ( state.open.some( ( key ) => keys.includes( key ) ) ) {
			sections.forEach( ( section ) => {
				const head = section.querySelector( '[data-actions-queue-section-toggle="1"]' );
				if ( !( head instanceof HTMLElement ) ) {
					return;
				}
				const isOpen = state.open.includes( section.dataset.actionsQueueSectionKey || '' );
				head.setAttribute( 'aria-expanded', String( isOpen ) );
				const body = document.getElementById( head.getAttribute( 'aria-controls' ) || '' );
				if ( body !== null && root.dataset.view !== 'tabs' ) {
					body.hidden = !isOpen;
				}
			} );
		}

		root.querySelectorAll( '[data-actions-queue-box-toggle]' ).forEach( ( toggle ) => {
			if ( toggle instanceof HTMLElement && state.boxes.includes( toggle.dataset.actionsQueueBoxToggle || '' ) ) {
				toggle.setAttribute( 'aria-expanded', 'true' );
				const body = document.getElementById( toggle.getAttribute( 'aria-controls' ) || '' );
				body !== null && ( body.hidden = false );
			}
		} );

		if ( keys.includes( state.tab ) ) {
			root.querySelectorAll( '[data-actions-queue-tab]' ).forEach( ( tab ) => {
				if ( tab instanceof HTMLElement ) {
					const isSelected = tab.dataset.actionsQueueTab === state.tab;
					tab.setAttribute( 'aria-selected', String( isSelected ) );
					tab.tabIndex = isSelected ? 0 : -1;
				}
			} );
			sections.forEach( ( section ) => {
				section.dataset.tabSelected = section.dataset.actionsQueueSectionKey === state.tab ? '1' : '0';
			} );
		}
	}

	handleRetryClick( item ) {
		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}

		this.rootEl = root;
		this.shellEl = this.getShell( root );

		if ( item.dataset.actionsQueueRetry === 'detail' ) {
			this.loadDetailLayer();
		}
	}

	handleDrillBack( evt ) {
		const root = this.rootEl || this.getRoot();
		const shell = evt.target;
		if ( root === null || !( shell instanceof HTMLElement ) || !root.contains( shell ) ) {
			return;
		}

		// Back from results is always back to the landing.
		this.setResultsOverlay( shell, false );
		this.selectedBucket = null;
		this.selectedGroup = null;
		this.refreshRequest = null;
		this.cancelLayerRequest( 'detail' );
	}

	handleTableActionSuccess( evt ) {
		this.refreshAfterNestedTableEvent( evt );
	}

	handleScanResultsChanged( evt ) {
		this.refreshAfterNestedTableEvent( evt );
	}

	refreshAfterNestedTableEvent( evt ) {
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
		this.announceAssetPanelStatus( panel, this.rootEl?.dataset.actionsPaneLoading || '', {
			politeness: 'polite',
			allowRepeat: false,
		} );

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
				this.announceAssetPanelStatus( panel, this.getErrorMessage(), {
					politeness: 'assertive',
				} );
			} )
			.catch( () => {
				if ( panel.dataset.actionsQueueAssetPanelRequest !== requestKey ) {
					return;
				}

				panel.dataset.actionsQueueAssetPanelLoaded = '0';
				this.replaceLayerBodyHtml( content, `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>` );
				this.announceAssetPanelStatus( panel, this.getErrorMessage(), {
					politeness: 'assertive',
				} );
			} )
			.finally( () => {
				if ( panel.dataset.actionsQueueAssetPanelRequest === requestKey ) {
					delete panel.dataset.actionsQueueAssetPanelLoading;
					delete panel.dataset.actionsQueueAssetPanelRequest;
				}
			} );
	}

	replaceLayerBodyHtml( body, html, activate = false ) {
		super.replaceLayerBodyHtml( body, html, activate );
		// Lists shown as tabs open on their first entry.
		body.querySelectorAll( '[data-mode-open-first="1"]' ).forEach( ( shell ) => {
			const first = shell.querySelector( '[data-mode-tile="1"]' );
			if ( first instanceof HTMLElement && shell.querySelector( '[data-mode-panel="1"].is-open' ) === null ) {
				first.click();
			}
		} );
	}

	syncAssetHintVisibility( shell ) {
		this.setAssetHintVisible(
			shell,
			shell.querySelector( '[data-mode-panel="1"].is-open' ) === null
		);
	}

	announceAssetPanelStatus( panel, message, options = {} ) {
		announceStatus( panel, message, options );
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
			? evt.target.closest( '[data-actions-queue-maintenance-action], [data-actions-queue-maintenance-all]' )
			: null;
		if ( target === null ) {
			return;
		}

		const root = this.rootEl || this.getRoot();
		if ( root === null || !root.contains( target ) || target.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		evt.preventDefault();
		this.rootEl = root;
		this.shellEl = this.getShell( root );
		this.adoptLandingBucket( target );
		BootstrapTooltips.HideAndDisposeTooltip( target );

		// "Ignore all" and "Stop ignoring all" send their group's ignores, or its restores, as one request, then
		// refresh once.
		const allKind = target.dataset.actionsQueueMaintenanceAll || '';
		const isAll = allKind !== '';
		const scope = isAll ? target.closest( '.actions-queue-row-group' ) : target.closest( '.actions-queue-row' );
		const actions = ( isAll
			? [ ...( scope?.querySelectorAll( `[data-actions-queue-maintenance-kind="${allKind}"][data-actions-queue-maintenance-action]` ) || [] ) ]
			: [ target ]
		).map( ( el ) => this.parseJsonDataset( el.dataset.actionsQueueMaintenanceAction ) )
		.filter( ( data ) => !ObjectOps.IsEmpty( data ) );
		if ( actions.length === 0 ) {
			return;
		}

		// Something is plainly happening: the button says it's busy and its row or group dims until the
		// queue has refreshed in place.
		target.setAttribute( 'aria-busy', 'true' );
		scope?.classList.add( 'is-busy' );
		const done = () => {
			target.removeAttribute( 'aria-busy' );
			scope?.classList.remove( 'is-busy' );
		};

		const service = new AjaxService();
		const send = service.send( isAll ? this.combineActions( actions ) : actions[ 0 ] )
			.then( ( resp ) => resp?.success === true );

		send.then( ( ok ) => ok ? this.refreshAfterNestedAction( true ) : null )
			.catch( () => null )
			.finally( done );
	}

	/**
	 * One request for a group's ignores, or its restores: scan results go as one list of result IDs,
	 * maintenance items as one list of identifiers for their shared key.
	 * @param {Array<Object>} actions
	 * @return {Object}
	 */
	combineActions( actions ) {
		const first = actions[ 0 ];
		if ( Array.isArray( first.rids ) ) {
			return { ...first, rids: actions.flatMap( ( data ) => data.rids ) };
		}
		const combined = { ...first, identifiers: actions.map( ( data ) => data.identifier ) };
		delete combined.identifier;
		return combined;
	}

	refreshAfterNestedAction( reloadDetail ) {
		const openAssetPanelTarget = reloadDetail
			? this.getOpenAssetPanelTarget()
			: '';
		const resultsDisplayOptions = this.consumePendingResultsDisplayOptions();

		return this.refreshQueue( { includeSelectedGroup: this.selectedGroup !== null } ).then( ( data ) => {
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

	/**
	 * Actions taken on the landing (enabling a protection, ignoring a maintenance item) refresh
	 * through the lane they belong to, so that lane becomes the selected one.
	 */
	adoptLandingBucket( element ) {
		// Rows in the shared passing and not-covered sections carry their own lane; lanes carry theirs.
		const section = element instanceof Element ? element.closest( '[data-actions-queue-bucket-selection]' ) : null;
		if ( !( section instanceof HTMLElement ) ) {
			return false;
		}
		const bucket = this.readBucketSelectionPayload( this.parseJsonDataset( section.dataset.actionsQueueBucketSelection ) );
		if ( bucket.key.length < 1 ) {
			return false;
		}
		// The landing may not have been drilled into yet, so the shell isn't known.
		const root = section.closest( '[data-actions-landing="1"]' );
		if ( root instanceof HTMLElement && this.rootEl !== root ) {
			this.rootEl = root;
			this.shellEl = this.getShell( root );
		}
		this.selectedBucket = bucket;
		this.selectedGroup = null;
		return true;
	}

	readBucketSelection( item ) {
		return this.readBucketSelectionPayload( this.parseJsonDataset( item.dataset.drillBucketSelection ) );
	}

	readGroupSelection( item ) {
		return this.readGroupSelectionPayload( this.parseJsonDataset( item.dataset.drillGroupSelection ) );
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

	prepareLayerHtml( layerKey, html ) {
		return layerKey === 'detail'
			? this.buildDetailLayerHtml( html )
			: super.prepareLayerHtml( layerKey, html );
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
