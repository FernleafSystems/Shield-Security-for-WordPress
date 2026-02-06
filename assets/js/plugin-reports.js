import { ReportsHandler } from "./components/reporting/ReportsHandler";
import { ShieldEventsHandler } from "./services/ShieldEventsHandler";

window.addEventListener( 'load', () => {
	global.shieldEventsHandler_Reports = new ShieldEventsHandler( {
		events_container_selector: 'body'
	} );
	new ReportsHandler();
}, false );