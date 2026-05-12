import { ToasterService } from "../components/toast/ToasterService";
import { BaseService } from "./BaseService";
import { ShieldAdminDialogService } from "./ShieldAdminDialogService";

export class ShieldServicesPlugin extends BaseService {

	dialogService = null;

	notification() {
		return new ToasterService();
	}

	dialog() {
		if ( this.dialogService === null ) {
			this.dialogService = new ShieldAdminDialogService();
		}
		return this.dialogService;
	}

	container_ShieldPage() {
		return document.getElementById( 'PageContainer-Apto' ) || false;
	}
}
