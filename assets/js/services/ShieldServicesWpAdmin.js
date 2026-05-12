import { WpAdminToastService } from "../components/toast/WpAdminToastService";
import { AccessibleAdminDialogService } from "./AccessibleAdminDialogService";

export class ShieldServicesWpAdmin {

	static me;
	notificationService = null;
	dialogService = null;

	static Instance() {
		if ( !ShieldServicesWpAdmin.me ) {
			ShieldServicesWpAdmin.me = new ShieldServicesWpAdmin();
		}
		return ShieldServicesWpAdmin.me;
	}

	notification() {
		if ( this.notificationService === null ) {
			this.notificationService = new WpAdminToastService();
		}
		return this.notificationService;
	}

	dialog() {
		if ( this.dialogService === null ) {
			this.dialogService = new AccessibleAdminDialogService();
		}
		return this.dialogService;
	}
}
