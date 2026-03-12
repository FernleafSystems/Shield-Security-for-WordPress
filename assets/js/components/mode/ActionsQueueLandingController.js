import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { AjaxBatchService } from "../services/AjaxBatchService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ActionsQueueLandingController extends BaseAutoExecComponent {

	canRun() {
		return true;
	}

	run() {
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'[data-shield-rail-target][data-bs-toggle="tab"]',
			( item ) => this.handleRailTabShown( item ),
			false
		);
		this.initializeCurrentRoot();
	}

	handleRailTabShown( item ) {
		const root = this.getRoot();
		if ( root === null || !root.contains( item ) ) {
			return;
		}
		this.rootEl = root;

		const pane = this.findTargetPane( item );
		if ( pane === null ) {
			return;
		}

		this.requestPaneLoad( pane, { showPlaceholder: true } );
		this.initializeLoadedPane( pane );
	}

	requestPaneLoad( pane, {
		showPlaceholder = false,
	} = {} ) {
		const renderAction = this.preparePaneLoad( pane, { showPlaceholder: showPlaceholder } );
		if ( ObjectOps.IsEmpty( renderAction ) ) {
			return;
		}

		( new AjaxService() )
		.send( renderAction, false, true )
		.then( ( resp ) => {
			if ( !resp.success || typeof resp?.data?.html !== 'string' ) {
				this.handlePaneLoadFailure( pane );
				return;
			}

			this.applyPaneHtml( pane, resp.data.html, this.isPaneActive( pane ) );
		} )
		.catch( () => this.handlePaneLoadFailure( pane ) )
		.finally( () => {
			delete pane.dataset.actionsQueuePaneLoading;
		} );
	}

	preparePaneLoad( pane, {
		showPlaceholder = false,
	} = {} ) {
		if ( pane.dataset.actionsQueuePaneLoaded === '1' || pane.dataset.actionsQueuePaneLoading === '1' ) {
			return {};
		}

		const renderAction = this.getPaneRenderAction( pane );
		if ( ObjectOps.IsEmpty( renderAction ) ) {
			return {};
		}

		pane.dataset.actionsQueuePaneLoading = '1';
		if ( showPlaceholder ) {
			pane.innerHTML = this.buildLoadingMarkup();
		}

		return renderAction;
	}

	preloadRailPanes() {
		if ( this.rootEl.dataset.actionsQueuePreloadStarted === '1' ) {
			return;
		}

		const preloadAction = this.parseJsonDataset( this.rootEl.dataset.actionsQueuePreloadAction );
		if ( ObjectOps.IsEmpty( preloadAction ) ) {
			return;
		}

		const panes = this.getPreloadablePanes();
		if ( panes.length < 1 ) {
			return;
		}

		this.rootEl.dataset.actionsQueuePreloadStarted = '1';
		const batchService = new AjaxBatchService( preloadAction );

		panes.forEach( ( pane ) => {
			const renderAction = this.preparePaneLoad( pane );
			if ( ObjectOps.IsEmpty( renderAction ) ) {
				return;
			}

			batchService.add( {
				id: pane.dataset.actionsQueuePaneKey || '',
				request: renderAction,
				onSuccess: ( result ) => {
					if ( result?.success && typeof result?.data?.html === 'string' ) {
						this.applyPaneHtml( pane, result.data.html, this.isPaneActive( pane ) );
						return;
					}
					this.handlePaneLoadFailure( pane, true );
				},
				onError: () => this.handlePaneLoadFailure( pane, true ),
			} );
		} );

		batchService.flush()
			.finally( () => {
				delete this.rootEl.dataset.actionsQueuePreloadStarted;
			} );
	}

	handlePaneLoadFailure( pane, retryIfActive = false ) {
		delete pane.dataset.actionsQueuePaneLoading;
		if ( retryIfActive && this.isPaneActive( pane ) ) {
			this.requestPaneLoad( pane, { showPlaceholder: true } );
			return;
		}
		if ( retryIfActive ) {
			return;
		}

		this.renderLoadFailure( pane );
	}

	applyPaneHtml( pane, html, initializeNow = false ) {
		pane.innerHTML = html;
		pane.dataset.actionsQueuePaneLoaded = '1';
		delete pane.dataset.actionsQueuePaneLoading;

		if ( initializeNow ) {
			this.initializeLoadedPane( pane );
		}
	}

	initializeLoadedPane( pane ) {
		if ( pane.dataset.actionsQueuePaneLoaded !== '1' || pane.dataset.actionsQueuePaneInitialized === '1' ) {
			return;
		}

		pane.dataset.actionsQueuePaneInitialized = '1';
		UiContentActivator.activateCurrentSubtree( pane );
	}

	initializeCurrentRoot() {
		this.rootEl = this.getRoot();
		if ( this.rootEl === null ) {
			return;
		}

		this.loadInitialActivePane();
		this.hydrateRailMetrics();
		this.preloadRailPanes();
	}

	getRoot() {
		return document.querySelector( '[data-actions-landing="1"]' );
	}

	loadInitialActivePane() {
		const activeItem = this.rootEl?.querySelector(
			'[data-shield-rail-target][data-bs-toggle="tab"].active, '
			+ '[data-shield-rail-target][data-bs-toggle="tab"][aria-selected="true"]'
		);
		if ( activeItem !== null ) {
			this.handleRailTabShown( activeItem );
		}
	}

	getPreloadablePanes() {
		return [ ...this.rootEl.querySelectorAll( '[data-actions-queue-pane-key]' ) ].filter( ( pane ) => {
			return pane.dataset.actionsQueuePaneLoaded !== '1'
				&& pane.dataset.actionsQueuePaneLoading !== '1'
				&& !ObjectOps.IsEmpty( this.getPaneRenderAction( pane ) );
		} );
	}

	renderLoadFailure( pane ) {
		delete pane.dataset.actionsQueuePaneLoading;
		delete pane.dataset.actionsQueuePaneInitialized;
		pane.innerHTML = `<div class="alert alert-warning mb-0">${this.escapeHtml( this.getErrorMessage() )}</div>`;
	}

	hydrateRailMetrics() {
		if ( this.rootEl.dataset.actionsQueueMetricsLoading === '1'
			|| this.rootEl.dataset.actionsQueueMetricsLoaded === '1' ) {
			return;
		}

		const scope = this.rootEl.querySelector( '[data-shield-rail-scope="1"]' );
		if ( scope === null ) {
			return;
		}

		if ( this.rootEl.dataset.actionsQueueMetricsAction === undefined ) {
			return;
		}
		const metricsAction = this.parseJsonDataset( this.rootEl.dataset.actionsQueueMetricsAction );
		if ( ObjectOps.IsEmpty( metricsAction ) ) {
			return;
		}

		this.rootEl.dataset.actionsQueueMetricsLoading = '1';

		( new AjaxService() )
		.send( metricsAction, false, true )
		.then( ( resp ) => {
			if ( resp.success ) {
				this.applyRailMetrics( resp.data );
				this.rootEl.dataset.actionsQueueMetricsLoaded = '1';
			}
		} )
		.catch( () => null )
		.finally( () => {
			delete this.rootEl.dataset.actionsQueueMetricsLoading;
		} );
	}

	applyRailMetrics( data ) {
		const scope = this.rootEl.querySelector( '[data-shield-rail-scope="1"]' );
		if ( scope === null ) {
			return;
		}

		this.updateAccent( scope, data.rail_accent_status );

		Object.entries( data.tabs ).forEach( ( [ key, tabData ] ) => {
			this.updateRailItem( scope, key, tabData );
		} );
	}

	updateRailItem( scope, key, tabData ) {
		const button = scope.querySelector( `[data-shield-rail-key="${key}"]` );
		if ( button === null ) {
			return;
		}

		this.updateRailStatus( button, tabData.status );
		this.updateRailMarker( button, tabData.status );
		this.updateRailBadge( button, tabData.count, tabData.status );
	}

	updateRailStatus( button, status ) {
		button.dataset.shieldRailStatus = status;
	}

	updateRailMarker( button, status ) {
		const marker = button.querySelector( '.shield-rail-sidebar__icon, .shield-rail-sidebar__pip' );
		if ( marker === null ) {
			return;
		}

		const classPrefix = marker.classList.contains( 'shield-rail-sidebar__icon' )
			? 'shield-rail-sidebar__icon--'
			: 'shield-rail-sidebar__pip--';

		[ ...marker.classList ]
		.filter( ( className ) => className.startsWith( classPrefix ) )
		.forEach( ( className ) => marker.classList.remove( className ) );

		marker.classList.add( `${classPrefix}${status}` );
	}

	updateRailBadge( button, count, status ) {
		let badge = button.querySelector( '.shield-rail-sidebar__badge' );
		if ( badge === null ) {
			badge = document.createElement( 'span' );
			button.appendChild( badge );
		}

		if ( count === null || count === undefined ) {
			badge.className = 'shield-badge badge-disabled shield-rail-sidebar__badge shield-rail-sidebar__badge--placeholder';
			badge.textContent = '-';
			return;
		}

		const badgeStatus = status === 'neutral' ? 'disabled' : status;
		badge.className = `shield-badge badge-${badgeStatus} shield-rail-sidebar__badge`;
		badge.textContent = String( count );
	}

	updateAccent( scope, status ) {
		const accent = scope.querySelector( '.shield-rail-sidebar__accent' );
		if ( accent === null ) {
			return;
		}

		[ ...accent.classList ]
		.filter( ( className ) => className.startsWith( 'shield-rail-sidebar__accent--' ) )
		.forEach( ( className ) => accent.classList.remove( className ) );

		accent.classList.add( `shield-rail-sidebar__accent--${status}` );
	}

	buildLoadingMarkup() {
		const message = this.rootEl?.dataset?.actionsPaneLoading || 'Loading scan details...';
		return `<div class="text-muted small" data-actions-queue-pane-placeholder="1">${this.escapeHtml( message )}</div>`;
	}

	getPaneRenderAction( pane ) {
		return this.parseJsonDataset( pane.dataset.actionsQueueRenderAction );
	}

	findTargetPane( item ) {
		const targetSelector = ( item.dataset.bsTarget || item.getAttribute( 'href' ) || '' ).trim();
		if ( targetSelector.length < 2 || !targetSelector.startsWith( '#' ) ) {
			return null;
		}

		return this.rootEl.querySelector( targetSelector );
	}

	isPaneActive( pane ) {
		return pane.classList.contains( 'active' ) || pane.classList.contains( 'show' );
	}

	parseJsonDataset( value = '{}' ) {
		try {
			return JSON.parse( value );
		}
		catch ( e ) {
			return {};
		}
	}

	getErrorMessage() {
		return this.rootEl.dataset.actionsPaneError || 'Unable to load these scan details. Please try again.';
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
