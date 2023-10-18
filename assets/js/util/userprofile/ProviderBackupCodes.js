import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderBackupCodes extends ProviderBase {

	init() {
		$( document ).on( 'click', '#IcwpWpsfGenBackupLoginCode', () => {
			this.sendReq( this._base_data.ajax.profile_backup_codes_gen );
		} );
		$( document ).on( 'click', '#IcwpWpsfDelBackupLoginCode', () => {
			this.sendReq( this._base_data.ajax.profile_backup_codes_del );
		} );
	}
}