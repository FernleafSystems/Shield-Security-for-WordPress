import { BaseService } from "./BaseService";

export class ShieldStrings extends BaseService {

	string( str ) {
		return this.strings()[ str ];
	}

	strings() {
		return this._base_data;
	}
}