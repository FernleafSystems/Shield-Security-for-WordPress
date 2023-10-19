import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderBackupCodes extends ProviderBase {

	init() {
		$( this.container ).on( 'click', '.shield-gen-backup-login-code', () => {
			this.sendReq( this._base_data.ajax.profile_backup_codes_gen );
		} );
		$( this.container ).on( 'click', '.shield-del-backup-login-code', () => {
			this.sendReq( this._base_data.ajax.profile_backup_codes_del );
		} );
	}
}