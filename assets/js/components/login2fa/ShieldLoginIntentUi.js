import { BaseComponent } from "../BaseComponent";

export class ShieldLoginIntentUi extends BaseComponent {

	init() {
		this.form = document.querySelector( 'form#loginform.shield-2fa-custom' );
		this.tabs = Array.from( document.querySelectorAll( '.mfa-tab' ) );
		this.panes = Array.from( document.querySelectorAll( '.mfa-pane' ) );
		this.exec();
	}

	canRun() {
		return this.form instanceof HTMLFormElement && this.tabs.length > 0 && this.panes.length > 0;
	}

	run() {
		this.tabs.forEach( ( tab, index ) => {
			tab.addEventListener( 'click', () => this.activateTab( tab ) );
			tab.addEventListener( 'keydown', ( event ) => this.onTabKeydown( event, index ) );
		} );

		if ( !this.shouldSkipFocus() ) {
			window.setTimeout( () => this.focusActivePane(), 80 );
		}
	}

	onTabKeydown( event, index ) {
		let nextIndex = null;

		if ( event.key === 'ArrowRight' || event.key === 'ArrowDown' ) {
			nextIndex = ( index + 1 ) % this.tabs.length;
		}
		else if ( event.key === 'ArrowLeft' || event.key === 'ArrowUp' ) {
			nextIndex = ( index - 1 + this.tabs.length ) % this.tabs.length;
		}
		else if ( event.key === 'Home' ) {
			nextIndex = 0;
		}
		else if ( event.key === 'End' ) {
			nextIndex = this.tabs.length - 1;
		}

		if ( nextIndex !== null ) {
			event.preventDefault();
			this.tabs[ nextIndex ].focus();
			this.activateTab( this.tabs[ nextIndex ] );
		}
	}

	activateTab( tabToActivate ) {
		if ( !( tabToActivate instanceof HTMLButtonElement ) ) {
			return;
		}

		const target = tabToActivate.dataset.tab;

		this.tabs.forEach( ( tab ) => {
			const isActive = tab === tabToActivate;
			tab.classList.toggle( 'active', isActive );
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', isActive ? '0' : '-1' );
		} );

		this.panes.forEach( ( pane ) => {
			const isActive = pane.dataset.pane === target;
			pane.classList.toggle( 'active', isActive );
			pane.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
		} );

		if ( !this.shouldSkipFocus() ) {
			window.setTimeout( () => this.focusActivePane(), 80 );
		}
	}

	focusActivePane() {
		const activePane = document.querySelector( '.mfa-pane.active' );
		if ( !( activePane instanceof HTMLElement ) ) {
			return;
		}

		const focusTarget = this.findVisible( activePane, '[data-otp]:not(.filled)' )
							|| this.findVisible( activePane, '[data-otp]' )
							|| this.findVisible( activePane, 'button[name="icwp_wpsf_start_passkey"]' )
							|| this.findVisible( activePane, '.mfa-yubi-input' )
							|| this.findVisible( activePane, '.mfa-fallback-input:not([type="hidden"])' );

		if ( focusTarget instanceof HTMLElement && typeof focusTarget.focus === 'function' ) {
			focusTarget.focus();
			if ( typeof focusTarget.select === 'function'
				&& ( focusTarget.matches( '[data-otp]' ) || focusTarget.matches( '.mfa-yubi-input' ) ) ) {
				focusTarget.select();
			}
		}
	}

	findVisible( root, selector ) {
		return Array.from( root.querySelectorAll( selector ) )
			.find( ( element ) => element.closest( '[hidden]' ) === null ) || null;
	}

	shouldSkipFocus() {
		return this._base_data?.flags?.passkey_auth_auto === true
			&& this.panes.length === 1
			&& this.panes[ 0 ]?.dataset?.pane === 'passkey';
	}
}
