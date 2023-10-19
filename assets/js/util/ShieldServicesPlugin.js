import { ToasterService } from "./ToasterService";

export class ShieldServicesPlugin {

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