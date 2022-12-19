<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FileDownload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PluginURLs {

	use PluginControllerConsumer;

	public const NAV_ACTIVITY_LOG = 'audit_trail';
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

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modAdminPage( $mod ) :string {
		return Services::WpGeneral()
					   ->getUrl_AdminPage( $mod->getModSlug(), $this->getCon()->getIsWpmsNetworkAdminOnly() );
	}

	/**
	 * @param ModCon|string $mod
	 */
	public function modOptionsCfg( $mod ) :string {
		return $this->adminTop( PluginURLs::NAV_OPTIONS_CONFIG, $mod->getSlug() );
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modOption( $mod, string $optKey ) :string {
		$def = $mod->getOptions()->getOptDefinition( $optKey );
		return empty( $def[ 'section' ] ) ? $this->modOptionsCfg( $mod ) : $this->modOptionSection( $mod, $def[ 'section' ] );
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	public function modOptionSection( $mod, string $optSection ) :string {
		if ( $optSection == 'primary' ) {
			$optSection = $mod->getOptions()->getPrimarySection()[ 'slug' ];
		}
		return $this->modOptionsCfg( $mod ).'#tab-'.$optSection;
	}

	public function adminHome() :string {
		return $this->adminTop( PluginURLs::NAV_OVERVIEW );
	}

	public function adminTop( string $nav, string $subNav = '' ) :string {
		return URL::Build( $this->modAdminPage( $this->getCon()->getModule_Insights() ), [
			Constants::NAV_ID     => sanitize_key( $nav ),
			Constants::NAV_SUB_ID => sanitize_key( $subNav ),
		] );
	}

	public function adminIpRules() :string {
		return $this->adminTop( self::NAV_IP_RULES );
	}

	public function ipAnalysis( string $ip ) :string {
		return URL::Build( $this->adminIpRules(), [ 'analyse_ip' => $ip ] );
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

	public function fileDownload( string $downloadType, array $params = [] ) :string {
		return $this->noncedPluginAction(
			FileDownload::SLUG,
			Services::WpGeneral()->getAdminUrl(),
			array_merge( $params, [ 'download_category' => $downloadType ] )
		);
	}
}