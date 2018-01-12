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
	protected $sFeatureSlug;

	/**
	 * @var boolean
	 */
	protected static $bForceOffFileExists;

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
	 * @param array                       $aFeatureProperties
	 * @throws Exception
	 */
	public function __construct( $oPluginController, $aFeatureProperties = array() ) {
		if ( empty( $oPluginController ) ) {
			throw new Exception();
		}
		else if ( empty( self::$oPluginController ) ) {
			self::$oPluginController = $oPluginController;
		}

		if ( isset( $aFeatureProperties[ 'storage_key' ] ) ) {
			$this->sOptionsStoreKey = $aFeatureProperties[ 'storage_key' ];
		}

		if ( isset( $aFeatureProperties[ 'slug' ] ) ) {
			$this->sFeatureSlug = $aFeatureProperties[ 'slug' ];
		}

		// before proceeding, we must now test the system meets the minimum requirements.
		if ( $this->getModuleMeetRequirements() ) {

			$nRunPriority = isset( $aFeatureProperties[ 'load_priority' ] ) ? $aFeatureProperties[ 'load_priority' ] : 100;
			// Handle any upgrades as necessary (only go near this if it's the admin area)
			add_action( $this->prefix( 'run_processors' ), array( $this, 'onRunProcessors' ), $nRunPriority );
			add_action( 'init', array( $this, 'onWpInit' ), 1 );
			add_action( $this->prefix( 'import_options' ), array( $this, 'processImportOptions' ) );
			add_action( $this->prefix( 'form_submit' ), array( $this, 'handleFormSubmit' ) );
			add_filter( $this->prefix( 'filter_plugin_submenu_items' ), array( $this, 'filter_addPluginSubMenuItem' ) );
			add_filter( $this->prefix( 'get_feature_summary_data' ), array( $this, 'filter_getFeatureSummaryData' ) );
			add_action( $this->prefix( 'plugin_shutdown' ), array( $this, 'action_doFeatureShutdown' ) );
			add_action( $this->prefix( 'delete_plugin' ), array( $this, 'deletePluginOptions' ) );
			add_filter( $this->prefix( 'aggregate_all_plugin_options' ), array( $this, 'aggregateOptionsValues' ) );

			add_filter( $this->prefix( 'register_admin_notices' ), array( $this, 'fRegisterAdminNotices' ) );
			add_filter( $this->prefix( 'gather_options_for_export' ), array( $this, 'exportTransferableOptions' ) );

			$this->doPostConstruction();
		}
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
	protected function hasValidPremiumLicense() {
		return apply_filters( $this->getPremiumLicenseFilterName(), false );
	}

	/**
	 * @return string
	 */
	protected function getPremiumLicenseFilterName() {
		return $this->prefix( 'license_is_valid'.self::getConn()->getUniqueRequestId( false ) );
	}

	/**
	 * @return bool
	 */
	protected function verifyModuleMeetRequirements() {
		$bMeetsReqs = true;

		$aPhpReqs = $this->getOptionsVo()->getFeatureRequirement( 'php' );
		if ( !empty( $aPhpReqs ) ) {

			if ( !empty( $aPhpReqs[ 'version' ] ) ) {
				$bMeetsReqs = $bMeetsReqs && $this->loadDataProcessor()
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

		if ( $this->getIsMainFeatureEnabled() && $this->isReadyToExecute() ) {
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
		if ( $this->loadDataProcessor()->FetchGet( 'icwp_shield_import' ) == 1 ) {
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
		$oProcessor = $this->getProcessor();
		return ( is_object( $oProcessor ) && $oProcessor instanceof ICWP_WPSF_Processor_Base )
			   && !self::getConn()->getIfForceOffActive();
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->run();
	}

	/**
	 * A action added to WordPress 'init' hook
	 */
	public function onWpInit() {
		$this->runWizards();
		$this->updateHandler();
		$this->setupAjaxHandlers();
	}

	/**
	 * Override this and adapt per feature
	 * @return ICWP_WPSF_Processor_Base
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			include_once( self::getConn()
							  ->getPath_SourceFile( sprintf( 'processors%s%s.php', DIRECTORY_SEPARATOR, $this->getFeatureSlug() ) ) );
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
			   str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getFeatureSlug() ) ) );
	}

	/**
	 * Override this and adapt per feature
	 * @return string
	 */
	protected function getWizardClassName() {
		return ucwords( self::getConn()->getOptionStoragePrefix() ).'Wizard_'.
			   str_replace( ' ', '', ucwords( str_replace( '_', ' ', $this->getFeatureSlug() ) ) );
	}

	/**
	 * @return ICWP_WPSF_OptionsVO
	 */
	protected function getOptionsVo() {
		if ( !isset( $this->oOptions ) ) {
			$oCon = self::getConn();
			$this->oOptions = ICWP_WPSF_Factory::OptionsVo();
			$this->oOptions
				->setPathToConfig( $oCon->getPath_ConfigFile( $this->getFeatureSlug() ) )
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
		return $this->loadFeatureProcessor();
	}

	/**
	 * @return string
	 */
	public function getUrl_AdminPage() {
		return $this->loadWp()
					->getUrl_AdminPage(
						$this->prefix( $this->getFeatureSlug() ),
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
		return $this->setOpt( 'enable_'.$this->getFeatureSlug(), $bEnable ? 'Y' : 'N' );
	}

	/**
	 * @return mixed
	 */
	public function getIsMainFeatureEnabled() {
		if ( apply_filters( $this->prefix( 'globally_disabled' ), false ) ) {
			return false;
		}

		$bEnabled =
			$this->getOptIs( 'enable_'.$this->getFeatureSlug(), 'Y' )
			|| $this->getOptIs( 'enable_'.$this->getFeatureSlug(), true, true )
			|| ( $this->getOptionsVo()->getFeatureProperty( 'auto_enabled' ) === true );
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
	 * @return string
	 */
	public function getFeatureSlug() {
		if ( !isset( $this->sFeatureSlug ) ) {
			$this->sFeatureSlug = $this->getOptionsVo()->getFeatureProperty( 'slug' );
		}
		return $this->sFeatureSlug;
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
				   ->getRootDir().'resources'.DIRECTORY_SEPARATOR.ltrim( $sSourceFile, DIRECTORY_SEPARATOR );
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	public function filter_addPluginSubMenuItem( $aItems ) {
		$sMenuTitleName = $this->getOptionsVo()->getFeatureProperty( 'menu_title' );
		if ( is_null( $sMenuTitleName ) ) {
			$sMenuTitleName = $this->getMainFeatureName();
		}
		if ( $this->getIfShowFeatureMenuItem() && !empty( $sMenuTitleName ) ) {

			$sHumanName = self::getConn()->getHumanName();

			$bMenuHighlighted = $this->getOptionsVo()->getFeatureProperty( 'highlight_menu_item' );
			if ( $bMenuHighlighted ) {
				$sMenuTitleName = sprintf( '<span class="icwp_highlighted">%s</span>', $sMenuTitleName );
			}
			$sMenuPageTitle = $sMenuTitleName.' - '.$sHumanName;
			$aItems[ $sMenuPageTitle ] = array(
				$sMenuTitleName,
				$this->prefix( $this->getFeatureSlug() ),
				array( $this, 'displayFeatureConfigPage' )
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
	public function filter_getFeatureSummaryData( $aSummaryData ) {
		if ( !$this->getIfShowSummaryItem() ) {
			return $aSummaryData;
		}

		$sMenuTitle = $this->getOptionsVo()->getFeatureProperty( 'menu_title' );
		$aSummary = array(
			'enabled'    => $this->getIsMainFeatureEnabled(),
			'active'     => self::$sActivelyDisplayedModuleOptions == $this->getFeatureSlug(),
			'slug'       => $this->getFeatureSlug(),
			'name'       => $this->getMainFeatureName(),
			'menu_title' => empty( $sMenuTitle ) ? $this->getMainFeatureName() : $sMenuTitle,
			'href'       => network_admin_url( 'admin.php?page='.$this->prefix( $this->getFeatureSlug() ) ),
		);
		$aSummary[ 'content' ] = $this->renderTemplate( 'snippets/summary_single', $aSummary );

		$aSummaryData[] = $aSummary;
		return $aSummaryData;
	}

	/**
	 * @return boolean
	 */
	public function getIfShowFeatureMenuItem() {
		return $this->getOptionsVo()->getFeatureProperty( 'show_feature_menu_item' );
	}

	/**
	 * @return boolean
	 */
	public function getIfShowSummaryItem() {
		return $this->getIfShowFeatureMenuItem() && !$this->getOptionsVo()->getFeatureProperty( 'hide_summary' );
	}

	/**
	 * @return boolean
	 */
	public function getIfUseSessions() {
		return $this->getOptionsVo()->getFeatureProperty( 'use_sessions' );
	}

	/**
	 * @param string $sDefinitionKey
	 * @return mixed|null
	 */
	public function getDefinition( $sDefinitionKey ) {
		return $this->getOptionsVo()->getFeatureDefinition( $sDefinitionKey );
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
	public function setLastErrors( $mErrors ) {
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

	protected function setupAjaxHandlers() {
		$bAdminRun = false;

		if ( $this->isValidAjaxRequestForModule() ) { // TODO replicate all this for the backend
			$this->frontEndAjaxHandlers();
			$this->adminAjaxHandlers();
			$bAdminRun = true;
//			$this->sendAjaxResponse( false, array( 'message' => 'Failed Ajax Nonce' ) );
		}

		if ( !$bAdminRun && $this->loadWp()->isAjax() ) { //TODO: isValidAjaxRequest()
			if ( is_admin() || is_network_admin() ) {
				$this->adminAjaxHandlers();
			}
		}
	}

	/**
	 * A valid Ajax request must have all the icwp items as posted with getBaseAjaxActionRenderData()
	 * Note: Also performs nonce checking
	 * @return bool
	 */
	protected function isValidAjaxRequestForModule() {
		$oDp = $this->loadDataProcessor();

		$bValid = $this->loadWp()->isAjax()
				  && ( $this->prefix( $this->getFeatureSlug() ) == $oDp->post( 'icwp_action_module', '' ) );
		if ( $bValid ) {
			$aItems = array_keys( $this->getBaseAjaxActionRenderData() );
			foreach ( $aItems as $sKey ) {
				if ( strpos( $sKey, 'icwp_' ) === 0 ) {
					$bValid = $bValid && ( strlen( $oDp->post( $sKey, '' ) ) > 0 );
				}
			}
		}
		return $bValid && $this->checkNonceAction( $oDp->post( 'icwp_nonce' ), $oDp->post( 'icwp_nonce_action' ) );
	}

	/**
	 * @param string $sAction
	 * @param bool   $bAsJsonEncodedObject
	 * @return array
	 */
	public function getBaseAjaxActionRenderData( $sAction = '', $bAsJsonEncodedObject = false ) {
		$aData = array(
			'action'             => $this->prefix( $sAction ), //wp ajax doesn't work without this.
			'icwp_ajax_action'   => $this->prefix( $sAction ),
			'icwp_nonce'         => $this->genNonce( $sAction ),
			'icwp_nonce_action'  => $sAction,
			'icwp_action_module' => $this->prefix( $this->getFeatureSlug() ),
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
		);
		return $bAsJsonEncodedObject ? json_encode( (object)$aData ) : $aData;
	}

	protected function adminAjaxHandlers() {
		add_action( 'wp_ajax_icwp_OptionsFormSave', array( $this, 'ajaxOptionsFormSave' ) );

		// TODO: move this to the wizard handler itself
		if ( $this->getCanRunWizards() && $this->hasWizard() ) {
			$oWiz = $this->getWizardHandler();
			if ( !is_null( $oWiz ) ) {
				add_action( $this->prefixWpAjax( 'WizardProcessStepSubmit' ), array(
					$oWiz,
					'ajaxWizardProcessStepSubmit'
				) );
				add_action( $this->prefixWpAjax( 'WizardRenderStep' ), array(
					$oWiz,
					'ajaxWizardRenderStep'
				) );
			}
		}
	}

	protected function frontEndAjaxHandlers() {
	}

	/**
	 * @param string $sAction
	 * @return string
	 */
	protected function genNonce( $sAction = '' ) {
		return wp_create_nonce( $this->prefix( $sAction ) );
	}

	/**
	 * @param string $sNonce
	 * @param string $sAction
	 * @return bool
	 */
	protected function checkNonceAction( $sNonce, $sAction = '' ) {
		return wp_verify_nonce( $sNonce, $this->prefix( $sAction ) );
	}

	/**
	 * Will send ajax error response immediately upon failure
	 * @return bool
	 */
	protected function checkAjaxNonce() {

		$sNonce = $this->loadDataProcessor()->FetchRequest( '_ajax_nonce', '' );
		if ( empty( $sNonce ) ) {
			$sMessage = $this->getTranslatedString( 'nonce_failed_empty', 'Nonce security checking failed - the nonce value was empty.' );
		}
		else if ( wp_verify_nonce( $sNonce, 'icwp_ajax' ) === false ) {
			$sMessage = $this->getTranslatedString( 'nonce_failed_supplied', 'Nonce security checking failed - the nonce supplied was "%s".' );
			$sMessage = sprintf( $sMessage, $sNonce );
		}
		else {
			return true; // At this stage we passed the nonce check
		}

		// At this stage we haven't returned after success so we failed the nonce check
		$this->sendAjaxResponse( false, array( 'message' => $sMessage ) );
		return false; //unreachable
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
			include_once( self::getConn()->getPath_SourceFile( sprintf( 'wizards/%s.php', $this->getFeatureSlug() ) ) );
			$sClassName = $this->getWizardClassName();
			if ( !class_exists( $sClassName, false ) ) {
				return null;
			}
			$this->oWizard = new $sClassName( $this );
		}
		return $this->oWizard;
	}

	/**
	 * @param       $bSuccess
	 * @param array $aData
	 */
	public function sendAjaxResponse( $bSuccess, $aData = array() ) {
		$bSuccess ? wp_send_json_success( $aData ) : wp_send_json_error( $aData );
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

		$aOptions = $this->getOptionsVo()->getOptionsForPluginUse();
		foreach ( $aOptions as $nSectionKey => $aSection ) {

			if ( !empty( $aSection[ 'options' ] ) ) {

				foreach ( $aSection[ 'options' ] as $nKey => $aOptionParams ) {
					$bIsPrem = isset( $aOptionParams[ 'premium' ] ) && $aOptionParams[ 'premium' ];
					if ( !$bIsPrem || $bPremiumEnabled ) {
						$aSection[ 'options' ][ $nKey ] = $this->buildOptionForUi( $aOptionParams );
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
			}
		}

		return $aOptions;
	}

	/**
	 * @param array $aOptParams
	 * @return array
	 */
	protected function buildOptionForUi( $aOptParams ) {

		$mCurrentVal = $aOptParams[ 'value' ];

		switch ( $aOptParams[ 'type' ] ) {

			case 'password':
				if ( !empty( $mCurrentVal ) ) {
					$mCurrentVal = '';
				}
				break;

			case 'array':

				if ( empty( $mCurrentVal ) || !is_array( $mCurrentVal ) ) {
					$mCurrentVal = array();
				}

				$aOptParams[ 'rows' ] = count( $mCurrentVal ) + 2;
				$mCurrentVal = implode( "\n", $mCurrentVal );

				break;

			case 'comma_separated_lists':

				$aNewValues = array();
				if ( !empty( $mCurrentVal ) && is_array( $mCurrentVal ) ) {

					foreach ( $mCurrentVal as $sPage => $aParams ) {
						$aNewValues[] = $sPage.', '.implode( ", ", $aParams );
					}
				}
				$aOptParams[ 'rows' ] = count( $aNewValues ) + 1;
				$mCurrentVal = implode( "\n", $aNewValues );

				break;

			case 'multiple_select':
				if ( !is_array( $mCurrentVal ) ) {
					$mCurrentVal = array();
				}
				break;

			case 'text':
				$mCurrentVal = stripslashes( $this->getTextOpt( $aOptParams[ 'key' ] ) );
				break;
		}

		$aOptParams[ 'value' ] = is_scalar( $mCurrentVal ) ? esc_attr( $mCurrentVal ) : $mCurrentVal;
		$aOptParams[ 'disabled' ] = !$this->isPremium() && ( isset( $aOptParams[ 'premium' ] ) && $aOptParams[ 'premium' ] );
		$aOptParams[ 'enabled' ] = !$aOptParams[ 'disabled' ];
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

	public function ajaxOptionsFormSave() {

		$sProcessingModule = $this->loadDataProcessor()->FetchPost( $this->prefixOptionKey( 'feature_slug' ) );
		if ( $this->getFeatureSlug() != $sProcessingModule ) {
			return;
		}

		$oCon = self::getConn();
		$bSuccess = false;
		$sName = $oCon->getHumanName();
		$sMessage = sprintf( _wpsf__( 'Failed up to update %s plugin options.' ), $sName );

		if ( $oCon->getIsValidAdminArea() ) {
			$bSuccess = $this->handleFormSubmit();
			if ( $bSuccess ) {
				$sMessage = sprintf( _wpsf__( '%s Plugin options updated successfully.' ), $sName );
			}
		}
		else {
			$sMessage = sprintf( _wpsf__( 'Failed to update %s options as you are not authenticated with %s as a Security Admin.' ), $sName, $sName );
		}

		$this->sendAjaxResponse(
			$bSuccess,
			array(
				'options_form' => $this->renderOptionsForm(),
				'message'      => $sMessage
			)
		);
	}

	/**
	 * @return bool
	 */
	public function handleFormSubmit() {
		$bVerified = $this->verifyFormSubmit();
		if ( $bVerified ) {
			$this->doSaveStandardOptions();
			$this->doExtraSubmitProcessing();
		}
		return $bVerified;
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
		$sAllOptions = $this->loadDataProcessor()
							->FetchPost( $this->prefixOptionKey( 'all_options_input' ) );

		if ( !empty( $sAllOptions ) ) {
			$this->updatePluginOptionsFromSubmit( $sAllOptions );
		}
	}

	protected function doExtraSubmitProcessing() {
	}

	/**
	 * @return bool
	 */
	public function isPremium() {
		return $this->hasValidPremiumLicense();
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
	 * @param string $sAllOptionsInput - comma separated list of all the input keys to be processed from the $_POST
	 * @return void
	 */
	public function updatePluginOptionsFromSubmit( $sAllOptionsInput ) {
		if ( empty( $sAllOptionsInput ) ) {
			return;
		}
		$oDp = $this->loadDataProcessor();

		$aAllInputOptions = explode( self::CollateSeparator, $sAllOptionsInput );
		foreach ( $aAllInputOptions as $sInputKey ) {
			$aInput = explode( ':', $sInputKey );
			list( $sOptionType, $sOptionKey ) = $aInput;

			$sOptionValue = $oDp->FetchPost( $this->prefixOptionKey( $sOptionKey ) );
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
				else if ( $sOptionType == 'email' && !$oDp->validEmail( $sOptionValue ) ) {
					$sOptionValue = '';
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
	}

	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function updateHandler() {
	}

	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function runWizards() {
		if ( $this->getCanRunWizards() && $this->isWizardPage() && $this->hasWizard() ) {
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
		return $this->loadDP()->query( 'page' ) == $this->prefix( $this->getFeatureSlug() );
	}

	/**
	 * @return bool
	 */
	protected function isWizardPage() {
		return ( $this->loadDP()->query( 'shield_action' ) == 'wizard' && $this->isModulePage() );
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
		return self::getConn()->doPluginPrefix( $sSuffix, $sGlue );
	}

	/**
	 * @param string $sSuffix
	 * @return string
	 */
	public function prefixWpAjax( $sSuffix = '' ) {
		return sprintf( '%s%s',
			'wp_ajax_',
			$this->prefix( $sSuffix )
		);
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
	public function displayFeatureConfigPage() {
		if ( $this->canDisplayOptionsForm() ) {
			$this->displayModulePage();
		}
		else {
			$this->displayRestrictedPage();
		}
	}

	/**
	 * Override this to customize anything with the display of the page
	 */
	protected function displayModulePage() {
		$this->display();
	}

	protected function displayRestrictedPage() {
		$this->display(
			array( 'flags' => array( 'show_summary' => false ) ),
			'subfeature-access_restricted.php'
		);
	}

	/**
	 * @return array
	 */
	protected function getBaseDisplayData() {
		$oCon = self::getConn();
		self::$sActivelyDisplayedModuleOptions = $this->getFeatureSlug();

		$aData = array(
			'var_prefix'      => $oCon->getOptionStoragePrefix(),
			'sPluginName'     => $oCon->getHumanName(),
			'sFeatureName'    => $this->getMainFeatureName(),
			'bFeatureEnabled' => $this->getIsMainFeatureEnabled(),
			'feature_slug'    => self::$sActivelyDisplayedModuleOptions,
			'sTagline'        => $this->getOptionsVo()->getFeatureTagline(),
			'nonce_field'     => wp_nonce_field( $oCon->getPluginPrefix(), '_wpnonce', true, false ), //don't echo!
			'sFeatureSlug'    => $this->prefix( $this->getFeatureSlug() ),
			'form_action'     => 'admin.php?page='.$this->prefix( $this->getFeatureSlug() ),
			'nOptionsPerRow'  => 1,
			'aPluginLabels'   => $oCon->getPluginLabels(),
			'help_video'      => array(
				'auto_show'   => $this->getIfAutoShowHelpVideo(),
				'iframe_url'  => $this->getHelpVideoUrl( $this->getHelpVideoId() ),
				'display_id'  => 'ShieldHelpVideo'.$this->getFeatureSlug(),
				'options'     => $this->getHelpVideoOptions(),
				'displayable' => $this->isHelpVideoDisplayable(),
				'show'        => $this->isHelpVideoDisplayable() && !$this->getHelpVideoHasBeenClosed(),
				'width'       => 772,
				'height'      => 454,
			),
			'sAjaxNonce'      => wp_create_nonce( 'icwp_ajax' ),

			'aSummaryData' => apply_filters( $this->prefix( 'get_feature_summary_data' ), array() ),

			'aAllOptions'       => $this->buildOptions(),
			'aHiddenOptions'    => $this->getOptionsVo()->getHiddenOptions(),
			'all_options_input' => $this->collateAllFormInputsForAllOptions(),

			'sPageTitle' => sprintf( '%s: %s', $oCon->getHumanName(), $this->getMainFeatureName() ),
			'strings'    => array(
				'go_to_settings'                    => __( 'Settings' ),
				'on'                                => __( 'On' ),
				'off'                               => __( 'Off' ),
				'more_info'                         => __( 'More Info' ),
				'blog'                              => __( 'Blog' ),
				'plugin_activated_features_summary' => __( 'Plugin Activated Features Summary:' ),
				'save_all_settings'                 => __( 'Save All Settings' ),
				'see_help_video'                    => __( 'Watch Help Video' ),
			),
			'flags'      => array(
				'show_ads'              => $this->getIsShowMarketing(),
				'show_summary'          => false,
				'wrap_page_content'     => true,
				'show_standard_options' => true,
				'show_content_actions'  => $this->hasCustomActions(),
				'show_alt_content'      => false,
				'has_wizard'            => $this->hasWizard(),
			),
			'hrefs'      => array(
				'go_pro'          => 'http://icwp.io/shieldgoprofeature',
				'img_wizard_wand' => $oCon->getPluginUrl_Image( 'wand.png' ),
				'wizard_link'     => $this->getUrl_WizardLanding(),
				'wizard_landing'  => $this->getUrl_WizardLanding(),
				'primary_wizard'  => $this->getUrl_WizardPrimary(),
			),
			'content'    => array(
				'alt'     => '',
				'actions' => $this->getContentCustomActions(),
				'help'    => $this->getContentHelp()
			)
		);
		$aData[ 'flags' ][ 'show_content_help' ] = strpos( $aData[ 'content' ][ 'help' ], 'Error:' ) !== 0;
		return $aData;
	}

	/**
	 * @return string
	 */
	protected function getContentCustomActions() {
		return '<h3 style="margin: 10px 0 100px">'._wpsf__( 'No Actions For This Module' ).'</h3>';
	}

	/**
	 * @return bool
	 */
	public function getCanRunWizards() {
		return $this->loadDP()->getPhpVersionIsAtLeast( '5.4.0' );
	}

	/**
	 * @return string
	 */
	protected function getContentHelp() {
		return $this->renderTemplate( 'snippets/module-help-'.$this->getFeatureSlug().'.php', array() );
	}

	/**
	 * @return string|null
	 */
	protected function getPrimaryWizard() {
		return $this->hasWizard() ? key( $this->getWizardDefinitions() ) : null;
	}

	/**
	 * @param string $sWizardSlug
	 * @return string
	 */
	public function getUrl_Wizard( $sWizardSlug ) {
		return add_query_arg(
			array(
				'page'          => $this->prefix( $this->getFeatureSlug() ),
				'shield_action' => 'wizard',
				'wizard'        => $sWizardSlug
			),
			$this->getUrl_AdminPage()
		);
	}

	/**
	 * @return string
	 */
	protected function getUrl_WizardLanding() {
		return $this->getUrl_Wizard( 'landing' );
	}

	/**
	 * @return string
	 */
	protected function getUrl_WizardPrimary() {
		$sPrimary = $this->getPrimaryWizard();
		return $this->getUrl_Wizard( $sPrimary );
	}

	/**
	 * @return array
	 */
	public function getWizardDefinitions() {
		$aW = $this->getDefinition( 'wizards' );
		return is_array( $aW ) ? $aW : array();
	}

	/**
	 * @return bool
	 */
	public function hasWizard() {
		return ( count( $this->getWizardDefinitions() ) > 0 );
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
	 * @throws Exception
	 */
	protected function renderOptionsForm() {

		if ( $this->canDisplayOptionsForm() ) {
			$sTemplate = 'snippets/options_form.php';
		}
		else {
			$sTemplate = 'subfeature-access_restricted';
		}

		// Get the same Base Data as normal display
		$aData = apply_filters( $this->prefix( $this->getFeatureSlug().'display_data' ), $this->getBaseDisplayData() );
		$aData[ 'strings' ] = array_merge( $aData[ 'strings' ], $this->getDisplayStrings() );
		return $this->loadRenderer( self::getConn()->getPath_Templates() )
					->setTemplate( $sTemplate )
					->setRenderVars( $aData )
					->render();
	}

	/**
	 * @return bool
	 */
	protected function canDisplayOptionsForm() {
		return $this->getOptionsVo()->isAccessRestricted() ? self::getConn()
																 ->getHasPermissionToView() : true;
	}

	/**
	 * @param array  $aData
	 * @param string $sSubView
	 */
	protected function display( $aData = array(), $sSubView = '' ) {
		$oRndr = $this->loadRenderer( self::getConn()->getPath_Templates() );
		$oDp = $this->loadDataProcessor();

		// Get Base Data
		$aData = $oDp->mergeArraysRecursive( $this->getBaseDisplayData(), $aData );
		if ( empty( $sSubView ) || !$oRndr->getTemplateExists( $sSubView ) ) {
			$sModuleView = 'feature-'.$this->getFeatureSlug();
			$sSubView = $oRndr->getTemplateExists( $sModuleView ) ? $sModuleView : 'feature-default';
		}

		$aData[ 'sFeatureInclude' ] = $this->loadDataProcessor()->addExtensionToFilePath( $sSubView, '.php' );
		$aData[ 'strings' ] = array_merge( $aData[ 'strings' ], $this->getDisplayStrings() );
		$aData[ 'options_form' ] = $this->renderOptionsForm();
		try {
			echo $oRndr
				->setTemplate( 'index.php' )
				->setRenderVars( $aData )
				->render();
		}
		catch ( Exception $oE ) {
			echo $oE->getMessage();
		}
	}

	/**
	 * @param array  $aData
	 * @param string $sSubView
	 */
	protected function displayByTemplate( $aData = array(), $sSubView = '' ) {

		$oCon = self::getConn();
		// Get Base Data
		$aData = apply_filters( $this->prefix( $this->getFeatureSlug().'display_data' ), array_merge( $this->getBaseDisplayData(), $aData ) );
		$bPermissionToView = $oCon->getHasPermissionToView();

		if ( !$bPermissionToView ) {
			$sSubView = 'subfeature-access_restricted';
		}

		if ( empty( $sSubView ) ) {
			$oWpFs = $this->loadFS();
			$sFeatureInclude = 'feature-'.$this->getFeatureSlug();
			if ( $oWpFs->exists( $oCon->getPath_TemplatesFile( $sFeatureInclude ) ) ) {
				$sSubView = $sFeatureInclude;
			}
			else {
				$sSubView = 'feature-default';
			}
		}

		$aData[ 'sFeatureInclude' ] = $sSubView;
		$aData[ 'strings' ] = array_merge( $aData[ 'strings' ], $this->getDisplayStrings() );

		echo $this->renderTemplate( 'features/'.$sSubView, $aData );
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

		if ( !isset( $aData[ 'icwp_ajax_nonce' ] ) ) {
			$aData[ 'icwp_ajax_nonce' ] = wp_create_nonce( 'icwp_ajax' );
		}
		if ( !isset( $aData[ 'icwp_admin_notice_template' ] ) ) {
			$aData[ 'icwp_admin_notice_template' ] = $aData[ 'notice_attributes' ][ 'notice_id' ];
		}

		if ( !isset( $aData[ 'notice_classes' ] ) ) {
			$aData[ 'notice_classes' ] = array();
		}
		if ( is_array( $aData[ 'notice_classes' ] ) ) {
			if ( empty( $aData[ 'notice_classes' ] ) ) {
				$aData[ 'notice_classes' ][] = 'updated';
			}
			$aData[ 'notice_classes' ][] = $aData[ 'notice_attributes' ][ 'type' ];
		}
		$aData[ 'notice_classes' ] = implode( ' ', $aData[ 'notice_classes' ] );

		return $this->renderTemplate( 'notices'.DIRECTORY_SEPARATOR.'admin-notice-template', $aData );
	}

	/**
	 * @param string $sTemplate
	 * @param array  $aData
	 * @return string
	 */
	public function renderTemplate( $sTemplate, $aData = array() ) {
		if ( empty( $aData[ 'unique_render_id' ] ) ) {
			$aData[ 'unique_render_id' ] = substr( md5( mt_rand() ), 0, 5 );
		}
		try {
			$sOutput = $this
				->loadRenderer( self::getConn()->getPath_Templates() )
				->setTemplate( $sTemplate )
				->setRenderVars( $aData )
				->render();
		}
		catch ( Exception $oE ) {
			$sOutput = $oE->getMessage();
		}

		return $sOutput;
	}

	/**
	 * @return array
	 */
	protected function getDisplayStrings() {
		return array();
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
}