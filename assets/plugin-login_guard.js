import { LoginGuardHandler } from "./js/components/login/LoginGuardHandler";

window.addEventListener( 'load', () => {
	( 'shield_vars_login_guard' in window ) && new LoginGuardHandler( window.shield_vars_login_guard.comps.login_guard );
}, false );