import { BaseComponent } from "../BaseComponent";

export class BlockPageAutoRecover extends BaseComponent {

	init() {
		this.checkbox = document.querySelector( '[data-shield-autorecover-confirm="1"]' ) || false;
		this.submit = document.querySelector( '[data-shield-autorecover-submit="1"]' ) || false;
		this.exec();
	}

	canRun() {
		return this.checkbox && this.submit;
	}

	run() {
		const syncSubmitState = () => {
			this.submit.toggleAttribute( 'disabled', !this.checkbox.checked );
		};

		this.checkbox.addEventListener( 'change', syncSubmitState, false );
		syncSubmitState();
	}
}
