<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions,
	Actions\Render\Components\BannerGoPro,
	Actions\Render\Components\ToastPlaceholder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
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
	 * @var Shield\Utilities\ReCaptcha\Enqueue
	 */
	private $oCaptchaEnqueue;

	/**
	 * @var Shield\ShieldNetApi\ShieldNetApiController
	 */
	private $shieldNetCon;

	/**
	 * @var Lib\Sessions\SessionController
	 */
	private $sessionCon;

	public function getSessionCon() :Lib\Sessions\SessionController {
		return $this->sessionCon ?? $this->sessionCon = ( new  Lib\Sessions\SessionController() )->setMod( $this );
	}

	/**
	 * @var Lib\Reporting\ReportingController
	 */
	private $reportsCon;

	public function getDbH_ReportLogs() :DB\Report\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'report' );
	}

	public function getImpExpController() :Lib\ImportExport\ImportExportController {
		return $this->importExportCon ?? $this->importExportCon = ( new Lib\ImportExport\ImportExportController() )->setMod( $this );
	}

	public function getPluginBadgeCon() :Components\PluginBadge {
		return $this->pluginBadgeCon ?? $this->pluginBadgeCon = ( new Components\PluginBadge() )->setMod( $this );
	}

	public function getReportingController() :Lib\Reporting\ReportingController {
		return $this->reportsCon ?? $this->reportsCon = ( new Lib\Reporting\ReportingController() )->setMod( $this );
	}

	public function getShieldNetApiController() :Shield\ShieldNetApi\ShieldNetApiController {
		return $this->shieldNetCon ?? $this->shieldNetCon = ( new Shield\ShieldNetApi\ShieldNetApiController() )->setMod( $this );
	}

	protected function doPostConstruction() {
		$this->setVisitorIpSource();
		$this->setupCacheDir();
	}

	protected function setupCacheDir() {
		$opts = $this->getOptions();
		$url = Services::WpGeneral()->getWpUrl();
		$lastKnownDirs = $opts->getOpt( 'last_known_cache_basedirs' );
		if ( empty( $lastKnownDirs ) || !is_array( $lastKnownDirs ) ) {
			$lastKnownDirs = [
				$url => ''
			];
		}

		$cacheDirFinder = ( new Shield\Utilities\CacheDirHandler( $lastKnownDirs[ $url ] ?? '' ) )->setCon( $this->getCon() );
		$workableDir = $cacheDirFinder->dir();
		$lastKnownDirs[ $url ] = empty( $workableDir ) ? '' : dirname( $workableDir );

		$opts->setOpt( 'last_known_cache_basedirs', $lastKnownDirs );
		$this->getCon()->cache_dir_handler = $cacheDirFinder;
	}

	protected function enumRuleBuilders() :array {
		return [
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

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->getIpSource() === 'AUTO_DETECT_IP' ) {
			$opts->setOpt( 'ipdetect_at', 0 );
		}

		( new Lib\Captcha\CheckCaptchaSettings() )
			->setMod( $this )
			->checkAll();
	}

	public function deleteAllPluginCrons() {
		$con = $this->getCon();
		$wpCrons = Services::WpCron();

		foreach ( $wpCrons->getCrons() as $key => $cronArgs ) {
			foreach ( $cronArgs as $hook => $cron ) {
				if ( strpos( (string)$hook, $con->prefix() ) === 0 || strpos( (string)$hook, $con->prefixOption() ) === 0 ) {
					$wpCrons->deleteCronJob( $hook );
				}
			}
		}
	}

	/**
	 * Forcefully sets preferred Visitor IP source in the Data component for use throughout the plugin
	 */
	private function setVisitorIpSource() {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->getOptions();
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
		if ( class_exists( '\WP_Site_Health' ) && method_exists( '\WP_Site_Health', 'get_instance' ) ) {
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
		return $this->getCon()->plugin_urls->noncedPluginAction( Actions\PluginDumpTelemetry::SLUG );
	}

	public function getPluginReportEmail() :string {
		$e = (string)$this->getOptions()->getOpt( 'block_send_email_address' );
		if ( $this->isPremium() ) {
			$e = apply_filters( $this->getCon()->prefix( 'report_email' ), $e );
		}
		$e = trim( $e );
		return Services::Data()->validEmail( $e ) ? $e : Services::WpGeneral()->getSiteAdminEmail();
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->storeRealInstallDate();

		if ( $opts->isTrackingEnabled() && !$opts->isTrackingPermissionSet() ) {
			$opts->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
		}

		$this->cleanRecaptchaKey( 'google_recaptcha_site_key' );
		$this->cleanRecaptchaKey( 'google_recaptcha_secret_key' );

		$this->cleanImportExportWhitelistUrls();
		$this->cleanImportExportMasterImportUrl();
	}

	public function getFirstInstallDate() :int {
		return (int)Services::WpGeneral()->getOption( $this->getCon()->prefixOption( 'install_date' ) );
	}

	public function getInstallDate() :int {
		return (int)$this->getOptions()->getOpt( 'installation_time', 0 );
	}

	public function isShowAdvanced() :bool {
		return $this->getOptions()->isOpt( 'show_advanced', 'Y' );
	}

	/**
	 * @return int - the real install timestamp
	 */
	public function storeRealInstallDate() {
		$WP = Services::WpGeneral();
		$ts = Services::Request()->ts();

		$key = $this->getCon()->prefixOption( 'install_date' );

		$nWpDate = $WP->getOption( $key );
		if ( empty( $nWpDate ) ) {
			$nWpDate = $ts;
		}

		$nPluginDate = $this->getInstallDate();
		if ( $nPluginDate == 0 ) {
			$nPluginDate = $ts;
		}

		$nFinal = min( $nPluginDate, $nWpDate );
		$WP->updateOption( $key, $nFinal );
		$this->getOptions()->setOpt( 'installation_time', $nPluginDate );

		return $nFinal;
	}

	/**
	 * @param string $optionKey
	 */
	protected function cleanRecaptchaKey( $optionKey ) {
		$opts = $this->getOptions();
		$sCaptchaKey = trim( (string)$opts->getOpt( $optionKey, '' ) );
		$nSpacePos = strpos( $sCaptchaKey, ' ' );
		if ( $nSpacePos !== false ) {
			$sCaptchaKey = substr( $sCaptchaKey, 0, $nSpacePos + 1 ); // cut off the string if there's spaces
		}
		$sCaptchaKey = preg_replace( '#[^\da-zA-Z_-]#', '', $sCaptchaKey ); // restrict character set
//			if ( strlen( $sCaptchaKey ) != 40 ) {
//				$sCaptchaKey = ''; // need to verify length is 40.
//			}
		$opts->setOpt( $optionKey, $sCaptchaKey );
	}

	public function getActivateLength() :int {
		return Services::Request()->ts() - (int)$this->getOptions()->getOpt( 'activated_at', 0 );
	}

	public function getTourManager() :Lib\TourManager {
		return ( new Lib\TourManager() )->setMod( $this );
	}

	public function setActivatedAt() {
		$this->getOptions()->setOpt( 'activated_at', Services::Request()->ts() );
	}

	private function cleanImportExportWhitelistUrls() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$cleaned = [];
		$whitelist = $opts->getImportExportWhitelist();
		foreach ( $whitelist as $url ) {

			$url = Services::Data()->validateSimpleHttpUrl( $url );
			if ( $url !== false ) {
				$cleaned[] = $url;
			}
		}
		$opts->setOpt( 'importexport_whitelist', array_unique( $cleaned ) );
	}

	private function cleanImportExportMasterImportUrl() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$url = Services::Data()->validateSimpleHttpUrl( $opts->getImportExportMasterImportUrl() );
		$opts->setOpt( 'importexport_masterurl', $url === false ? '' : $url );
	}

	public function isXmlrpcBypass() :bool {
		return (bool)apply_filters( 'shield/allow_xmlrpc_login_bypass', false );
	}

	public function getCanAdminNotes() :bool {
		return Services::WpUsers()->isUserAdmin();
	}

	public function getDbHandler_Notes() :Shield\Databases\AdminNotes\Handler {
		return $this->getDbH( 'notes' );
	}

	public function getCaptchaEnqueue() :Shield\Utilities\ReCaptcha\Enqueue {
		if ( !isset( $this->oCaptchaEnqueue ) ) {
			$this->oCaptchaEnqueue = ( new Shield\Utilities\ReCaptcha\Enqueue() )->setMod( $this );
		}
		return $this->oCaptchaEnqueue;
	}

	protected function setupCustomHooks() {
		add_action( 'admin_footer', function () {
			$AR = $this->getCon()->action_router;
			if ( !empty( $AR ) ) {
				echo $AR->render( BannerGoPro::SLUG );
				if ( $this->getCon()->isModulePage() ) {
					echo $AR->render( ToastPlaceholder::SLUG );
				}
			}
		}, 100, 0 );
	}

	public function isModOptEnabled() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !$opts->isPluginGloballyDisabled();
	}

	/**
	 * Ensure we always a valid installation ID.
	 *
	 * @return string
	 * @deprecated but still used because it aligns with stats collection
	 * @deprecated 17.0
	 */
	public function getPluginInstallationId() {
		return $this->getCon()->getInstallationID()[ 'id' ];
	}

	/**
	 * @param string $newID - leave empty to reset if the current isn't valid
	 * @return string
	 * @deprecated 17.0
	 */
	protected function setPluginInstallationId( $newID = null ) {
		return $newID;
	}

	/**
	 * @deprecated 17.0
	 */
	protected function genInstallId() :string {
		return $this->getCon()->getInstallationID()[ 'id' ];
	}

	protected function getNamespaceBase() :string {
		return 'Plugin';
	}

	/**
	 * @param string $id
	 * @deprecated 17.0
	 */
	protected function isValidInstallId( $id ) :bool {
		return false;
	}
}