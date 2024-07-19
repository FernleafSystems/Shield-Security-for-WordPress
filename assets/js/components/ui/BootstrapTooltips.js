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
				let found = false;
				this.actionTooltips.forEach( ( tt ) => {
					if ( tt === targetEl ) {
						found = true;
					}
				} );
				if ( !found ) {
					this.actionTooltips.push( Tooltip.getOrCreateInstance( targetEl ) );
				}
			},
			false
		);
		shieldEventsHandler_Main.add_Mouseout(
			'[data-bs-toggle="tooltip"]',
			( targetEl ) => {
				this.actionTooltips.pop();
			},
			false
		);
	};
}