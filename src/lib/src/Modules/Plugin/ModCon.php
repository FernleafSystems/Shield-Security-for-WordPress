<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\ShieldNetApiController;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\{
	CacheDirHandler,
	Integration\WhitelistUs
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\RequestIpDetect;
use FernleafSystems\Wordpress\Services\Utilities\Net\VisitorIpDetection;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public const SLUG = 'plugin';

	/**
	 * @var Lib\ImportExport\ImportExportController
	 */
	private $importExportCon;

	/**
	 * @var Components\PluginBadge
	 */
	private $pluginBadgeCon;

	/**
	 * @var Lib\Reporting\ReportingController
	 */
	private $reportsCon;

	/**
	 * @var ShieldNetApiController
	 */
	private $shieldNetCon;

	/**
	 * @var Lib\Sessions\SessionController
	 */
	private $sessionCon;

	/**
	 * @var Lib\TrackingVO
	 */
	private $tracking;

	/**
	 * @deprecated 19.1
	 */
	public function getImpExpController() :Lib\ImportExport\ImportExportController {
		return self::con()->comps !== null ? self::con()->comps->import_export :
			( $this->importExportCon ?? $this->importExportCon = new Lib\ImportExport\ImportExportController() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getPluginBadgeCon() :Components\PluginBadge {
		return self::con()->comps !== null ? self::con()->comps->badge :
			( $this->pluginBadgeCon ?? $this->pluginBadgeCon = new Components\PluginBadge() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getReportingController() :Lib\Reporting\ReportingController {
		return self::con()->comps !== null ? self::con()->comps->reports :
			( $this->reportsCon ?? $this->reportsCon = new Lib\Reporting\ReportingController() );
	}

	public function getSessionCon() :Lib\Sessions\SessionController {
		return self::con()->comps !== null ? self::con()->comps->session :
			( $this->sessionCon ?? $this->sessionCon = new Lib\Sessions\SessionController() );
	}

	public function getShieldNetApiController() :ShieldNetApiController {
		return self::con()->comps !== null ? self::con()->comps->shieldnet :
			( $this->shieldNetCon ?? $this->shieldNetCon = new ShieldNetApiController() );
	}

	protected function doPostConstruction() {
		$this->setVisitorIpSource();
		$this->setupCacheDir();
		$this->declareWooHposCompat();
		$this->storeRealInstallDate();
	}

	public function isModuleEnabled() :bool {
		return true;
	}

	public function onWpInit() {
		parent::onWpInit();
		if ( self::con()->cfg->previous_version !== self::con()->cfg->version() ) {
			$this->getTracking()->last_upgrade_at = Services::Request()->ts();
		}
		self::con()->comps->assets_customizer->execute();
	}

	/**
	 * @return string[]
	 * @deprecated 19.1
	 */
	public function getDismissedNotices() :array {
		return $this->opts()->getOpt( 'dismissed_notices' );
	}

	protected function setupCacheDir() {
		$con = self::con();
		$url = Services::WpGeneral()->getWpUrl();

		$lastKnownDirs = $con->opts->optGet( 'last_known_cache_basedirs' );
		$lastKnownDirs = \array_merge( [
			$url => '',
		], \is_array( $lastKnownDirs ) ? $lastKnownDirs : [] );

		$cacheDirFinder = new CacheDirHandler( $lastKnownDirs[ $url ], $con->opts->optGet( 'preferred_temp_dir' ) );
		$lastKnownDirs[ $url ] = \dirname( $cacheDirFinder->dir() );
		$con->opts->optSet( 'last_known_cache_basedirs', $lastKnownDirs );

		$con->cache_dir_handler = $cacheDirFinder;
	}

	/**
	 * https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
	 */
	private function declareWooHposCompat() {
		add_action( 'before_woocommerce_init', function () {
			if ( \class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', self::con()->root_file, true );
			}
		} );
	}

	public function deleteAllPluginCrons() {
		$con = self::con();
		$wpCrons = Services::WpCron();

		foreach ( $wpCrons->getCrons() as /** $key => */ $cronArgs ) {
			foreach ( $cronArgs as $hook => $cron ) {
				if ( \strpos( (string)$hook, $con->prefix() ) === 0 || \strpos( (string)$hook, $con->prefix( '', '_' ) ) === 0 ) {
					$wpCrons->deleteCronJob( $hook );
				}
			}
		}
	}

	/**
	 * Forcefully sets preferred Visitor IP source in the Data component for use throughout the plugin
	 */
	private function setVisitorIpSource() {
		$con = self::con();
		$source = $con->comps->opts_lookup->ipSource();
		if ( $source !== 'AUTO_DETECT_IP' ) {
			Services::Request()->setIpDetector(
				( new RequestIpDetect() )->setPreferredSource( $source )
			);
			Services::IP()->setIpDetector(
				( new VisitorIpDetection() )->setPreferredSource( $source )
			);
		}
		$con->this_req->ip = Services::Request()->ip();
		$con->this_req->ip_is_public = !empty( $con->this_req->ip )
									   && Services::IP()->isValidIp_PublicRemote( $con->this_req->ip );
	}

	/**
	 * @throws \Exception
	 */
	public function canSiteLoopback() :bool {
		$can = false;
		if ( \class_exists( '\WP_Site_Health' ) && \method_exists( '\WP_Site_Health', 'get_instance' ) ) {
			$can = \WP_Site_Health::get_instance()->get_test_loopback_requests()[ 'status' ] === 'good';
		}
		if ( !$can ) {
			$can = Services::HttpRequest()->post( site_url( 'wp-cron.php' ), [
				'timeout' => 10
			] );
		}
		return $can;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getLinkToTrackingDataDump() :string {
		return self::con()->plugin_urls->noncedPluginAction( Actions\PluginDumpTelemetry::class );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getPluginReportEmail() :string {
		$con = self::con();
		$e = (string)$this->opts()->getOpt( 'block_send_email_address' );
		if ( $con->isPremiumActive() ) {
			$e = apply_filters( $con->prefix( 'report_email' ), $e );
		}
		$e = \trim( $e );
		return Services::Data()->validEmail( $e ) ? $e : Services::WpGeneral()->getSiteAdminEmail();
	}

	public function getInstallDate() :int {
		return self::con()->comps === null ? $this->opts()->getOpt( 'installation_time' )
			: self::con()->comps->opts_lookup->getInstalledAt();
	}

	/**
	 * @deprecated 19.1
	 */
	public function isShowAdvanced() :bool {
		return false;
	}

	/**
	 * @return int - the real install timestamp
	 */
	public function storeRealInstallDate() {
		$key = self::con()->prefix( 'install_date', '_' );
		$wpDate = Services::WpGeneral()->getOption( $key );
		if ( empty( $wpDate ) ) {
			$wpDate = Services::Request()->ts();
		}

		$date = $this->getInstallDate();
		if ( $date == 0 ) {
			$date = Services::Request()->ts();
		}

		$finalDate = \min( $date, $wpDate );
		Services::WpGeneral()->updateOption( $key, $finalDate );
		self::con()->opts->optSet( 'installation_time', $date );

		return $finalDate;
	}

	/**
	 * @deprecated 19.1
	 */
	public function getActivateLength() :int {
		return Services::Request()->ts() - (int)$this->opts()->getOpt( 'activated_at', 0 );
	}

	/**
	 * @deprecated 19.1
	 */
	public function setActivatedAt() {
		self::con()->opts->optSet( 'activated_at', Services::Request()->ts() );
	}

	public function runDailyCron() {
		( new WhitelistUs() )->all();
		( new Lib\CleanOldOptions() )->execute();
	}

	public function isXmlrpcBypass() :bool {
		return (bool)apply_filters( 'shield/allow_xmlrpc_login_bypass', false );
	}

	protected function setupHooks() {
		parent::setupHooks();

		add_action( 'admin_footer', function () {
			if ( self::con()->isPluginAdminPageRequest() ) {
				echo self::con()->action_router->render( Actions\Render\Components\ToastPlaceholder::SLUG );
			}
		}, 100, 0 );
	}

	public function getTracking() :Lib\TrackingVO {
		if ( !isset( $this->tracking ) ) {
			$opts = self::con()->opts;

			$data = \method_exists( $opts, 'optGet' ) ?
				$opts->optGet( 'transient_tracking' ) : $this->opts()->getOpt( 'transient_tracking' );
			$this->tracking = ( new Lib\TrackingVO() )->applyFromArray( $data );

			add_action( self::con()->prefix( 'pre_options_store' ), function () {
				$opts = self::con()->opts;
				\method_exists( $opts, 'optSet' ) ?
					$opts->optSet( 'transient_tracking', $this->tracking->getRawData() )
					: $this->opts()->setOpt( 'transient_tracking', $this->tracking->getRawData() );
			} );
		}
		return $this->tracking;
	}

	public function isModOptEnabled() :bool {
		return $this->opts()->isOpt( 'global_enable_plugin_features', 'Y' );
	}
}