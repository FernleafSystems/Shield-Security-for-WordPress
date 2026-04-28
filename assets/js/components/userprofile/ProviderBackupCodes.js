import { ProviderBase } from "./ProviderBase";
import { mfaConfirm } from "./MfaProfileDialog";

export class ProviderBackupCodes extends ProviderBase {
	run() {
		shieldEventsHandler_UserProfile.add_Click( '.shield-gen-backup-login-code', async ( targetEl ) => {
			if ( !this._base_data.flags.has_backup_code || await this.confirmAction( targetEl ) ) {
				this.sendReq( this._base_data.ajax.profile_backup_codes_gen, targetEl );
			}
		} );
		shieldEventsHandler_UserProfile.add_Click( '.shield-del-backup-login-code', async ( targetEl ) => {
			if ( await this.confirmAction( targetEl ) ) {
				this.sendReq( this._base_data.ajax.profile_backup_codes_del, targetEl );
			}
		} );
	}

	confirmAction( launcher ) {
		return mfaConfirm( {
			title: shieldStrings.string( 'dialog_confirm_title' ),
			message: shieldStrings.string( 'are_you_sure' ),
			confirmLabel: shieldStrings.string( 'confirm' ),
			cancelLabel: shieldStrings.string( 'cancel' ),
			danger: true,
			launcher,
		} );
	}
}
