import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
		this.popovers();
		this.tooltips();
	}

	popovers() {
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

	tooltips() {
		this.actionTooltips = [];
		shieldEventsHandler_Main.add_Mouseover(
			'[data-bs-toggle="tooltip"]',
			( targetEl ) => {
				Tooltip.getOrCreateInstance( targetEl )
			},
			false
		);
		shieldEventsHandler_Main.add_Mouseout(
			'[data-bs-toggle="tooltip"]',
			( targetEl ) => {
				// this.actionTooltips.pop();
			},
			false
		);
	};

	registerNewTooltipsWithin( container ) {
		if ( container ) {
			container.querySelectorAll( '[data-bs-toggle="tooltip"]' ).forEach( ( targetEl ) => Tooltip.getOrCreateInstance( targetEl ) );
		}
		console.log( container );
	}
}