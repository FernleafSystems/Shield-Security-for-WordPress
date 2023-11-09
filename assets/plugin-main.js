import "./css/plugin-main.scss";

import $ from 'jquery';
import { BootstrapTooltips } from "./js/components/ui/BootstrapTooltips";
import { ChartsSummaryCharts } from "./js/components/charts/ChartsSummaryCharts";
import { ConfigImport } from "./js/components/options/ConfigImport";
import { DivPrinter } from "./js/components/general/DivPrinter";
import { DynamicActionButtons } from "./js/components/general/DynamicActionButtons";
import { FileLocker } from "./js/components/scans/FileLocker";
import { HelpscoutBeacon } from "./js/components/general/HelpscoutBeacon";
import { IpSourceAutoDetect } from "./js/components/ips/IpSourceAutoDetect";
import { IpRules } from "./js/components/ips/IpRules";
import { IpAnalyse } from "./js/components/ips/IpAnalyse";
import { LeanBe } from "./js/components/third/LeanBe";
import { LicenseHandler } from "./js/components/general/LicenseHandler";
import { Merlin } from "./js/components/general/Merlin";
import { MiscHooks } from "./js/components/general/MiscHooks";
import { OffCanvasService } from "./js/components/ui/OffCanvasService";
import { OptionsHandler } from "./js/components/options/OptionsHandler";
import { Navigation } from "./js/components/general/Navigation";
import { NoticeHandler } from "./js/components/notices/NoticeHandler";
import { ProgressMeters } from "./js/components/general/ProgressMeters";
import { ReportingHandler } from "./js/components/reporting/ReportingHandler";
import { ScansHandler } from "./js/components/scans/ScansHandler";
import { SecurityAdmin } from "./js/components/general/SecurityAdmin";
import { ShieldServicesPlugin } from "./js/services/ShieldServicesPlugin";
import { ShieldTableActivityLog } from "./js/components/tables/ShieldTableActivityLog";
import { ShieldTableIpRules } from "./js/components/tables/ShieldTableIpRules";
import { ShieldTableTrafficLog } from "./js/components/tables/ShieldTableTrafficLog";
import { ShieldTableSessions } from "./js/components/tables/ShieldTableSessions";
import { ShieldStrings } from "./js/services/ShieldStrings";
import { ShieldEventsHandler } from "./js/services/ShieldEventsHandler";
import { SuperSearchService } from "./js/components/search/SuperSearchService";
import { Tours } from "./js/components/general/Tours";
import { TrafficLiveLogs } from "./js/components/general/TrafficLiveLogs";
import { TestRest } from "./js/components/general/TestRest";

$( document ).ready( function () {

	if ( 'shield_vars_main' in window ) {

		global.shieldServices = new ShieldServicesPlugin();
		global.shieldStrings = new ShieldStrings( window.shield_vars_main.strings );
		global.shieldEventsHandler_Main = new ShieldEventsHandler( {
			events_container_selector: '#PageContainer-Shield'
		} );

		const comps = window.shield_vars_main.comps;

		/**
		 * Must init early.
		 */
		new OffCanvasService();

		new BootstrapTooltips();
		( 'charts' in comps ) && new ChartsSummaryCharts( comps.charts );
		( 'import' in comps ) && new ConfigImport( comps.import );
		new DynamicActionButtons();
		( 'file_locker' in comps ) && new FileLocker( comps.file_locker );
		( 'helpscout' in comps ) && new HelpscoutBeacon( comps.helpscout );
		( 'ip_detect' in comps ) && new IpSourceAutoDetect( comps.ip_detect );
		( 'ip_analyse' in comps ) && new IpAnalyse( comps.ip_analyse );
		( 'ip_rules' in comps ) && new IpRules( comps.ip_rules );
		( 'leanbe' in comps ) && new LeanBe( comps.leanbe );
		( 'license' in comps ) && new LicenseHandler( comps.license );
		( 'merlin' in comps ) && new Merlin( comps.merlin );
		new MiscHooks();
		new DivPrinter();
		( 'notices' in comps ) && new NoticeHandler( comps.notices );
		( 'navi' in comps ) && new Navigation( comps.navi );
		( 'mod_options' in comps ) && new OptionsHandler( comps.mod_options );
		( 'progress_meters' in comps ) && new ProgressMeters( comps.progress_meters );
		( 'reports' in comps ) && new ReportingHandler( comps.reports );
		( 'super_search' in comps ) && new SuperSearchService( comps.super_search );
		( 'scans' in comps ) && new ScansHandler( comps.scans );
		( 'sec_admin' in comps ) && new SecurityAdmin( comps.sec_admin );
		( 'tours' in comps ) && new Tours( comps.tours );
		( 'traffic' in comps ) && new TrafficLiveLogs( comps.traffic );
		( 'activity' in comps.tables ) && new ShieldTableActivityLog( comps.tables.activity );
		( 'ip_rules' in comps.tables ) && new ShieldTableIpRules( comps.tables.ip_rules );
		( 'sessions' in comps.tables ) && new ShieldTableSessions( comps.tables.sessions );
		( 'traffic' in comps.tables ) && new ShieldTableTrafficLog( comps.tables.traffic );
		( 'testrest' in comps ) && new TestRest( comps.testrest );
	}
} );