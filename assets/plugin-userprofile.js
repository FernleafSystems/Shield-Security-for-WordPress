import "./css/plugin-userprofile.scss";
import { ShieldStrings } from "./js/services/ShieldStrings";
import { UserProfileHandler } from "./js/components/userprofile/UserProfileHandler";
import { ShieldEventsHandler } from "./js/services/ShieldEventsHandler";

window.addEventListener( 'load', () => {
	if ( typeof window.shield_vars_userprofile === 'undefined' ) {
		console.log( 'shield_vars_userprofile var is unavailable.' );
	}
	else if ( 'userprofile' in window.shield_vars_userprofile.comps ) {
		global.shieldStrings = new ShieldStrings( window.shield_vars_userprofile.strings );
		global.shieldEventsHandler_UserProfile = new ShieldEventsHandler( {
			events_container_selector: '.shield_user_mfa_container'
		} );
		new UserProfileHandler( window.shield_vars_userprofile.comps.userprofile );
	}
}, false );