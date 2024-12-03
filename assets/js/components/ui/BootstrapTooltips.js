import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
		// this.popovers();
		this.tooltips();
	}

	popovers1() {
		/*
		shieldEventsHandler_Main.add_Mouseover(
			'[data-bs-toggle="popover"]',
			( targetEl ) => {
				let po = Popover.getInstance( targetEl );
				if ( !po ) {
					po = Popover.getOrCreateInstance( targetEl );
					targetEl.addEventListener( 'shown.bs.popover', () => {
						alert( 'asdf' );
						window.setTimeout( () => po.hide(), 7000 );
					} );
				}
			},
			false
		);
		 */
	};

	popovers() {
		this.actionTooltips = [];
		shieldEventsHandler_Main.add_Mouseover(
			'[data-bs-toggle="popover"]',
			( targetEl ) => {
				Popover.getOrCreateInstance( targetEl )
			},
			false
		);
	};

	tooltips() {
		const primaryContainer = document.getElementById( 'PageContainer-Apto' ) || false;
		if ( primaryContainer ) {
			BootstrapTooltips.RegisterNewTooltipsWithin( primaryContainer );
		}
		// shieldEventsHandler_Main.add_Mouseover(
		// 	'[data-bs-toggle="tooltip"]',
		// 	( targetEl ) => {
		// 		Tooltip.getOrCreateInstance( targetEl )
		// 	},
		// 	false
		// );
	};

	static RegisterNewTooltipsWithin( container ) {
		if ( container ) {
			container.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( targetEl ) => {
				Tooltip.getOrCreateInstance( targetEl )
			} );
		}
		// console.log( container );
	}
}