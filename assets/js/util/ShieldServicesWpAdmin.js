import { ShieldServicesBase } from "./ShieldServicesBase";
import { ToastifyService } from "./ToastifyService";

export class ShieldServicesWpAdmin extends ShieldServicesBase {

	static me;

	static Instance() {
		if ( !ShieldServicesWpAdmin.me ) {
			ShieldServicesWpAdmin.me = new ShieldServicesWpAdmin();
		}
		return ShieldServicesWpAdmin.me;
	}

	notification() {
		return new ToastifyService();
	}
}