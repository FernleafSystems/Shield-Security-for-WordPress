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
	}

	handleRailPaneSwitched( evt ) {
		const scope = evt?.detail?.scope || null;
		const pane = evt?.detail?.pane || null;
		if ( scope === null || pane === null || !this.rootEl.contains( scope ) ) {
			return;
		}

		this.ensurePaneLoaded( pane );
	}

	ensurePaneLoaded( pane ) {
		if ( pane.dataset.actionsQueuePaneLoaded === '1' || pane.dataset.actionsQueuePaneLoading === '1' ) {
			return;
		}

		const renderAction = this.parseJsonObject( pane.dataset.actionsQueueRenderAction || '' );
		if ( renderAction === null ) {
			return;
		}

		pane.dataset.actionsQueuePaneLoading = '1';
		pane.innerHTML = this.buildLoadingMarkup();

		( new AjaxService() )
		.send( renderAction, false, true )
		.then( ( resp ) => {
			const html = ( resp && resp.success && typeof resp.data?.render_output === 'string' )
				? resp.data.render_output
				: '';

			if ( html.length < 1 ) {
				this.renderLoadFailure( pane );
				return;
			}

			pane.innerHTML = html;
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
		const scanTables = window?.shield_vars_main?.comps?.scans?.vars?.scan_results_tables || {};

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

	buildLoadingMarkup() {
		const message = this.rootEl?.dataset?.actionsPaneLoading || 'Loading scan details...';
		return `<div class="text-muted small" data-actions-queue-pane-placeholder="1">${this.escapeHtml( message )}</div>`;
	}

	getErrorMessage() {
		return this.rootEl?.dataset?.actionsPaneError || 'Unable to load these scan details. Please try again.';
	}

	parseJsonObject( rawJson ) {
		if ( rawJson.length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawJson );
			return ( parsed && typeof parsed === 'object' ) ? parsed : null;
		}
		catch ( e ) {
			return null;
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
