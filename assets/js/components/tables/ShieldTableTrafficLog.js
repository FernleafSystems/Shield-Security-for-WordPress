import { ShieldTableBase } from "./ShieldTableBase";

export class ShieldTableTrafficLog extends ShieldTableBase {

	getTableSelector() {
		return '#ShieldTable-TrafficViewer';
	}

	buildDatatableConfig() {
		let cfg = super.buildDatatableConfig();
		cfg.select = {
			style: 'api'
		};
		return cfg;
	}
}