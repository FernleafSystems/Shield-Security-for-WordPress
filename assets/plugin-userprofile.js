import "./css/plugin-userprofile.scss";
import { UserProfileHandler } from "./js/util/UserProfileHandler";

window.addEventListener( 'load', () => {
	if ( typeof window.shield_vars_userprofile === 'undefined' ) {
		console.log( 'shield_vars_userprofile var is unavailable.' );
	}
	else {
		if ( 'userprofile' in window.shield_vars_userprofile.comps ) {
			new UserProfileHandler( window.shield_vars_userprofile.comps.userprofile );
		}
	}
}, false );