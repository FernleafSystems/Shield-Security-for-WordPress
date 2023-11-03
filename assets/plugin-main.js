import "./css/plugin-main.scss";

import $ from 'jquery';
import { BootstrapTooltips } from "./js/util/BootstrapTooltips";
import { ChartsSummaryCharts } from "./js/util/ChartsSummaryCharts";
import { ConfigImport } from "./js/util/ConfigImport";
import { DivPrinter } from "./js/util/DivPrinter";
import { DynamicActionButtons } from "./js/util/DynamicActionButtons";
import { FileLocker } from "./js/util/FileLocker";
import { HelpscoutBeacon } from "./js/util/HelpscoutBeacon";
import { IpSourceAutoDetect } from "./js/util/IpSourceAutoDetect";
import { IpRules } from "./js/util/IpRules";
import { IpAnalyse } from "./js/util/IpAnalyse";
import { LeanBe } from "./js/util/third/LeanBe";
import { LicenseHandler } from "./js/util/LicenseHandler";
import { Merlin } from "./js/util/Merlin";
import { MiscHooks } from "./js/util/MiscHooks";
import { OffCanvasService } from "./js/util/OffCanvasService";
import { OptionsHandler } from "./js/util/OptionsHandler";
import { Navigation } from "./js/util/Navigation";
import { NoticeHandler } from "./js/util/NoticeHandler";
import { ProgressMeters } from "./js/util/ProgressMeters";
import { ReportingHandler } from "./js/util/ReportingHandler";
import { ScansHandler } from "./js/util/ScansHandler";
import { SecurityAdmin } from "./js/util/SecurityAdmin";
import { ShieldServicesPlugin } from "./js/util/ShieldServicesPlugin";
import { ShieldTableActivityLog } from "./js/util/ShieldTableActivityLog";
import { ShieldTableIpRules } from "./js/util/ShieldTableIpRules";
import { ShieldTableTrafficLog } from "./js/util/ShieldTableTrafficLog";
import { SuperSearchService } from "./js/util/SuperSearchService";
import { Tours } from "./js/util/Tours";
import { TrafficLiveLogs } from "./js/util/TrafficLiveLogs";
import { ShieldTableSessions } from "./js/util/ShieldTableSessions";
import { ShieldStrings } from "./js/util/ShieldStrings";
import { ShieldEventsHandler } from "./js/util/ShieldEventsHandler";

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
	}
} );