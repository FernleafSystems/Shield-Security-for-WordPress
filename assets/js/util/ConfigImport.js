import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";

export class ConfigImport extends BaseService {

	init() {
		this.form = document.getElementById( 'ImportSiteForm' ) || false;
		this.exec();
	}

	canRun() {
		return this.form;
	}

	run() {
		this.form.addEventListener( 'submit', ( evt ) => {
			evt.preventDefault();

			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.import_from_site, { form_params: Forms.Serialize( evt.currentTarget ) } )
			)
			.finally();

		}, false );
	}
}