import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class AjaxRenderService extends BaseService {

	render( reqData, showOverlay = true ) {
		return ( new AjaxService() ).send( reqData, showOverlay );
	};
}