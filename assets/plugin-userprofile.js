import "./css/plugin-userprofile.scss";
import { ShieldStrings } from "./js/util/ShieldStrings";
import { UserProfileHandler } from "./js/util/UserProfileHandler";

window.addEventListener( 'load', () => {
	if ( typeof window.shield_vars_userprofile === 'undefined' ) {
		console.log( 'shield_vars_userprofile var is unavailable.' );
	}
	else if ( 'userprofile' in window.shield_vars_userprofile.comps ) {
		global.shieldStrings = new ShieldStrings( window.shield_vars_userprofile.strings );
		new UserProfileHandler( window.shield_vars_userprofile.comps.userprofile );
	}
}, false );