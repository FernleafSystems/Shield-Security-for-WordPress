import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

/**
 * The "Where should reports go?" step, wherever it's shown (the setup guide, the welcome wizard). Its save
 * button is only there to press once the box holds an address that can be saved, and an address that can't
 * be is pointed out when the box is left, not while it's being typed.
 */
export class ReportEmailStep extends BaseAutoExecComponent {

	run() {
		document.addEventListener( 'input', ( evt ) => {
			const input = this.emailInput( evt.target );
			if ( input !== null ) {
				const save = input.form?.querySelector( '[data-report-email-save="1"]' );
				if ( save instanceof HTMLButtonElement ) {
					save.disabled = !this.isSaveable( input );
				}
				if ( this.isSaveable( input ) ) {
					this.setInvalidNote( input, false );
				}
			}
		} );

		document.addEventListener( 'focusout', ( evt ) => {
			const input = this.emailInput( evt.target );
			if ( input !== null ) {
				this.setInvalidNote( input, input.value.trim().length > 0 && !this.isSaveable( input ) );
			}
		} );
	}

	emailInput( target ) {
		return target instanceof HTMLInputElement && target.matches( '[data-report-email-input="1"]' ) ? target : null;
	}

	isSaveable( input ) {
		return input.value.trim().length > 0 && input.checkValidity();
	}

	setInvalidNote( input, isShown ) {
		const note = input.form?.querySelector( '[data-report-email-invalid="1"]' );
		if ( note instanceof HTMLElement ) {
			note.hidden = !isShown;
		}
	}
}
