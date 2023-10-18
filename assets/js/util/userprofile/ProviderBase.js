import { AjaxService } from "../AjaxService";
import { BaseService } from "../BaseService";

export class ProviderBase extends BaseService {

	isAvailable() {
		return this._base_data && this._base_data.flags.is_available;
	}

	sendReq( params ) {
		( new AjaxService() )
		.send( params )
		.finally();
	};
}