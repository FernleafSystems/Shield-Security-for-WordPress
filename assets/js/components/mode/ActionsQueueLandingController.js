import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { DataTableVisibilityAdjuster } from "../tables/DataTableVisibilityAdjuster";
import { ShieldTableScanResults } from "../tables/ShieldTableScanResults";
import { ObjectOps } from "../../util/ObjectOps";

export class ActionsQueueLandingController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-actions-landing="1"]' ) !== null;
	}

	run() {
		this.rootEl = document.querySelector( '[data-actions-landing="1"]' );
		document.addEventListener( 'shield:rail-pane-switched', ( evt ) => this.handleRailPaneSwitched( evt ) );
		this.hydrateRailMetrics();
	}

	handleRailPaneSwitched( evt ) {
		const { scope, pane } = evt.detail;
		if ( scope === null || pane === null || !this.rootEl.contains( scope ) ) {
			return;
		}

		this.ensurePaneLoaded( pane );
	}

	ensurePaneLoaded( pane ) {
		if ( pane.dataset.actionsQueuePaneLoaded === '1' || pane.dataset.actionsQueuePaneLoading === '1' ) {
			return;
		}

		const renderAction = JSON.parse( pane.dataset.actionsQueueRenderAction );

		pane.dataset.actionsQueuePaneLoading = '1';
		pane.innerHTML = this.buildLoadingMarkup();

		( new AjaxService() )
		.send( renderAction, false, true )
		.then( ( resp ) => {
			if ( !resp.success ) {
				this.renderLoadFailure( pane );
				return;
			}

			pane.innerHTML = resp.data.html;
			pane.dataset.actionsQueuePaneLoaded = '1';
			this.initializeDynamicContent( pane );
		} )
		.catch( () => this.renderLoadFailure( pane ) )
		.finally( () => {
			delete pane.dataset.actionsQueuePaneLoading;
			DataTableVisibilityAdjuster.adjustWithinNextFrame( pane );
			BootstrapTooltips.RegisterNewTooltipsWithin( pane );
		} );
	}

	initializeDynamicContent( pane ) {
		const scanTables = window.shield_vars_main.comps.scans.vars.scan_results_tables;

		if ( pane.querySelector( '#ShieldTable-ScanResultsWordpress' ) && scanTables.wordpress ) {
			new ShieldTableScanResults( ObjectOps.ObjClone( scanTables.wordpress ) );
		}

		if ( pane.querySelector( '#ShieldTable-ScanResultsMalware' ) && scanTables.malware ) {
			new ShieldTableScanResults( ObjectOps.ObjClone( scanTables.malware ) );
		}
	}

	renderLoadFailure( pane ) {
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
		const metricsAction = JSON.parse( this.rootEl.dataset.actionsQueueMetricsAction );

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

		badge.className = `shield-badge badge-${status} shield-rail-sidebar__badge`;
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
