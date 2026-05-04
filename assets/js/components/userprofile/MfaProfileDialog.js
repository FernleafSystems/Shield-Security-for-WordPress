import A11yDialog from 'a11y-dialog';
import { focusElement } from '../ui/ShieldA11y';

const DIALOG_ID = 'ShieldMfaDialog';
const TITLE_ID = 'ShieldMfaDialogTitle';
const MESSAGE_ID = 'ShieldMfaDialogMessage';
const INPUT_ID = 'ShieldMfaDialogInput';
const VALIDATION_ID = 'ShieldMfaDialogValidation';

let dialogInstance = null;
let dialogEl = null;
let pendingResolver = null;
let pendingLauncher = null;
let pendingResult = null;
let currentConfig = null;

function dialogStrings() {
	return window.shield_vars_userprofile?.comps?.userprofile?.strings || {};
}

function profileForm() {
	return document.getElementById( 'ShieldMfaUserProfileForm' );
}

function isFocusableLauncher( element ) {
	return element instanceof HTMLElement
		&& element.isConnected
		&& !element.hasAttribute( 'disabled' )
		&& element.getAttribute( 'aria-hidden' ) !== 'true';
}

function restoreFocus() {
	if ( isFocusableLauncher( pendingLauncher ) && focusElement( pendingLauncher ) ) {
		return;
	}
	focusElement( profileForm() );
}

function ensureDialog() {
	if ( dialogInstance !== null && dialogEl !== null ) {
		return { dialog: dialogInstance, element: dialogEl };
	}

	dialogEl = document.createElement( 'div' );
	dialogEl.id = DIALOG_ID;
	dialogEl.dataset.shieldMfaDialog = '1';
	dialogEl.setAttribute( 'aria-hidden', 'true' );
	dialogEl.setAttribute( 'aria-labelledby', TITLE_ID );
	dialogEl.innerHTML = `
		<div class="shield-mfa-dialog__overlay" data-a11y-dialog-hide></div>
		<div class="shield-mfa-dialog__surface" role="document">
			<h2 id="${TITLE_ID}" class="shield-mfa-dialog__title"></h2>
			<div id="${MESSAGE_ID}" class="shield-mfa-dialog__message"></div>
			<div class="shield-mfa-dialog__field" hidden>
				<label id="ShieldMfaDialogInputLabel" for="${INPUT_ID}"></label>
				<input id="${INPUT_ID}" type="text" autocomplete="off" />
			</div>
			<div id="${VALIDATION_ID}" class="shield-mfa-dialog__validation" role="alert" hidden></div>
			<div class="shield-mfa-dialog__actions">
				<button type="button" class="button shield-mfa-dialog__cancel" data-a11y-dialog-hide></button>
				<button type="button" class="button button-primary shield-mfa-dialog__confirm"></button>
			</div>
		</div>
	`;
	document.body.appendChild( dialogEl );

	dialogInstance = new A11yDialog( dialogEl );
	dialogInstance.on( 'hide', () => {
		const resolver = pendingResolver;
		const result = pendingResult;
		const launcher = pendingLauncher;
		pendingResolver = null;
		pendingResult = null;
		pendingLauncher = null;

		window.setTimeout( () => {
			if ( resolver ) {
				resolver( result );
			}
			pendingLauncher = launcher;
			restoreFocus();
			pendingLauncher = null;
		}, 0 );
	} );

	dialogEl.querySelector( '.shield-mfa-dialog__confirm' ).addEventListener( 'click', () => {
		submitCurrentDialog();
	} );
	dialogEl.querySelector( `#${INPUT_ID}` ).addEventListener( 'keydown', ( evt ) => {
		if ( evt.key === 'Enter' ) {
			evt.preventDefault();
			submitCurrentDialog();
		}
	} );

	return { dialog: dialogInstance, element: dialogEl };
}

function setText( selector, value ) {
	const el = dialogEl.querySelector( selector );
	el.textContent = value || '';
	return el;
}

function normalizedText( value ) {
	return String( value || '' ).trim();
}

function setCancelAction( button, isVisible, label, shouldAutofocus = false ) {
	button.textContent = isVisible ? label : '';
	button.hidden = !isVisible;
	button.disabled = !isVisible;
	button.toggleAttribute( 'autofocus', isVisible && shouldAutofocus );
	if ( isVisible ) {
		button.removeAttribute( 'aria-hidden' );
	}
	else {
		button.setAttribute( 'aria-hidden', 'true' );
	}
}

function normalizeDialogConfig( config ) {
	const type = [ 'alert', 'confirm', 'prompt' ].includes( config.type ) ? config.type : 'alert';
	const localizedStrings = dialogStrings();
	const titleFallbacks = {
		alert: localizedStrings.dialog_alert_title,
		confirm: localizedStrings.dialog_confirm_title,
		prompt: localizedStrings.dialog_prompt_title,
	};

	const normalized = {
		...config,
		type,
		title: normalizedText( config.title || titleFallbacks[ type ] ),
		message: normalizedText( config.message ),
		label: type === 'prompt' ? normalizedText( config.label ) : '',
		confirmLabel: normalizedText(
			config.confirmLabel || ( type === 'alert' ? localizedStrings.continue : localizedStrings.confirm )
		),
		cancelLabel: type === 'alert' ? '' : normalizedText( config.cancelLabel || localizedStrings.cancel ),
	};

	if ( normalized.title.length < 1 || normalized.confirmLabel.length < 1 ) {
		throw new Error( 'MFA profile dialog requires a non-empty title and confirm label.' );
	}
	if ( type !== 'alert' && normalized.cancelLabel.length < 1 ) {
		throw new Error( 'MFA profile dialog requires a non-empty cancel label when cancel is visible.' );
	}
	if ( type === 'prompt' && normalized.label.length < 1 ) {
		throw new Error( 'MFA profile dialog requires a non-empty prompt label.' );
	}

	return normalized;
}

function resetValidation() {
	const input = dialogEl.querySelector( `#${INPUT_ID}` );
	const validation = dialogEl.querySelector( `#${VALIDATION_ID}` );
	input.removeAttribute( 'aria-invalid' );
	input.removeAttribute( 'aria-describedby' );
	validation.hidden = true;
	validation.textContent = '';
}

function showValidation( message ) {
	const input = dialogEl.querySelector( `#${INPUT_ID}` );
	const validation = dialogEl.querySelector( `#${VALIDATION_ID}` );
	input.setAttribute( 'aria-invalid', 'true' );
	input.setAttribute( 'aria-describedby', VALIDATION_ID );
	validation.hidden = false;
	validation.textContent = message || '';
	focusElement( input );
}

function configureDialog( config ) {
	const { element } = ensureDialog();
	element.classList.toggle( 'shield-mfa-dialog--danger', config.danger === true );
	setText( `#${TITLE_ID}`, config.title );
	setText( `#${MESSAGE_ID}`, config.message );
	setText( '.shield-mfa-dialog__confirm', config.confirmLabel );
	const cancelButton = element.querySelector( '.shield-mfa-dialog__cancel' );
	setCancelAction( cancelButton, config.type !== 'alert', config.cancelLabel );
	resetValidation();

	const field = element.querySelector( '.shield-mfa-dialog__field' );
	const input = element.querySelector( `#${INPUT_ID}` );
	const label = element.querySelector( '#ShieldMfaDialogInputLabel' );
	if ( config.type === 'prompt' ) {
		field.hidden = false;
		label.textContent = config.label;
		input.value = config.value || '';
		input.setAttribute( 'autofocus', 'autofocus' );
		cancelButton.removeAttribute( 'autofocus' );
	}
	else {
		field.hidden = true;
		input.value = '';
		input.removeAttribute( 'autofocus' );
		setCancelAction( cancelButton, config.type !== 'alert', config.cancelLabel, config.danger === true );
	}

	currentConfig = config;
}

function submitCurrentDialog() {
	const config = currentConfig;
	if ( !config ) {
		return;
	}

	if ( config.type === 'prompt' ) {
		const input = dialogEl.querySelector( `#${INPUT_ID}` );
		const value = input.value;
		const validation = typeof config.validate === 'function' ? config.validate( value ) : true;
		if ( validation !== true ) {
			showValidation( String( validation || '' ) );
			return;
		}
		pendingResult = value;
	}
	else if ( config.type === 'confirm' ) {
		pendingResult = true;
	}
	else {
		pendingResult = undefined;
	}

	dialogInstance.hide();
}

function showProfileDialog( config, cancelValue ) {
	const { dialog } = ensureDialog();
	if ( pendingResolver !== null ) {
		return Promise.resolve( cancelValue );
	}

	config = normalizeDialogConfig( config );
	pendingLauncher = config.launcher instanceof HTMLElement ? config.launcher : document.activeElement;
	pendingResult = cancelValue;
	configureDialog( config );

	return new Promise( ( resolve ) => {
		pendingResolver = resolve;
		dialog.show();
	} );
}

export function mfaConfirm( config ) {
	const localizedStrings = dialogStrings();
	return showProfileDialog( {
		...config,
		type: 'confirm',
		title: config.title || localizedStrings.dialog_confirm_title,
		confirmLabel: config.confirmLabel || localizedStrings.confirm,
		cancelLabel: config.cancelLabel || localizedStrings.cancel,
	}, false );
}

export function mfaPrompt( config ) {
	const localizedStrings = dialogStrings();
	return showProfileDialog( {
		...config,
		type: 'prompt',
		title: config.title || localizedStrings.dialog_prompt_title,
		confirmLabel: config.confirmLabel || localizedStrings.confirm,
		cancelLabel: config.cancelLabel || localizedStrings.cancel,
	}, null );
}

export function mfaAlert( config ) {
	const localizedStrings = dialogStrings();
	return showProfileDialog( {
		...config,
		type: 'alert',
		title: config.title || localizedStrings.dialog_alert_title,
		confirmLabel: config.confirmLabel || localizedStrings.continue,
	}, undefined );
}

export function isValidMfaDeviceLabel( value ) {
	return typeof value === 'string' && ( new RegExp( "^[\\s\\da-zA-Z_-]{1,16}$" ) ).test( value );
}
