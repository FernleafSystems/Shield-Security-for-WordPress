import { ToastifyService } from "../components/toast/ToastifyService";
import { AccessibleAdminDialogService } from "./AccessibleAdminDialogService";

export class ShieldServicesWpAdmin {

	static me;
	dialogService = null;

	static Instance() {
		if ( !ShieldServicesWpAdmin.me ) {
			ShieldServicesWpAdmin.me = new ShieldServicesWpAdmin();
		}
		return ShieldServicesWpAdmin.me;
	}

	notification() {
		return new ToastifyService();
	}

	dialog() {
		if ( this.dialogService === null ) {
			this.dialogService = new AccessibleAdminDialogService();
		}
		return this.dialogService;
	}
}
