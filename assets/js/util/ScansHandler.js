import { BaseService } from "./BaseService";
import { MalaiFileScanQuery } from "./MalaiFileScanQuery";
import { ScansCheck } from "./ScansCheck";
import { ScansStart } from "./ScansStart";
import { ScansResults } from "./ScansResults";

export class ScansHandler extends BaseService {
	init() {
		new MalaiFileScanQuery( this._base_data );
		new ScansStart( this._base_data );
		new ScansCheck( this._base_data );
		new ScansResults( this._base_data );
	}
}