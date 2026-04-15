import { AppBase } from "./AppBase";
import { IpSourceAutoDetect } from "../components/ips/IpSourceAutoDetect";
import { NoticeHandler } from "../components/notices/NoticeHandler";
import { SecurityAdmin } from "../components/general/SecurityAdmin";
import { ShieldServicesWpAdmin } from "../services/ShieldServicesWpAdmin";
import { ShieldEventsHandler } from "../services/ShieldEventsHandler";
import { TestRest } from "../components/general/TestRest";

export class AppWpAdmin extends AppBase {

	canRun() {
		return 'shield_vars_wpadmin' in window
	}

	initEvents() {
		global.shieldServices = ShieldServicesWpAdmin.Instance();
		global.shieldEventsHandler_Main = new ShieldEventsHandler( {
			events_container_selector: 'body'
		} );
	}

	initComponents() {
		super.initComponents();

		const comps = window.shield_vars_wpadmin.comps;

		this.components.ip_detect = ( 'ip_detect' in comps ) ? new IpSourceAutoDetect( comps.ip_detect ) : null;
		this.components.notices = ( 'notices' in comps ) ? new NoticeHandler( comps.notices ) : null;
		this.components.sec_admin = ( 'sec_admin' in comps ) ? new SecurityAdmin( comps.sec_admin ) : null;
		this.components.testrest = ( 'testrest' in comps ) ? new TestRest( comps.testrest ) : null;
	}
}
