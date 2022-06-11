<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\RequestIpDetect;
use FernleafSystems\Wordpress\Services\Utilities\Net\VisitorIpDetection;

class ModCon extends BaseShield\ModCon {

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

	public function getImpExpController() :Lib\ImportExport\ImportExportController {
		if ( !isset( $this->importExportCon ) ) {
			$this->importExportCon = ( new Lib\ImportExport\ImportExportController() )
				->setMod( $this );
		}
		return $this->importExportCon;
	}

	public function getPluginBadgeCon() :Components\PluginBadge {
		if ( !isset( $this->pluginBadgeCon ) ) {
			$this->pluginBadgeCon = ( new Components\PluginBadge() )
				->setMod( $this );
		}
		return $this->pluginBadgeCon;
	}

	public function getShieldNetApiController() :Shield\ShieldNetApi\ShieldNetApiController {
		if ( !isset( $this->shieldNetCon ) ) {
			$this->shieldNetCon = ( new Shield\ShieldNetApi\ShieldNetApiController() )
				->setMod( $this );
		}
		return $this->shieldNetCon;
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

		$cacheDirFinder = ( new Shield\Utilities\CacheDirFinder( $lastKnownDirs[ $url ] ?? '' ) )->setCon( $this->getCon() );
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

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'plugin_export':
				( new Lib\ImportExport\Export() )
					->setMod( $this )
					->toFile();
				break;
			default:
				break;
		}
	}

	protected function handleModAction( string $action ) {

		switch ( $action ) {
			case 'import_file_upload':
				try {
					( new Lib\ImportExport\Import() )
						->setMod( $this )
						->fromFileUpload();
					$success = true;
					$msg = __( 'Options imported successfully', 'wp-simple-firewall' );
				}
				catch ( \Exception $e ) {
					$success = false;
					$msg = $e->getMessage();
				}
				$this->setFlashAdminNotice( $msg, null, !$success );
				Services::Response()->redirect(
					$this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'importexport' )
				);
				break;

			default:
				parent::handleModAction( $action );
				break;
		}
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
		return add_query_arg( [ 'shield_action' => 'dump_tracking_data' ], Services::WpGeneral()->getAdminUrl() );
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

		$this->setPluginInstallationId();
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
	 * @return string
	 */
	public function getOpenSslPrivateKey() {
		$opts = $this->getOptions();
		$key = null;
		$srvEnc = Services::Encrypt();
		if ( $srvEnc->isSupportedOpenSslDataEncryption() ) {
			$key = $opts->getOpt( 'openssl_private_key' );
			if ( empty( $key ) ) {
				try {
					$keys = $srvEnc->createNewPrivatePublicKeyPair();
					if ( !empty( $keys[ 'private' ] ) ) {
						$key = $keys[ 'private' ];
						$opts->setOpt( 'openssl_private_key', base64_encode( $key ) );
						$this->saveModOptions();
					}
				}
				catch ( \Exception $e ) {
				}
			}
			else {
				$key = base64_decode( $key );
			}
		}
		return $key;
	}

	/**
	 * @return string|null
	 */
	public function getOpenSslPublicKey() {
		$key = null;
		if ( $this->hasOpenSslPrivateKey() ) {
			try {
				$key = Services::Encrypt()->getPublicKeyFromPrivateKey( $this->getOpenSslPrivateKey() );
			}
			catch ( \Exception $e ) {
			}
		}
		return $key;
	}

	public function hasOpenSslPrivateKey() :bool {
		return !empty( $this->getOpenSslPrivateKey() );
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

	/**
	 * Ensure we always a valid installation ID.
	 *
	 * @return string
	 * @deprecated but still used because it aligns with stats collection
	 */
	public function getPluginInstallationId() {
		$ID = $this->getOptions()->getOpt( 'unique_installation_id', '' );

		if ( !$this->isValidInstallId( $ID ) ) {
			$ID = $this->setPluginInstallationId();
		}
		return $ID;
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

	/**
	 * @param string $newID - leave empty to reset if the current isn't valid
	 * @return string
	 */
	protected function setPluginInstallationId( $newID = null ) {
		// only reset if it's not of the correct type
		if ( !$this->isValidInstallId( $newID ) ) {
			$newID = $this->genInstallId();
		}
		$this->getOptions()->setOpt( 'unique_installation_id', $newID );
		return $newID;
	}

	protected function genInstallId() :string {
		return sha1(
			$this->getInstallDate()
			.Services::WpGeneral()->getWpUrl()
			.Services::WpDb()->getPrefix()
		);
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

	/**
	 * @param string $id
	 */
	protected function isValidInstallId( $id ) :bool {
		return !empty( $id ) && is_string( $id ) && strlen( $id ) == 40;
	}

	public function isXmlrpcBypass() :bool {
		return (bool)apply_filters( 'shield/allow_xmlrpc_login_bypass', false );
	}

	public function getCanAdminNotes() :bool {
		return Services::WpUsers()->isUserAdmin();
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();
		$con = $this->getCon();

		$tourManager = $this->getTourManager();
		$locals[] = [
			'shield/tours',
			'shield_vars_tourmanager',
			[
				'ajax'        => $this->getAjaxActionData( 'mark_tour_finished' ),
				'tour_states' => $tourManager->getUserTourStates(),
				'tours'       => $tourManager->getAllTours(),
			]
		];

		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_plugin',
			[
				'components' => [
					'helpscout'   => [
						'beacon_id' => $con->isPremiumActive() ? 'db2ff886-2329-4029-9452-44587df92c8c' : 'aded6929-af83-452d-993f-a60c03b46568',
						'visible'   => $con->isModulePage()
					],
					'mod_options' => [
						'ajax' => [
							'mod_options_save' => $this->getAjaxActionData( 'mod_options_save' )
						]
					],
				],
				'strings'    => [
					'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
					'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
				],
			]
		];

		$locals[] = [
			'global-plugin',
			'icwp_wpsf_vars_globalplugin',
			[
				'vars' => [
					'dashboard_widget' => [
						'ajax' => [
							'render_dashboard_widget' => $this->getAjaxActionData( 'render_dashboard_widget' )
						]
					],
					'notices'          => [
						'ajax' => [
							'auto_db_repair'  => $this->getAjaxActionData( 'auto_db_repair' ),
							'delete_forceoff' => $this->getAjaxActionData( 'delete_forceoff' ),
						]
					]
				],
			]
		];

		$req = Services::Request();
		$opts = $this->getOptions();
		$runCheck = ( $req->ts() - $opts->getOpt( 'ipdetect_at' ) > WEEK_IN_SECONDS*4 )
					|| ( Services::WpUsers()->isUserAdmin() && !empty( $req->query( 'shield_check_ip_source' ) ) );
		if ( $runCheck ) {
			$opts->setOpt( 'ipdetect_at', $req->ts() );
			$locals[] = [
				'shield/ip_detect',
				'icwp_wpsf_vars_ipdetect',
				[
					'url'     => 'https://net.getshieldsecurity.com/wp-json/apto-snapi/v2/tools/what_is_my_ip',
					'ajax'    => $this->getAjaxActionData( 'ipdetect' ),
					'strings' => [
						'source_found' => __( 'Valid visitor IP address source discovered.', 'wp-simple-firewall' ),
						'ip_source'    => __( 'IP Source', 'wp-simple-firewall' ),
						'reloading'    => __( 'Please reload the page.', 'wp-simple-firewall' ),
					],
				]
			];
		}

		return $locals;
	}

	public function getCustomScriptEnqueues() :array {
		$enqs = [];
		if ( Services::WpPost()->isCurrentPage( 'plugins.php' ) ) {
			$enqs[ Enqueue::CSS ] = [
				'wp-wp-jquery-ui-dialog'
			];
			$enqs[ Enqueue::JS ] = [
				'wp-jquery-ui-dialog'
			];
		}
		return $enqs;
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

	public function isModOptEnabled() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !$opts->isPluginGloballyDisabled();
	}

	protected function getNamespaceBase() :string {
		return 'Plugin';
	}
}