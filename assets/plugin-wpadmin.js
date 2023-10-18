import "./css/plugin-wpadmin.scss";

import { DashboardWidget } from "./js/util/DashboardWidget";
import { HackGuardPluginReinstall } from "./js/util/HackGuardPluginReinstall";
import { IpSourceAutoDetect } from "./js/util/IpSourceAutoDetect";
import { NoticeHandler } from "./js/util/NoticeHandler";
import { SecurityAdmin } from "./js/util/SecurityAdmin";
import { ShieldServicesWpAdmin } from "./js/util/ShieldServicesWpAdmin";


window.addEventListener( 'load', () => {
	global.shieldServices = ShieldServicesWpAdmin.Instance();

	const comps = window.shield_vars_wpadmin.comps;

	new DashboardWidget( comps.dashboard_widget );
	new IpSourceAutoDetect( comps.ip_detect );
	new NoticeHandler( comps.notices );
	new SecurityAdmin( comps.sec_admin );
	if ( typeof comps.plugin_reinstall !== 'undefined' ) {
		new HackGuardPluginReinstall( comps.plugin_reinstall );
	}
}, false );