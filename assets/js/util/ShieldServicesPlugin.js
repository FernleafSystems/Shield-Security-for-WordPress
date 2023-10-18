import { OffCanvasService } from "./OffCanvasService";
import { ShieldServicesBase } from "./ShieldServicesBase";
import { ToasterService } from "./ToasterService";

export class ShieldServicesPlugin extends ShieldServicesBase {

	static me;

	static Instance() {
		if ( !ShieldServicesPlugin.me ) {
			ShieldServicesPlugin.me = new ShieldServicesPlugin();
			// ShieldServicesPlugin.me.offCanvas();
		}
		return ShieldServicesPlugin.me;
	}

	notification() {
		return new ToasterService();
	}
}