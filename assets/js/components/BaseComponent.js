export class BaseComponent {

	retrieveBaseData() {
		return this._init_data;
	}

	constructor( props = {} ) {
		this._init_data = props;
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