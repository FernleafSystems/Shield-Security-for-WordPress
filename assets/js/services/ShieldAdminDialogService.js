import { confirmDialog, messageDialog, resolveDialogConfirmLabel } from "../components/ui/ShieldDialog";

export class ShieldAdminDialogService {

	confirm( config = {} ) {
		return confirmDialog( config );
	}

	message( config = {} ) {
		return messageDialog( config );
	}

	resolveConfirmLabel( launcher = null ) {
		return resolveDialogConfirmLabel( launcher );
	}
}
