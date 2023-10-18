import $ from 'jquery';
import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { ObjectOps } from "./ObjectOps";

export class ConfigImport extends BaseService {

	init() {
		$( document ).on( "submit", 'form#ImportExportFileForm', ( evt ) => this.#submitForm( evt ) );
	}

	#submitForm( evt ) {
		evt.preventDefault();

		( new AjaxService() )
		.send( ObjectOps.Merge( this._base_data.ajax.import_from_site, { 'form_params': $( evt.currentTarget ).serialize() } ) )
		.finally();

		return false;
	};
}