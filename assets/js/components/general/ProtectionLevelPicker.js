import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

/**
 * The "Choose a protection level" step, wherever it's shown (the setup guide, the welcome wizard). Every level's
 * comparison with the site's settings is already on the page; choosing a level shows its comparison, names it on
 * the apply button and sets it as the form's choice. Only what a level would change is listed, until the count
 * of what it leaves alone is clicked.
 */
export class ProtectionLevelPicker extends BaseAutoExecComponent {

	run() {
		document.addEventListener( 'click', ( evt ) => {
			const target = evt.target instanceof Element ? evt.target : null;
			const picker = target?.closest( '[data-protection-levels="1"]' );
			if ( !picker ) {
				return;
			}

			const choice = target.closest( '[data-protection-level]' );
			if ( choice instanceof HTMLElement ) {
				const level = choice.dataset.protectionLevel;
				picker.querySelectorAll( '[data-protection-level]' ).forEach(
					( other ) => other.setAttribute( 'aria-pressed', String( other === choice ) )
				);
				picker.querySelectorAll( '[data-protection-level-comparison]' ).forEach( ( comparison ) => {
					comparison.hidden = comparison.dataset.protectionLevelComparison !== level;
				} );
				picker.querySelector( '[data-protection-levels-choice="1"]' ).value = level;
				const apply = picker.querySelector( '[data-protection-levels-apply="1"]' );
				apply.textContent = choice.dataset.applyLabel;
				apply.disabled = false;
			}
			else if ( target.closest( '[data-protection-levels-show-unchanged="1"]' ) ) {
				// One choice for the whole step, so it holds from one level's list to the next.
				const showsEverything = picker.classList.toggle( 'shows-everything' );
				picker.querySelectorAll( '[data-protection-levels-show-unchanged="1"]' ).forEach(
					( toggle ) => toggle.setAttribute( 'aria-pressed', String( showsEverything ) )
				);
			}
		} );
	}
}
