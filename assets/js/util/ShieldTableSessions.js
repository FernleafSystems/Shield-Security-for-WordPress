import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableSessions extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-SessionsViewer';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.dom = 'Brpftip';
		return cfg;
	}
}