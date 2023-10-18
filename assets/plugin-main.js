import "./css/plugin-main.scss";

import $ from 'jquery';
import { BootstrapTooltips } from "./js/util/BootstrapTooltips";
import { ChartsSummaryCharts } from "./js/util/ChartsSummaryCharts";
import { ConfigImport } from "./js/util/ConfigImport";
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

$( document ).ready( function () {
	global.shieldServices = ShieldServicesPlugin.Instance();

	if ( typeof window.shield_vars_main === 'undefined' ) {
		console.log( 'window.shield_vars_main in unavailable!' )
		return;
	}

	const comps = window.shield_vars_main.comps;

	new BootstrapTooltips();
	new ChartsSummaryCharts( comps.charts );
	new ConfigImport( comps.import_cfg );
	new DynamicActionButtons();
	new FileLocker( comps.file_locker );
	new HelpscoutBeacon( comps.helpscout );
	new IpSourceAutoDetect( comps.ip_detect );
	new IpAnalyse( comps.ip_analyse );
	new IpRules( comps.ip_rules );
	new LeanBe( comps.leanbe );
	new LicenseHandler( comps.license );
	( 'merlin' in comps ) && new Merlin( comps.merlin );
	new MiscHooks();
	new NoticeHandler( comps.notices );
	new Navigation( comps.navi );
	new OffCanvasService();
	new OptionsHandler( comps.mod_options );
	( 'progress_meters' in comps ) && new ProgressMeters( comps.progress_meters );
	( 'reports' in comps ) && new ReportingHandler( comps.reports );
	new SuperSearchService( comps.super_search );
	( 'scans' in comps ) && new ScansHandler( comps.scans );
	new SecurityAdmin( comps.sec_admin );
	new Tours( comps.tours );
	new TrafficLiveLogs( comps.traffic );
	new ShieldTableActivityLog( comps.tables.activity );
	new ShieldTableIpRules( comps.tables.ip_rules );
	new ShieldTableSessions( comps.tables.sessions );
	new ShieldTableTrafficLog( comps.tables.traffic );
} );