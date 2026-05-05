import { AccessibleDialog, resolveAccessibleDialogConfirmLabel } from "../components/ui/AccessibleDialog";

export class AccessibleAdminDialogService {

	constructor() {
		this.dialog = new AccessibleDialog( {
			id: 'ShieldWpAdminAccessibleDialog',
			titleId: 'ShieldWpAdminAccessibleDialogTitle',
			messageId: 'ShieldWpAdminAccessibleDialogMessage',
			inputId: 'ShieldWpAdminAccessibleDialogInput',
			inputLabelId: 'ShieldWpAdminAccessibleDialogInputLabel',
			validationId: 'ShieldWpAdminAccessibleDialogValidation',
			datasetKey: 'shieldAccessibleDialog',
			classPrefix: 'shield-accessible-dialog',
			stringsProvider: () => window.shield_vars_wpadmin?.strings || {},
			errorContext: 'WordPress admin accessible dialog',
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
}
