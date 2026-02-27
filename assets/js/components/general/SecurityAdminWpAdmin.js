import { SecurityAdminBase } from "./SecurityAdminBase";

export class SecurityAdminWpAdmin extends SecurityAdminBase {

	restrictWPOptions() {
		if ( this._base_data.flags.restrict_options ) {
			this._base_data.vars.wp_options_to_restrict.forEach( ( element ) => {
				Array.from( document.getElementsByName( element ) )
				.filter( ( inputEl ) => inputEl instanceof HTMLInputElement )
				.forEach( ( inputEl ) => {
					inputEl.disabled = true;
					inputEl.closest( 'tr' )?.classList.add( 'restricted-option-row' );

					const td = inputEl.closest( 'td' );
					if ( td ) {
						td.insertAdjacentHTML(
							'beforeend',
							'<div style="clear:both"></div><div class="shield-restricted-option">' +
							'<span class="dashicons dashicons-lock"></span>' +
							this._base_data.strings.editing_restricted +
							' ' + this._base_data.strings.unlock_link +
							'</div>'
						);
					}
				} );
			} );
		}
	};
}
