import { InvestigateLookupSelect2 } from "../mode/InvestigateLookupSelect2";
import { InvestigationTable } from "../tables/InvestigationTable";
import { ShieldTableScanResults } from "../tables/ShieldTableScanResults";
import { DataTableVisibilityAdjuster } from "../tables/DataTableVisibilityAdjuster";
import { BootstrapTooltips } from "./BootstrapTooltips";

export class UiContentActivator {

	static investigateLookupSelect2 = null;
	static ownerSelector = '.tab-pane, [data-shield-expand-body="1"], [data-mode-panel="1"], .offcanvas';
	static activeOwnerSelectors = [
		'.tab-pane.active',
		'.tab-pane.show',
		'[data-shield-expand-body="1"].show',
		'[data-mode-panel="1"].is-open',
		'.offcanvas.show'
	];

	// Activate all supported widgets within a subtree the caller already knows is current/visible/open.
	static activateCurrentSubtree( contextEl ) {
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return;
		}

		UiContentActivator.activateStandaloneWithin( root );
		UiContentActivator.collectDirectActiveOwnerRoots( root ).forEach( ( ownerRoot ) => {
			UiContentActivator.activateCurrentSubtree( ownerRoot );
		} );
		DataTableVisibilityAdjuster.adjustWithinNextFrame( root );
		BootstrapTooltips.RegisterNewTooltipsWithin( root );
	}

	static activateCurrentWithinRoot( contextEl ) {
		UiContentActivator.activateCurrentSubtree( contextEl );
	}

	static normalizeContext( contextEl ) {
		return contextEl instanceof Element || contextEl instanceof Document
			? contextEl
			: null;
	}

	static activateStandaloneWithin( contextEl ) {
		const tableEls = UiContentActivator.collectDirectStandaloneElements( contextEl, '[data-investigation-table="1"]' );
		UiContentActivator.activateInvestigationTables( tableEls );
		const scanResultsTableEls = UiContentActivator.collectDirectStandaloneElements( contextEl, '[data-scan-results-table="1"]' );
		UiContentActivator.activateScanResultsTables( scanResultsTableEls );
		const selectEls = UiContentActivator.collectDirectStandaloneElements( contextEl, 'select[data-investigate-select2="1"]' );
		UiContentActivator.activateInvestigateSelect2( selectEls );
	}

	static activateInvestigationTablesWithin( contextEl ) {
		UiContentActivator.activateInvestigationTables(
			UiContentActivator.collectElements( contextEl, '[data-investigation-table="1"]' )
		);
	}

	static activateInvestigateSelect2Within( contextEl ) {
		UiContentActivator.activateInvestigateSelect2(
			UiContentActivator.collectElements( contextEl, 'select[data-investigate-select2="1"]' )
		);
	}

	static activateInvestigationTables( tableEls ) {
		if ( tableEls.length > 0 ) {
			new InvestigationTable( { tableEls } );
		}
	}

	static activateScanResultsTables( tableEls ) {
		tableEls.forEach( ( tableEl ) => {
			if ( !( tableEl instanceof HTMLTableElement ) || tableEl.dataset.shieldScanResultsInitialized === '1' || !tableEl.id ) {
				return;
			}

			const datatablesInit = UiContentActivator.parseJsonObject( tableEl.dataset.datatablesInit || '' );
			const tableAction = UiContentActivator.parseJsonObject( tableEl.dataset.tableAction || '' );
			if ( datatablesInit === null || tableAction === null ) {
				return;
			}

			const renderItemAnalysis = UiContentActivator.parseJsonObject( tableEl.dataset.renderItemAnalysis || '' ) || {};
			new ShieldTableScanResults( {
				ajax: {
					table_action: tableAction,
					render_item_analysis: renderItemAnalysis,
				},
				vars: {
					table_selector: '#'+tableEl.id,
					datatables_init: datatablesInit,
				},
			} );
			tableEl.dataset.shieldScanResultsInitialized = '1';
		} );
	}

	static activateInvestigateSelect2( selectEls ) {
		if ( selectEls.length > 0 ) {
			UiContentActivator.getInvestigateLookupSelect2().initializeElements( selectEls );
		}
	}

	static getInvestigateLookupSelect2() {
		if ( UiContentActivator.investigateLookupSelect2 === null ) {
			UiContentActivator.investigateLookupSelect2 = new InvestigateLookupSelect2();
		}
		return UiContentActivator.investigateLookupSelect2;
	}

	static parseJsonObject( rawData ) {
		if ( typeof rawData !== 'string' || rawData.trim().length < 1 ) {
			return null;
		}

		try {
			const parsed = JSON.parse( rawData );
			return parsed && typeof parsed === 'object' ? parsed : null;
		}
		catch ( e ) {
			return null;
		}
	}

	static collectElements( contextEl, selector ) {
		const elements = [];
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return elements;
		}

		if ( root instanceof Element && root.matches( selector ) ) {
			elements.push( root );
		}

		root.querySelectorAll( selector ).forEach( ( el ) => {
			if ( !elements.includes( el ) ) {
				elements.push( el );
			}
		} );

		return elements;
	}

	static collectDirectStandaloneElements( contextEl, selector ) {
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return [];
		}

		return UiContentActivator.collectElements( root, selector ).filter( ( el ) => {
			const nearestOwner = UiContentActivator.findNearestOwnerAncestor( el );
			if ( nearestOwner === null ) {
				return true;
			}

			return root instanceof Element && nearestOwner === root;
		} );
	}

	static collectDirectActiveOwnerRoots( contextEl ) {
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return [];
		}

		const roots = [];
		UiContentActivator.activeOwnerSelectors.forEach( ( selector ) => {
			UiContentActivator.collectElements( root, selector ).forEach( ( candidate ) => {
				if ( candidate === root ) {
					return;
				}
				if ( roots.includes( candidate ) ) {
					return;
				}

				const nearestOwner = UiContentActivator.findNearestOwnerAncestor( candidate );
				if ( nearestOwner === null || ( root instanceof Element && nearestOwner === root ) ) {
					roots.push( candidate );
				}
			} );
		} );
		return roots;
	}

	static findNearestOwnerAncestor( el ) {
		return el instanceof Element
			? el.parentElement?.closest( UiContentActivator.ownerSelector ) || null
			: null;
	}
}
