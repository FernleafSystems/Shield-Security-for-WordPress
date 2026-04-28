import { BootstrapModals } from "./BootstrapModals";
import { focusElement } from "./ShieldA11y";

const DIALOG_ID = 'AptoGeneralPurposeDialog';
const TITLE_ID = 'AptoGeneralPurposeDialogTitle';
const MESSAGE_ID = 'AptoGeneralPurposeDialogMessage';
const DIALOG_Z_INDEX = '100000';
let pendingDialogPromise = null;

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
	if ( pendingDialogPromise !== null ) {
		return Promise.resolve( false );
	}

	const dialogElements = resolveDialogElements();
	if ( dialogElements === null ) {
		return Promise.resolve( false );
	}
	const { confirmButton, cancelButton } = dialogElements;

	prepareDialogContent(
		dialogElements,
		normalizeText( title ) || normalizeString( 'confirm_title', 'Confirm Action' ),
		message
	);
	setDialogFooterMode( dialogElements, {
		confirmLabel: normalizeText( confirmLabel ) || normalizeString( 'confirm', 'Confirm' ),
		cancelLabel: normalizeText( cancelLabel ) || normalizeString( 'cancel', 'Cancel' ),
		showCancel: true,
		danger,
	} );

	const confirmPromise = openDialog( dialogElements, {
		launcher,
		focusTarget: danger ? cancelButton : confirmButton,
		resolveValue: false,
		handlers: {
			confirm: () => true,
			cancel: () => false,
		},
	} );

	return confirmPromise;
}

export function messageDialog( {
	title = '',
	message = '',
	confirmLabel = '',
	launcher = null,
} = {} ) {
	if ( pendingDialogPromise !== null ) {
		return Promise.resolve();
	}

	const dialogElements = resolveDialogElements();
	if ( dialogElements === null ) {
		return Promise.resolve();
	}
	const { confirmButton } = dialogElements;

	prepareDialogContent(
		dialogElements,
		normalizeText( title ) || normalizeString( 'message_title', 'Message' ),
		message
	);
	setDialogFooterMode( dialogElements, {
		confirmLabel: normalizeText( confirmLabel ) || normalizeString( 'close', 'Close' ),
		cancelLabel: normalizeString( 'cancel', 'Cancel' ),
		showCancel: false,
		danger: false,
	} );

	return openDialog( dialogElements, {
		launcher,
		focusTarget: confirmButton,
		resolveValue: undefined,
		handlers: {
			confirm: () => undefined,
			cancel: () => undefined,
		},
	} );
}

function resolveDialogElements() {
	const dialog = document.getElementById( DIALOG_ID );
	if ( !( dialog instanceof HTMLElement ) ) {
		return null;
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
		return null;
	}

	return {
		dialog,
		titleEl,
		messageEl,
		confirmButton,
		cancelButton,
	};
}

function prepareDialogContent( dialogElements, title, message ) {
	const { dialog, titleEl, messageEl } = dialogElements;
	const normalizedMessage = normalizeText( message );
	titleEl.textContent = normalizeText( title );
	messageEl.textContent = normalizedMessage;

	dialog.setAttribute( 'aria-labelledby', TITLE_ID );
	if ( normalizedMessage.length > 0 ) {
		dialog.setAttribute( 'aria-describedby', MESSAGE_ID );
	}
	else {
		dialog.removeAttribute( 'aria-describedby' );
	}
}

function setDialogFooterMode( dialogElements, {
	confirmLabel,
	cancelLabel,
	showCancel,
	danger,
} ) {
	const { confirmButton, cancelButton } = dialogElements;
	confirmButton.textContent = confirmLabel;
	cancelButton.textContent = normalizeText( cancelLabel ) || normalizeString( 'cancel', 'Cancel' );
	confirmButton.classList.toggle( 'btn-danger', danger );
	confirmButton.classList.toggle( 'btn-primary', !danger );
	cancelButton.hidden = !showCancel;
	cancelButton.disabled = !showCancel;
}

function openDialog( dialogElements, {
	launcher = null,
	focusTarget,
	resolveValue,
	handlers,
} ) {
	const { dialog, confirmButton, cancelButton } = dialogElements;
	let dialogValue = resolveValue;

	let resolveDialog = ( value ) => {
		void value;
	};
	const dialogPromise = new Promise( ( resolve ) => {
		resolveDialog = resolve;
	} );
	pendingDialogPromise = dialogPromise;
	let isResolved = false;
	const finish = () => {
		if ( isResolved ) {
			return;
		}
		isResolved = true;
		BootstrapModals.Hide( dialog );
	};
	const cleanup = () => {
		confirmButton.removeEventListener( 'click', confirmHandler );
		cancelButton.removeEventListener( 'click', cancelHandler );
		dialog.removeEventListener( 'hidden.bs.modal', hiddenHandler );
		dialog.removeEventListener( 'shown.bs.modal', shownHandler );
		setDialogFooterMode( dialogElements, {
			confirmLabel: normalizeString( 'confirm', 'Confirm' ),
			cancelLabel: normalizeString( 'cancel', 'Cancel' ),
			showCancel: true,
			danger: false,
		} );
	};
	const confirmHandler = () => {
		dialogValue = handlers.confirm();
		finish();
	};
	const cancelHandler = () => {
		dialogValue = handlers.cancel();
		finish();
	};
	const hiddenHandler = () => {
		if ( !isResolved ) {
			dialogValue = handlers.cancel();
			isResolved = true;
		}
		cleanup();
		pendingDialogPromise = null;
		resolveDialog( dialogValue );
	};
	const shownHandler = () => {
		focusElement( focusTarget );
	};

	dialog.addEventListener( 'shown.bs.modal', shownHandler );
	dialog.addEventListener( 'hidden.bs.modal', hiddenHandler );
	confirmButton.addEventListener( 'click', confirmHandler );
	cancelButton.addEventListener( 'click', cancelHandler );

	if ( launcher instanceof HTMLElement && !dialog.contains( launcher ) ) {
		launcher.focus();
	}

	if ( !BootstrapModals.Show( dialog ) ) {
		cleanup();
		pendingDialogPromise = null;
		resolveDialog( handlers.cancel() );
	}

	return dialogPromise;
}

function normalizeString( key, fallback ) {
	return typeof shieldStrings !== 'undefined' && typeof shieldStrings.string === 'function'
		? normalizeText( shieldStrings.string( key ) ) || fallback
		: fallback;
}

function normalizeText( value ) {
	return String( value || '' ).trim();
}
