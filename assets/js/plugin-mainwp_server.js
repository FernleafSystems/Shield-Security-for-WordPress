import "../css/plugin-mainwp-server.scss";
import { MainwpServer } from "./components/integrations/MainwpServer";

window.addEventListener( 'load', () => {
	( 'shield_vars_mainwp_server' in window ) && new MainwpServer( window.shield_vars_mainwp_server.comps.mainwp_server );
}, false );