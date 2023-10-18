import "./css/plugin-userprofile.scss";
import { ProviderBackupCodes } from "./js/util/userprofile/ProviderBackupCodes";
import { ProviderEmail } from "./js/util/userprofile/ProviderEmail";
import { ProviderGA } from "./js/util/userprofile/ProviderGA";
import { ProviderYubikey } from "./js/util/userprofile/ProviderYubikey";
import { RemoveAllProviders } from "./js/util/userprofile/RemoveAllProviders";

window.addEventListener( 'load', () => {

	if ( typeof window.shield_vars_userprofile === 'undefined' ) {
		console.log( 'shield_vars_userprofile var is unavailable.' );
	}
	else {
		const comps = window.shield_vars_userprofile.comps;

		if ( typeof comps.userprofile !== 'undefined' ) {
			if ( comps.userprofile.vars.providers.hasOwnProperty( 'email' ) ) {
				new ProviderEmail( comps.userprofile.vars.providers.email );
			}
			if ( comps.userprofile.vars.providers.hasOwnProperty( 'yubi' ) ) {
				new ProviderYubikey( comps.userprofile.vars.providers.yubi );
			}
			if ( comps.userprofile.vars.providers.hasOwnProperty( 'ga' ) ) {
				new ProviderGA( comps.userprofile.vars.providers.ga );
			}
			if ( comps.userprofile.vars.providers.hasOwnProperty( 'backupcode' ) ) {
				new ProviderBackupCodes( comps.userprofile.vars.providers.backupcode );
			}
			new RemoveAllProviders( comps.userprofile );
		}
	}
}, false );