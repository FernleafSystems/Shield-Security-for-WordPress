export class BaseService {

	retrieveBaseData() {
		return this._init_data;
	}

	constructor( initData = {} ) {
		this._init_data = initData;
		this._base_data = this.retrieveBaseData();
		this.init();
	}

	init() {
	}

	run() {
	}

	exec() {
		this.canRun() && this.run();
	}

	canRun() {
		return true;
	}
}