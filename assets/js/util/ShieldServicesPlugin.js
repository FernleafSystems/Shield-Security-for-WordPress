import { ToasterService } from "./ToasterService";
import { BaseService } from "./BaseService";

export class ShieldServicesPlugin extends BaseService {

	notification() {
		return new ToasterService();
	}
}