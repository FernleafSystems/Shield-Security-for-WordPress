<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Base', false ) ) {
	return;
}

abstract class ICWP_WPSF_FeatureHandler_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Plugin_Controller
	 */
	static protected $oPluginController;

	/**
	 * @var boolean
	 */
	protected $bBypassAdminAccess = false;

	/**
	 * @var ICWP_WPSF_OptionsVO
	 */
	protected $oOptions;

	/**
	 * @var boolean
	 */
	protected $bModuleMeetsRequirements;

	/**
	 * @var string
	 */
	const CollateSeparator = '--SEP--';
	/**
	 * @var string
	 */
	const PluginVersionKey = 'current_plugin_version';

	/**
	 * @var boolean
	 */
	protected $bPluginDeleting = false;

	/**
	 * @var string
	 */
	protected $sOptionsStoreKey;

	/**
	 * @var string
	 */
	protected $sFeatureName;

	/**
	 * @var string
	 */
	protected $sModSlug;

	/**
	 * @var boolean
	 */
	protected static $bForceOffFileExists;

	/**
	 * @var boolean
	 */
	protected $bImportExportWhitelistNotify = false;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Email
	 */
	protected static $oEmailHandler;

	/**
	 * @var ICWP_WPSF_Processor_Base
	 */
	protected $oProcessor;

	/**
	 * @var ICWP_WPSF_Wizard_Base
	 */
	protected $oWizard;

	/**
	 * @var string
	 */
	protected static $sActivelyDisplayedModuleOptions = '';

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oPluginController
	 * @param array                       $aModProps
	 * @throws Exception
	 */
	public function __construct( $oPluginController, $aModProps = array() ) {
		if ( empty( $oPluginController ) ) {
			throw new Exception();
		}
		else if ( empty( self::$oPluginController ) ) {
			self::$oPluginController = $oPluginController;
		}

		if ( isset( $aModProps[ 'storage_key' ] ) ) {
			$this->sOptionsStoreKey = $aModProps[ 'storage_key' ];
		}

		if ( isset( $aModProps[ 'slug' ] ) ) {
			$this->sModSlug = $aModProps[ 'slug' ];
		}

		// before proceeding, we must now test the system meets the minimum requirements.
		if ( $this->getModuleMeetRequirements() ) {

			$nRunPriority = isset( $aModProps[ 'load_priority' ] ) ? $aModProps[ 'load_priority' ] : 100;
			// Handle any upgrades as necessary (only go near this if it's the admin area)
			add_action( $this->prefix( 'run_processors' ), array( $this, 'onRunProcessors' ), $nRunPriority );
			add_action( 'init', array( $this, 'onWpInit' ), 1 );
			add_action( $this->prefix( 'import_options' ), array( $this, 'processImportOptions' ) );

			if ( $this->isModuleRequest() ) {
				add_action( $this->prefix( 'form_submit' ), array( $this, 'handleOptionsSubmit' ) );
				add_filter( $this->prefix( 'ajaxAction' ), array( $this, 'handleAjax' ) );
				add_filter( $this->prefix( 'ajaxAuthAction' ), array( $this, 'handleAuthAjax' ) );
				add_filter( $this->prefix( 'ajaxNonAuthAction' ), array( $this, 'handleNonAuthAjax' ) );
			}

			$nMenuPriority = isset( $aModProps[ 'menu_priority' ] ) ? $aModProps[ 'menu_priority' ] : 100;
			add_filter( $this->prefix( 'submenu_items' ), array( $this, 'supplySubMenuItem' ), $nMenuPriority );
			add_filter( $this->prefix( 'collect_module_summary_data' ), array( $this, 'addModuleSummaryData' ), $nMenuPriority );
			add_filter( $this->prefix( 'collect_notices' ), array( $this, 'addInsightsNoticeData' ) );
			add_action( $this->prefix( 'plugin_shutdown' ), array( $this, 'action_doFeatureShutdown' ) );
			add_action( $this->prefix( 'delete_plugin' ), array( $this, 'deletePluginOptions' ) );
			add_filter( $this->prefix( 'aggregate_all_plugin_options' ), array( $this, 'aggregateOptionsValues' ) );

			add_filter( $this->prefix( 'register_admin_notices' ), array( $this, 'fRegisterAdminNotices' ) );
			add_filter( $this->prefix( 'gather_options_for_export' ), array( $this, 'exportTransferableOptions' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'insertCustomJsVars' ), 100 );

			if ( $this->isAdminOptionsPage() ) {
//				add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
			}

			$this->doPostConstruction();
		}
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
			switch ( $this->loadDP()->request( 'exec' ) ) {

				case 'mod_options':
					$aAjaxResponse = $this->ajaxExec_ModOptions();
					break;

				case 'wiz_process_step':
					$aAjaxResponse = $this->ajaxExec_ModOptions();
					if ( $this->canRunWizards() && $this->hasWizard() ) {
						$aAjaxResponse = $this->getWizardHandler()
											  ->ajaxExec_WizProcessStep();
					}
					break;

				case 'wiz_render_step':
					if ( $this->canRunWizards() && $this->hasWizard() ) {
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
				array(
					'success' => false,
					'message' => 'Unknown',
					'html'    => '',
				),
				$aAjaxResponse
			);
		}
		return $aAjaxResponse;
	}

	/**
	 * @param array $aAdminNotices
	 * @return array
	 */
	public function fRegisterAdminNotices( $aAdminNotices ) {
		if ( !is_array( $aAdminNotices ) ) {
			$aAdminNotices = array();
		}
		return array_merge( $aAdminNotices, $this->getOptionsVo()->getAdminNotices() );
	}

	/**
	 * @return bool
	 */
	protected function getModuleMeetRequirements() {
		if ( !isset( $this->bModuleMeetsRequirements ) ) {
			$this->bModuleMeetsRequirements = $this->verifyModuleMeetRequirements();
		}
		return $this->bModuleMeetsRequirements;
	}

	/**
	 * @return bool
	 */
	protected function verifyModuleMeetRequirements() {
		$bMeetsReqs = true;

		$aPhpReqs = $this->getOptionsVo()->getFeatureRequirement( 'php' );
		if ( !empty( $aPhpReqs ) ) {

			if ( !empty( $aPhpReqs[ 'version' ] ) ) {
				$bMeetsReqs = $bMeetsReqs && $this->loadDP()
												  ->getPhpVersionIsAtLeast( $aPhpReqs[ 'version' ] );
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

	protected function doPostConstruction() {
	}

	/**
	 */
	public function onRunProcessors() {
		$this->getOptionsVo()
			 ->setIsPremiumLicensed( $this->isPremium() );

		if ( $this->isModuleEnabled() && $this->isReadyToExecute() ) {
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
			$this->setBypassAdminProtection( true )
				 ->savePluginOptions();
		}
	}

	/**
	 * for now only import by file is supported
	 */
	protected function importOptions() {
		// So we don't poll for the file every page load.
		if ( $this->loadDP()->query( 'icwp_shield_import' ) == 1 ) {
			$aOptions = self::getConn()->getOptionsImportFromFile();
			if ( !empty( $aOptions ) && is_array( $aOptions ) && array_key_exists( $this->getOptionsStorageKey(), $aOptions ) ) {
				$this->getOptionsVo()->setMultipleOptions( $aOptions[ $this->getOptionsStorageKey() ] );
				$this
					->setBypassAdminProtection( true )
					->savePluginOptions();
			}
		}
	}

	/**
	 * Used to effect certain processing that is to do with options etc. but isn't related to processing
	 * functionality of the plugin.
	 */
	protected function isReadyToExecute() {
		$bReady = !self::getConn()->getIfForceOffActive();
		if ( $bReady ) {
			$oProcessor = $this->getProcessor();
			$bReady = $bReady && ( $oProcessor instanceof ICWP_WPSF_Processor_Base );
		}
		return $bReady;
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->run();
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		$this->runWizards();
		if ( $this->getIsUpgrading() ) {
			$this->updateHandler();
		}

		// GDPR
		if ( $this->isPremium() ) {
			add_filter( $this->prefix( 'wpPrivacyExport' ), array( $this, 'onWpPrivacyExport' ), 10, 3 );
			add_filter( $this->prefix( 'wpPrivacyErase' ), array( $this, 'onWpPrivacyErase' ), 10, 3 );
		}
	}

	/**
	 * We have to do it this way as the "page hook" is built upon the top-level plugin
	 * menu name. But what if we white label?  So we need to dynamically grab the page hook
	 */
	public function onSetCurrentScreen() {
		global $page_hook;
		add_action( 'load-'.$page_hook, array( $this, 'onLoadOptionsScreen' ) );
	}

	/**
	 */
	public function onLoadOptionsScreen() {
		if ( $this->getConn()->getIsValidAdminArea() ) {
			$this->buildContextualHelp();
		}
	}

	/**
	 * Override this and adapt per feature
	 * @return ICWP_WPSF_Processor_Base
	 */
	protected function loadProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			include_once( self::getConn()
							  ->getPath_SourceFile( sprintf( 'processors/%s.php', $this->getSlug() ) ) );
			$sClassName = $this->getProcessorClassName();
			if ( !class_exists( $sClassName, false ) ) {
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
		return ucwords( self::getConn()->getOptionStoragePrefix() ).'Processor_'.
			   str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) );
	}

	/**
	 * Override this and adapt per feature
	 * @return string
	 */
	protected function getWizardClassName() {
		return ucwords( self::getConn()->getOptionStoragePrefix() ).'Wizard_'.
			   str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getSlug() ) ) );
	}

	/**
	 * @return ICWP_WPSF_OptionsVO
	 */
	protected function getOptionsVo() {
		if ( !isset( $this->oOptions ) ) {
			$oCon = self::getConn();
			$this->oOptions = ICWP_WPSF_Factory::OptionsVo();
			$this->oOptions
				->setPathToConfig( $oCon->getPath_ConfigFile( $this->getSlug() ) )
				->setIsPremiumLicensed( $this->isPremium() )
				->setOptionsEncoding( $oCon->getOptionsEncoding() )
				->setRebuildFromFile( $oCon->getIsRebuildOptionsFromFile() )
				->setOptionsStorageKey( $this->getOptionsStorageKey() )
				->setIfLoadOptionsFromStorage( !$oCon->getIsResetPlugin() );
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
	public function getIsUpgrading() {
//			return $this->getVersion() != self::getController()->getVersion();
		return self::getConn()->getIsRebuildOptionsFromFile();
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function action_doFeatureShutdown() {
		if ( !$this->isPluginDeleting() ) {
			$this->savePluginOptions();
		}
	}

	/**
	 * @return bool
	 */
	public function isPluginDeleting() {
		return $this->bPluginDeleting;
	}

	/**
	 * @return string
	 */
	protected function getOptionsStorageKey() {
		if ( !isset( $this->sOptionsStoreKey ) ) {
			// not ideal as it doesn't take into account custom storage keys as when passed into the constructor
			$this->sOptionsStoreKey = $this->getOptionsVo()->getFeatureProperty( 'storage_key' );
		}

		return $this->prefixOptionKey( $this->sOptionsStoreKey ).'_options';
	}

	/**
	 * @return ICWP_WPSF_Processor_Base
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	/**
	 * @return string
	 */
	public function getUrl_AdminPage() {
		return $this->loadWp()
					->getUrl_AdminPage(
						$this->getModSlug(),
						self::getConn()->getIsWpmsNetworkAdminOnly()
					);
	}

	/**
	 * TODO: Get rid of this crap and/or handle the Exception thrown in loadFeatureHandler()
	 * @return ICWP_WPSF_FeatureHandler_Email
	 */
	public function getEmailHandler() {
		if ( is_null( self::$oEmailHandler ) ) {
			self::$oEmailHandler = self::getConn()->loadFeatureHandler( array( 'slug' => 'email' ) );
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

		$bEnabled = $this->getOptIs( 'enable_'.$this->getSlug(), 'Y' )
					|| $this->getOptIs( 'enable_'.$this->getSlug(), true, true );

		if ( $oOpts->getFeatureProperty( 'auto_enabled' ) === true ) {
			$bEnabled = true;
		}
		else if ( apply_filters( $this->prefix( 'globally_disabled' ), false ) ) {
			$bEnabled = false;
		}
		else if ( $oOpts->getFeatureProperty( 'premium' ) === true && !$this->isPremium() ) {
			$bEnabled = false;
		}

		return $bEnabled;
	}

	/**
	 * @return string
	 */
	protected function getMainFeatureName() {
		if ( !isset( $this->sFeatureName ) ) {
			$this->sFeatureName = $this->getOptionsVo()->getFeatureProperty( 'name' );
		}
		return $this->sFeatureName;
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
	 * @return int
	 */
	public function getPluginInstallationTime() {
		return $this->getOpt( 'installation_time', 0 );
	}

	/**
	 * With trailing slash
	 * @param string $sSourceFile
	 * @return string
	 */
	public function getResourcesDir( $sSourceFile = '' ) {
		return self::getConn()
				   ->getRootDir().'resources/'.ltrim( $sSourceFile, '/' );
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

			$sHumanName = self::getConn()->getHumanName();

			$bMenuHighlighted = $this->getOptionsVo()->getFeatureProperty( 'highlight_menu_item' );
			if ( $bMenuHighlighted ) {
				$sMenuTitleName = sprintf( '<span class="icwp_highlighted">%s</span>', $sMenuTitleName );
			}

			$sMenuPageTitle = $sMenuTitleName.' - '.$sHumanName;
			$aItems[ $sMenuPageTitle ] = array(
				$sMenuTitleName,
				$this->getModSlug(),
				array( $this, 'displayModuleAdminPage' ),
				$this->getIfShowModuleMenuItem()
			);

			$aAdditionalItems = $this->getOptionsVo()->getAdditionalMenuItems();
			if ( !empty( $aAdditionalItems ) && is_array( $aAdditionalItems ) ) {

				foreach ( $aAdditionalItems as $aMenuItem ) {

					if ( empty( $aMenuItem[ 'callback' ] ) || !method_exists( $this, $aMenuItem[ 'callback' ] ) ) {
						continue;
					}

					$sMenuPageTitle = $sHumanName.' - '.$aMenuItem[ 'title' ];
					$aItems[ $sMenuPageTitle ] = array(
						$aMenuItem[ 'title' ],
						$this->prefix( $aMenuItem[ 'slug' ] ),
						array( $this, $aMenuItem[ 'callback' ] )
					);
				}
			}
		}
		return $aItems;
	}

	/**
	 * @return array
	 */
	protected function getAdditionalMenuItem() {
		return array();
	}

	/**
	 * @param array $aSummaryData
	 * @return array
	 */
	public function addModuleSummaryData( $aSummaryData ) {
		if ( $this->getIfShowModuleLink() ) {
			$aSummaryData[] = $this->buildSummaryData();
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
	 * @return array
	 */
	protected function buildSummaryData() {
		$oOptions = $this->getOptionsVo();
		$sMenuTitle = $oOptions->getFeatureProperty( 'menu_title' );

		$aSections = $oOptions->getSections();
		foreach ( $aSections as $sSlug => $aSection ) {
			$aSections[ $sSlug ] = $this->loadStrings_SectionTitles( $aSection );
		}

		$aSummary = array(
			'enabled'    => $this->isEnabledForUiSummary(),
			'active'     => self::$sActivelyDisplayedModuleOptions == $this->getSlug(),
			'slug'       => $this->getSlug(),
			'name'       => $this->getMainFeatureName(),
			'menu_title' => empty( $sMenuTitle ) ? $this->getMainFeatureName() : $sMenuTitle,
			'href'       => network_admin_url( 'admin.php?page='.$this->getModSlug() ),
			'sections'   => $aSections,
		);
		$aSummary[ 'content' ] = $this->renderTemplate( 'snippets/summary_single', $aSummary );
		return $aSummary;
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
		return $this->getOptionsVo()->getFeatureProperty( 'show_module_menu_item' );
	}

	/**
	 * @return boolean
	 */
	public function getIfShowModuleLink() {
		return $this->getIfShowModuleMenuItem();
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
	 * @deprecated
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getDefinition( $sKey ) {
		return $this->getDef( $sKey );
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors( array() );
	}

	/**
	 * @param bool   $bAsString
	 * @param string $sGlue
	 * @return string|array
	 */
	public function getLastErrors( $bAsString = true, $sGlue = " " ) {
		$aErrors = $this->getOpt( 'last_errors' );
		if ( !is_array( $aErrors ) ) {
			$aErrors = array();
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
	public function getOptIs( $sOptionKey, $mValueToTest, $bStrict = false ) {
		$mOptionValue = $this->getOptionsVo()->getOpt( $sOptionKey );
		return $bStrict ? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * Retrieves the full array of options->values
	 * @return array
	 */
	public function getOptions() {
		return $this->buildOptions();
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
	 * @return string
	 */
	public function getVersion() {
		$sVersion = $this->getOpt( self::PluginVersionKey );
		return empty( $sVersion ) ? self::getConn()->getVersion() : $sVersion;
	}

	/**
	 * @param array|string $mErrors
	 * @return $this
	 */
	public function setLastErrors( $mErrors = array() ) {
		if ( !is_array( $mErrors ) ) {
			if ( is_string( $mErrors ) ) {
				$mErrors = array( $mErrors );
			}
			else {
				$mErrors = array();
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
		$this->setBypassAdminProtection( true );
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
		return ( $this->getModSlug() == $this->loadDP()->request( 'mod_slug' ) );
	}

	/**
	 * @param string $sAction
	 * @param bool   $bAsJsonEncodedObject
	 * @return array
	 */
	public function getAjaxActionData( $sAction = '', $bAsJsonEncodedObject = false ) {
		$aData = array(
			'action'     => $this->prefix(), //wp ajax doesn't work without this.
			'exec'       => $sAction,
			'exec_nonce' => $this->genNonce( $sAction ),
			'mod_slug'   => $this->getModSlug(),
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
		);
		return $bAsJsonEncodedObject ? json_encode( (object)$aData ) : $aData;
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
	 * @return bool
	 */
	public function getBypassAdminRestriction() {
		return $this->bBypassAdminAccess;
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
			include_once( self::getConn()->getPath_SourceFile( sprintf( 'wizards/%s.php', $this->getSlug() ) ) );
			$sClassName = $this->getWizardClassName();
			if ( !class_exists( $sClassName, false ) ) {
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
		$this->updateOptionsVersion();
		if ( apply_filters( $this->prefix( 'force_options_resave' ), false ) ) {
			$this->getOptionsVo()
				 ->setIsPremiumLicensed( $this->isPremium() )
				 ->setNeedSave( true );
		}

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		$this->bImportExportWhitelistNotify = $this->getOptionsVo()->getNeedSave();
		$this->store();
	}

	private function store() {
		add_filter( $this->prefix( 'bypass_permission_to_manage' ), '__return_true', 1000 );
		$this->getOptionsVo()->doOptionsSave( self::getConn()->getIsResetPlugin() );
		remove_filter( $this->prefix( 'bypass_permission_to_manage' ), '__return_true', 1000 );
	}

	protected function updateOptionsVersion() {
		if ( $this->getIsUpgrading() || self::getConn()->getIsRebuildOptionsFromFile() ) {
			$this->setOpt( self::PluginVersionKey, self::getConn()->getVersion() );
			$this->getOptionsVo()->cleanTransientStorage();
		}
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

		$bPremiumEnabled = self::getConn()->isPremiumExtensionsEnabled();

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

				if ( !empty( $aSection[ 'help_video_id' ] ) ) {
					$aSection[ 'help_video_url' ] = $this->getHelpVideoUrl( $aSection[ 'help_video_id' ] );
				}

				if ( empty( $aSection[ 'options' ] ) ) {
					unset( $aOptions[ $nSectionKey ] );
				}
				else {
					$aOptions[ $nSectionKey ] = $this->loadStrings_SectionTitles( $aSection );
				}

				$aWarnings = array();
				if ( !$oOptsVo->isSectionReqsMet( $aSection[ 'slug' ] ) ) {
					$aWarnings[] = _wpsf__( 'Unfortunately your PHP version is too low to support this feature.' );
				}
				$aOptions[ $nSectionKey ][ 'warnings' ] = array_merge(
					$aWarnings,
					$this->getSectionWarnings( $aSection[ 'slug' ] )
				);
				$aOptions[ $nSectionKey ][ 'notices' ] = $this->getSectionNotices( $aSection[ 'slug' ] );
			}
		}

		return $aOptions;
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionNotices( $sSectionSlug ) {
		return array();
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 */
	protected function getSectionWarnings( $sSectionSlug ) {
		return array();
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
					$mCurrent = array();
				}

				$aOptParams[ 'rows' ] = count( $mCurrent ) + 2;
				$mCurrent = stripslashes( implode( "\n", $mCurrent ) );

				break;

			case 'comma_separated_lists':

				$aNewValues = array();
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
					$mCurrent = array();
				}
				break;

			case 'text':
				$mCurrent = stripslashes( $this->getTextOpt( $aOptParams[ 'key' ] ) );
				break;
		}

		$aParams = array(
			'value'    => is_scalar( $mCurrent ) ? esc_attr( $mCurrent ) : $mCurrent,
			'disabled' => !$this->isPremium() && ( isset( $aOptParams[ 'premium' ] ) && $aOptParams[ 'premium' ] ),
		);
		$aParams[ 'enabled' ] = !$aParams[ 'disabled' ];
		$aOptParams = array_merge( array( 'rows' => 2 ), $aOptParams, $aParams );

		// add strings
		return $this->loadStrings_Options( $aOptParams );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	/**
	 * Deletes all the options including direct save.
	 */
	public function deletePluginOptions() {
		if ( self::getConn()->getHasPermissionToManage() ) {
			$this->getOptionsVo()->doOptionsDelete();
			$this->bPluginDeleting = true;
		}
	}

	/**
	 * @return string
	 */
	protected function collateAllFormInputsForAllOptions() {

		$aOptions = $this->buildOptions();

		$aToJoin = array();
		foreach ( $aOptions as $aOptionsSection ) {

			if ( empty( $aOptionsSection ) ) {
				continue;
			}
			foreach ( $aOptionsSection[ 'options' ] as $aOption ) {
				$aToJoin[] = $aOption[ 'type' ].':'.$aOption[ 'key' ];
			}
		}
		return implode( self::CollateSeparator, $aToJoin );
	}

	/**
	 * @return array - map of each option to its option type
	 */
	protected function getAllFormOptionsAndTypes() {
		$aOpts = array();

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

		$oCon = self::getConn();
		$bSuccess = false;
		$sName = $oCon->getHumanName();
		$sMessage = sprintf( _wpsf__( 'Failed up to update %s plugin options.' ), $sName );

		if ( $oCon->getIsValidAdminArea() ) {
			$bSuccess = $this->saveOptionsSubmit();
			if ( $bSuccess ) {
				$sMessage = sprintf( _wpsf__( '%s Plugin options updated successfully.' ), $sName );
			}
		}
		else {
			$sMessage = sprintf( _wpsf__( 'Failed to update %s options as you are not authenticated with %s as a Security Admin.' ), $sName, $sName );
		}

		try {
			$sForm = $this->renderOptionsForm();
		}
		catch ( Exception $oE ) {
			$sForm = 'Error during form render';
		}
		return array(
			'success' => $bSuccess,
			'html'    => $sForm,
			'message' => $sMessage
		);
	}

	/**
	 * @return bool
	 */
	public function handleOptionsSubmit() {
		$bVerified = $this->verifyFormSubmit();
		if ( $bVerified ) {
			$this->saveOptionsSubmit();
			$this->setSaveUserResponse();
		}
		return $bVerified;
	}

	/**
	 * @return bool
	 */
	protected function saveOptionsSubmit() {
		$bSuccess = true;
		if ( self::getConn()->getHasPermissionToManage() ) {
			$this->doSaveStandardOptions();
			$this->doExtraSubmitProcessing();
		}
		else {
//			TODO: manage how we react to prohibited submissions
			$bSuccess = false;
		}
		return $bSuccess;
	}

	protected function verifyFormSubmit() {
		if ( !self::getConn()->getHasPermissionToManage() ) {
//				TODO: manage how we react to prohibited submissions
			return false;
		}

		// Now verify this is really a valid submission.
		return check_admin_referer( self::getConn()->getPluginPrefix() );
	}

	/**
	 * @return void
	 */
	protected function doSaveStandardOptions() {
		$this->updatePluginOptionsFromSubmit();
	}

	protected function doExtraSubmitProcessing() {
	}

	protected function setSaveUserResponse() {
		if ( $this->isAdminOptionsPage() ) {
			$this->loadWpNotices()
				 ->addFlashMessage( sprintf( _wpsf__( '%s Plugin options updated successfully.' ), self::getConn()
																									   ->getHumanName() ) );
		}
	}

	/**
	 * @return bool
	 */
	protected function isAdminOptionsPage() {
		return ( is_admin() && !$this->loadWp()->isAjax() && $this->isThisModulePage() );
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return $this->getConn()->isPremiumActive();
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
	 * @param bool $bBypass
	 * @return $this
	 */
	protected function setBypassAdminProtection( $bBypass ) {
		$this->bBypassAdminAccess = (bool)$bBypass;
		return $this;
	}

	/**
	 * @return void
	 */
	protected function updatePluginOptionsFromSubmit() {
		$oDp = $this->loadDP();

		foreach ( $this->getAllFormOptionsAndTypes() as $sOptionKey => $sOptionType ) {

			$sOptionValue = $oDp->post( $sOptionKey );
			if ( is_null( $sOptionValue ) ) {

				if ( $sOptionType == 'text' || $sOptionType == 'email' ) { //if it was a text box, and it's null, don't update anything
					continue;
				}
				else if ( $sOptionType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				else if ( $sOptionType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
				else if ( $sOptionType == 'multiple_select' ) {
					$sOptionValue = array();
				}
			}
			else { //handle any pre-processing we need to.

				if ( $sOptionType == 'text' || $sOptionType == 'email' ) {
					$sOptionValue = trim( $sOptionValue );
				}
				if ( $sOptionType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				else if ( $sOptionType == 'password' && $this->hasEncryptOption() ) { //md5 any password fields
					$sTempValue = trim( $sOptionValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}
					$sOptionValue = md5( $sTempValue );
				}
				else if ( $sOptionType == 'array' ) { //arrays are textareas, where each is separated by newline
					$sOptionValue = array_filter( explode( "\n", esc_textarea( $sOptionValue ) ), 'trim' );
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					$sOptionValue = $oDp->extractCommaSeparatedList( $sOptionValue );
				}
				else if ( $sOptionType == 'multiple_select' ) {
				}
			}

			// Prevent overwriting of non-editable fields
			if ( !in_array( $sOptionType, array( 'noneditable_text' ) ) ) {
				$this->setOpt( $sOptionKey, $sOptionValue );
			}
		}

		$this->savePluginOptions();

		// only use this flag when the options are being updated with a MANUAL save.
		if ( isset( $this->bImportExportWhitelistNotify ) && $this->bImportExportWhitelistNotify ) {
			if ( !wp_next_scheduled( $this->prefix( 'importexport_notify' ) ) ) {
				wp_schedule_single_event( $this->loadDP()->time() + 15, $this->prefix( 'importexport_notify' ) );
			}
		}
	}

	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function updateHandler() {
	}

	/**
	 */
	protected function runWizards() {
		if ( $this->canRunWizards() && $this->isWizardPage() && $this->hasWizard() ) {
			$oWiz = $this->getWizardHandler();
			if ( $oWiz instanceof ICWP_WPSF_Wizard_Base ) {
				$oWiz->init();
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function isModulePage() {
		return strpos( $this->loadDP()->query( 'page' ), $this->prefix() ) === 0;
	}

	/**
	 * @return bool
	 */
	protected function isThisModulePage() {
		return $this->loadDP()->query( 'page' ) == $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	protected function isModuleOptionsRequest() {
		return $this->loadDP()->post( 'mod_slug' ) === $this->getModSlug();
	}

	/**
	 * @return bool
	 */
	protected function isWizardPage() {
		return ( $this->loadDP()->query( 'shield_action' ) == 'wizard' && $this->isThisModulePage() );
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
	public function prefixOptionKey( $sKey ) {
		return $this->prefix( $sKey, '_' );
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function prefix( $sSuffix = '', $sGlue = '-' ) {
		return self::getConn()->prefix( $sSuffix, $sGlue );
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getOptionStoragePrefix() {
		return self::getConn()->getOptionStoragePrefix();
	}

	/**
	 */
	public function displayModuleAdminPage() {
		if ( $this->canDisplayOptionsForm() ) {
			$this->displayModulePage();
		}
		else {
			$this->displayRestrictedPage();
		}
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $aData
	 */
	protected function displayModulePage( $aData = array() ) {
		// Get Base Data
		$aData = $this->loadDP()->mergeArraysRecursive( $this->getBaseDisplayData( true ), $aData );
		$aData[ 'content' ][ 'options_form' ] = $this->renderOptionsForm();

		echo $this->renderTemplate( 'index.php', $aData );
	}

	protected function displayRestrictedPage() {
		$aData = $this->loadDP()
					  ->mergeArraysRecursive(
						  $this->getBaseDisplayData( false ),
						  array(
							  'ajax' => array(
								  'restricted_access' => $this->getAjaxActionData( 'restricted_access' )
							  )
						  )
					  );
		echo $this->renderTemplate( 'access_restricted.php', $aData );
	}

	/**
	 * @param bool $bRenderEmbeddedContent
	 * @return array
	 */
	protected function getBaseDisplayData( $bRenderEmbeddedContent = false ) {
		$oCon = self::getConn();
		self::$sActivelyDisplayedModuleOptions = $this->getSlug();

		$aData = array(
			'sPluginName'     => $oCon->getHumanName(),
			'sFeatureName'    => $this->getMainFeatureName(),
			'bFeatureEnabled' => $this->isModuleEnabled(),
			'sTagline'        => $this->getOptionsVo()->getFeatureTagline(),
			'nonce_field'     => wp_nonce_field( $oCon->getPluginPrefix(), '_wpnonce', true, false ), //don't echo!
			'form_action'     => 'admin.php?page='.$this->getModSlug(),
			'nOptionsPerRow'  => 1,
			'aPluginLabels'   => $oCon->getPluginLabels(),
			'help_video'      => array(
				'auto_show'   => $this->getIfAutoShowHelpVideo(),
				'iframe_url'  => $this->getHelpVideoUrl( $this->getHelpVideoId() ),
				'display_id'  => 'ShieldHelpVideo'.$this->getSlug(),
				'options'     => $this->getHelpVideoOptions(),
				'displayable' => $this->isHelpVideoDisplayable(),
				'show'        => $this->isHelpVideoDisplayable() && !$this->getHelpVideoHasBeenClosed(),
				'width'       => 772,
				'height'      => 454,
			),
			'aSummaryData'    => $this->getModulesSummaryData(),

			//			'sPageTitle' => sprintf( '%s: %s', $oCon->getHumanName(), $this->getMainFeatureName() ),
			'sPageTitle'      => $this->getMainFeatureName(),
			'data'            => array(
				'form_nonce'        => $this->genNonce( '' ),
				'mod_slug'          => $this->getModSlug( true ),
				'mod_slug_short'    => $this->getModSlug( false ),
				'all_options'       => $this->buildOptions(),
				'all_options_input' => $this->collateAllFormInputsForAllOptions(),
				'hidden_options'    => $this->getOptionsVo()->getHiddenOptions()
			),
			'ajax'            => array(
				'mod_options' => $this->getAjaxActionData( 'mod_options' ),
			),
			'strings'         => $this->getDisplayStrings(),
			'flags'           => array(
				'access_restricted'     => !$this->canDisplayOptionsForm(),
				'show_ads'              => $this->getIsShowMarketing(),
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_actions'  => $this->hasCustomActions(),
				'show_content_help'     => true,
				'show_alt_content'      => false,
				'can_wizard'            => $this->canRunWizards(),
				'has_wizard'            => $this->hasWizard(),
			),
			'hrefs'           => array(
				'go_pro'         => 'https://icwp.io/shieldgoprofeature',
				'goprofooter'    => 'https://icwp.io/goprofooter',
				'wizard_link'    => $this->getUrl_WizardLanding(),
				'wizard_landing' => $this->getUrl_WizardLanding()
			),
			'content'         => array(
				'options_form'   => '',
				'alt'            => '',
				'actions'        => '',
				'help'           => '',
				'wizard_landing' => ''
			)
		);

		if ( $bRenderEmbeddedContent ) { // prevents recursive loops
			$aData[ 'content' ] = array(
				'options_form'   => 'no form',
				'alt'            => '',
				'actions'        => $this->getContentCustomActions(),
				'help'           => $this->getContentHelp(),
				'wizard_landing' => $this->getContentWizardLanding()
			);
			$aData[ 'flags' ][ 'show_content_help' ] = strpos( $aData[ 'content' ][ 'help' ], 'Error:' ) !== 0;
		}
		return $aData;
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return array(
			'go_to_settings'    => __( 'Settings' ),
			'on'                => __( 'On' ),
			'off'               => __( 'Off' ),
			'more_info'         => __( 'More Info' ),
			'blog'              => __( 'Blog' ),
			'save_all_settings' => __( 'Save All Settings' ),
			'see_help_video'    => __( 'Watch Help Video' ),
			'btn_save'          => __( 'Save Options' ),
			'btn_options'       => __( 'Options' ),
			'btn_help'          => __( 'Help' ),
			'btn_actions'       => $this->hasCustomActions() ? __( 'Actions' ) : __( 'No Actions' ),
			'btn_wizards'       => $this->hasWizard() ? __( 'Wizards' ) : __( 'No Wizards' ),
		);
	}

	/**
	 * @return string
	 */
	protected function getContentCustomActions() {
		return $this->renderTemplate( 'snippets/module-actions-template.php',
			$this->loadDP()->mergeArraysRecursive(
				$this->getContentCustomActionsData(),
				$this->getBaseDisplayData( false )
			) );
	}

	/**
	 * @return array
	 */
	protected function getContentCustomActionsData() {
		return $this->getBaseDisplayData( false );
	}

	/**
	 * @return string
	 */
	protected function getContentHelp() {
		return $this->renderTemplate( 'snippets/module-help-template.php', $this->getBaseDisplayData( false ) );
	}

	/**
	 * @return string
	 */
	protected function getContentWizardLanding() {
		$aData = $this->getBaseDisplayData( false );
		if ( $this->hasWizard() && $this->canRunWizards() ) {
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
		$screen->add_help_tab( array(
			'id'      => 'my-plugin-default',
			'title'   => __( 'Default' ),
			'content' => 'This is where I would provide tabbed help to the user on how everything in my admin panel works. Formatted HTML works fine in here too'
		) );
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
		return apply_filters( $this->prefix( 'collect_module_summary_data' ), array() );
	}

	/**
	 * @uses nonce
	 * @param string $sWizardSlug
	 * @return string
	 */
	public function getUrl_Wizard( $sWizardSlug ) {
		$aDef = $this->getWizardDefinition( $sWizardSlug );
		if ( empty( $aDef[ 'min_user_permissions' ] ) ) { // i.e. no login/minimum perms
			$sUrl = $this->loadWp()->getHomeUrl();
		}
		else {
			$sUrl = $this->loadWp()->getUrl_WpAdmin( 'admin.php' );
		}

		return add_query_arg(
			array(
				'page'          => $this->getModSlug(),
				'shield_action' => 'wizard',
				'wizard'        => $sWizardSlug,
				'nonwizard'     => wp_create_nonce( 'wizard'.$sWizardSlug )
			),
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
		return is_array( $aW ) ? $aW : array();
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
			$sTemplate = 'snippets/options_form.php';
		}
		else {
			$sTemplate = 'subfeature-access_restricted';
		}

		// Get the same Base Data as normal display
		try {
			return $this->loadRenderer( self::getConn()->getPath_Templates() )
						->setTemplate( $sTemplate )
						->setRenderVars( $this->getBaseDisplayData( true ) )
						->render();
		}
		catch ( Exception $oE ) {
			return 'Error rendering options form';
		}
	}

	/**
	 * @return bool
	 */
	protected function canDisplayOptionsForm() {
		return $this->getOptionsVo()->isAccessRestricted() ? self::getConn()
																 ->getHasPermissionToView() : true;
	}

	/**
	 * @return bool
	 */
	public function canRunWizards() {
		return $this->loadDP()->getPhpVersionIsAtLeast( '5.4.0' );
	}

	/**
	 * Override this with custom JS vars for your particular module.
	 */
	public function insertCustomJsVars() {
	}

	/**
	 * @param array  $aData
	 * @param string $sSubView
	 */
	protected function display( $aData = array(), $sSubView = '' ) {
	}

	/**
	 * @param array $aData
	 * @return string
	 * @throws Exception
	 */
	public function renderAdminNotice( $aData ) {
		if ( empty( $aData[ 'notice_attributes' ] ) ) {
			throw new Exception( 'notice_attributes is empty' );
		}

		if ( !isset( $aData[ 'icwp_admin_notice_template' ] ) ) {
			$aData[ 'icwp_admin_notice_template' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		}

		if ( !isset( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ] = array();
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

		return $this->renderTemplate( 'notices/admin-notice-template', $aData );
	}

	/**
	 * @param string $sTemplate
	 * @param array  $aData
	 * @param bool   $bUseTwig
	 * @return string
	 */
	public function renderTemplate( $sTemplate, $aData = array(), $bUseTwig = false ) {
		if ( empty( $aData[ 'unique_render_id' ] ) ) {
			$aData[ 'unique_render_id' ] = substr( md5( mt_rand() ), 0, 5 );
		}
		try {
			$oRndr = $this->loadRenderer( self::getConn()->getPath_Templates() );
			if ( $bUseTwig ) {
				$oRndr->setTemplateEngineTwig();
			}

			$sOutput = $oRndr->setTemplate( $sTemplate )
							 ->setRenderVars( $aData )
							 ->render();
		}
		catch ( Exception $oE ) {
			$sOutput = $oE->getMessage();
			error_log( $oE->getMessage() );
		}

		return $sOutput;
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	static public function getConn() {
		return self::$oPluginController;
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	static public function getController() {
		return self::getConn();
	}

	/**
	 * @param array $aTransferableOptions
	 * @return array
	 */
	public function exportTransferableOptions( $aTransferableOptions ) {
		if ( !is_array( $aTransferableOptions ) ) {
			$aTransferableOptions = array();
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

	/** Help Video options */

	/**
	 * @return array
	 */
	protected function getHelpVideoOptions() {
		$aOptions = $this->getOpt( 'help_video_options', array() );
		if ( is_null( $aOptions ) || !is_array( $aOptions ) ) {
			$aOptions = array(
				'closed'    => false,
				'displayed' => false,
				'played'    => false,
			);
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
		return $this->getDefinition( 'help_video_id' );
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
		return !empty( $sId );
	}

	/**
	 * @return $this
	 */
	protected function resetHelpVideoOptions() {
		return $this->setOpt( 'help_video_options', array() );
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
		return $this->setOpt( $sOpt, is_null( $nAt ) ? $this->loadDP()->time() : max( 0, (int)$nAt ) );
	}

	/**
	 * @deprecated since 6.9
	 * @return string
	 */
	public function getFeatureSlug() {
		return $this->getSlug();
	}

	/**
	 * @deprecated since 6.9
	 * @param $oUser WP_User
	 * @return ICWP_UserMeta
	 */
	public function getUserMeta( $oUser ) {
		return $this->loadWpUsers()->metaVoForUser( $this->prefix(), $oUser->ID );
	}

	/**
	 * @deprecated since 6.9
	 * @return ICWP_UserMeta
	 */
	public function getCurrentUserMeta() {
		return $this->loadWpUsers()->metaVoForUser( $this->prefix() );
	}
}