<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;
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
	}

	protected function preProcessOptions() {
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

	public function onPluginShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			$preferred = Services::IP()->getIpDetector()->getLastSuccessfulSource();
			if ( !empty( $preferred ) ) {
				$this->getOptions()->setOpt( 'last_ip_detect_source', $preferred );
			}
		}
		parent::onPluginShutdown();
	}

	public function onWpInit() {
		parent::onWpInit();
		$this->getImportExportSecretKey();
	}

	/**
	 * Forcefully sets preferred Visitor IP source in the Data component for use throughout the plugin
	 */
	private function setVisitorIpSource() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !$opts->isIpSourceAutoDetect() ) {
			Services::IP()->setIpDetector(
				( new VisitorIpDetection() )->setPreferredSource( $opts->getIpSource() )
			);
		}
	}

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'plugin_export':
				( new Lib\ImportExport\Export() )
					->setMod( $this )
					->toFile();
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
	 * @return bool
	 * @throws \Exception
	 */
	public function canSiteLoopback() :bool {
		$canLoopback = false;
		if ( class_exists( '\WP_Site_Health' ) && method_exists( '\WP_Site_Health', 'get_instance' ) ) {
			$canLoopback = \WP_Site_Health::get_instance()->get_test_loopback_requests()[ 'status' ] === 'good';
		}
		if ( !$canLoopback ) {
			$canLoopback = Services::HttpRequest()->post( site_url( 'wp-cron.php' ), [
				'timeout' => 10
			] );
		}
		return $canLoopback;
	}

	public function getActivePluginFeatures() :array {
		$features = $this->getOptions()->getDef( 'active_plugin_features' );

		$available = [];
		if ( is_array( $features ) ) {

			foreach ( $features as $feature ) {
				if ( isset( $feature[ 'hidden' ] ) && $feature[ 'hidden' ] ) {
					continue;
				}
				$available[ $feature[ 'slug' ] ] = $feature;
			}
		}
		return $available;
	}

	public function getLinkToTrackingDataDump() :string {
		return add_query_arg(
			[ 'shield_action' => 'dump_tracking_data' ],
			Services::WpGeneral()->getAdminUrl()
		);
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
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$this->storeRealInstallDate();

		if ( $oOpts->isTrackingEnabled() && !$oOpts->isTrackingPermissionSet() ) {
			$oOpts->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
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

		$sOptKey = $this->getCon()->prefixOption( 'install_date' );

		$nWpDate = $WP->getOption( $sOptKey );
		if ( empty( $nWpDate ) ) {
			$nWpDate = $ts;
		}

		$nPluginDate = $this->getInstallDate();
		if ( $nPluginDate == 0 ) {
			$nPluginDate = $ts;
		}

		$nFinal = min( $nPluginDate, $nWpDate );
		$WP->updateOption( $sOptKey, $nFinal );
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
		$sCaptchaKey = preg_replace( '#[^0-9a-zA-Z_-]#', '', $sCaptchaKey ); // restrict character set
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

	public function hasImportExportWhitelistSites() :bool {
		return count( $this->getImportExportWhitelist() ) > 0;
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		$list = $this->getOptions()->getOpt( 'importexport_whitelist', [] );
		return is_array( $list ) ? $list : [];
	}

	/**
	 * @return string
	 */
	protected function getImportExportSecretKey() {
		$opts = $this->getOptions();
		$ID = $opts->getOpt( 'importexport_secretkey', '' );
		if ( empty( $ID ) || $this->isImportExportSecretKeyExpired() ) {
			$ID = sha1( $this->getCon()->getSiteInstallationId().wp_rand( 0, PHP_INT_MAX ) );
			$opts->setOpt( 'importexport_secretkey', $ID )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + HOUR_IN_SECONDS );
		}
		return $ID;
	}

	protected function isImportExportSecretKeyExpired() :bool {
		return Services::Request()->ts() >
			   $this->getOptions()->getOpt( 'importexport_secretkey_expires_at' );
	}

	public function isImportExportWhitelistNotify() :bool {
		return $this->getOptions()->isOpt( 'importexport_whitelist_notify', 'Y' );
	}

	/**
	 * @param string $sUrl
	 * @return $this
	 */
	public function addUrlToImportExportWhitelistUrls( $sUrl ) {
		$sUrl = Services::Data()->validateSimpleHttpUrl( $sUrl );
		if ( $sUrl !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$aWhitelistUrls[] = $sUrl;
			$this->getOptions()->setOpt( 'importexport_whitelist', $aWhitelistUrls );
			$this->saveModOptions();
		}
		return $this;
	}

	/**
	 * @param string $url
	 * @return $this
	 */
	public function removeUrlFromImportExportWhitelistUrls( $url ) {
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$aWhitelistUrls = $this->getImportExportWhitelist();
			$sKey = array_search( $url, $aWhitelistUrls );
			if ( $sKey !== false ) {
				unset( $aWhitelistUrls[ $sKey ] );
			}
			$this->getOptions()->setOpt( 'importexport_whitelist', $aWhitelistUrls );
			$this->saveModOptions();
		}
		return $this;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function isImportExportSecretKey( $sKey ) :bool {
		return !empty( $sKey ) && $this->getImportExportSecretKey() == $sKey;
	}

	protected function cleanImportExportWhitelistUrls() {
		$oDP = Services::Data();

		$aCleaned = [];
		$aWhitelistUrls = $this->getImportExportWhitelist();
		foreach ( $aWhitelistUrls as $nKey => $sUrl ) {

			$sUrl = $oDP->validateSimpleHttpUrl( $sUrl );
			if ( $sUrl !== false ) {
				$aCleaned[] = $sUrl;
			}
		}
		$this->getOptions()->setOpt( 'importexport_whitelist', array_unique( $aCleaned ) );
	}

	protected function cleanImportExportMasterImportUrl() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		$url = Services::Data()->validateSimpleHttpUrl( $oOpts->getImportExportMasterImportUrl() );
		if ( $url === false ) {
			$url = '';
		}
		$this->getOptions()->setOpt( 'importexport_masterurl', $url );
	}

	/**
	 * @param string $url
	 * @return $this
	 */
	public function setImportExportMasterImportUrl( $url ) {
		$this->getOptions()->setOpt( 'importexport_masterurl', $url ); //saving will clean the URL
		return $this->saveModOptions();
	}

	/**
	 * @param string $sId
	 * @return bool
	 */
	protected function isValidInstallId( $sId ) :bool {
		return !empty( $sId ) && is_string( $sId ) && strlen( $sId ) == 40;
	}

	public function isXmlrpcBypass() :bool {
		return $this->getOptions()->isOpt( 'enable_xmlrpc_compatibility', 'Y' );
	}

	public function getCanAdminNotes() :bool {
		return Services::WpUsers()->isUserAdmin();
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();

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
				'strings' => [
					'downloading_file'         => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
					'downloading_file_problem' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
				],
			]
		];

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

	protected function getNamespaceBase() :string {
		return 'Plugin';
	}
}