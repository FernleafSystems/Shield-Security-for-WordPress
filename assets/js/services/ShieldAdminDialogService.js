import { AccessibleDialog, resolveAccessibleDialogConfirmLabel, resolveAccessibleDialogLauncher } from "../components/ui/AccessibleDialog";

export class ShieldAdminDialogService {

	constructor() {
		this.dialog = new AccessibleDialog( {
			id: 'ShieldMainAccessibleDialog',
			titleId: 'ShieldMainAccessibleDialogTitle',
			messageId: 'ShieldMainAccessibleDialogMessage',
			inputId: 'ShieldMainAccessibleDialogInput',
			inputLabelId: 'ShieldMainAccessibleDialogInputLabel',
			validationId: 'ShieldMainAccessibleDialogValidation',
			datasetKey: 'shieldAccessibleDialog',
			classPrefix: 'shield-accessible-dialog',
			stringsProvider: dialogStrings,
			fallbackFocus: () => document.getElementById( 'PageContainer-Apto' ),
			errorContext: 'Shield admin accessible dialog',
		} );
	}

	confirm( config = {} ) {
		return this.dialog.confirm( config );
	}

	message( config = {} ) {
		return this.dialog.message( config );
	}

	prompt( config = {} ) {
		return this.dialog.prompt( config );
	}

	resolveConfirmLabel( launcher = null ) {
		return resolveAccessibleDialogConfirmLabel( launcher );
	}

	resolveLauncher( event = null, node = null ) {
		return resolveAccessibleDialogLauncher( event, node );
	}
}

function dialogStrings() {
	if ( typeof shieldStrings !== 'undefined' && typeof shieldStrings.strings === 'function' ) {
		return shieldStrings.strings() || {};
	}
	return window.shield_vars_main?.strings || {};
}
