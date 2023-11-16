<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AssetsCustomizer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\RequestIpDetect;
use FernleafSystems\Wordpress\Services\Utilities\Net\VisitorIpDetection;

class ModCon extends BaseShield\ModCon {

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
	 * @var Shield\ShieldNetApi\ShieldNetApiController
	 */
	private $shieldNetCon;

	/**
	 * @var Lib\Sessions\SessionController
	 */
	private $sessionCon;

	/**
	 * @var Lib\Merlin\MerlinController
	 */
	private $wizardCon;

	/**
	 * @var Lib\TrackingVO
	 */
	private $tracking;

	public function getImpExpController() :Lib\ImportExport\ImportExportController {
		return $this->importExportCon ?? $this->importExportCon = new Lib\ImportExport\ImportExportController();
	}

	public function getPluginBadgeCon() :Components\PluginBadge {
		return $this->pluginBadgeCon ?? $this->pluginBadgeCon = new Components\PluginBadge();
	}

	public function getReportingController() :Lib\Reporting\ReportingController {
		return $this->reportsCon ?? $this->reportsCon = new Lib\Reporting\ReportingController();
	}

	public function getSessionCon() :Lib\Sessions\SessionController {
		return $this->sessionCon ?? $this->sessionCon = new Lib\Sessions\SessionController();
	}

	public function getShieldNetApiController() :Shield\ShieldNetApi\ShieldNetApiController {
		return $this->shieldNetCon ?? $this->shieldNetCon = new Shield\ShieldNetApi\ShieldNetApiController();
	}

	public function getWizardCon() :Lib\Merlin\MerlinController {
		return $this->wizardCon ?? $this->wizardCon = new Lib\Merlin\MerlinController();
	}

	public function getDbH_Reports() :DB\Reports\Ops\Handler {
		return self::con()->db_con->loadDbH( 'reports' );
	}

	protected function doPostConstruction() {
		$this->setVisitorIpSource();
		$this->setupCacheDir();
		$this->declareWooHposCompat();
	}

	public function onWpLoaded() {
		parent::onWpLoaded();
		if ( self::con()->cfg->previous_version !== self::con()->cfg->version() ) {
			$this->getTracking()->last_upgrade_at = Services::Request()->ts();
		}
		( new AssetsCustomizer() )->execute();
	}

	protected function setupCacheDir() {
		$url = Services::WpGeneral()->getWpUrl();
		$lastKnownDirs = $this->opts()->getOpt( 'last_known_cache_basedirs' );
		if ( empty( $lastKnownDirs ) || !\is_array( $lastKnownDirs ) ) {
			$lastKnownDirs = [
				$url => ''
			];
		}

		$cacheDirFinder = new Shield\Utilities\CacheDirHandler( $lastKnownDirs[ $url ] ?? '' );
		$workableDir = $cacheDirFinder->dir();
		$lastKnownDirs[ $url ] = empty( $workableDir ) ? '' : \dirname( $workableDir );

		$this->opts()->setOpt( 'last_known_cache_basedirs', $lastKnownDirs );
		self::con()->cache_dir_handler = $cacheDirFinder;
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

	protected function enumRuleBuilders() :array {
		return [
			Shield\Modules\IPs\Rules\Build\IsPathWhitelisted::class, // this is place here as a hack, so it runs early
			Rules\Build\RequestIsSiteBlockdownBlocked::class,
			Rules\Build\RequestStatusIsAdmin::class,
			Rules\Build\RequestStatusIsAjax::class,
			Rules\Build\RequestStatusIsXmlRpc::class,
			Rules\Build\RequestStatusIsWpCli::class,
			Rules\Build\IsServerLoopback::class,
			Rules\Build\IsTrustedBot::class,
			Rules\Build\IsPublicWebRequest::class,
			Rules\Build\RequestBypassesAllRestrictions::class,
		];
	}

	public function preProcessOptions() {
	}

	public function deleteAllPluginCrons() {
		$con = self::con();
		$wpCrons = Services::WpCron();

		foreach ( $wpCrons->getCrons() as $key => $cronArgs ) {
			foreach ( $cronArgs as $hook => $cron ) {
				if ( \strpos( (string)$hook, $con->prefix() ) === 0 || \strpos( (string)$hook, $con->prefixOption() ) === 0 ) {
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
		/** @var Options $opts */
		$opts = $this->opts();
		if ( $opts->getIpSource() !== 'AUTO_DETECT_IP' ) {
			Services::Request()->setIpDetector(
				( new RequestIpDetect() )->setPreferredSource( $opts->getIpSource() )
			);
			Services::IP()->setIpDetector(
				( new VisitorIpDetection() )->setPreferredSource( $opts->getIpSource() )
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

	public function getLinkToTrackingDataDump() :string {
		return self::con()->plugin_urls->noncedPluginAction( Actions\PluginDumpTelemetry::class );
	}

	public function getPluginReportEmail() :string {
		$con = self::con();
		$e = (string)$this->opts()->getOpt( 'block_send_email_address' );
		if ( $con->isPremiumActive() ) {
			$e = apply_filters( $con->prefix( 'report_email' ), $e );
		}
		$e = \trim( $e );
		return Services::Data()->validEmail( $e ) ? $e : Services::WpGeneral()->getSiteAdminEmail();
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	public function doPrePluginOptionsSave() {
	}

	public function getFirstInstallDate() :int {
		return (int)Services::WpGeneral()->getOption( self::con()->prefixOption( 'install_date' ) );
	}

	public function getInstallDate() :int {
		return (int)$this->opts()->getOpt( 'installation_time', 0 );
	}

	public function isShowAdvanced() :bool {
		return $this->opts()->isOpt( 'show_advanced', 'Y' );
	}

	/**
	 * @return int - the real install timestamp
	 */
	public function storeRealInstallDate() {
		$key = self::con()->prefixOption( 'install_date' );
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
		$this->opts()->setOpt( 'installation_time', $date );

		return $finalDate;
	}

	public function getActivateLength() :int {
		return Services::Request()->ts() - (int)$this->opts()->getOpt( 'activated_at', 0 );
	}

	public function setActivatedAt() {
		$this->opts()->setOpt( 'activated_at', Services::Request()->ts() );
	}

	/**
	 * @deprecated 18.5
	 */
	private function cleanImportExportWhitelistUrls() {
	}

	/**
	 * @deprecated 18.5
	 */
	private function cleanImportExportMasterImportUrl() {
	}

	public function runDailyCron() {
		parent::runDailyCron();
		( new Shield\Utilities\Integration\WhitelistUs() )->all();
	}

	public function isXmlrpcBypass() :bool {
		return (bool)apply_filters( 'shield/allow_xmlrpc_login_bypass', false );
	}

	protected function setupCustomHooks() {
		add_action( 'admin_footer', function () {
			if ( self::con()->isPluginAdminPageRequest() ) {
				echo self::con()->action_router->render( Actions\Render\Components\ToastPlaceholder::SLUG );
			}
		}, 100, 0 );
	}

	public function getTracking() :Lib\TrackingVO {
		if ( !isset( $this->tracking ) ) {
			$this->tracking = ( new Lib\TrackingVO() )->applyFromArray( $this->opts()->getOpt( 'transient_tracking' ) );
			add_action( self::con()->prefix( 'pre_options_store' ), function () {
				$this->opts()->setOpt( 'transient_tracking', $this->tracking->getRawData() );
			} );
		}
		return $this->tracking;
	}

	public function isModOptEnabled() :bool {
		/** @var Options $opts */
		$opts = $this->opts();
		return !$opts->isPluginGloballyDisabled();
	}

	/**
	 * @param string $optionKey
	 * @deprecated 18.5
	 */
	protected function cleanRecaptchaKey( $optionKey ) {
	}
}