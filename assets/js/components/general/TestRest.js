import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { RestService } from "../services/RestService";

export class TestRest extends BaseAutoExecComponent {

	canRun() {
		return this._base_data.flags.can_run;
	}

	run() {
		( new RestService() )
		.req( this._base_data.ajax.test_rest )
		.then( resp => console.log( resp ) )
	}
}