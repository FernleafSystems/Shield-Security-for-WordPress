<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FileDownload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
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
	public const NAV_NOTES = 'notes';
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
		$con = $this->getCon();
		return Services::WpGeneral()->getUrl_AdminPage( $this->rootAdminPageSlug(), $con->getIsWpmsNetworkAdminOnly() );
	}

	public function rootAdminPageSlug() :string {
		return $this->getCon()->getModule_Plugin()->getModSlug();
	}

	public static function GetAllNavs() :array {
		$cons = ( new \ReflectionClass( __CLASS__ ) )->getConstants();
		return array_intersect_key( $cons, array_flip( array_filter(
			array_keys( $cons ),
			function ( string $nav ) {
				return strpos( $nav, 'NAV_' ) === 0;
			}
		) ) );
	}

	public function adminHome() :string {
		return $this->adminTopNav( PluginURLs::NAV_OVERVIEW );
	}

	public function adminTopNav( string $nav, string $subNav = '' ) :string {
		return URL::Build( $this->rootAdminPage(), [
			Constants::NAV_ID     => sanitize_key( $nav ),
			Constants::NAV_SUB_ID => sanitize_key( $subNav ),
		] );
	}

	public function wizard( string $wizardKey ) :string {
		return URL::Build( $this->rootAdminPage(), [
			Constants::NAV_ID     => PluginURLs::NAV_WIZARD,
			Constants::NAV_SUB_ID => sanitize_key( $wizardKey ),
		] );
	}

	public function adminIpRules() :string {
		return $this->adminTopNav( self::NAV_IP_RULES );
	}

	public function ipAnalysis( string $ip ) :string {
		return URL::Build( $this->adminIpRules(), [ 'analyse_ip' => $ip ] );
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modCfg( $mod ) :string {
		return $this->adminTopNav( PluginURLs::NAV_OPTIONS_CONFIG, $mod->cfg->slug );
	}

	public function modCfgOption( string $optKey ) :string {
		$mod = OptUtils::ModFromOpt( $optKey );
		$def = $mod->getOptions()->getOptDefinition( $optKey );
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
			array_merge( $params, [ 'download_category' => $downloadCategory ] )
		);
	}

	/**
	 * @param string $for - option, section, module
	 */
	public function offCanvasConfigRender( string $for ) :string {
		return sprintf( "javascript:{iCWP_WPSF_OffCanvas.renderConfig('%s')}", $for );
	}

	public function isValidNav( string $navID ) :bool {
		return in_array( $navID, self::GetAllNavs() );
	}
}