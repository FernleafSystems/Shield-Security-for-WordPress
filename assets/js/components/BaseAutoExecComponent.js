import { BaseComponent } from "./BaseComponent";

export class BaseAutoExecComponent extends BaseComponent {

	init() {
		this.exec();
	}
}