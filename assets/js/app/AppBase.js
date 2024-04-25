import { BaseAutoExecComponent } from "../components/BaseAutoExecComponent";
import { OffCanvasService } from "../components/ui/OffCanvasService";
import { BootstrapTooltips } from "../components/ui/BootstrapTooltips";

export class AppBase extends BaseAutoExecComponent {

	run() {
		window.addEventListener( 'load', () => {
			this.initEvents();
			this.initComponents();
		}, false );
	}

	initComponents() {
		this.components = {};
	}

	initEvents() {
	}

	getComponent( component ) {
		return component in this.components ? this.components[ component ] : null;
	}
}