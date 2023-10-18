import $ from 'jquery';
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";
import { OffCanvasService } from "./OffCanvasService";
import { OptionsFormSubmit } from "./OptionsFormSubmit";

export class OptionsHandler extends BaseService {

	init() {
		this.exec();
	}

	run() {
		new OptionsFormSubmit( this._base_data );
		this.handleOffcanvas();
	}

	handleOffcanvas() {
		$( document ).on( 'click', '.offcanvas_form_mod_cfg', ( evt ) => {
			const data = evt.currentTarget.dataset;
			if ( typeof data.config_item !== 'undefined' && data.config_item.length > 0 ) {
				evt.preventDefault();
				OffCanvasService.RenderCanvas( ObjectOps.Merge( this._base_data.ajax.render_offcanvas, data ) ).finally();
				return false;
			}
		} );
	}
}