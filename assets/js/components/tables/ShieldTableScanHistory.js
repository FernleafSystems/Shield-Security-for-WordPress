import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableScanHistory extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SecurityScanHistory';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brptip';
		return cfg;
	}
}