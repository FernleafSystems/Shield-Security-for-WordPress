import "./css/plugin-login2fa.scss";
import { Login2faHandler } from "./js/util/Login2faHandler";

window.addEventListener( 'load', () => {
	( 'shield_vars_login_2fa' in window ) && new Login2faHandler( window.shield_vars_login_2fa );
}, false );