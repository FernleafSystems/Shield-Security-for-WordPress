import { ProviderBase } from "./ProviderBase";

export class ProviderBackupCodes extends ProviderBase {
	postRender() {
		const gen = this.container().querySelector( '.shield-gen-backup-login-code' );
		if ( gen ) {
			gen.addEventListener( 'click', () => {
				if ( !this._base_data.flags.has_backup_code || confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.sendReq( this._base_data.ajax.profile_backup_codes_gen );
				}
			}, false );
		}

		const del = this.container().querySelector( '.shield-del-backup-login-code' );
		if ( del ) {
			del.addEventListener( 'click', () => {
				if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
					this.sendReq( this._base_data.ajax.profile_backup_codes_del );
				}
			}, false );
		}
	}
}