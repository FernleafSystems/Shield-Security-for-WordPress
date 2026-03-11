import { InvestigateLookupSelect2 } from "../mode/InvestigateLookupSelect2";
import { InvestigationTable } from "../tables/InvestigationTable";
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

	static activateWithin( contextEl ) {
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return;
		}

		UiContentActivator.initializeInvestigationTablesWithin( root );
		UiContentActivator.initializeInvestigateLookupSelect2Within( root );
		DataTableVisibilityAdjuster.adjustWithinNextFrame( root );
		BootstrapTooltips.RegisterNewTooltipsWithin( root );
	}

	static activateInitialWithin( contextEl ) {
		const root = UiContentActivator.normalizeContext( contextEl );
		if ( root === null ) {
			return;
		}

		UiContentActivator.activateStandaloneWithin( root );
		UiContentActivator.collectDirectActiveOwnerRoots( root ).forEach( ( ownerRoot ) => {
			UiContentActivator.activateInitialWithin( ownerRoot );
		} );
		DataTableVisibilityAdjuster.adjustWithinNextFrame( root );
		BootstrapTooltips.RegisterNewTooltipsWithin( root );
	}

	static normalizeContext( contextEl ) {
		return contextEl instanceof Element || contextEl instanceof Document
			? contextEl
			: null;
	}

	static initializeInvestigationTablesWithin( contextEl ) {
		const tableEls = UiContentActivator.collectElements( contextEl, '[data-investigation-table="1"]' );
		if ( tableEls.length > 0 ) {
			new InvestigationTable( { tableEls } );
		}
	}

	static initializeInvestigateLookupSelect2Within( contextEl ) {
		if ( UiContentActivator.investigateLookupSelect2 === null ) {
			UiContentActivator.investigateLookupSelect2 = new InvestigateLookupSelect2();
		}

		const selectEls = UiContentActivator.collectElements( contextEl, 'select[data-investigate-select2="1"]' );
		if ( selectEls.length > 0 ) {
			UiContentActivator.investigateLookupSelect2.initializeElements( selectEls );
		}
	}

	static activateStandaloneWithin( contextEl ) {
		const tableEls = UiContentActivator.collectDirectStandaloneElements( contextEl, '[data-investigation-table="1"]' );
		if ( tableEls.length > 0 ) {
			new InvestigationTable( { tableEls } );
		}

		if ( UiContentActivator.investigateLookupSelect2 === null ) {
			UiContentActivator.investigateLookupSelect2 = new InvestigateLookupSelect2();
		}

		const selectEls = UiContentActivator.collectDirectStandaloneElements( contextEl, 'select[data-investigate-select2="1"]' );
		if ( selectEls.length > 0 ) {
			UiContentActivator.investigateLookupSelect2.initializeElements( selectEls );
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
