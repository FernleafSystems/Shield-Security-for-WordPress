import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

/**
 * The "Choose a protection level" step, wherever it's shown (the setup guide, the welcome wizard). Every level's
 * comparison with the site's settings is already on the page; choosing a level shows its comparison, names it on
 * the apply button and sets it as the form's choice.
 */
export class ProtectionLevelPicker extends BaseAutoExecComponent {

	run() {
		document.addEventListener( 'click', ( evt ) => {
			const choice = evt.target instanceof Element ? evt.target.closest( '[data-protection-level]' ) : null;
			const picker = choice?.closest( '[data-protection-levels="1"]' );
			if ( !( choice instanceof HTMLElement ) || !picker ) {
				return;
			}
			const level = choice.dataset.protectionLevel;
			picker.querySelectorAll( '[data-protection-level]' ).forEach(
				( other ) => other.setAttribute( 'aria-pressed', String( other === choice ) )
			);
			picker.querySelectorAll( '[data-protection-level-comparison]' ).forEach( ( comparison ) => {
				comparison.hidden = comparison.dataset.protectionLevelComparison !== level;
			} );
			picker.querySelector( '[data-protection-levels-choice="1"]' ).value = level;
			picker.querySelector( '[data-protection-levels-apply="1"]' ).textContent = choice.dataset.applyLabel;
		} );

		document.addEventListener( 'change', ( evt ) => {
			const toggle = evt.target;
			if ( toggle instanceof HTMLInputElement && toggle.matches( '[data-protection-levels-only-changes="1"]' ) ) {
				toggle.closest( '[data-protection-levels="1"]' )?.classList.toggle( 'shows-everything', !toggle.checked );
			}
		} );
	}
}
