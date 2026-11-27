import { BaseAutoExecComponent } from "../components/BaseAutoExecComponent";

export class AppBase extends BaseAutoExecComponent {

	/**
	 * Starts once the page is parsed. Nothing the page draws responds until then, so it doesn't wait for the
	 * browser's load event, which also waits for every image, frame and script on the page, other plugins' included.
	 */
	run() {
		const start = () => {
			this.initEvents();
			this.initComponents();
		};
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', start, { once: true } );
		}
		else {
			start();
		}
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