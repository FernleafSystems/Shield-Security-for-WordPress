import { announceGlobal } from "../ui/ShieldA11y";

const DEFAULT_DURATION = 5000;

export class WpAdminToastService {

	container = null;
	timers = new WeakMap();

	showMessage( msg, success = false ) {
		const text = String( msg || '' ).trim();
		if ( text.length < 1 ) {
			return;
		}

		const isSuccess = Boolean( success );
		announceGlobal( text, { politeness: isSuccess ? 'polite' : 'assertive' } );

		const container = this.ensureContainer();
		const toast = document.createElement( 'div' );
		toast.className = 'shield-wpadmin-toast ' + ( isSuccess ? 'shield-wpadmin-toast--success' : 'shield-wpadmin-toast--warning' );
		toast.setAttribute( 'role', isSuccess ? 'status' : 'alert' );
		toast.setAttribute( 'aria-live', isSuccess ? 'polite' : 'assertive' );
		toast.setAttribute( 'aria-atomic', 'true' );

		const message = document.createElement( 'div' );
		message.className = 'shield-wpadmin-toast__message';
		message.textContent = text;

		const close = document.createElement( 'button' );
		close.className = 'shield-wpadmin-toast__close';
		close.type = 'button';
		close.setAttribute( 'aria-label', 'Dismiss notification' );
		close.textContent = 'x';
		close.addEventListener( 'click', () => this.dismiss( toast ) );

		toast.append( message, close );
		container.appendChild( toast );

		requestAnimationFrame( () => toast.classList.add( 'shield-wpadmin-toast--visible' ) );

		this.startAutoDismiss( toast );
		toast.addEventListener( 'mouseenter', () => this.clearAutoDismiss( toast ) );
		toast.addEventListener( 'focusin', () => this.clearAutoDismiss( toast ) );
		toast.addEventListener( 'mouseleave', () => this.startAutoDismiss( toast ) );
		toast.addEventListener( 'focusout', () => this.startAutoDismiss( toast ) );
	}

	ensureContainer() {
		if ( this.container instanceof HTMLElement && this.container.isConnected ) {
			return this.container;
		}

		this.container = document.createElement( 'div' );
		this.container.className = 'shield-wpadmin-toast-container';
		this.container.dataset.shieldWpadminToasts = '1';
		document.body.appendChild( this.container );
		return this.container;
	}

	startAutoDismiss( toast ) {
		this.clearAutoDismiss( toast );
		this.timers.set( toast, setTimeout( () => this.dismiss( toast ), DEFAULT_DURATION ) );
	}

	clearAutoDismiss( toast ) {
		const timer = this.timers.get( toast );
		if ( timer ) {
			clearTimeout( timer );
			this.timers.delete( toast );
		}
	}

	dismiss( toast ) {
		this.clearAutoDismiss( toast );
		toast.classList.remove( 'shield-wpadmin-toast--visible' );
		toast.classList.add( 'shield-wpadmin-toast--dismissed' );
		setTimeout( () => toast.remove(), 160 );
	}
}
