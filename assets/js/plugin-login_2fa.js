import "../css/plugin-login2fa.scss";
import { Login2faHandler } from "./components/login2fa/Login2faHandler";
import { ShieldEventsHandler } from "./services/ShieldEventsHandler";

window.addEventListener( 'load', () => {

	if ( 'shield_vars_login_2fa' in window ) {
		global.shieldEventsHandler_Login2fa = new ShieldEventsHandler( {
			events_container_selector: 'body',
		} );
		new Login2faHandler( window.shield_vars_login_2fa.comps.login_2fa );
	}

}, false );