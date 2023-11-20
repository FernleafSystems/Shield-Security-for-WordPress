import "./css/plugin-wpadmin.scss";

import { DashboardWidget } from "./js/components/general/DashboardWidget";
import { HackGuardPluginReinstall } from "./js/components/general/HackGuardPluginReinstall";
import { IpSourceAutoDetect } from "./js/components/ips/IpSourceAutoDetect";
import { NoticeHandler } from "./js/components/notices/NoticeHandler";
import { SecurityAdmin } from "./js/components/general/SecurityAdmin";
import { ShieldServicesWpAdmin } from "./js/services/ShieldServicesWpAdmin";
import { ShieldEventsHandler } from "./js/services/ShieldEventsHandler";

window.addEventListener( 'load', () => {
	global.shieldServices = ShieldServicesWpAdmin.Instance();

	if ( 'shield_vars_wpadmin' in window ) {

		global.shieldEventsHandler_Main = new ShieldEventsHandler( {
			events_container_selector: 'body'
		} );

		const comps = window.shield_vars_wpadmin.comps;
		( 'dashboard_widget' in comps ) && new DashboardWidget( comps.dashboard_widget );
		( 'ip_detect' in comps ) && new IpSourceAutoDetect( comps.ip_detect );
		( 'notices' in comps ) && new NoticeHandler( comps.notices );
		( 'plugin_reinstall' in comps ) && new HackGuardPluginReinstall( comps.plugin_reinstall );
		( 'sec_admin' in comps ) && new SecurityAdmin( comps.sec_admin );
	}
}, false );