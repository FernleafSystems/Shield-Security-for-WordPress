import { ToastifyService } from "../components/toast/ToastifyService";

export class ShieldServicesWpAdmin {

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