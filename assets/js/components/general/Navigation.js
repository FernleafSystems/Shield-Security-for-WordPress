import { Tab } from 'bootstrap';
import { BaseComponent } from "../BaseComponent";

export class Navigation extends BaseComponent {

	init() {
		this.navSideBar = document.getElementById( 'NavSideBar' ) || false;
		this.isModeNavigationPending = false;
		this.exec();
	}

	canRun() {
		return this.navSideBar;
	}

	run() {
		this.navSideBar.addEventListener( 'click', ( evt ) => this.handleModeNavigationClick( evt ) );

		// Add the active nav-tab to the current URL to support refresh.
		shieldEventsHandler_Main.addHandler(
			'shown.bs.tab',
			'#PageMainBody_Inner-Apto .nav-item > .nav-link',
			( targetEl ) => {
				const d = targetEl.dataset;
				if ( 'bsToggle' in d && targetEl.id ) {
					let href = window.location.href;
					window.history.replaceState(
						{},
						'',
						( href.indexOf( '#' ) > 0 ? href.substring( 0, href.indexOf( '#' ) ) : href ) + '#' + targetEl.id
					);
				}
			}
		);

		this.setActiveNavTab( window.location.hash );
		this.fitSidebarToWindow();
	}

	/**
	 * The sidebar's contents stick in view as the page scrolls, and are never taller than the room left in
	 * the window below where they start and above any bar fixed to its bottom. So the items pinned to the
	 * bottom of the nav sit at the bottom of the window, without scrolling the page to reach them.
	 */
	fitSidebarToWindow() {
		const inner = this.navSideBar.closest( '.sidebar-inner' );
		if ( !( inner instanceof HTMLElement ) ) {
			return;
		}
		let queued = false;
		const fit = () => {
			queued = false;
			const bar = document.querySelector( '.shield-setup-guide:not([hidden])' );
			const barHeight = bar instanceof HTMLElement ? bar.getBoundingClientRect().height : 0;
			const top = Math.max( inner.getBoundingClientRect().top, 0 );
			inner.style.setProperty( '--shield-sidebar-max-height', `${Math.max( 240, window.innerHeight - top - barHeight )}px` );
		};
		const queue = () => {
			if ( !queued ) {
				queued = true;
				window.requestAnimationFrame( fit );
			}
		};
		window.addEventListener( 'scroll', queue, { passive: true } );
		window.addEventListener( 'resize', queue );
		new ResizeObserver( queue ).observe( document.body );
		fit();
	}

	handleModeNavigationClick( evt ) {
		const targetEl = evt.target.closest( '.shield-mode-selector a.mode-item[data-mode]' );
		if ( !targetEl || !this.shouldSwapModeNavigationPlaceholder( evt, targetEl ) ) {
			return;
		}

		evt.preventDefault();
		if ( this.isModeNavigationPending ) {
			return;
		}

		this.isModeNavigationPending = true;
		this.swapModeNavigationPlaceholder( targetEl.dataset.mode );
		this.navigateAfterNextPaint( targetEl.href );
	}

	shouldSwapModeNavigationPlaceholder( evt, targetEl ) {
		if ( evt.defaultPrevented ) {
			return false;
		}

		if ( typeof evt.button === 'number' && evt.button !== 0 ) {
			return false;
		}

		if ( evt.metaKey || evt.ctrlKey || evt.shiftKey || evt.altKey ) {
			return false;
		}

		if ( targetEl.target && targetEl.target !== '_self' ) {
			return false;
		}

		return [ 'actions', 'investigate', 'configure', 'reports' ].includes( targetEl.dataset.mode || '' );
	}

	swapModeNavigationPlaceholder( mode ) {
		const placeholder = document.querySelector( `[data-shield-nav-loading-placeholder="${ mode }"]` );
		const pageBody = document.querySelector( '#PageMainBody_Inner-Apto' );
		if ( !placeholder || !pageBody ) {
			return false;
		}

		const placeholderNode = placeholder.cloneNode( true );
		placeholderNode.classList.remove( 'd-none' );
		placeholderNode.removeAttribute( 'aria-hidden' );
		pageBody.replaceChildren( placeholderNode );
		pageBody.setAttribute( 'aria-busy', 'true' );
		window.scrollTo( 0, 0 );
		return true;
	}

	navigateAfterNextPaint( href ) {
		const navigate = () => window.location.assign( href );
		const requestFrame = window.requestAnimationFrame?.bind( window );
		if ( !requestFrame ) {
			window.setTimeout( navigate, 32 );
			return;
		}

		requestFrame( () => requestFrame( navigate ) );
	}

	setActiveNavTab( urlHash = null ) {
		if ( urlHash ) {
			let theTabToShow = document.querySelector( urlHash );
			if ( theTabToShow && theTabToShow.classList.contains( 'nav-link' ) ) {
				( new Tab( theTabToShow ) ).show();
			}
		}
	}
}
