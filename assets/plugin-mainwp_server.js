import "./css/plugin-mainwp-server.scss";
import { MainwpServer } from "./js/util/integrations/MainwpServer";

window.addEventListener( 'load', () => {
	new MainwpServer( window.shield_vars_mainwp_server );
}, false );