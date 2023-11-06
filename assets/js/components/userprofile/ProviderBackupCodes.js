import { ProviderBase } from "./ProviderBase";

export class ProviderBackupCodes extends ProviderBase {
	run() {
		shieldEventsHandler_UserProfile.add_Click( '.shield-gen-backup-login-code', ( targetEl ) => {
			if ( !this._base_data.flags.has_backup_code || confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.sendReq( this._base_data.ajax.profile_backup_codes_gen );
			}
		} );
		shieldEventsHandler_UserProfile.add_Click( '.shield-del-backup-login-code', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this.sendReq( this._base_data.ajax.profile_backup_codes_del );
			}
		} );
	}
}