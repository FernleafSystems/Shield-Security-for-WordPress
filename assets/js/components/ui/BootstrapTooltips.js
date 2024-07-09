import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
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
		$( document ).ajaxComplete( () => {
			let popoverTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="popover"]' ) )
			popoverTriggerList.map( ( popoverElement ) => new Popover( popoverElement, {} ) );

			// let tooltipTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="tooltip"]' ) )
			// tooltipTriggerList.map( ( tooltipElement ) => new Tooltip( tooltipElement, {} ) );
		} );
	}
}