import { AppBase } from "./AppBase";
import { Blockdown } from "../components/general/Blockdown";
import { BootstrapTooltips } from "../components/ui/BootstrapTooltips";
import { ChartsSummaryCharts } from "../components/charts/ChartsSummaryCharts";
import { ConfigImport } from "../components/options/ConfigImport";
import { DivPrinter } from "../components/general/DivPrinter";
import { DynamicActionButtons } from "../components/general/DynamicActionButtons";
import { FileLocker } from "../components/scans/FileLocker";
import { HelpscoutBeacon } from "../components/general/HelpscoutBeacon";
import { IpSourceAutoDetect } from "../components/ips/IpSourceAutoDetect";
import { IpRules } from "../components/ips/IpRules";
import { IpAnalyse } from "../components/ips/IpAnalyse";
import { LeanBe } from "../components/third/LeanBe";
import { LicenseHandler } from "../components/general/LicenseHandler";
import { Merlin } from "../components/general/Merlin";
import { MiscHooks } from "../components/general/MiscHooks";
import { OffCanvasService } from "../components/ui/OffCanvasService";
import { OptionsHandler } from "../components/options/OptionsHandler";
import { Navigation } from "../components/general/Navigation";
import { NoticeHandler } from "../components/notices/NoticeHandler";
import { ProgressMeters } from "../components/meters/ProgressMeters";
import { ReportingHandler } from "../components/reporting/ReportingHandler";
import { RuleBuilder } from "../components/rules/RuleBuilder";
import { RulesManager } from "../components/rules/RulesManager";
import { ScansHandler } from "../components/scans/ScansHandler";
import { SecurityAdmin } from "../components/general/SecurityAdmin";
import { ShieldServicesPlugin } from "../services/ShieldServicesPlugin";
import { ShieldTableActivityLog } from "../components/tables/ShieldTableActivityLog";
import { ShieldTableIpRules } from "../components/tables/ShieldTableIpRules";
import { ShieldTableTrafficLog } from "../components/tables/ShieldTableTrafficLog";
import { ShieldTableSessions } from "../components/tables/ShieldTableSessions";
import { ShieldStrings } from "../services/ShieldStrings";
import { ShieldEventsHandler } from "../services/ShieldEventsHandler";
import { SuperSearchService } from "../components/search/SuperSearchService";
import { Tours } from "../components/general/Tours";
import { TrafficLiveLogs } from "../components/general/TrafficLiveLogs";
import { TestRest } from "../components/general/TestRest";

export class AppMain extends AppBase {

	canRun() {
		return 'shield_vars_main' in window
	}

	initEvents() {
		global.shieldServices = new ShieldServicesPlugin();
		global.shieldStrings = new ShieldStrings( window.shield_vars_main.strings );
		global.shieldEventsHandler_Main = new ShieldEventsHandler( {
			events_container_selector: 'body'
		} );
	}

	initComponents() {
		super.initComponents();

		const comps = window.shield_vars_main.comps;

		this.components.offcanvas = new OffCanvasService();
		this.components.bootstrap_tooltips = new BootstrapTooltips();
		this.components.blockdown = ( 'blockdown' in comps ) ? new Blockdown( comps.blockdown ) : null;
		this.components.charts = ( 'charts' in comps ) ? new ChartsSummaryCharts( comps.charts ) : null;
		this.components.import = ( 'import' in comps ) ? new ConfigImport( comps.import ) : null;
		this.components.div_printer = new DivPrinter();
		this.components.dynamic_buttons = new DynamicActionButtons();
		this.components.file_locker = ( 'file_locker' in comps ) ? new FileLocker( comps.file_locker ) : null;
		this.components.helpscout = ( 'helpscout' in comps ) ? new HelpscoutBeacon( comps.helpscout ) : null;
		this.components.ip_analyse = ( 'ip_analyse' in comps ) ? new IpAnalyse( comps.ip_analyse ) : null;
		this.components.ip_detect = ( 'ip_detect' in comps ) ? new IpSourceAutoDetect( comps.ip_detect ) : null;
		this.components.ip_rules = ( 'ip_rules' in comps ) ? new IpRules( comps.ip_rules ) : null;
		this.components.leanbe = ( 'leanbe' in comps ) ? new LeanBe( comps.leanbe ) : null;
		this.components.license = ( 'license' in comps ) ? new LicenseHandler( comps.license ) : null;
		this.components.merlin = ( 'merlin' in comps ) ? new Merlin( comps.merlin ) : null;
		this.components.misc_hooks = new MiscHooks();
		this.components.mod_options = ( 'mod_options' in comps ) ? new OptionsHandler( comps.mod_options ) : null;
		this.components.notices = ( 'notices' in comps ) ? new NoticeHandler( comps.notices ) : null;
		this.components.navi = ( 'navi' in comps ) ? new Navigation( comps.navi ) : null;
		this.components.progress_meters = ( 'progress_meters' in comps ) ? new ProgressMeters( comps.progress_meters ) : null;
		this.components.reports = ( 'reports' in comps ) ? new ReportingHandler( comps.reports ) : null;
		this.components.rule_builder = ( 'rule_builder' in comps ) ? new RuleBuilder( comps.rule_builder ) : null;
		this.components.rules_manager = ( 'rules_manager' in comps ) ? new RulesManager( comps.rules_manager ) : null;
		this.components.super_search = ( 'super_search' in comps ) ? new SuperSearchService( comps.super_search ) : null;
		this.components.scans = ( 'scans' in comps ) ? new ScansHandler( comps.scans ) : null;
		this.components.sec_admin = ( 'sec_admin' in comps ) ? new SecurityAdmin( comps.sec_admin ) : null;
		this.components.testrest = ( 'testrest' in comps ) ? new TestRest( comps.testrest ) : null;
		this.components.tours = ( 'tours' in comps ) ? new Tours( comps.tours ) : null;
		this.components.traffic = ( 'traffic' in comps ) ? new TrafficLiveLogs( comps.traffic ) : null;

		this.components.tables_activity = ( 'activity' in comps.tables ) ? new ShieldTableActivityLog( comps.tables.activity ) : null;
		this.components.tables_ip_rules = ( 'ip_rules' in comps.tables ) ? new ShieldTableIpRules( comps.tables.ip_rules ) : null;
		this.components.tables_sessions = ( 'sessions' in comps.tables ) ? new ShieldTableSessions( comps.tables.sessions ) : null;
		this.components.tables_traffic = ( 'traffic' in comps.tables ) ? new ShieldTableTrafficLog( comps.tables.traffic ) : null;
	}
}