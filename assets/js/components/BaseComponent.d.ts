export class BaseComponent<TBaseData = Record<string, any>> {
	protected _init_data: TBaseData;
	protected _base_data: TBaseData;

	constructor( props?: TBaseData );

	retrieveBaseData() :TBaseData;

	init() :void;

	run() :void;

	exec() :void;

	canRun() :boolean;
}
