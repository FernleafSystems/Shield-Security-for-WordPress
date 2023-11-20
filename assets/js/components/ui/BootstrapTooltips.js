import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseComponent {
	init() {
		$( document ).ajaxComplete( () => {
			let popoverTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="popover"]' ) )
			popoverTriggerList.map( ( popoverElement ) => new Popover( popoverElement, {} ) );

			let tooltipTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="tooltip"]' ) )
			tooltipTriggerList.map( ( tooltipElement ) => new Tooltip( tooltipElement, {} ) );
		} );
	}
}