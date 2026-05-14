import { BaseComponent } from "../BaseComponent";
import { ScansCheck } from "./ScansCheck";
import { ScansStart } from "./ScansStart";

export class ScansHandler extends BaseComponent {
	init() {
		new ScansStart( this._base_data );
		new ScansCheck( this._base_data );
	}
}
