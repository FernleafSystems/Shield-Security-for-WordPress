import $ from 'jquery';
import { BaseService } from "./BaseService";
import { Popover, Tooltip } from 'bootstrap';

export class BootstrapTooltips extends BaseService {
	init() {
		$( document ).ajaxComplete( () => {
			let popoverTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="popover"]' ) )
			popoverTriggerList.map( ( popoverElement ) => new Popover( popoverElement, {} ) );

			let tooltipTriggerList = [].slice.call( document.querySelectorAll( '[data-bs-toggle="tooltip"]' ) )
			tooltipTriggerList.map( ( tooltipElement ) => new Tooltip( tooltipElement, {} ) );
		} );
	}
}