import { Tab, Tooltip } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { BootstrapTooltips } from "../ui/BootstrapTooltips";
import { DataTableVisibilityAdjuster } from "../tables/DataTableVisibilityAdjuster";

export class RailSidebarController extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-shield-rail-scope="1"]' ) !== null;
	}

	run() {
		shieldEventsHandler_Main.add_Click(
			'[data-shield-rail-target]',
			( item, evt ) => this.handleRailItemClick( item, evt ),
			false
		);
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'[data-shield-rail-target][data-bs-toggle="tab"]',
			( item ) => this.handleBootstrapTabShown( item ),
			false
		);

		shieldEventsHandler_Main.add_Click(
			'[data-shield-rail-switch]',
			( el, evt ) => {
				evt.preventDefault();
				const targetKey = ( el.dataset.shieldRailSwitch || '' ).trim();
				if ( targetKey.length < 1 ) {
					return;
				}
				const scope = el.closest( '[data-shield-rail-scope="1"]' );
				if ( !scope ) {
					return;
				}
				const railItem = scope.querySelector( `[data-shield-rail-target="${targetKey}"]` );
				if ( railItem ) {
					if ( this.isBootstrapTab( railItem ) ) {
						Tab.getOrCreateInstance( railItem ).show();
						return;
					}
					this.switchPane( railItem, targetKey );
				}
			},
			false
		);
	}

	isBootstrapTab( item ) {
		return item?.dataset?.bsToggle === 'tab';
	}

	handleRailItemClick( item ) {
		if ( this.isBootstrapTab( item ) ) {
			return;
		}

		const targetKey = ( item.dataset.shieldRailTarget || '' ).trim();
		if ( targetKey.length < 1 ) {
			return;
		}

		this.switchPane( item, targetKey );
	}

	handleBootstrapTabShown( item ) {
		const scope = item.closest( '[data-shield-rail-scope="1"]' );
		if ( scope === null ) {
			return;
		}

		const sidebar = scope.querySelector( '.shield-rail-sidebar' );
		if ( sidebar !== null ) {
			sidebar.querySelectorAll( '[data-shield-rail-target]' ).forEach( ( candidate ) => {
				candidate.classList.toggle( 'is-active', candidate === item );
			} );
		}

		const targetPane = this.findBootstrapTargetPane( item, scope );
		if ( targetPane === null ) {
			return;
		}

		this.activatePaneEnhancements( scope, targetPane );
	}

	findBootstrapTargetPane( item, scope ) {
		const targetSelector = ( item.dataset.bsTarget || item.getAttribute( 'href' ) || '' ).trim();
		if ( targetSelector.length < 2 || !targetSelector.startsWith( '#' ) ) {
			return null;
		}

		return scope.querySelector( targetSelector );
	}

	switchPane( clickedItem, targetKey ) {
		const scope = clickedItem.closest( '[data-shield-rail-scope="1"]' );
		if ( scope === null ) {
			return;
		}

		const sidebar = scope.querySelector( '.shield-rail-sidebar' );
		if ( sidebar !== null ) {
			sidebar.querySelectorAll( '[data-shield-rail-target]' ).forEach( ( item ) => {
				item.classList.remove( 'is-active' );
				item.setAttribute( 'aria-current', 'false' );
			} );
		}

		clickedItem.classList.add( 'is-active' );
		clickedItem.setAttribute( 'aria-current', 'true' );

		const contentArea = scope.querySelector( '.shield-rail-layout__content' );
		if ( contentArea === null ) {
			return;
		}

		this.disposeTooltipsWithin( contentArea );

		contentArea.querySelectorAll( '[data-shield-rail-pane]' ).forEach( ( pane ) => {
			pane.style.display = 'none';
		} );

		const targetPane = contentArea.querySelector( `[data-shield-rail-pane="${targetKey}"]` );
		if ( targetPane === null ) {
			return;
		}

		targetPane.style.display = '';
		this.activatePaneEnhancements( scope, targetPane );
	}

	disposeTooltipsWithin( container ) {
		container.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( el ) => {
			const tip = Tooltip.getInstance( el );
			if ( tip ) {
				tip.dispose();
			}
		} );
	}

	activatePaneEnhancements( scope, targetPane ) {
		const contentArea = scope.querySelector( '.shield-rail-layout__content' );
		if ( contentArea !== null ) {
			this.disposeTooltipsWithin( contentArea );
		}
		DataTableVisibilityAdjuster.adjustWithinNextFrame( targetPane );
		BootstrapTooltips.RegisterNewTooltipsWithin( targetPane );
	}
}
