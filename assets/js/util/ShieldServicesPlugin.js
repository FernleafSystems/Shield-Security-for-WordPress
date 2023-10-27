import { ToasterService } from "./ToasterService";
import { BaseService } from "./BaseService";

export class ShieldServicesPlugin extends BaseService {

	notification() {
		return new ToasterService();
	}

	string( str ) {
		return this.strings()[ str ];
	}

	strings() {
		return this._base_data.strings;
	}
}