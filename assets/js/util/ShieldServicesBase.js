import { ShieldOverlay } from "./ShieldOverlay";
import { DivPrinter } from "./DivPrinter";

export class ShieldServicesBase {

	constructor() {
	}

	overlay() {
		return new ShieldOverlay();
	}

	divPrinter() {
		return new DivPrinter();
	}
}