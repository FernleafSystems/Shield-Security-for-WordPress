<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ICWP_WPSF_FeatureHandler_Base
 */
abstract class ICWP_WPSF_FeatureHandler_Base {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @var string
	 */
	private $sOptionsStoreKey;

	/**
	 * @var string
	 */
	protected $sModSlug;

	/**
	 * @var bool
	 */
	protected $bImportExportWhitelistNotify = false;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Email
	 */
	private static $oEmailHandler;

	/**
	 * @var Shield\Modules\Base\BaseProcessor
	 */
	private $oProcessor;

	/**
	 * @var ICWP_WPSF_Wizard_Base
	 */
	private $oWizard;

	/**
	 * @var Shield\Modules\Base\BaseReporting
	 */
	private $oReporting;

	/**
	 * @var Shield\Modules\Base\Strings
	 */
	private $oStrings;

	/**
	 * @var Shield\Modules\Base\UI
	 */
	private $oUI;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $oOpts;

	/**
	 * @var Shield\Modules\Base\WpCli
	 */
	private $oWpCli;

	/**
	 * @var Shield\Databases\Base\Handler[]
	 */
	private $aDbHandlers;

	/**
	 * @param Shield\Controller\Controller $oPluginController
	 * @param array                        $aMod
	 * @throws \Exception
	 */
	public function __construct( $oPluginController, $aMod = [] ) {
		if ( !$oPluginController instanceof Shield\Controller\Controller ) {
			throw new \Exception( 'Plugin controller not supplied to Module' );
		}
		$this->setCon( $oPluginController );

		if ( empty( $aMod[ 'storage_key' ] ) && empty( $aMod[ 'slug' ] ) ) {
			throw new \Exception( 'Module storage key AND slug are undefined' );
		}

		$this->sOptionsStoreKey = empty( $aMod[ 'storage_key' ] ) ? $aMod[ 'slug' ] : $aMod[ 'storage_key' ];
		if ( isset( $aMod[ 'slug' ] ) ) {
			$this->sModSlug = $aMod[ 'slug' ];
		}

		if ( $this->verifyModuleMeetRequirements() ) {
			$this->handleAutoPageRedirects();
			$this->setupHooks( $aMod );
			$this->doPostConstruction();
		}
	}

	/**
	 * @param array $aModProps
	 */
	protected function setupHooks( $aModProps ) {
		$con = $this->getCon();
		$nRunPriority = isset( $aModProps[ 'load_priority' ] ) ? $aModProps[ 'load_priority' ] : 100;

		add_action( $con->prefix( 'modules_loaded' ), function () {
			$this->onModulesLoaded();
		}, $nRunPriority );
		add_action( $con->prefix( 'run_processors' ), [ $this, 'onRunProcessors' ], $nRunPriority );
		add_action( 'init', [ $this, 'onWpInit' ], 1 );

		$nMenuPri = isset( $aModProps[ 'menu_priority' ] ) ? $aModProps[ 'menu_priority' ] : 100;
		add_filter( $con->prefix( 'submenu_items' ), [ $this, 'supplySubMenuItem' ], $nMenuPri );
		add_action( $con->prefix( 'plugin_shutdown' ), [ $this, 'onPluginShutdown' ] );
		add_action( $con->prefix( 'deactivate_plugin' ), [ $this, 'onPluginDeactivate' ] );
		add_action( $con->prefix( 'delete_plugin' ), [ $this, 'onPluginDelete' ] );
		add_filter( $con->prefix( 'aggregate_all_plugin_options' ), [ $this, 'aggregateOptionsValues' ] );

		add_filter( $con->prefix( 'register_admin_notices' ), [ $this, 'fRegisterAdminNotices' ] );

		add_action( $con->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $con->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );

		// supply supported events for this module
		add_filter( $con->prefix( 'get_all_events' ), function ( $aEvents ) {
			return array_merge(
				is_array( $aEvents ) ? $aEvents : [],
				array_map(
					function ( $aEvt ) {
						$aEvt[ 'context' ] = $this->getSlug();
						return $aEvt;
					},
					is_array( $this->getDef( 'events' ) ) ? $this->getDef( 'events' ) : []
				)
			);
		} );

		add_action( 'admin_enqueue_scripts', [ $this, 'onWpEnqueueAdminJs' ], 100 );

		if ( is_admin() || is_network_admin() ) {
			$this->loadAdminNotices();
		}

//		if ( $this->isAdminOptionsPage() ) {
//			add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
//		}

		$this->setupCustomHooks();
	}

	protected function setupCustomHooks() {
	}

	protected function doPostConstruction() {
	}

	public function runDailyCron() {
		$this->cleanupDatabases();
	}

	public function runHourlyCron() {
	}

	protected function cleanupDatabases() {
		foreach ( $this->getDbHandlers( true ) as $oDbh ) {
			if ( $oDbh instanceof Shield\Databases\Base\Handler && $oDbh->isReady() ) {
				$oDbh->autoCleanDb();
			}
		}
	}

	/**
	 * @param bool $bInitAll
	 * @return Shield\Databases\Base\Handler[]
	 */
	protected function getDbHandlers( $bInitAll = false ) {
		if ( $bInitAll ) {
			foreach ( $this->getAllDbClasses() as $sDbSlug => $sDbClass ) {
				$this->getDbH( $sDbSlug );
			}
		}
		return is_array( $this->aDbHandlers ) ? $this->aDbHandlers : [];
	}

	/**
	 * @param string $sDbhKey
	 * @return Shield\Databases\Base\Handler|mixed|false
	 */
	protected function getDbH( $sDbhKey ) {
		$oDbH = false;

		if ( !is_array( $this->aDbHandlers ) ) {
			$this->aDbHandlers = [];
		}

		if ( !empty( $this->aDbHandlers[ $sDbhKey ] ) ) {
			$oDbH = $this->aDbHandlers[ $sDbhKey ];
		}
		else {
			$aDbClasses = $this->getAllDbClasses();
			if ( isset( $aDbClasses[ $sDbhKey ] ) ) {
				/** @var Shield\Databases\Base\Handler $oDbH */
				$oDbH = new $aDbClasses[ $sDbhKey ]();
				try {
					$oDbH->setMod( $this )->tableInit();
				}
				catch ( \Exception $oE ) {
				}
			}
			$this->aDbHandlers[ $sDbhKey ] = $oDbH;
		}

		return $oDbH;
	}

	/**
	 * @return string[]
	 */
	private function getAllDbClasses() {
		$classes = $this->getOptions()->getDef( 'db_classes' );
		return is_array( $classes ) ? $classes : [];
	}

	/**
	 * @return false|Shield\Modules\Base\Upgrade|mixed
	 */
	public function getUpgradeHandler() {
		return $this->loadClass( 'Upgrade' );
	}

	/**
	 * @param string $sEncoding
	 * @return array
	 */
	public function getAjaxFormParams( $sEncoding = 'none' ) {
		$oReq = Services::Request();
		$aFormParams = [];
		$sRaw = $oReq->post( 'form_params', '' );

		if ( !empty( $sRaw ) ) {

			$sMaybeEncoding = $oReq->post( 'enc_params' );
			if ( in_array( $sMaybeEncoding, [ 'none', 'lz-string', 'b64' ] ) ) {
				$sEncoding = $sMaybeEncoding;
			}

			switch ( $sEncoding ) {
				case 'lz-string':
					$sRaw = \LZCompressor\LZString::decompress( base64_decode( $sRaw ) );
					break;

				case 'b64':
					$sRaw = base64_decode( $sRaw );
					break;

				case 'none':
				default:
					break;
			}

			parse_str( $sRaw, $aFormParams );
		}
		return $aFormParams;
	}

	/**
	 * @param array $aAdminNotices
	 * @return array
	 */
	public function fRegisterAdminNotices( $aAdminNotices ) {
		if ( !is_array( $aAdminNotices ) ) {
			$aAdminNotices = [];
		}
		return array_merge( $aAdminNotices, $this->getOptions()->getAdminNotices() );
	}

	/**
	 * @return bool
	 */
	private function verifyModuleMeetRequirements() {
		$bMeetsReqs = true;

		$aPhpReqs = $this->getOptions()->getFeatureRequirement( 'php' );
		if ( !empty( $aPhpReqs ) ) {

			if ( !empty( $aPhpReqs[ 'version' ] ) ) {
				$bMeetsReqs = $bMeetsReqs && Services::Data()->getPhpVersionIsAtLeast( $aPhpReqs[ 'version' ] );
			}
			if ( !empty( $aPhpReqs[ 'functions' ] ) && is_array( $aPhpReqs[ 'functions' ] ) ) {
				foreach ( $aPhpReqs[ 'functions' ] as $sFunction ) {
					$bMeetsReqs = $bMeetsReqs && function_exists( $sFunction );
				}
			}
			if ( !empty( $aPhpReqs[ 'constants' ] ) && is_array( $aPhpReqs[ 'constants' ] ) ) {
				foreach ( $aPhpReqs[ 'constants' ] as $sConstant ) {
					$bMeetsReqs = $bMeetsReqs && defined( $sConstant );
				}
			}
		}

		return $bMeetsReqs;
	}

	protected function onModulesLoaded() {
	}

	public function onRunProcessors() {
		$oOpts = $this->getOptions();
		if ( $oOpts->getFeatureProperty( 'auto_load_processor' ) ) {
			$this->loadProcessor();
		}
		try {
			$bSkip = (bool)$oOpts->getFeatureProperty( 'skip_processor' );
			if ( !$bSkip && !$this->isUpgrading() && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->doExecuteProcessor();
			}
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return !is_null( $this->getProcessor() );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpInit() {

		$sShieldAction = $this->getCon()->getShieldAction();
		if ( !empty( $sShieldAction ) ) {
			do_action( $this->getCon()->prefix( 'shield_action' ), $sShieldAction );
		}

		if ( Services::Data()->getPhpVersionIsAtLeast( '7.0' ) ) {
			add_action( 'cli_init', function () {
				try {
					$this->getWpCli()->execute();
				}
				catch ( \Exception $oE ) {
				}
			} );
		}

		if ( $this->isModuleRequest() ) {

			if ( Services::WpGeneral()->isAjax() ) {
				$this->loadAjaxHandler();
			}
			else {
				try {
					if ( $this->verifyModActionRequest() ) {
						$this->handleModAction( Services::Request()->request( 'exec' ) );
					}
				}
				catch ( \Exception $oE ) {
					wp_nonce_ays( '' );
				}
			}
		}

		$this->runWizards();

		// GDPR
		if ( $this->isPremium() ) {
			add_filter( $this->prefix( 'wpPrivacyExport' ), [ $this, 'onWpPrivacyExport' ], 10, 3 );
			add_filter( $this->prefix( 'wpPrivacyErase' ), [ $this, 'onWpPrivacyErase' ], 10, 3 );
		}
	}

	/**
	 * We have to do it this way as the "page hook" is built upon the top-level plugin
	 * menu name. But what if we white label?  So we need to dynamically grab the page hook
	 */
	public function onSetCurrentScreen() {
		global $page_hook;
		add_action( 'load-'.$page_hook, [ $this, 'onLoadOptionsScreen' ] );
	}

	public function onLoadOptionsScreen() {
		if ( $this->getCon()->isValidAdminArea() ) {
			$this->buildContextualHelp();
		}
	}

	/**
	 * Override this and adapt per feature
	 * @return Shield\Modules\Base\BaseProcessor|mixed
	 */
	protected function loadProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			$sClassName = $this->getProcessorClassName();
			if ( !class_exists( $sClassName ) ) {
				return null;
			}
			$this->oProcessor = new $sClassName( $this );
		}
		return $this->oProcessor;
	}

	/**
	 * Override this and adapt per feature
	 * @return string
	 */
	protected function getProcessorClassName() {
		return implode( '_',
			[
				strtoupper( $this->getCon()->getPluginPrefix( '_' ) ),
				'Processor',
				str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) )
			]
		);
	}

	/**
	 * Override this and adapt per feature
	 * @return string
	 */
	protected function getWizardClassName() {
		return implode( '_',
			[
				strtoupper( $this->getCon()->getPluginPrefix( '_' ) ),
				'Wizard',
				str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) )
			]
		);
	}

	/**
	 * @return bool
	 */
	public function isUpgrading() {
		return $this->getCon()->getIsRebuildOptionsFromFile() || $this->getOptions()->getRebuildFromFile();
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			if ( rand( 1, 40 ) === 2 ) {
				// cleanup databases randomly just in-case cron doesn't run.
				$this->cleanupDatabases();
			}
			$this->saveModOptions();
		}
	}

	/**
	 * @return string
	 */
	public function getOptionsStorageKey() {
		return $this->getCon()->prefixOption( $this->sOptionsStoreKey ).'_options';
	}

	/**
	 * @return Shield\Modules\Base\BaseProcessor|Shield\Modules\Base\OneTimeExecute|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	/**
	 * @return string
	 */
	public function getUrl_AdminPage() {
		return Services::WpGeneral()
					   ->getUrl_AdminPage(
						   $this->getModSlug(),
						   $this->getCon()->getIsWpmsNetworkAdminOnly()
					   );
	}

	/**
	 * @param string $sAction
	 * @return string
	 */
	public function buildAdminActionNonceUrl( $sAction ) {
		$aActionNonce = $this->getNonceActionData( $sAction );
		$aActionNonce[ 'ts' ] = Services::Request()->ts();
		return add_query_arg( $aActionNonce, $this->getUrl_AdminPage() );
	}

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function getModActionParams( $sAction ) {
		$con = $this->getCon();
		return [
			'action'     => $con->prefix(),
			'exec'       => $sAction,
			'mod_slug'   => $this->getModSlug(),
			'ts'         => Services::Request()->ts(),
			'exec_nonce' => substr(
				hash_hmac( 'md5', $sAction.Services::Request()->ts(), $con->getSiteInstallationId() )
				, 0, 6 )
		];
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function verifyModActionRequest() {
		$bValid = false;

		$oCon = $this->getCon();
		$oReq = Services::Request();

		$sExec = $oReq->request( 'exec' );
		if ( !empty( $sExec ) && $oReq->request( 'action' ) == $oCon->prefix() ) {


			if ( wp_verify_nonce( $oReq->request( 'exec_nonce' ), $sExec ) && $oCon->getMeetsBasePermissions() ) {
				$bValid = true;
			}
			else {
				$bValid = $oReq->request( 'exec_nonce' ) ===
						  substr( hash_hmac( 'md5', $sExec.$oReq->request( 'ts' ), $oCon->getSiteInstallationId() ), 0, 6 );
			}
			if ( !$bValid ) {
				throw new Exception( 'Invalid request' );
			}
		}

		return $bValid;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getUrl_DirectLinkToOption( $sOptKey ) {
		$sUrl = $this->getUrl_AdminPage();
		$aDef = $this->getOptions()->getOptDefinition( $sOptKey );
		if ( !empty( $aDef[ 'section' ] ) ) {
			$sUrl = $this->getUrl_DirectLinkToSection( $aDef[ 'section' ] );
		}
		return $sUrl;
	}

	/**
	 * @param string $sSection
	 * @return string
	 */
	public function getUrl_DirectLinkToSection( $sSection ) {
		if ( $sSection == 'primary' ) {
			$aSec = $this->getOptions()->getPrimarySection();
			$sSection = $aSec[ 'slug' ];
		}
		return $this->getUrl_AdminPage().'#tab-'.$sSection;
	}

	/**
	 * TODO: Get rid of this crap and/or handle the \Exception thrown in loadFeatureHandler()
	 * @return ICWP_WPSF_FeatureHandler_Email
	 * @throws \Exception
	 */
	public function getEmailHandler() {
		if ( is_null( self::$oEmailHandler ) ) {
			self::$oEmailHandler = $this->getCon()->getModule( 'email' );
		}
		return self::$oEmailHandler;
	}

	/**
	 * @return ICWP_WPSF_Processor_Email
	 */
	public function getEmailProcessor() {
		return $this->getEmailHandler()->getProcessor();
	}

	/**
	 * @param bool $bEnable
	 * @return $this
	 */
	public function setIsMainFeatureEnabled( $bEnable ) {
		return $this->setOpt( 'enable_'.$this->getSlug(), $bEnable ? 'Y' : 'N' );
	}

	/**
	 * @return bool
	 */
	public function isModuleEnabled() {
		/** @var Shield\Modules\Plugin\Options $oPluginOpts */
		$oPluginOpts = $this->getCon()->getModule_Plugin()->getOptions();

		if ( $this->getOptions()->getFeatureProperty( 'auto_enabled' ) === true ) {
			// Auto enabled modules always run regardless
			$bEnabled = true;
		}
		elseif ( $oPluginOpts->isPluginGloballyDisabled() ) {
			$bEnabled = false;
		}
		elseif ( $this->getCon()->getIfForceOffActive() ) {
			$bEnabled = false;
		}
		elseif ( $this->getOptions()->getFeatureProperty( 'premium' ) === true
				 && !$this->isPremium() ) {
			$bEnabled = false;
		}
		else {
			$bEnabled = $this->isModOptEnabled();
		}

		return $bEnabled;
	}

	/**
	 * @return bool
	 */
	public function isModOptEnabled() {
		return $this->isOpt( $this->getEnableModOptKey(), 'Y' )
			   || $this->isOpt( $this->getEnableModOptKey(), true, true );
	}

	/**
	 * @return string
	 */
	public function getEnableModOptKey() {
		return 'enable_'.$this->getSlug();
	}

	/**
	 * @return string
	 */
	public function getMainFeatureName() {
		return __( $this->getOptions()->getFeatureProperty( 'name' ), 'wp-simple-firewall' );
	}

	/**
	 * @param bool $bWithPrefix
	 * @return string
	 */
	public function getModSlug( $bWithPrefix = true ) {
		return $bWithPrefix ? $this->prefix( $this->getSlug() ) : $this->getSlug();
	}

	/**
	 * @return string
	 */
	public function getSlug() {
		if ( !isset( $this->sModSlug ) ) {
			$this->sModSlug = $this->getOptions()->getFeatureProperty( 'slug' );
		}
		return $this->sModSlug;
	}

	/**
	 * @param array $aItems
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function addAdminMenuBarItems( array $aItems ) {
		return $aItems;
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function supplySubMenuItem( $aItems ) {

		$sTitle = $this->getOptions()->getFeatureProperty( 'menu_title' );
		$sTitle = empty( $sTitle ) ? $this->getMainFeatureName() : __( $sTitle, 'wp-simple-firewall' );

		if ( !empty( $sTitle ) ) {

			$sHumanName = $this->getCon()->getHumanName();

			$bMenuHighlighted = $this->getOptions()->getFeatureProperty( 'highlight_menu_item' );
			if ( $bMenuHighlighted ) {
				$sTitle = sprintf( '<span class="icwp_highlighted">%s</span>', $sTitle );
			}

			$sMenuPageTitle = $sTitle.' - '.$sHumanName;
			$aItems[ $sMenuPageTitle ] = [
				$sTitle,
				$this->getModSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$this->getIfShowModuleMenuItem()
			];

			$aAdditionalItems = $this->getOptions()->getAdditionalMenuItems();
			if ( !empty( $aAdditionalItems ) && is_array( $aAdditionalItems ) ) {

				foreach ( $aAdditionalItems as $aMenuItem ) {
					$sMenuPageTitle = $sHumanName.' - '.$aMenuItem[ 'title' ];
					$aItems[ $sMenuPageTitle ] = [
						__( $aMenuItem[ 'title' ], 'wp-simple-firewall' ),
						$this->prefix( $aMenuItem[ 'slug' ] ),
						[ $this, $aMenuItem[ 'callback' ] ],
						true
					];
				}
			}
		}
		return $aItems;
	}

	/**
	 * Handles the case where we want to redirect certain menu requests to other pages
	 * of the plugin automatically. It lets us create custom menu items.
	 * This can of course be extended for any other types of redirect.
	 */
	public function handleAutoPageRedirects() {
		$aConf = $this->getOptions()->getRawData_FullFeatureConfig();
		if ( !empty( $aConf[ 'custom_redirects' ] ) && $this->getCon()->isValidAdminArea() ) {
			foreach ( $aConf[ 'custom_redirects' ] as $aRedirect ) {
				if ( Services::Request()->query( 'page' ) == $this->prefix( $aRedirect[ 'source_mod_page' ] ) ) {
					Services::Response()->redirect(
						$this->getCon()->getModule( $aRedirect[ 'target_mod_page' ] )->getUrl_AdminPage(),
						$aRedirect[ 'query_args' ],
						true,
						false
					);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getAdditionalMenuItem() {
		return [];
	}

	/**
	 * @param array $aSummaryData
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function addModuleSummaryData( $aSummaryData ) {
		return $aSummaryData;
	}

	/**
	 * TODO: not the place for this method.
	 * @return array[]
	 */
	public function getModulesSummaryData() {
		return array_map(
			function ( $mod ) {
				return $mod->buildSummaryData();
			},
			$this->getCon()->modules
		);
	}

	/**
	 * @return array
	 */
	public function buildSummaryData() {
		$opts = $this->getOptions();
		$sMenuTitle = $opts->getFeatureProperty( 'menu_title' );

		$aSections = $opts->getSections();
		foreach ( $aSections as $sSlug => $aSection ) {
			try {
				$aStrings = $this->getStrings()->getSectionStrings( $aSection[ 'slug' ] );
				foreach ( $aStrings as $sKey => $sVal ) {
					unset( $aSection[ $sKey ] );
					$aSection[ $sKey ] = $sVal;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$aSum = [
			'slug'          => $this->getSlug(),
			'enabled'       => $this->getUIHandler()->isEnabledForUiSummary(),
			'active'        => $this->isThisModulePage() || $this->isPage_InsightsThisModule(),
			'name'          => $this->getMainFeatureName(),
			'sidebar_name'  => $opts->getFeatureProperty( 'sidebar_name' ),
			'menu_title'    => empty( $sMenuTitle ) ? $this->getMainFeatureName() : __( $sMenuTitle, 'wp-simple-firewall' ),
			'href'          => network_admin_url( 'admin.php?page='.$this->getModSlug() ),
			'sections'      => $aSections,
			'options'       => [],
			'show_mod_opts' => $this->getIfShowModuleOpts(),
		];

		foreach ( $opts->getVisibleOptionsKeys() as $sOptKey ) {
			try {
				$aOptData = $this->getStrings()->getOptionStrings( $sOptKey );
				$aOptData[ 'href' ] = $this->getUrl_DirectLinkToOption( $sOptKey );
				$aSum[ 'options' ][ $sOptKey ] = $aOptData;
			}
			catch ( \Exception $e ) {
			}
		}

		$aSum[ 'tooltip' ] = sprintf(
			'%s',
			empty( $aSum[ 'sidebar_name' ] ) ? $aSum[ 'name' ] : __( $aSum[ 'sidebar_name' ], 'wp-simple-firewall' )
		);
		return $aSum;
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function addInsightsConfigData( $aAllData ) {
		return $aAllData;
	}

	/**
	 * @return bool
	 * @deprecated 9.2.0
	 */
	public function isEnabledForUiSummary() {
		return $this->isModuleEnabled();
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleMenuItem() {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_menu_item' );
	}

	/**
	 * @return bool
	 */
	public function getIfShowModuleOpts() {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_options' );
	}

	/**
	 * @return bool
	 */
	public function getIfUseSessions() {
		return $this->getOptions()->getFeatureProperty( 'use_sessions' );
	}

	/**
	 * Get config 'definition'.
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getDef( $sKey ) {
		return $this->getOptions()->getDef( $sKey );
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors( [] );
	}

	/**
	 * @param bool   $bAsString
	 * @param string $sGlue
	 * @return string|array
	 */
	public function getLastErrors( $bAsString = false, $sGlue = " " ) {
		$aErrors = $this->getOpt( 'last_errors' );
		if ( !is_array( $aErrors ) ) {
			$aErrors = [];
		}
		return $bAsString ? implode( $sGlue, $aErrors ) : $aErrors;
	}

	/**
	 * @return bool
	 */
	public function hasLastErrors() {
		return count( $this->getLastErrors( false ) ) > 0;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mDefault
	 * @return mixed
	 */
	public function getOpt( $sOptionKey, $mDefault = false ) {
		return $this->getOptions()->getOpt( $sOptionKey, $mDefault );
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed  $mValueToTest
	 * @param bool   $bStrict
	 * @return bool
	 */
	public function isOpt( $sOptionKey, $mValueToTest, $bStrict = false ) {
		$mOptionValue = $this->getOptions()->getOpt( $sOptionKey );
		return $bStrict ? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOpt( $sOptKey ) {
		$sValue = $this->getOpt( $sOptKey, 'default' );
		if ( $sValue == 'default' ) {
			$sValue = $this->getTextOptDefault( $sOptKey );
		}
		return __( $sValue, 'wp-simple-firewall' );
	}

	/**
	 * Override this on each feature that has Text field options to supply the text field defaults
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {
		return 'Undefined Text Opt Default';
	}

	/**
	 * @param array|string $mErrors
	 * @return $this
	 */
	public function setLastErrors( $mErrors = [] ) {
		if ( !is_array( $mErrors ) ) {
			if ( is_string( $mErrors ) ) {
				$mErrors = [ $mErrors ];
			}
			else {
				$mErrors = [];
			}
		}
		return $this->setOpt( 'last_errors', $mErrors );
	}

	/**
	 * Sets the value for the given option key
	 * @param string $sOptionKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	protected function setOpt( $sOptionKey, $mValue ) {
		$this->getOptions()->setOpt( $sOptionKey, $mValue );
		return $this;
	}

	/**
	 * @param array $aOptions
	 */
	public function setOptions( $aOptions ) {
		$oVO = $this->getOptions();
		foreach ( $aOptions as $sKey => $mValue ) {
			$oVO->setOpt( $sKey, $mValue );
		}
	}

	/**
	 * @return bool
	 */
	public function isModuleRequest() {
		return ( $this->getModSlug() == Services::Request()->request( 'mod_slug' ) );
	}

	/**
	 * @param string $sAction
	 * @param bool   $bAsJsonEncodedObject
	 * @return array|string
	 */
	public function getAjaxActionData( $sAction = '', $bAsJsonEncodedObject = false ) {
		$aData = $this->getNonceActionData( $sAction );
		$aData[ 'ajaxurl' ] = admin_url( 'admin-ajax.php' );
		return $bAsJsonEncodedObject ? json_encode( (object)$aData ) : $aData;
	}

	/**
	 * @param string $sAction
	 * @return array
	 */
	public function getNonceActionData( $sAction = '' ) {
		$aData = $this->getCon()->getNonceActionData( $sAction );
		$aData[ 'mod_slug' ] = $this->getModSlug();
		return $aData;
	}

	/**
	 * @return string[]
	 */
	public function getDismissedNotices() {
		$aDN = $this->getOpt( 'dismissed_notices' );
		return is_array( $aDN ) ? $aDN : [];
	}

	/**
	 * @return string[]
	 */
	public function getUiTrack() {
		$aDN = $this->getOpt( 'ui_track' );
		return is_array( $aDN ) ? $aDN : [];
	}

	/**
	 * @param string[] $aDismissed
	 * @return $this
	 */
	public function setDismissedNotices( $aDismissed ) {
		return $this->setOpt( 'dismissed_notices', $aDismissed );
	}

	/**
	 * @param string[] $aDismissed
	 * @return $this
	 */
	public function setUiTrack( $aDismissed ) {
		return $this->setOpt( 'ui_track', $aDismissed );
	}

	/**
	 * @return \ICWP_WPSF_Wizard_Base|null
	 */
	public function getWizardHandler() {
		if ( !isset( $this->oWizard ) ) {
			$sClassName = $this->getWizardClassName();
			if ( !class_exists( $sClassName ) ) {
				return null;
			}
			$this->oWizard = new $sClassName();
			$this->oWizard->setMod( $this );
		}
		return $this->oWizard;
	}

	/**
	 * @param bool $bPreProcessOptions
	 * @return $this
	 */
	public function saveModOptions( $bPreProcessOptions = false ) {

		if ( $bPreProcessOptions ) {
			$this->preProcessOptions();
		}

		$this->doPrePluginOptionsSave();
		if ( apply_filters( $this->prefix( 'force_options_resave' ), false ) ) {
			$this->getOptions()
				 ->setNeedSave( true );
		}

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		$this->bImportExportWhitelistNotify = $this->getOptions()->getNeedSave();
		$this->store();
		return $this;
	}

	protected function preProcessOptions() {
	}

	private function store() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		$this->getOptions()
			 ->doOptionsSave( $this->getCon()->getIsResetPlugin(), $this->isPremium() );
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
	}

	/**
	 * @param array $aAggregatedOptions
	 * @return array
	 */
	public function aggregateOptionsValues( $aAggregatedOptions ) {
		return array_merge( $aAggregatedOptions, $this->getOptions()->getAllOptionsValues() );
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 * @deprecated 9.2.0
	 */
	public function buildOptions() {
		return $this->getUIHandler()->buildOptions();
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 * @deprecated 9.2.0
	 */
	protected function buildOptionForUi( $aOptParams ) {
		return [];
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	public function onPluginDeactivate() {
	}

	public function onPluginDelete() {
		foreach ( $this->getDbHandlers( true ) as $oDbh ) {
			if ( !empty( $oDbh ) ) {
				$oDbh->tableDelete();
			}
		}
		$this->getOptions()->deleteStorage();
	}

	/**
	 * @return array - map of each option to its option type
	 */
	protected function getAllFormOptionsAndTypes() {
		$aOpts = [];

		foreach ( $this->getUIHandler()->buildOptions() as $aOptionsSection ) {
			if ( !empty( $aOptionsSection ) ) {
				foreach ( $aOptionsSection[ 'options' ] as $aOption ) {
					$aOpts[ $aOption[ 'key' ] ] = $aOption[ 'type' ];
				}
			}
		}

		return $aOpts;
	}

	/**
	 * @param string $sAction
	 */
	protected function handleModAction( $sAction ) {
	}

	/**
	 * @throws \Exception
	 */
	public function saveOptionsSubmit() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
		}

		$this->doSaveStandardOptions();

		$this->saveModOptions( true );

		// only use this flag when the options are being updated with a MANUAL save.
		if ( isset( $this->bImportExportWhitelistNotify ) && $this->bImportExportWhitelistNotify ) {
			if ( !wp_next_scheduled( $this->prefix( 'importexport_notify' ) ) ) {
				wp_schedule_single_event( Services::Request()->ts() + 15, $this->prefix( 'importexport_notify' ) );
			}
		}
	}

	/**
	 * @param string $sMsg
	 * @param bool   $bError
	 * @param bool   $bShowOnLogin
	 * @return $this
	 */
	public function setFlashAdminNotice( $sMsg, $bError = false, $bShowOnLogin = false ) {
		$this->getCon()
			 ->getAdminNotices()
			 ->addFlash(
				 sprintf( '[%s] %s', $this->getCon()->getHumanName(), $sMsg ),
				 $bError,
				 $bShowOnLogin
			 );
		return $this;
	}

	/**
	 * @return bool
	 */
	protected function isAdminOptionsPage() {
		return ( is_admin() && !Services::WpGeneral()->isAjax() && $this->isThisModulePage() );
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @throws \Exception
	 */
	private function doSaveStandardOptions() {
		$aForm = $this->getAjaxFormParams( 'b64' ); // standard options use b64 and failover to lz-string

		foreach ( $this->getAllFormOptionsAndTypes() as $sKey => $sOptType ) {

			$sOptionValue = isset( $aForm[ $sKey ] ) ? $aForm[ $sKey ] : null;
			if ( is_null( $sOptionValue ) ) {

				if ( in_array( $sOptType, [ 'text', 'email' ] ) ) { //text box, and it's null, don't update
					continue;
				}
				elseif ( $sOptType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				elseif ( $sOptType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
				elseif ( $sOptType == 'multiple_select' ) {
					$sOptionValue = [];
				}
			}
			else { //handle any pre-processing we need to.

				if ( $sOptType == 'text' || $sOptType == 'email' ) {
					$sOptionValue = trim( $sOptionValue );
				}
				if ( $sOptType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				elseif ( $sOptType == 'password' ) {
					$sTempValue = trim( $sOptionValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}

					$sConfirm = isset( $aForm[ $sKey.'_confirm' ] ) ? $aForm[ $sKey.'_confirm' ] : null;
					if ( $sTempValue !== $sConfirm ) {
						throw new \Exception( __( 'Password values do not match.', 'wp-simple-firewall' ) );
					}

					$sOptionValue = md5( $sTempValue );
				}
				elseif ( $sOptType == 'array' ) { //arrays are textareas, where each is separated by newline
					$sOptionValue = array_filter( explode( "\n", esc_textarea( $sOptionValue ) ), 'trim' );
				}
				elseif ( $sOptType == 'comma_separated_lists' ) {
					$sOptionValue = Services::Data()->extractCommaSeparatedList( $sOptionValue );
				}
				/* elseif ( $sOptType == 'multiple_select' ) { } */
			}

			// Prevent overwriting of non-editable fields
			if ( !in_array( $sOptType, [ 'noneditable_text' ] ) ) {
				$this->setOpt( $sKey, $sOptionValue );
			}
		}

		// Handle Import/Export exclusions
		if ( $this->isPremium() ) {
			( new Shield\Modules\Plugin\Lib\ImportExport\Options\SaveExcludedOptions() )
				->setMod( $this )
				->save( $aForm );
		}
	}

	protected function runWizards() {
		if ( $this->isWizardPage() && $this->hasWizard() ) {
			$oWiz = $this->getWizardHandler();
			if ( $oWiz instanceof ICWP_WPSF_Wizard_Base ) {
				$oWiz->init();
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isThisModulePage() {
		return $this->getCon()->isModulePage()
			   && Services::Request()->query( 'page' ) == $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	public function isPage_Insights() {
		return Services::Request()->query( 'page' ) == $this->getCon()->getModule_Insights()->getModSlug();
	}

	/**
	 * @return bool
	 */
	public function isPage_InsightsThisModule() {
		return $this->isPage_Insights()
			   && Services::Request()->query( 'subnav' ) == $this->getSlug();
	}

	/**
	 * @return bool
	 */
	protected function isModuleOptionsRequest() {
		return Services::Request()->post( 'mod_slug' ) === $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	protected function isWizardPage() {
		return ( $this->getCon()->getShieldAction() == 'wizard' && $this->isThisModulePage() );
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function prefix( $sSuffix = '', $sGlue = '-' ) {
		return $this->getCon()->prefix( $sSuffix, $sGlue );
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getOptionStoragePrefix() {
		return $this->getCon()->getOptionStoragePrefix();
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo $this->renderModulePage();
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $aData
	 * @return string
	 */
	protected function renderModulePage( $aData = [] ) {
		return $this->renderTemplate(
			'index.php',
			Services::DataManipulation()->mergeArraysRecursive( $this->getUIHandler()->getBaseDisplayData(), $aData )
		);
	}

	/**
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function getBaseDisplayData() {
		return $this->getUIHandler()->getBaseDisplayData();
	}

	/**
	 * @return string
	 */
	protected function getContentWizardLanding() {
		$aData = $this->getUIHandler()->getBaseDisplayData();
		if ( $this->hasWizard() ) {
			$aData[ 'content' ][ 'wizard_landing' ] = $this->getWizardHandler()->renderWizardLandingSnippet();
		}
		return $this->renderTemplate( 'snippets/module-wizard-template.php', $aData );
	}

	protected function buildContextualHelp() {
		if ( !function_exists( 'get_current_screen' ) ) {
			require_once( ABSPATH.'wp-admin/includes/screen.php' );
		}
		$screen = get_current_screen();
		//$screen->remove_help_tabs();
		$screen->add_help_tab( [
			'id'      => 'my-plugin-default',
			'title'   => __( 'Default' ),
			'content' => 'This is where I would provide tabbed help to the user on how everything in my admin panel works. Formatted HTML works fine in here too'
		] );
		//add more help tabs as needed with unique id's

		// Help sidebars are optional
		$screen->set_help_sidebar(
			'<p><strong>'.__( 'For more information:' ).'</strong></p>'.
			'<p><a href="http://wordpress.org/support/" target="_blank">'._( 'Support Forums' ).'</a></p>'
		);
	}

	/**
	 * @param string $sWizardSlug
	 * @return string
	 * @uses nonce
	 */
	public function getUrl_Wizard( $sWizardSlug ) {
		$aDef = $this->getWizardDefinition( $sWizardSlug );
		if ( empty( $aDef[ 'min_user_permissions' ] ) ) { // i.e. no login/minimum perms
			$sUrl = Services::WpGeneral()->getHomeUrl();
		}
		else {
			$sUrl = Services::WpGeneral()->getAdminUrl( 'admin.php' );
		}

		return add_query_arg(
			[
				'page'          => $this->getModSlug(),
				'shield_action' => 'wizard',
				'wizard'        => $sWizardSlug,
				'nonwizard'     => wp_create_nonce( 'wizard'.$sWizardSlug )
			],
			$sUrl
		);
	}

	/**
	 * @return string
	 */
	public function getUrl_WizardLanding() {
		return $this->getUrl_Wizard( 'landing' );
	}

	/**
	 * @param string $sWizardSlug
	 * @return array
	 */
	public function getWizardDefinition( $sWizardSlug ) {
		$aDef = null;
		if ( $this->hasWizardDefinition( $sWizardSlug ) ) {
			$aW = $this->getWizardDefinitions();
			$aDef = $aW[ $sWizardSlug ];
		}
		return $aDef;
	}

	/**
	 * @return array
	 */
	public function getWizardDefinitions() {
		$aW = $this->getDef( 'wizards' );
		return is_array( $aW ) ? $aW : [];
	}

	/**
	 * @return bool
	 */
	public function hasWizard() {
		return ( count( $this->getWizardDefinitions() ) > 0 );
	}

	/**
	 * @param string $sWizardSlug
	 * @return bool
	 */
	public function hasWizardDefinition( $sWizardSlug ) {
		$aW = $this->getWizardDefinitions();
		return !empty( $aW[ $sWizardSlug ] );
	}

	/**
	 * @return bool
	 */
	public function getIsShowMarketing() {
		return apply_filters( $this->prefix( 'show_marketing' ), !$this->isPremium() );
	}

	/**
	 * @return string
	 */
	public function renderOptionsForm() {

		if ( $this->canDisplayOptionsForm() ) {
			$sTemplate = 'components/options_form/main.twig';
		}
		else {
			$sTemplate = 'subfeature-access_restricted';
		}

		try {
			return $this->getCon()
						->getRenderer()
						->setTemplate( $sTemplate )
						->setRenderVars( $this->getUIHandler()->getBaseDisplayData() )
						->setTemplateEngineTwig()
						->render();
		}
		catch ( \Exception $oE ) {
			return 'Error rendering options form: '.$oE->getMessage();
		}
	}

	/**
	 * @return bool
	 */
	public function canDisplayOptionsForm() {
		return $this->getOptions()->isAccessRestricted() ? $this->getCon()->isPluginAdmin() : true;
	}

	public function onWpEnqueueAdminJs() {
		$this->insertCustomJsVars_Admin();
	}

	/**
	 * Override this with custom JS vars for your particular module.
	 */
	public function insertCustomJsVars_Admin() {

		if ( $this->isThisModulePage() ) {
			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_base',
				[
					'ajax' => [
						'mod_options'          => $this->getAjaxActionData( 'mod_options' ),
						'mod_opts_form_render' => $this->getAjaxActionData( 'mod_opts_form_render' ),
					]
				]
			);
		}
	}

	/**
	 * @param array  $aData
	 * @param string $sSubView
	 */
	protected function display( $aData = [], $sSubView = '' ) {
	}

	/**
	 * @param array $aData
	 * @return string
	 * @throws \Exception
	 */
	public function renderAdminNotice( $aData ) {
		if ( empty( $aData[ 'notice_attributes' ] ) ) {
			throw new \Exception( 'notice_attributes is empty' );
		}

		if ( !isset( $aData[ 'icwp_admin_notice_template' ] ) ) {
			$aData[ 'icwp_admin_notice_template' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		}

		if ( !isset( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ] = [];
		}
		if ( is_array( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ][] = $aData[ 'notice_attributes' ][ 'type' ];
			if ( empty( $aData[ 'notice_classes' ] )
				 || ( !in_array( 'error', $aData[ 'notice_classes' ] ) && !in_array( 'updated', $aData[ 'notice_classes' ] ) ) ) {
				$aData[ 'notice_classes' ][] = 'updated';
			}
		}
		$aData[ 'notice_classes' ] = implode( ' ', $aData[ 'notice_classes' ] );

		$aAjaxData = $this->getAjaxActionData( 'dismiss_admin_notice' );
		$aAjaxData[ 'hide' ] = 1;
		$aAjaxData[ 'notice_id' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		$aData[ 'ajax' ][ 'dismiss_admin_notice' ] = json_encode( $aAjaxData );

		$bTwig = $aData[ 'notice_attributes' ][ 'twig' ];
		$sTemplate = $bTwig ? '/notices/'.$aAjaxData[ 'notice_id' ] : 'notices/admin-notice-template';
		return $this->renderTemplate( $sTemplate, $aData, $bTwig );
	}

	/**
	 * @param string $sTemplate
	 * @param array  $aData
	 * @param bool   $bUseTwig
	 * @return string
	 */
	public function renderTemplate( $sTemplate, $aData = [], $bUseTwig = false ) {
		if ( empty( $aData[ 'unique_render_id' ] ) ) {
			$aData[ 'unique_render_id' ] = 'noticeid-'.substr( md5( mt_rand() ), 0, 5 );
		}
		try {
			$oRndr = $this->getCon()->getRenderer();
			if ( $bUseTwig || preg_match( '#^.*\.twig$#i', $sTemplate ) ) {
				$oRndr->setTemplateEngineTwig();
			}

			$sOutput = $oRndr->setTemplate( $sTemplate )
							 ->setRenderVars( $aData )
							 ->render();
		}
		catch ( \Exception $oE ) {
			$sOutput = $oE->getMessage();
			error_log( $oE->getMessage() );
		}

		return $sOutput;
	}

	/**
	 * @param array $aTransferableOptions
	 * @return array
	 */
	public function exportTransferableOptions( $aTransferableOptions ) {
		if ( !is_array( $aTransferableOptions ) ) {
			$aTransferableOptions = [];
		}
		$aTransferableOptions[ $this->getOptionsStorageKey() ] = $this->getOptions()->getTransferableOptions();
		return $aTransferableOptions;
	}

	/**
	 * @return array
	 */
	public function collectOptionsForTracking() {
		$oVO = $this->getOptions();
		$aOptionsData = $this->getOptions()->getOptionsForTracking();
		foreach ( $aOptionsData as $sOption => $mValue ) {
			unset( $aOptionsData[ $sOption ] );
			// some cleaning to ensure we don't have disallowed characters
			$sOption = preg_replace( '#[^_a-z]#', '', strtolower( $sOption ) );
			$sType = $oVO->getOptionType( $sOption );
			if ( $sType == 'checkbox' ) { // only want a boolean 1 or 0
				$aOptionsData[ $sOption ] = (int)( $mValue == 'Y' );
			}
			else {
				$aOptionsData[ $sOption ] = $mValue;
			}
		}
		return $aOptionsData;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 * @param array  $aExportItems
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyExport( $aExportItems, $sEmail, $nPage = 1 ) {
		return $aExportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 * @param array  $aData
	 * @param string $sEmail
	 * @param int    $nPage
	 * @return array
	 */
	public function onWpPrivacyErase( $aData, $sEmail, $nPage = 1 ) {
		return $aData;
	}

	/**
	 * @return null|Shield\Modules\Base\ShieldOptions|mixed
	 */
	public function getOptions() {
		if ( !isset( $this->oOpts ) ) {
			$oCon = $this->getCon();
			$this->oOpts = $this->loadOptions()->setMod( $this );
			$this->oOpts->setPathToConfig( $oCon->getPath_ConfigFile( $this->getSlug() ) )
						->setRebuildFromFile( $oCon->getIsRebuildOptionsFromFile() )
						->setOptionsStorageKey( $this->getOptionsStorageKey() )
						->setIfLoadOptionsFromStorage( !$oCon->getIsResetPlugin() );
		}
		return $this->oOpts;
	}

	/**
	 * @return Shield\Modules\Base\WpCli
	 * @throws \Exception
	 */
	public function getWpCli() {
		if ( !isset( $this->oWpCli ) ) {
			$this->oWpCli = $this->loadClass( 'WpCli' );
			if ( !$this->oWpCli instanceof Shield\Modules\Base\WpCli ) {
				$this->oWpCli = $this->loadClassFromBase( 'WpCli' );
				if ( !$this->oWpCli instanceof Shield\Modules\Base\WpCli ) {
					throw new Exception( 'WP-CLI not supported' );
				}
			}
			$this->oWpCli->setMod( $this );
		}
		return $this->oWpCli;
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		if ( !isset( $this->oStrings ) ) {
			$this->oStrings = $this->loadStrings()->setMod( $this );
		}
		return $this->oStrings;
	}

	/**
	 * @return Shield\Modules\Base\UI
	 */
	public function getUIHandler() {
		if ( !isset( $this->oUI ) ) {
			$this->oUI = $this->loadClass( 'UI' );
			if ( !$this->oUI instanceof Shield\Modules\Base\UI ) {
				// TODO: autoloader for base classes
				$this->oUI = $this->loadClassFromBase( 'ShieldUI' );
			}
			$this->oUI->setMod( $this );
		}
		return $this->oUI;
	}

	/**
	 * @return Shield\Modules\Base\BaseReporting|mixed|false
	 */
	public function getReportingHandler() {
		if ( !isset( $this->oReporting ) ) {
			$this->oReporting = $this->loadClass( 'Reporting' );
			if ( $this->oReporting instanceof Shield\Modules\Base\BaseReporting ) {
				$this->oReporting->setMod( $this );
			}
		}
		return $this->oReporting;
	}

	/**
	 * @return $this
	 */
	protected function loadAdminNotices() {
		$oNotices = $this->loadClass( 'AdminNotices' );
		if ( $oNotices instanceof Shield\Modules\Base\AdminNotices ) {
			$oNotices->setMod( $this )->run();
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function loadAjaxHandler() {
		$oAj = $this->loadClass( 'AjaxHandler' );
		if ( !$oAj instanceof Shield\Modules\Base\AjaxHandlerBase ) {
			$oAj = new Shield\Modules\Base\AjaxHandlerShield();
		}
		$oAj->setMod( $this );
		return $this;
	}

	/**
	 * @return Shield\Modules\Base\ShieldOptions|mixed
	 */
	protected function loadOptions() {
		return $this->loadClass( 'Options' );
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadClass( 'Strings' );
	}

	/**
	 * @param $sClass
	 * @return \stdClass|mixed|false
	 */
	private function loadClass( $sClass ) {
		$sC = $this->getNamespace().$sClass;
		return @class_exists( $sC ) ? new $sC() : false;
	}

	/**
	 * @param $sClass
	 * @return \stdClass|mixed|false
	 */
	private function loadClassFromBase( $sClass ) {
		$sC = $this->getBaseNamespace().$sClass;
		return @class_exists( $sC ) ? new $sC() : false;
	}

	/**
	 * @return string
	 */
	private function getBaseNamespace() {
		return '\FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\\';
	}

	/**
	 * @return string
	 */
	private function getNamespace() {
		return '\FernleafSystems\Wordpress\Plugin\Shield\Modules\\'.$this->getNamespaceBase().'\\';
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Base';
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
		$this->saveModOptions();
	}

	/**
	 * @deprecated 9.0
	 */
	protected function doExtraSubmitProcessing() {
		$this->preProcessOptions();
	}
}