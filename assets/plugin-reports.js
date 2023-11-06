import { ReportsHandler } from "./js/components/reporting/ReportsHandler";
import { ShieldEventsHandler } from "./js/services/ShieldEventsHandler";

window.addEventListener( 'load', () => {
	global.shieldEventsHandler_Reports = new ShieldEventsHandler( {
		events_container_selector: 'body'
	} );
	new ReportsHandler();
}, false );