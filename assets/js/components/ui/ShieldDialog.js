import { BootstrapModals } from "./BootstrapModals";
import { focusElement } from "./ShieldA11y";

const DIALOG_ID = 'AptoGeneralPurposeDialog';
const TITLE_ID = 'AptoGeneralPurposeDialogTitle';
const MESSAGE_ID = 'AptoGeneralPurposeDialogMessage';
const DIALOG_Z_INDEX = '100000';
let pendingConfirmPromise = null;

export function resolveDialogLauncher( event = null, node = null ) {
	if ( event?.currentTarget instanceof HTMLElement ) {
		return event.currentTarget;
	}
	if ( node?.[ 0 ] instanceof HTMLElement ) {
		return node[ 0 ];
	}
	if ( node instanceof HTMLElement ) {
		return node;
	}
	return null;
}

export function resolveDialogConfirmLabel( launcher = null ) {
	if ( !( launcher instanceof HTMLElement ) ) {
		return '';
	}

	return [
		launcher.getAttribute( 'aria-label' ),
		launcher.getAttribute( 'title' ),
		launcher.textContent,
	]
	.map( ( label ) => normalizeText( label ) )
	.find( ( label ) => label.length > 0 ) || '';
}

export function confirmDialog( {
	title = '',
	message = '',
	confirmLabel = '',
	cancelLabel = '',
	danger = false,
	launcher = null,
} = {} ) {
	if ( pendingConfirmPromise !== null ) {
		return Promise.resolve( false );
	}

	const dialog = document.getElementById( DIALOG_ID );
	if ( !( dialog instanceof HTMLElement ) ) {
		return Promise.resolve( false );
	}
	if ( dialog.parentElement !== document.body ) {
		document.body.appendChild( dialog );
	}
	dialog.style.setProperty( 'z-index', DIALOG_Z_INDEX, 'important' );

	const titleEl = dialog.querySelector( `#${TITLE_ID}` );
	const messageEl = dialog.querySelector( `#${MESSAGE_ID}` );
	const confirmButton = dialog.querySelector( '[data-shield-dialog-confirm="1"]' );
	const cancelButton = dialog.querySelector( '[data-shield-dialog-cancel="1"]' );
	if (
		!( titleEl instanceof HTMLElement )
		|| !( messageEl instanceof HTMLElement )
		|| !( confirmButton instanceof HTMLButtonElement )
		|| !( cancelButton instanceof HTMLButtonElement )
	) {
		return Promise.resolve( false );
	}

	const normalizedTitle = normalizeText( title ) || normalizeString( 'confirm_title', 'Confirm Action' );
	const normalizedMessage = normalizeText( message );
	titleEl.textContent = normalizedTitle;
	messageEl.textContent = normalizedMessage;
	confirmButton.textContent = normalizeText( confirmLabel ) || normalizeString( 'confirm', 'Confirm' );
	cancelButton.textContent = normalizeText( cancelLabel ) || normalizeString( 'cancel', 'Cancel' );
	confirmButton.classList.toggle( 'btn-danger', danger );
	confirmButton.classList.toggle( 'btn-primary', !danger );

	dialog.setAttribute( 'aria-labelledby', TITLE_ID );
	if ( normalizedMessage.length > 0 ) {
		dialog.setAttribute( 'aria-describedby', MESSAGE_ID );
	}
	else {
		dialog.removeAttribute( 'aria-describedby' );
	}

	let resolveConfirm;
	const confirmPromise = new Promise( ( resolve ) => {
		resolveConfirm = resolve;
	} );
	pendingConfirmPromise = confirmPromise;
	let isResolved = false;
	const finish = ( value ) => {
		if ( isResolved ) {
			return;
		}
		isResolved = true;
		cleanup();
		pendingConfirmPromise = null;
		resolveConfirm( value );
	};
	const cleanup = () => {
		confirmButton.removeEventListener( 'click', confirmHandler );
		cancelButton.removeEventListener( 'click', cancelHandler );
		dialog.removeEventListener( 'hidden.bs.modal', hiddenHandler );
		dialog.removeEventListener( 'shown.bs.modal', shownHandler );
	};
	const confirmHandler = () => {
		finish( true );
		BootstrapModals.Hide( dialog );
	};
	const cancelHandler = () => {
		finish( false );
		BootstrapModals.Hide( dialog );
	};
	const hiddenHandler = () => finish( false );
	const shownHandler = () => {
		focusElement( danger ? cancelButton : confirmButton );
	};

	dialog.addEventListener( 'shown.bs.modal', shownHandler );
	dialog.addEventListener( 'hidden.bs.modal', hiddenHandler );
	confirmButton.addEventListener( 'click', confirmHandler );
	cancelButton.addEventListener( 'click', cancelHandler );

	if ( launcher instanceof HTMLElement && !dialog.contains( launcher ) ) {
		launcher.focus();
	}

	if ( !BootstrapModals.Show( dialog ) ) {
		finish( false );
	}

	return confirmPromise;
}

function normalizeString( key, fallback ) {
	return typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
		? normalizeText( shieldStrings.string( key ) ) || fallback
		: fallback;
}

function normalizeText( value ) {
	return String( value || '' ).trim();
}
