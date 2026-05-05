import { AccessibleDialog } from '../ui/AccessibleDialog';

const DIALOG_ID = 'ShieldMfaDialog';
const TITLE_ID = 'ShieldMfaDialogTitle';
const MESSAGE_ID = 'ShieldMfaDialogMessage';
const INPUT_ID = 'ShieldMfaDialogInput';
const VALIDATION_ID = 'ShieldMfaDialogValidation';

let profileDialog = null;

function dialogStrings() {
	return window.shield_vars_userprofile?.comps?.userprofile?.strings || {};
}

function profileForm() {
	return document.getElementById( 'ShieldMfaUserProfileForm' );
}

function dialog() {
	if ( profileDialog === null ) {
		profileDialog = new AccessibleDialog( {
			id: DIALOG_ID,
			titleId: TITLE_ID,
			messageId: MESSAGE_ID,
			inputId: INPUT_ID,
			inputLabelId: 'ShieldMfaDialogInputLabel',
			validationId: VALIDATION_ID,
			datasetKey: 'shieldMfaDialog',
			classPrefix: 'shield-mfa-dialog',
			stringsProvider: dialogStrings,
			fallbackFocus: profileForm,
			errorContext: 'MFA profile dialog',
			alertConfirmLabelKeys: [ 'continue', 'close' ],
		} );
	}
	return profileDialog;
}

export function mfaConfirm( config = {} ) {
	const localizedStrings = dialogStrings();
	return dialog().confirm( {
		...config,
		title: config.title || localizedStrings.dialog_confirm_title,
		confirmLabel: config.confirmLabel || localizedStrings.confirm,
		cancelLabel: config.cancelLabel || localizedStrings.cancel,
	} );
}

export function mfaPrompt( config = {} ) {
	const localizedStrings = dialogStrings();
	return dialog().prompt( {
		...config,
		title: config.title || localizedStrings.dialog_prompt_title,
		confirmLabel: config.confirmLabel || localizedStrings.confirm,
		cancelLabel: config.cancelLabel || localizedStrings.cancel,
	} );
}

export function mfaAlert( config = {} ) {
	const localizedStrings = dialogStrings();
	return dialog().message( {
		...config,
		title: config.title || localizedStrings.dialog_alert_title,
		confirmLabel: config.confirmLabel || localizedStrings.continue,
	} );
}

export function isValidMfaDeviceLabel( value ) {
	return typeof value === 'string' && ( new RegExp( "^[\\s\\da-zA-Z_-]{1,16}$" ) ).test( value );
}
