<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\FileDownload,
	Actions\FileDownloadAsStream,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities\OptUtils;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PluginURLs {

	use PluginControllerConsumer;

	public function rootAdminPage() :string {
		return Services::WpGeneral()->getUrl_AdminPage(
			$this->rootAdminPageSlug(), (bool)self::con()->cfg->properties[ 'wpms_network_admin_only' ] );
	}

	public function rootAdminPageSlug() :string {
		return self::con()->prefix( self::con()->getModule_Plugin()->cfg->slug );
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
	 * @param ModCon|string|mixed $mod
	 */
	public function modCfg( $mod ) :string {
		return $this->adminTopNav( PluginNavs::NAV_OPTIONS_CONFIG, \is_string( $mod ) ? $mod : $mod->cfg->slug );
	}

	public function modCfgOption( string $optKey ) :string {
		$con = self::con();
		if ( isset( $con->cfg->configuration ) ) {
			$mod = $con->modules[ $con->cfg->configuration->modFromOpt( $optKey ) ];
			$url = $this->modCfgSection( $mod, $con->opts->optDef( $optKey )[ 'section' ] );
		}
		else {
			/** @deprecated 19.1 */
			$mod = OptUtils::ModFromOpt( $optKey );
			$def = $mod->opts()->getOptDefinition( $optKey );
			$url = empty( $def[ 'section' ] ) ? $this->modCfg( $mod ) : $this->modCfgSection( $mod, $def[ 'section' ] );
		}
		return $url;
	}

	/**
	 * @param ModCon|string|mixed $mod
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
}