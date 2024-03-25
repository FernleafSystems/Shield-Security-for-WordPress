import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableScansHistory extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SecurityScansHistory';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brptip';
		return cfg;
	}
}