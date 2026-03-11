import { InvestigateLookupSelect2 } from "../mode/InvestigateLookupSelect2";
import { InvestigationTable } from "../tables/InvestigationTable";
import { DataTableVisibilityAdjuster } from "../tables/DataTableVisibilityAdjuster";
import { BootstrapTooltips } from "./BootstrapTooltips";

export class UiContentActivator {

	static investigateLookupSelect2 = null;

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

	static normalizeContext( contextEl ) {
		return contextEl instanceof Element || contextEl instanceof Document
			? contextEl
			: null;
	}

	static initializeInvestigationTablesWithin( contextEl ) {
		const activeTableEls = UiContentActivator.collectActiveElements( contextEl, '[data-investigation-table="1"]' );
		if ( activeTableEls.length > 0 ) {
			new InvestigationTable( { tableEls: activeTableEls } );
		}
	}

	static initializeInvestigateLookupSelect2Within( contextEl ) {
		if ( UiContentActivator.investigateLookupSelect2 === null ) {
			UiContentActivator.investigateLookupSelect2 = new InvestigateLookupSelect2();
		}

		const activeSelectEls = UiContentActivator.collectActiveElements( contextEl, 'select[data-investigate-select2="1"]' );
		if ( activeSelectEls.length > 0 ) {
			UiContentActivator.investigateLookupSelect2.initializeElements( activeSelectEls );
		}
	}

	static collectActiveElements( contextEl, selector ) {
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

		return elements.filter( ( el ) => !UiContentActivator.isWithinInactiveContainer( el, root ) );
	}

	static isWithinInactiveContainer( el, root ) {
		let current = el instanceof Element ? el.parentElement : null;
		while ( current !== null && current !== root ) {
			if ( UiContentActivator.isInactiveContainer( current ) ) {
				return true;
			}
			current = current.parentElement;
		}

		return false;
	}

	static isInactiveContainer( container ) {
		if ( container.matches( '.collapse' ) && !container.classList.contains( 'show' ) ) {
			return true;
		}

		if ( container.matches( '.tab-pane' ) && !( container.classList.contains( 'active' ) || container.classList.contains( 'show' ) ) ) {
			return true;
		}

		return container.matches( '[data-mode-panel="1"]' )
			&& !container.classList.contains( 'is-open' )
			&& container.getAttribute( 'aria-hidden' ) === 'true';
	}
}
