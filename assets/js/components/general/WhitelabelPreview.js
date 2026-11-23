import { BaseAutoExecComponent } from "../BaseAutoExecComponent";

/**
 * The White Label page's preview follows its form as you type: each preview element names the field it
 * shows, a fallback field for when that one is empty, and for a link, the field holding its address. Logos
 * update only for a full address; anything else shows once saved, as Shield resolves it then.
 */
export class WhitelabelPreview extends BaseAutoExecComponent {

	canRun() {
		return document.querySelector( '[data-whitelabel-preview="1"]' ) !== null;
	}

	run() {
		document.addEventListener( 'input', ( evt ) => {
			if ( evt.target instanceof Element && evt.target.closest( '[data-shield-whitelabel="1"] form' ) ) {
				this.sync();
			}
		} );
	}

	sync() {
		const page = document.querySelector( '[data-shield-whitelabel="1"]' );
		if ( !( page instanceof HTMLElement ) ) {
			return;
		}
		const value = ( name ) => {
			const field = page.querySelector( `form [name="${name}"]` );
			return field instanceof HTMLInputElement ? field.value.trim() : '';
		};
		page.querySelectorAll( '[data-wl-preview]' ).forEach( ( el ) => {
			const shown = value( el.dataset.wlPreview || '' ) || value( el.dataset.wlFallback || '' );
			if ( el instanceof HTMLImageElement ) {
				/^https?:\/\//i.test( shown ) && ( el.src = shown );
			}
			else {
				el.textContent = shown;
			}
			if ( el instanceof HTMLAnchorElement && el.dataset.wlHref ) {
				el.href = value( el.dataset.wlHref ) || '#';
			}
		} );
	}
}
