import { ReportsHandler } from "./js/util/ReportsHandler";
import { ShieldEventsHandler } from "./js/util/ShieldEventsHandler";

window.addEventListener( 'load', () => {
	global.shieldEventsHandler_Reports = new ShieldEventsHandler( {
		events_container_selector: 'body'
	} );
	new ReportsHandler();
}, false );