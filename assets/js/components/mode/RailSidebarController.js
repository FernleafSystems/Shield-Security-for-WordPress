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
		shieldEventsHandler_Main.add_Keyup(
			'[data-shield-rail-target]',
			( item, evt ) => this.handleRailItemKeyup( item, evt ),
			false
		);
	}

	handleRailItemClick( item ) {
		const targetKey = ( item.dataset.shieldRailTarget || '' ).trim();
		if ( targetKey.length < 1 ) {
			return;
		}

		this.switchPane( item, targetKey );
	}

	handleRailItemKeyup( item, evt ) {
		if ( evt.target !== item ) {
			return;
		}

		if ( evt.key !== 'Enter' && evt.key !== ' ' && evt.key !== 'Spacebar' ) {
			return;
		}

		evt.preventDefault();

		const targetKey = ( item.dataset.shieldRailTarget || '' ).trim();
		if ( targetKey.length < 1 ) {
			return;
		}

		this.switchPane( item, targetKey );
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

		contentArea.querySelectorAll( '[data-shield-rail-pane]' ).forEach( ( pane ) => {
			pane.style.display = 'none';
		} );

		const targetPane = contentArea.querySelector( `[data-shield-rail-pane="${targetKey}"]` );
		if ( targetPane === null ) {
			return;
		}

		targetPane.style.display = '';
		DataTableVisibilityAdjuster.adjustWithinNextFrame( targetPane );
		BootstrapTooltips.RegisterNewTooltipsWithin( targetPane );
	}
}
