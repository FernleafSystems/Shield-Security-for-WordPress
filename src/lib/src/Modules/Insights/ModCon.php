<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class ModCon extends BaseShield\ModCon {

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_IpAnalysis( string $ip ) :string {
		return URL::Build( $this->getUrl_IPs(), [ 'analyse_ip' => $ip ] );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_ActivityLog() :string {
		return $this->getUrl_SubInsightsPage( PluginURLs::NAV_ACTIVITY_LOG );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_IPs() :string {
		$urls = $this->getCon()->plugin_urls;
		return $urls ? $urls->adminTopNav( $urls::NAV_IP_RULES ) : $this->getUrl_SubInsightsPage( PluginURLs::NAV_IP_RULES );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_ScansResults() :string {
		$urls = $this->getCon()->plugin_urls;
		return $urls ? $urls->adminTopNav( $urls::NAV_SCANS_RESULTS ) : $this->getUrl_SubInsightsPage( PluginURLs::NAV_SCANS_RESULTS );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_ScansRun() :string {
		return $this->getUrl_SubInsightsPage( PluginURLs::NAV_SCANS_RUN );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_Sessions() :string {
		$urls = $this->getCon()->plugin_urls;
		return $urls ? $urls->adminTopNav( $urls::NAV_USER_SESSIONS ) : $this->getUrl_SubInsightsPage( PluginURLs::NAV_USER_SESSIONS );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_SubInsightsPage( string $inavPage, string $subNav = '' ) :string {
		$urls = $this->getCon()->plugin_urls;
		return $urls ? $urls->adminTopNav( $inavPage, $subNav ) :
			URL::Build( $this->getUrl_AdminPage(), [
				Constants::NAV_ID     => sanitize_key( $inavPage ),
				Constants::NAV_SUB_ID => sanitize_key( $subNav ),
			] );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getUrl_AdminPage() :string {
		$urls = $this->getCon()->plugin_urls;
		return $urls ? $urls->rootAdminPage()
			: Services::WpGeneral()->getUrl_AdminPage(
				$this->getCon()->getModule_Plugin()->getModSlug(),
				$this->getCon()->getIsWpmsNetworkAdminOnly()
			);
	}

	/**
	 * @return AdminPage
	 * @deprecated 17.0
	 */
	public function getAdminPage() {
		if ( !isset( $this->adminPage ) ) {
			$this->adminPage = ( new AdminPage() )->setMod( $this );
		}
		return $this->adminPage;
	}

	/**
	 * @deprecated 17.0
	 */
	public function getCurrentInsightsPage() :string {
		return (string)Services::Request()->query( Constants::NAV_ID );
	}

	/**
	 * @deprecated 17.0
	 */
	public function createFileDownloadLink( string $downloadID, array $additionalParams = [] ) :string {
		return '';
	}
}