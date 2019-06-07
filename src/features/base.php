<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_FeatureHandler_Base extends ICWP_WPSF_Foundation {

	use Shield\Modules\PluginControllerConsumer;

	/**
	 * @var ICWP_WPSF_OptionsVO
	 */
	protected $oOptions;

	/**
	 * @var string
	 */
	private $sOptionsStoreKey;

	/**
	 * @var string
	 */
	protected $sModSlug;

	/**
	 * @var boolean
	 */
	protected $bImportExportWhitelistNotify = false;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Email
	 */
	private static $oEmailHandler;

	/**
	 * @var ICWP_WPSF_Processor_Base
	 */
	private $oProcessor;

	/**
	 * @var ICWP_WPSF_Wizard_Base
	 */
	private $oWizard;

	/**
	 * @var Shield\Databases\Base\Handler
	 */
	private $oDbh;

	/**
	 * @var Shield\Modules\Base\Strings
	 */
	private $oStrings;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $oOpts;

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oPluginController
	 * @param array                       $aMod
	 * @throws \Exception
	 */
	public function __construct( $oPluginController, $aMod = [] ) {
		if ( empty( self::$oPluginController ) ) {
			if ( !$oPluginController instanceof ICWP_WPSF_Plugin_Controller ) {
				throw new \Exception( 'Plugin controller not supplied to Module' );
			}
			$this->setCon( $oPluginController );
		}

		if ( empty( $aMod[ 'storage_key' ] ) && empty( $aMod[ 'slug' ] ) ) {
			throw new \Exception( 'Module storage key AND slug are undefined' );
		}

		$this->sOptionsStoreKey = empty( $aMod[ 'storage_key' ] ) ? $aMod[ 'slug' ] : $aMod[ 'storage_key' ];
		if ( isset( $aMod[ 'slug' ] ) ) {
			$this->sModSlug = $aMod[ 'slug' ];
		}

		if ( $this->verifyModuleMeetRequirements() ) {
			$this->setupHooks( $aMod );
			$this->doPostConstruction();
		}
	}

	/**
	 * @param array $aModProps
	 */
	protected function setupHooks( $aModProps ) {
		$oReq = Services::Request();

		$nRunPriority = isset( $aModProps[ 'load_priority' ] ) ? $aModProps[ 'load_priority' ] : 100;
		add_action( $this->prefix( 'run_processors' ), [ $this, 'onRunProcessors' ], $nRunPriority );
		add_action( 'init', [ $this, 'onWpInit' ], 1 );
		add_action( $this->prefix( 'import_options' ), [ $this, 'processImportOptions' ] );

		if ( $this->isModuleRequest() ) {
			add_filter( $this->prefix( 'ajaxAction' ), [ $this, 'handleAjax' ] );
			add_filter( $this->prefix( 'ajaxAuthAction' ), [ $this, 'handleAuthAjax' ] );
			add_filter( $this->prefix( 'ajaxNonAuthAction' ), [ $this, 'handleNonAuthAjax' ] );

			if ( $oReq->request( 'action' ) == $this->prefix()
				 && check_admin_referer( $oReq->request( 'exec' ), 'exec_nonce' )
			) {
				add_action( $this->prefix( 'mod_request' ), [ $this, 'handleModRequest' ] );
			}
		}

		$nMenuPri = isset( $aModProps[ 'menu_priority' ] ) ? $aModProps[ 'menu_priority' ] : 100;
		add_filter( $this->prefix( 'submenu_items' ), [ $this, 'supplySubMenuItem' ], $nMenuPri );
		add_filter( $this->prefix( 'collect_mod_summary' ), [ $this, 'addModuleSummaryData' ], $nMenuPri );
		add_filter( $this->prefix( 'collect_notices' ), [ $this, 'addInsightsNoticeData' ] );
		add_filter( $this->prefix( 'collect_summary' ), [ $this, 'addInsightsConfigData' ], $nRunPriority );
		add_action( $this->prefix( 'plugin_shutdown' ), [ $this, 'action_doFeatureShutdown' ] );
		add_action( $this->prefix( 'deactivate_plugin' ), [ $this, 'deactivatePlugin' ] );
		add_action( $this->prefix( 'delete_plugin' ), [ $this, 'deletePluginOptions' ] );
		add_filter( $this->prefix( 'aggregate_all_plugin_options' ), [ $this, 'aggregateOptionsValues' ] );

		add_filter( $this->prefix( 'register_admin_notices' ), [ $this, 'fRegisterAdminNotices' ] );
		add_filter( $this->prefix( 'gather_options_for_export' ), [ $this, 'exportTransferableOptions' ] );

		// supply our supported plugin events for this module
		add_filter( $this->prefix( 'is_event_supported' ), function ( $bSupported, $sEventTag ) {
			return $bSupported || $this->isSupportedEvent( $sEventTag );
		}, 10, 2 );

		add_action( 'admin_enqueue_scripts', [ $this, 'onWpEnqueueAdminJs' ], 100 );

//		if ( $this->isAdminOptionsPage() ) {
//			add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
//		}

		$this->setupCustomHooks();
	}

	protected function setupCustomHooks() {
	}

	protected function doPostConstruction() {
	}

	/**
	 * @param string $sKey
	 * @return array|null
	 */
	public function getEventDef( $sKey ) {
		return $this->isSupportedEvent( $sKey ) ? $this->getEvents()[ $sKey ] : null;
	}

	/**
	 * @return array[]
	 */
	public function getEvents() {
		$aEvts = $this->getSupportedEvents();

		$aDefaults = [
			'slug'    => $this->getSlug(),
			'cat'     => 1,
			'stat'    => true,
			'audit'   => true,
			'recent'  => false, // whether to show in the recent events logs
			'offense' => false, // whether to mark offense against IP
		];
		foreach ( $aEvts as $sKey => $aEvt ) {
			$aEvts[ $sKey ] = array_merge( $aDefaults, $aEvt );
			$aEvts[ $sKey ][ 'key' ] = $sKey;
			$aEvts[ $sKey ][ 'msg' ] = $this->getStrings()->getAuditMessage( $sKey );
		}
		return $aEvts;
	}

	/**
	 * @return array[]
	 */
	public function getStatEvents_Recent() {
		return array_filter(
			$this->getEvents(),
			function ( $aEvt ) {
				return $aEvt[ 'recent' ];
			}
		);
	}

	/**
	 * @return array[]
	 */
	protected function getSupportedEvents() {
		$aEvts = $this->getDef( 'events' );
		return is_array( $aEvts ) ? $aEvts : [];
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function isSupportedEvent( $sKey ) {
		return array_key_exists( $sKey, $this->getSupportedEvents() );
	}

	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function updateHandler() {
	}

	/**
	 * This is ajax for anyone logged-in or not logged-care. Due care must be taken here.
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAjax( $aAjaxResponse ) {
		return $this->normaliseAjaxResponse( $aAjaxResponse );
	}

	/**
	 * Ajax for any request not logged-in.
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleNonAuthAjax( $aAjaxResponse ) {
		return $this->normaliseAjaxResponse( $aAjaxResponse );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'mod_opts_form_render':
					$aAjaxResponse = $this->ajaxExec_ModOptionsFormRender();
					break;

				case 'mod_options':
					$aAjaxResponse = $this->ajaxExec_ModOptions();
					break;

				case 'wiz_process_step':
					if ( $this->hasWizard() ) {
						$aAjaxResponse = $this->getWizardHandler()
											  ->ajaxExec_WizProcessStep();
					}
					break;

				case 'wiz_render_step':
					if ( $this->hasWizard() ) {
						$aAjaxResponse = $this->getWizardHandler()
											  ->ajaxExec_WizRenderStep();
					}
					break;
			}
		}

		return $this->normaliseAjaxResponse( $aAjaxResponse );
	}

	/**
	 * We check for empty since if it's empty, there's nothing to normalize. It's a filter,
	 * so if we send something back non-empty, it'll be treated like a "handled" response and
	 * processing will finish
	 * @param array $aAjaxResponse
	 * @return array
	 */
	protected function normaliseAjaxResponse( $aAjaxResponse ) {
		if ( !empty( $aAjaxResponse ) ) {
			$aAjaxResponse = array_merge(
				[
					'success'     => false,
					'page_reload' => false,
					'message'     => 'Unknown',
					'html'        => '',
				],
				$aAjaxResponse
			);
		}
		return $aAjaxResponse;
	}

	/**
	 * @param string $sEncoding
	 * @return array
	 */
	protected function getAjaxFormParams( $sEncoding = 'none' ) {
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
		return array_merge( $aAdminNotices, $this->getOptionsVo()->getAdminNotices() );
	}

	/**
	 * @return bool
	 */
	private function verifyModuleMeetRequirements() {
		$bMeetsReqs = true;

		$aPhpReqs = $this->getOptionsVo()->getFeatureRequirement( 'php' );
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

	/**
	 */
	public function onRunProcessors() {
		if ( $this->isUpgrading() ) {
			$this->updateHandler();
		}
		if ( $this->getOptionsVo()->getFeatureProperty( 'auto_load_processor' ) ) {
			$this->loadProcessor();
		}
		if ( !$this->isUpgrading() && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
			$this->doExecuteProcessor();
		}
	}

	/**
	 * @param array $aOptions
	 */
	public function processImportOptions( $aOptions ) {
		if ( !empty( $aOptions ) && is_array( $aOptions ) && array_key_exists( $this->getOptionsStorageKey(), $aOptions ) ) {
			$this->getOptionsVo()
				 ->setMultipleOptions( $aOptions[ $this->getOptionsStorageKey() ] );
			$this->savePluginOptions();
		}
	}

	/**
	 * Used to effect certain processing that is to do with options etc. but isn't related to processing
	 * functionality of the plugin.
	 */
	protected function isReadyToExecute() {
		$oProcessor = $this->getProcessor();
		return ( $oProcessor instanceof ICWP_WPSF_Processor_Base );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->run();
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		do_action( $this->prefix( 'mod_request' ) );

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

	/**
	 */
	public function onLoadOptionsScreen() {
		if ( $this->getCon()->isValidAdminArea() ) {
			$this->buildContextualHelp();
		}
	}

	/**
	 * Override this and adapt per feature
	 * @return ICWP_WPSF_Processor_Base
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
	 * @return \ICWP_WPSF_OptionsVO
	 */
	public function getOptionsVo() {
		if ( !isset( $this->oOptions ) ) {
			$this->oOptions = $this->getOptions();
		}
		return $this->oOptions;
	}

	/**
	 * @return array
	 */
	public function getAdminNotices() {
		return $this->getOptionsVo()->getAdminNotices();
	}

	/**
	 * @return bool
	 */
	public function isUpgrading() {
		return $this->getCon()->getIsRebuildOptionsFromFile() || $this->getOptionsVo()->getRebuildFromFile();
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( !$this->getCon()->isPluginDeleting() ) {
			$this->savePluginOptions();
		}
	}

	/**
	 * @return string
	 */
	protected function getOptionsStorageKey() {
		return $this->prefixOptionKey( $this->sOptionsStoreKey ).'_options';
	}

	/**
	 * @return ICWP_WPSF_Processor_Base|mixed
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
	 * @param string $sOptKey
	 * @return string
	 */
	protected function getUrl_DirectLinkToOption( $sOptKey ) {
		$sUrl = $this->getUrl_AdminPage();
		$aDef = $this->getOptionsVo()->getOptDefinition( $sOptKey );
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
			$aSec = $this->getOptionsVo()->getPrimarySection();
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
			self::$oEmailHandler = $this->getCon()->loadFeatureHandler( [ 'slug' => 'email' ] );
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
		$oOpts = $this->getOptionsVo();

		if ( $this->isAutoEnabled() ) {
			$bEnabled = true;
		}
		else if ( apply_filters( $this->prefix( 'globally_disabled' ), false ) ) {
			$bEnabled = false;
		}
		else if ( $this->getCon()->getIfForceOffActive() ) {
			$bEnabled = false;
		}
		else if ( $oOpts->getFeatureProperty( 'premium' ) === true && !$this->isPremium() ) {
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
	protected function isModOptEnabled() {
		return $this->isOpt( $this->getEnableModOptKey(), 'Y' )
			   || $this->isOpt( $this->getEnableModOptKey(), true, true );
	}

	/**
	 * @return string
	 */
	protected function getEnableModOptKey() {
		return 'enable_'.$this->getSlug();
	}

	/**
	 * @return bool
	 */
	protected function isAutoEnabled() {
		return ( $this->getOptionsVo()->getFeatureProperty( 'auto_enabled' ) === true );
	}

	/**
	 * @return string
	 */
	public function getMainFeatureName() {
		return $this->getOptionsVo()->getFeatureProperty( 'name' );
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
			$this->sModSlug = $this->getOptionsVo()->getFeatureProperty( 'slug' );
		}
		return $this->sModSlug;
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function supplySubMenuItem( $aItems ) {
		$sMenuTitleName = $this->getOptionsVo()->getFeatureProperty( 'menu_title' );
		if ( is_null( $sMenuTitleName ) ) {
			$sMenuTitleName = $this->getMainFeatureName();
		}
		if ( !empty( $sMenuTitleName ) ) {

			$sHumanName = $this->getCon()->getHumanName();

			$bMenuHighlighted = $this->getOptionsVo()->getFeatureProperty( 'highlight_menu_item' );
			if ( $bMenuHighlighted ) {
				$sMenuTitleName = sprintf( '<span class="icwp_highlighted">%s</span>', $sMenuTitleName );
			}

			$sMenuPageTitle = $sMenuTitleName.' - '.$sHumanName;
			$aItems[ $sMenuPageTitle ] = [
				$sMenuTitleName,
				$this->getModSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$this->getIfShowModuleMenuItem()
			];

			$aAdditionalItems = $this->getOptionsVo()->getAdditionalMenuItems();
			if ( !empty( $aAdditionalItems ) && is_array( $aAdditionalItems ) ) {

				foreach ( $aAdditionalItems as $aMenuItem ) {

					if ( empty( $aMenuItem[ 'callback' ] ) || !method_exists( $this, $aMenuItem[ 'callback' ] ) ) {
						continue;
					}

					$sMenuPageTitle = $sHumanName.' - '.$aMenuItem[ 'title' ];
					$aItems[ $sMenuPageTitle ] = [
						$aMenuItem[ 'title' ],
						$this->prefix( $aMenuItem[ 'slug' ] ),
						[ $this, $aMenuItem[ 'callback' ] ]
					];
				}
			}
		}
		return $aItems;
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
	 */
	public function addModuleSummaryData( $aSummaryData ) {
		if ( $this->getIfShowModuleLink() ) {
			$aSummaryData[ $this->getModSlug( false ) ] = $this->buildSummaryData();
		}
		return $aSummaryData;
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {
		return $aAllNotices;
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		return $aAllData;
	}

	/**
	 * @return array
	 */
	protected function buildSummaryData() {
		$oOptsVo = $this->getOptionsVo();
		$sMenuTitle = $oOptsVo->getFeatureProperty( 'menu_title' );

		$aSections = $oOptsVo->getSections();
		foreach ( $aSections as $sSlug => $aSection ) {
			try {
				$aStrings = $this->getStrings()->getSectionStrings( $aSection[ 'slug' ] );
				foreach ( $aStrings as $sKey => $sVal ) {
					unset( $aSection[ $sKey ] );
					$aSection[ $sKey ] = $sVal;
				}
			}
			catch ( \Exception $oE ) {
			}
		}

		$aSum = [
			'enabled'    => $this->isEnabledForUiSummary(),
			'active'     => $this->isThisModulePage() || $this->isPage_InsightsThisModule(),
			'slug'       => $this->getSlug(),
			'name'       => $this->getMainFeatureName(),
			'menu_title' => empty( $sMenuTitle ) ? $this->getMainFeatureName() : $sMenuTitle,
			'href'       => network_admin_url( 'admin.php?page='.$this->getModSlug() ),
			'sections'   => $aSections,
			'options'    => [],
		];

		foreach ( $oOptsVo->getVisibleOptionsKeys() as $sOptKey ) {
			try {
				$aOptData = $this->getStrings()->getOptionStrings( $sOptKey );
				$aOptData[ 'href' ] = $this->getUrl_DirectLinkToOption( $sOptKey );
				$aSum[ 'options' ][ $sOptKey ] = $aOptData;
			}
			catch ( \Exception $oE ) {
			}
		}

//		$aSum[ 'content' ] = $this->renderTemplate( 'snippets/summary_single', $aSum );
		$aSum[ 'tooltip' ] = sprintf(
			'%s: %s',
			$aSum[ 'name' ],
			sprintf( __( 'Module %s', 'wp-simple-firewall' ), ( $aSum[ 'enabled' ] ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' ) ) )
		);
		return $aSum;
	}

	/**
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return $this->isModuleEnabled();
	}

	/**
	 * @return boolean
	 */
	public function getIfShowModuleMenuItem() {
		return (bool)$this->getOptionsVo()->getFeatureProperty( 'show_module_menu_item' );
	}

	/**
	 * @return boolean
	 */
	public function getIfShowModuleLink() {
		return (bool)$this->getOptionsVo()->getFeatureProperty( 'show_module_options' );
	}

	/**
	 * @return boolean
	 */
	public function getIfUseSessions() {
		return $this->getOptionsVo()->getFeatureProperty( 'use_sessions' );
	}

	/**
	 * Get config 'definition'.
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getDef( $sKey ) {
		return $this->getOptionsVo()->getFeatureDefinition( $sKey );
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
	public function getLastErrors( $bAsString = true, $sGlue = " " ) {
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
		return $this->getOptionsVo()->getOpt( $sOptionKey, $mDefault );
	}

	/**
	 * @param string  $sOptionKey
	 * @param mixed   $mValueToTest
	 * @param boolean $bStrict
	 * @return bool
	 */
	public function isOpt( $sOptionKey, $mValueToTest, $bStrict = false ) {
		$mOptionValue = $this->getOptionsVo()->getOpt( $sOptionKey );
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
		return $sValue;
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
	 * Note: We also set the ability to bypass admin access since setOpt() is a protected function
	 * @param string $sOptionKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	protected function setOpt( $sOptionKey, $mValue ) {
		$this->getOptionsVo()->setOpt( $sOptionKey, $mValue );
		return $this;
	}

	/**
	 * @param array $aOptions
	 */
	public function setOptions( $aOptions ) {
		$oVO = $this->getOptionsVo();
		foreach ( $aOptions as $sKey => $mValue ) {
			$oVO->setOpt( $sKey, $mValue );
		}
	}

	/**
	 * @return bool
	 */
	protected function isModuleRequest() {
		return ( $this->getModSlug() == Services::Request()->request( 'mod_slug' ) );
	}

	/**
	 * @param string $sAction
	 * @param bool   $bAsJsonEncodedObject
	 * @return array
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
		return [
			'action'     => $this->prefix(), //wp ajax doesn't work without this.
			'exec'       => $sAction,
			'exec_nonce' => $this->genNonce( $sAction ),
			'mod_slug'   => $this->getModSlug(),
		];
	}

	/**
	 * @param string $sAction
	 * @return string
	 */
	public function genNonce( $sAction = '' ) {
		return wp_create_nonce( $sAction );
	}

	/**
	 * @param string $sNonce
	 * @param string $sAction
	 * @return bool
	 */
	public function checkNonceAction( $sNonce, $sAction = '' ) {
		return wp_verify_nonce( $sNonce, $this->prefix( $sAction ) );
	}

	/**
	 * @param string $sKey
	 * @param string $sDefault
	 * @return string
	 */
	protected function getTranslatedString( $sKey, $sDefault ) {
		return $sDefault;
	}

	/**
	 * @return ICWP_WPSF_Wizard_Base|null
	 */
	protected function getWizardHandler() {
		if ( !isset( $this->oWizard ) ) {
			$sClassName = $this->getWizardClassName();
			if ( !class_exists( $sClassName ) ) {
				return null;
			}
			$this->oWizard = new $sClassName( $this );
		}
		return $this->oWizard;
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * It will also update the stored plugin options version.
	 * @return void
	 */
	public function savePluginOptions() {
		$this->doPrePluginOptionsSave();
		if ( apply_filters( $this->prefix( 'force_options_resave' ), false ) ) {
			$this->getOptionsVo()
				 ->setNeedSave( true );
		}

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		$this->bImportExportWhitelistNotify = $this->getOptionsVo()->getNeedSave();
		$this->store();
	}

	private function store() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		$this->getOptionsVo()
			 ->doOptionsSave( $this->getCon()->getIsResetPlugin(), $this->isPremium() );
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
	}

	/**
	 * @param array $aAggregatedOptions
	 * @return array
	 */
	public function aggregateOptionsValues( $aAggregatedOptions ) {
		return array_merge( $aAggregatedOptions, $this->getOptionsVo()->getAllOptionsValues() );
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 */
	public function buildOptions() {

		$bPremiumEnabled = $this->getCon()->isPremiumExtensionsEnabled();

		$oOptsVo = $this->getOptionsVo();
		$aOptions = $oOptsVo->getOptionsForPluginUse();

		foreach ( $aOptions as $nSectionKey => $aSection ) {

			if ( !empty( $aSection[ 'options' ] ) ) {

				foreach ( $aSection[ 'options' ] as $nKey => $aOption ) {
					$aOption[ 'is_value_default' ] = ( $aOption[ 'value' ] === $aOption[ 'default' ] );
					$bIsPrem = isset( $aOption[ 'premium' ] ) && $aOption[ 'premium' ];
					if ( !$bIsPrem || $bPremiumEnabled ) {
						$aSection[ 'options' ][ $nKey ] = $this->buildOptionForUi( $aOption );
					}
					else {
						unset( $aSection[ 'options' ][ $nKey ] );
					}
				}

				if ( empty( $aSection[ 'options' ] ) ) {
					unset( $aOptions[ $nSectionKey ] );
				}
				else {
					try {
						$aStrings = $this->getStrings()->getSectionStrings( $aSection[ 'slug' ] );
						foreach ( $aStrings as $sKey => $sVal ) {
							unset( $aSection[ $sKey ] );
							$aSection[ $sKey ] = $sVal;
						}
					}
					catch ( \Exception $oE ) {
					}
					$aOptions[ $nSectionKey ] = $aSection;
				}

				$aWarnings = [];
				if ( !$oOptsVo->isSectionReqsMet( $aSection[ 'slug' ] ) ) {
					$aWarnings[] = __( 'Unfortunately your WordPress and/or PHP versions are too old to support this feature.', 'wp-simple-firewall' );
				}
				$aOptions[ $nSectionKey ][ 'warnings' ] = array_merge(
					$aWarnings,
					$this->getSectionWarnings( $aSection[ 'slug' ] )
				);
				$aOptions[ $nSectionKey ][ 'notices' ] = $this->getSectionNotices( $aSection[ 'slug' ] );

				if ( !empty( $aSection[ 'help_video_id' ] ) ) {
					$sHelpVideoUrl = $this->getHelpVideoUrl( $aSection[ 'help_video_id' ] );
				}
				else {
					$sHelpVideoUrl = '';
				}
				$aOptions[ $nSectionKey ][ 'help_video_url' ] = $sHelpVideoUrl;
			}
		}

		return $aOptions;
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionNotices( $sSectionSlug ) {
		return [];
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		return [];
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {

		$mCurrent = $aOptParams[ 'value' ];

		switch ( $aOptParams[ 'type' ] ) {

			case 'password':
				if ( !empty( $mCurrent ) ) {
					$mCurrent = '';
				}
				break;

			case 'array':

				if ( empty( $mCurrent ) || !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}

				$aOptParams[ 'rows' ] = count( $mCurrent ) + 2;
				$mCurrent = stripslashes( implode( "\n", $mCurrent ) );

				break;

			case 'comma_separated_lists':

				$aNewValues = [];
				if ( !empty( $mCurrent ) && is_array( $mCurrent ) ) {

					foreach ( $mCurrent as $sPage => $aParams ) {
						$aNewValues[] = $sPage.', '.implode( ", ", $aParams );
					}
				}
				$aOptParams[ 'rows' ] = count( $aNewValues ) + 1;
				$mCurrent = implode( "\n", $aNewValues );

				break;

			case 'multiple_select':
				if ( !is_array( $mCurrent ) ) {
					$mCurrent = [];
				}
				break;

			case 'text':
				$mCurrent = stripslashes( $this->getTextOpt( $aOptParams[ 'key' ] ) );
				break;
		}

		$aParams = [
			'value'    => is_scalar( $mCurrent ) ? esc_attr( $mCurrent ) : $mCurrent,
			'disabled' => !$this->isPremium() && ( isset( $aOptParams[ 'premium' ] ) && $aOptParams[ 'premium' ] ),
		];
		$aParams[ 'enabled' ] = !$aParams[ 'disabled' ];
		$aOptParams = array_merge( [ 'rows' => 2 ], $aOptParams, $aParams );

		// add strings
		try {
			$aOptParams = Services::DataManipulation()->mergeArraysRecursive(
				$aOptParams,
				$this->getStrings()->getOptionStrings( $aOptParams[ 'key' ] )
			);
		}
		catch ( \Exception $oE ) {
		}
		return $aOptParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	/**
	 */
	public function deactivatePlugin() {
	}

	/**
	 * Deletes all the options including direct save.
	 */
	public function deletePluginOptions() {
		$this->getOptionsVo()->doOptionsDelete();
	}

	/**
	 * @return array - map of each option to its option type
	 */
	protected function getAllFormOptionsAndTypes() {
		$aOpts = [];

		foreach ( $this->buildOptions() as $aOptionsSection ) {
			if ( !empty( $aOptionsSection ) ) {
				foreach ( $aOptionsSection[ 'options' ] as $aOption ) {
					$aOpts[ $aOption[ 'key' ] ] = $aOption[ 'type' ];
				}
			}
		}

		return $aOpts;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_ModOptions() {

		$sName = $this->getCon()->getHumanName();

		try {
			$this->saveOptionsSubmit();
			$bSuccess = true;
			$sMessage = sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $sName );
		}
		catch ( \Exception $oE ) {
			$bSuccess = false;
			$sMessage = sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $sName )
						.' '.$oE->getMessage();
		}

		return [
			'success' => $bSuccess,
			'html'    => '', //we reload the page
			'message' => $sMessage
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_ModOptionsFormRender() {
		return [
			'success' => true,
			'html'    => $this->renderOptionsForm(),
			'message' => 'loaded'
		];
	}

	/**
	 */
	public function handleModRequest() {
	}

	/**
	 * @throws \Exception
	 */
	protected function saveOptionsSubmit() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
		}
		$this->doSaveStandardOptions();
		$this->doExtraSubmitProcessing();
	}

	protected function verifyFormSubmit() {
		return $this->getCon()->isPluginAdmin()
			   && check_admin_referer( $this->getCon()->getPluginPrefix() );
	}

	protected function doExtraSubmitProcessing() {
	}

	protected function setSaveUserResponse() {
		if ( $this->isAdminOptionsPage() ) {
			$this->setFlashAdminNotice( __( 'Plugin options updated successfully.', 'wp-simple-firewall' ) );
		}
	}

	/**
	 * @param string $sMsg
	 * @param bool   $bError
	 * @return $this
	 */
	public function setFlashAdminNotice( $sMsg, $bError = false ) {
		$this->loadWpNotices()
			 ->addFlashUserMessage( sprintf( '[%s] %s', $this->getCon()->getHumanName(), $sMsg ), $bError );
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
	 * UNUSED
	 * Ensure that if an option is premium, it is never changed unless we have premium access
	 */
	protected function resetPremiumOptions() {
		if ( !$this->isPremium() ) {
			$this->getOptionsVo()->resetPremiumOptsToDefault();
		}
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
				else if ( $sOptType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				else if ( $sOptType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
				else if ( $sOptType == 'multiple_select' ) {
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
				else if ( $sOptType == 'password' && $this->hasEncryptOption() ) { //md5 any password fields
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
				else if ( $sOptType == 'array' ) { //arrays are textareas, where each is separated by newline
					$sOptionValue = array_filter( explode( "\n", esc_textarea( $sOptionValue ) ), 'trim' );
				}
				else if ( $sOptType == 'comma_separated_lists' ) {
					$sOptionValue = Services::Data()->extractCommaSeparatedList( $sOptionValue );
				}
				else if ( $sOptType == 'multiple_select' ) {
				}
			}

			// Prevent overwriting of non-editable fields
			if ( !in_array( $sOptType, [ 'noneditable_text' ] ) ) {
				$this->setOpt( $sKey, $sOptionValue );
			}
		}

		$this->savePluginOptions();

		// only use this flag when the options are being updated with a MANUAL save.
		if ( isset( $this->bImportExportWhitelistNotify ) && $this->bImportExportWhitelistNotify ) {
			if ( !wp_next_scheduled( $this->prefix( 'importexport_notify' ) ) ) {
				wp_schedule_single_event( Services::Request()->ts() + 15, $this->prefix( 'importexport_notify' ) );
			}
		}
	}

	/**
	 */
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
		return ( Services::Request()->query( 'shield_action' ) == 'wizard' && $this->isThisModulePage() );
	}

	/**
	 * @return boolean
	 */
	public function hasEncryptOption() {
		return function_exists( 'md5' );
		//	return extension_loaded( 'mcrypt' );
	}

	/**
	 * Prefixes an option key only if it's needed
	 * @param $sKey
	 * @return string
	 */
	public function prefixOptionKey( $sKey = '' ) {
		return $this->prefix( $sKey, '_' );
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
		if ( $this->canDisplayOptionsForm() ) {
			echo $this->renderModulePage();
		}
		else {
			echo $this->renderRestrictedPage();
		}
	}

	/**
	 * Not yet used but may be used above to replace renderModulePage() to load
	 * options pages by AJAX.
	 * @return string
	 */
	private function renderAdminBootstrap() {
		return $this->renderTemplate( 'admin_bootstrap.php' );
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $aData
	 * @return string
	 */
	protected function renderModulePage( $aData = [] ) {
		// Get Base Data
		$aData = Services::DataManipulation()
						 ->mergeArraysRecursive( $this->getBaseDisplayData(), $aData );
		$aData[ 'content' ][ 'options_form' ] = $this->renderOptionsForm();

		return $this->renderTemplate( 'index.php', $aData );
	}

	/**
	 * @return string
	 */
	protected function renderRestrictedPage() {
		$aData = Services::DataManipulation()
						 ->mergeArraysRecursive(
							 $this->getBaseDisplayData(),
							 [
								 'ajax' => [
									 'restricted_access' => $this->getAjaxActionData( 'restricted_access' )
								 ]
							 ]
						 );
		return $this->renderTemplate( 'access_restricted.php', $aData );
	}

	/**
	 * @return array
	 */
	protected function getBaseDisplayData() {
		$oCon = $this->getCon();

		return [
			'sPluginName'     => $oCon->getHumanName(),
			'sFeatureName'    => $this->getMainFeatureName(),
			'bFeatureEnabled' => $this->isModuleEnabled(),
			'sTagline'        => $this->getOptionsVo()->getFeatureTagline(),
			'nonce_field'     => wp_nonce_field( $oCon->getPluginPrefix(), '_wpnonce', true, false ), //don't echo!
			'form_action'     => 'admin.php?page='.$this->getModSlug(),
			'nOptionsPerRow'  => 1,
			'aPluginLabels'   => $oCon->getLabels(),
			'help_video'      => [
				'auto_show'   => $this->getIfAutoShowHelpVideo(),
				'iframe_url'  => $this->getHelpVideoUrl( $this->getHelpVideoId() ),
				'display_id'  => 'ShieldHelpVideo'.$this->getSlug(),
				'options'     => $this->getHelpVideoOptions(),
				'displayable' => $this->isHelpVideoDisplayable(),
				'show'        => $this->isHelpVideoDisplayable() && !$this->getHelpVideoHasBeenClosed(),
				'width'       => 772,
				'height'      => 454,
			],
			'aSummaryData'    => $this->getModulesSummaryData(),

			//			'sPageTitle' => sprintf( '%s: %s', $oCon->getHumanName(), $this->getMainFeatureName() ),
			'sPageTitle'      => $this->getMainFeatureName(),
			'data'            => [
				'form_nonce'     => $this->genNonce( '' ),
				'mod_slug'       => $this->getModSlug( true ),
				'mod_slug_short' => $this->getModSlug( false ),
				'all_options'    => $this->buildOptions(),
				'hidden_options' => $this->getOptionsVo()->getHiddenOptions()
			],
			'ajax'            => [
				'mod_options' => $this->getAjaxActionData( 'mod_options' ),
			],
			'strings'         => $this->getStrings()->getDisplayStrings(),
			'flags'           => [
				'access_restricted'     => !$this->canDisplayOptionsForm(),
				'show_ads'              => $this->getIsShowMarketing(),
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_help'     => true,
				'show_alt_content'      => false,
				'has_wizard'            => $this->hasWizard(),
			],
			'hrefs'           => [
				'go_pro'         => 'https://icwp.io/shieldgoprofeature',
				'goprofooter'    => 'https://icwp.io/goprofooter',
				'wizard_link'    => $this->getUrl_WizardLanding(),
				'wizard_landing' => $this->getUrl_WizardLanding()
			],
			'content'         => [
				'options_form'   => '',
				'alt'            => '',
				'actions'        => '',
				'help'           => '',
				'wizard_landing' => ''
			]
		];
	}

	/**
	 * @return string
	 */
	protected function getContentHelp() {
		return $this->renderTemplate( 'snippets/module-help-template.php', $this->getBaseDisplayData() );
	}

	/**
	 * @return string
	 */
	protected function getContentWizardLanding() {
		$aData = $this->getBaseDisplayData();
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
	 * @return array[]
	 */
	protected function getModulesSummaryData() {
		return apply_filters( $this->prefix( 'collect_mod_summary' ), [] );
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
	protected function getUrl_WizardLanding() {
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
	protected function hasCustomActions() {
		return (bool)$this->getOptionsVo()->getFeatureProperty( 'has_custom_actions' );
	}

	/**
	 * @return boolean
	 */
	protected function getIsShowMarketing() {
		return apply_filters( $this->prefix( 'show_marketing' ), !$this->isPremium() );
	}

	/**
	 * @return string
	 */
	protected function renderOptionsForm() {

		if ( $this->canDisplayOptionsForm() ) {
			$sTemplate = 'snippets/options_form.twig';
		}
		else {
			$sTemplate = 'subfeature-access_restricted';
		}

		// Get the same Base Data as normal display
		try {
			return $this->loadRenderer( $this->getCon()->getPath_Templates() )
						->setTemplate( $sTemplate )
						->setRenderVars( $this->getBaseDisplayData() )
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
	protected function canDisplayOptionsForm() {
		return $this->getOptionsVo()->isAccessRestricted() ? $this->getCon()->isPluginAdmin() : true;
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
			$oRndr = $this->loadRenderer( $this->getCon()->getPath_Templates() );
			if ( $bUseTwig ) {
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
		$aTransferableOptions[ $this->getOptionsStorageKey() ] = $this->getOptionsVo()->getTransferableOptions();
		return $aTransferableOptions;
	}

	/**
	 * @return array
	 */
	public function collectOptionsForTracking() {
		$oVO = $this->getOptionsVo();
		$aOptionsData = $this->getOptionsVo()->getOptionsMaskSensitive();
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

	protected function getHelpVideoOptions() {
		$aOptions = $this->getOpt( 'help_video_options', [] );
		if ( is_null( $aOptions ) || !is_array( $aOptions ) ) {
			$aOptions = [
				'closed'    => false,
				'displayed' => false,
				'played'    => false,
			];
			$this->setOpt( 'help_video_options', $aOptions );
		}
		return $aOptions;
	}

	/**
	 * @return bool
	 */
	protected function getHelpVideoHasBeenClosed() {
		return (bool)$this->getHelpVideoOption( 'closed' );
	}

	/**
	 * @return bool
	 */
	protected function getHelpVideoHasBeenDisplayed() {
		return (bool)$this->getHelpVideoOption( 'displayed' );
	}

	/**
	 * @return bool
	 */
	protected function getVideoHasBeenPlayed() {
		return (bool)$this->getHelpVideoOption( 'played' );
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	protected function getHelpVideoOption( $sKey ) {
		$aOpts = $this->getHelpVideoOptions();
		return isset( $aOpts[ $sKey ] ) ? $aOpts[ $sKey ] : null;
	}

	/**
	 * @return bool
	 */
	protected function getIfAutoShowHelpVideo() {
		return !$this->getHelpVideoHasBeenClosed();
	}

	/**
	 * @return string
	 */
	protected function getHelpVideoId() {
		return $this->getDef( 'help_video_id' );
	}

	/**
	 * @param string $sId
	 * @return string
	 */
	protected function getHelpVideoUrl( $sId ) {
		return sprintf( 'https://player.vimeo.com/video/%s', $sId );
	}

	/**
	 * @return bool
	 */
	protected function isHelpVideoDisplayable() {
		$sId = $this->getHelpVideoId();
		return false;
		return !empty( $sId );
	}

	/**
	 * @return $this
	 */
	protected function resetHelpVideoOptions() {
		return $this->setOpt( 'help_video_options', [] );
	}

	/**
	 * @return $this
	 */
	protected function setHelpVideoClosed() {
		return $this->setHelpVideoOption( 'closed', true );
	}

	/**
	 * @return $this
	 */
	protected function setHelpVideoDisplayed() {
		return $this->setHelpVideoOption( 'displayed', true );
	}

	/**
	 * @return $this
	 */
	protected function setHelpVideoPlayed() {
		return $this->setHelpVideoOption( 'played', true );
	}

	/**
	 * @param string          $sKey
	 * @param string|bool|int $mValue
	 * @return $this
	 */
	protected function setHelpVideoOption( $sKey, $mValue ) {
		$aOpts = $this->getHelpVideoOptions();
		$aOpts[ $sKey ] = $mValue;
		return $this->setOpt( 'help_video_options', $aOpts );
	}

	/**
	 * @param string $sOpt
	 * @param int    $nAt
	 * @return $this
	 */
	protected function setOptAt( $sOpt, $nAt = null ) {
		$nAt = is_null( $nAt ) ? Services::Request()->ts() : max( 0, (int)$nAt );
		return $this->setOpt( $sOpt, $nAt );
	}

	/**
	 * @return null|Shield\Modules\Base\Options|mixed
	 */
	public function getOptions() {
		if ( !isset( $this->oOpts ) ) {

			if ( @class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options' ) ) {
				$oOpts = $this->loadOptions()->setMod( $this );;
			}
			else {
				$oOpts = new \ICWP_WPSF_OptionsVO();
			}

			$oCon = $this->getCon();
			$this->oOpts = $oOpts->setPathToConfig( $oCon->getPath_ConfigFile( $this->getSlug() ) )
								 ->setRebuildFromFile( $oCon->getIsRebuildOptionsFromFile() )
								 ->setOptionsStorageKey( $this->getOptionsStorageKey() )
								 ->setIfLoadOptionsFromStorage( !$oCon->getIsResetPlugin() );
		}
		return $this->oOpts;
	}

	/**
	 * @return null|Shield\Databases\Base\Handler|mixed
	 */
	public function getDbHandler() {
		if ( !isset( $this->oDbh ) ) {
			$this->oDbh = $this->loadDbHandler()->setMod( $this );
		}
		return $this->oDbh;
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
	 * @return Shield\Databases\Base\Handler|mixed
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Base\Handler();
	}

	/**
	 * @return Shield\Modules\Base\Options|mixed
	 */
	protected function loadOptions() {
		return new Shield\Modules\Base\Options;
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return new Shield\Modules\Base\Strings();
	}

	/**
	 * @return array
	 * @deprecated
	 */
	protected function getDisplayStrings() {
		return $this->getStrings()->getDisplayStrings();
	}

	/**
	 * @param bool $bBypass
	 * @return $this
	 * @deprecated
	 */
	protected function setBypassAdminProtection( $bBypass ) {
		return $this;
	}
}