<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\FileDownload,
	Actions\FileDownloadAsStream,
	Constants};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities\OptUtils;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PluginURLs {

	use PluginControllerConsumer;

	public const NAV_ACTIVITY_LOG = 'activity_log';
	public const NAV_DEBUG = 'debug';
	public const NAV_DOCS = 'docs';
	public const NAV_IMPORT_EXPORT = 'importexport';
	public const NAV_IP_RULES = 'ips';
	public const NAV_LICENSE = 'license';
	public const NAV_OPTIONS_CONFIG = 'config';
	public const NAV_OVERVIEW = 'overview';
	public const NAV_RESTRICTED = 'restricted';
	public const NAV_REPORTS = 'reports';
	public const NAV_RULES_VIEW = 'rules';
	public const NAV_SCANS_RESULTS = 'scans_results';
	public const NAV_SCANS_RUN = 'scans_run';
	public const NAV_STATS = 'stats';
	public const NAV_TRAFFIC_VIEWER = 'traffic';
	public const NAV_USER_SESSIONS = 'users';
	public const NAV_WIZARD = 'merlin';

	public function rootAdminPage() :string {
		return Services::WpGeneral()->getUrl_AdminPage(
			$this->rootAdminPageSlug(), (bool)self::con()->cfg->properties[ 'wpms_network_admin_only' ] );
	}

	public function rootAdminPageSlug() :string {
		return self::con()->getModule_Plugin()->getModSlug();
	}

	public static function GetAllNavs() :array {
		$cons = ( new \ReflectionClass( __CLASS__ ) )->getConstants();
		return \array_intersect_key( $cons, \array_flip( \array_filter(
			\array_keys( $cons ),
			function ( string $nav ) {
				return \strpos( $nav, 'NAV_' ) === 0;
			}
		) ) );
	}

	public function adminHome() :string {
		return $this->adminTopNav( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_OVERVIEW );
	}

	public function adminTopNav( string $nav, string $subNav = '' ) :string {
		return URL::Build( $this->rootAdminPage(), [
			Constants::NAV_ID     => sanitize_key( $nav ),
			Constants::NAV_SUB_ID => sanitize_key( $subNav ),
		] );
	}

	public function wizard( string $wizardKey ) :string {
		return $this->adminTopNav( PluginNavs::NAV_WIZARD, $wizardKey );
	}

	public function adminIpRules() :string {
		return $this->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES );
	}

	public function ipAnalysis( string $ip ) :string {
		return URL::Build( $this->adminIpRules(), [ 'analyse_ip' => $ip ] );
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modCfg( $mod ) :string {
		return $this->adminTopNav( PluginNavs::NAV_OPTIONS_CONFIG, $mod->cfg->slug );
	}

	public function modCfgOption( string $optKey ) :string {
		$mod = OptUtils::ModFromOpt( $optKey );
		$def = $mod->opts()->getOptDefinition( $optKey );
		return empty( $def[ 'section' ] ) ? $this->modCfg( $mod ) : $this->modCfgSection( $mod, $def[ 'section' ] );
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modCfgSection( $mod, string $optSection ) :string {
		return $this->modCfg( $mod ).'#tab-'.$optSection;
	}

	/**
	 * Builds a URL with a nonce + any other auxiliary data for executing a Shield Plugin action
	 */
	public function noncedPluginAction( string $action, ?string $url = null, array $aux = [] ) :string {
		return URL::Build(
			empty( $url ) ? Services::WpGeneral()->getHomeUrl() : $url,
			ActionData::Build( $action, false, $aux )
		);
	}

	public function fileDownload( string $downloadCategory, array $params = [] ) :string {
		return $this->noncedPluginAction(
			FileDownload::class,
			Services::WpGeneral()->getAdminUrl(),
			\array_merge( $params, [ 'download_category' => $downloadCategory ] )
		);
	}

	public function fileDownloadAsStream( string $downloadCategory, array $params = [] ) :string {
		return $this->noncedPluginAction(
			FileDownloadAsStream::class,
			Services::WpGeneral()->getAdminUrl(),
			\array_merge( $params, [ 'download_category' => $downloadCategory ] )
		);
	}

	/**
	 * @param string $trigger - option, section, module
	 */
	public function offCanvasTrigger( string $trigger ) :string {
		return sprintf( "javascript:{iCWP_WPSF_OffCanvas.%s}", $trigger );
	}

	/**
	 * @param string $for - option, section, module
	 */
	public function offCanvasConfigRender( string $for ) :string {
		return $this->offCanvasTrigger( sprintf( "renderConfig('%s')", $for ) );
	}

	public function isValidNav( string $navID ) :bool {
		return \in_array( $navID, PluginNavs::GetAllNavs() );
	}
}