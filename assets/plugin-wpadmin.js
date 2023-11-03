import "./css/plugin-wpadmin.scss";

import { DashboardWidget } from "./js/util/DashboardWidget";
import { HackGuardPluginReinstall } from "./js/util/HackGuardPluginReinstall";
import { IpSourceAutoDetect } from "./js/util/IpSourceAutoDetect";
import { NoticeHandler } from "./js/util/NoticeHandler";
import { SecurityAdmin } from "./js/util/SecurityAdmin";
import { ShieldServicesWpAdmin } from "./js/util/ShieldServicesWpAdmin";
import { ShieldEventsHandler } from "./js/util/ShieldEventsHandler";

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