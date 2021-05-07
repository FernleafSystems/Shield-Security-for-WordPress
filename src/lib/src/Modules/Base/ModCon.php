<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ModCon
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Base
 */
abstract class ModCon {

	use Modules\PluginControllerConsumer;
	use Shield\Crons\PluginCronsConsumer;

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
	 * @var Shield\Modules\Base\Processor
	 */
	private $oProcessor;

	/**
	 * @var \ICWP_WPSF_Wizard_Base
	 */
	private $oWizard;

	/**
	 * @var Shield\Modules\Base\Reporting
	 */
	private $oReporting;

	/**
	 * @var Shield\Modules\Base\UI
	 */
	private $oUI;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $opts;

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
	 * @param Shield\Controller\Controller $pluginCon
	 * @param array                        $mod
	 * @throws \Exception
	 */
	public function __construct( $pluginCon, $mod = [] ) {
		if ( !$pluginCon instanceof Shield\Controller\Controller ) {
			throw new \Exception( 'Plugin controller not supplied to Module' );
		}
		$this->setCon( $pluginCon );

		if ( empty( $mod[ 'storage_key' ] ) && empty( $mod[ 'slug' ] ) ) {
			throw new \Exception( 'Module storage key AND slug are undefined' );
		}

		$this->sOptionsStoreKey = empty( $mod[ 'storage_key' ] ) ? $mod[ 'slug' ] : $mod[ 'storage_key' ];
		if ( isset( $mod[ 'slug' ] ) ) {
			$this->sModSlug = $mod[ 'slug' ];
		}

		if ( $this->verifyModuleMeetRequirements() ) {
			$this->handleAutoPageRedirects();
			$this->setupHooks( $mod );
			$this->doPostConstruction();
		}
	}

	protected function setupHooks( array $modProps ) {
		$con = $this->getCon();
		$nRunPriority = $modProps[ 'load_priority' ] ?? 100;

		add_action( $con->prefix( 'modules_loaded' ), function () {
			$this->onModulesLoaded();
		}, $nRunPriority );
		add_action( $con->prefix( 'run_processors' ), [ $this, 'onRunProcessors' ], $nRunPriority );
		add_action( 'init', [ $this, 'onWpInit' ], 1 );

		$nMenuPri = $modProps[ 'menu_priority' ] ?? 100;
		add_filter( $con->prefix( 'submenu_items' ), [ $this, 'supplySubMenuItem' ], $nMenuPri );
		add_action( $con->prefix( 'plugin_shutdown' ), [ $this, 'onPluginShutdown' ] );
		add_action( $con->prefix( 'deactivate_plugin' ), [ $this, 'onPluginDeactivate' ] );
		add_action( $con->prefix( 'delete_plugin' ), [ $this, 'onPluginDelete' ] );
		add_filter( $con->prefix( 'aggregate_all_plugin_options' ), [ $this, 'aggregateOptionsValues' ] );

		add_filter( $con->prefix( 'register_admin_notices' ), [ $this, 'fRegisterAdminNotices' ] );

		if ( is_admin() || is_network_admin() ) {
			$this->loadAdminNotices();
		}

		if ( $this->getOptions()->getDef( 'rest_api' ) ) {
			add_action( 'rest_api_init', function () {
				try {
					$this->getRestHandler()->init();
				}
				catch ( \Exception $e ) {
				}
			} );
		}

//		if ( $this->isAdminOptionsPage() ) {
//			add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
//		}
		$this->setupCronHooks();
		$this->setupCustomHooks();
	}

	protected function setupCustomHooks() {
	}

	protected function doPostConstruction() {
	}

	public function runDailyCron() {
		$this->cleanupDatabases();
	}

	protected function cleanupDatabases() {
		foreach ( $this->getDbHandlers( true ) as $dbh ) {
			if ( $dbh instanceof Shield\Databases\Base\Handler && $dbh->isReady() ) {
				$dbh->autoCleanDb();
			}
		}
	}

	/**
	 * @param bool $bInitAll
	 * @return Shield\Databases\Base\Handler[]
	 */
	protected function getDbHandlers( $bInitAll = false ) {
		if ( $bInitAll ) {
			foreach ( $this->getAllDbClasses() as $dbSlug => $dbClass ) {
				$this->getDbH( $dbSlug );
			}
		}
		return is_array( $this->aDbHandlers ) ? $this->aDbHandlers : [];
	}

	/**
	 * @param string $dbhKey
	 * @return Shield\Databases\Base\Handler|mixed|false
	 */
	protected function getDbH( $dbhKey ) {
		$dbh = false;

		if ( !is_array( $this->aDbHandlers ) ) {
			$this->aDbHandlers = [];
		}

		if ( !empty( $this->aDbHandlers[ $dbhKey ] ) ) {
			$dbh = $this->aDbHandlers[ $dbhKey ];
		}
		else {
			$aDbClasses = $this->getAllDbClasses();
			if ( isset( $aDbClasses[ $dbhKey ] ) ) {
				/** @var Shield\Databases\Base\Handler $dbh */
				$dbh = new $aDbClasses[ $dbhKey ]( $dbhKey );
				try {
					// TODO remove 10.3: method_exists + table init
					if ( method_exists( $dbh, 'execute' ) ) {
						$dbh->setMod( $this )->execute();
					}
					else {
						$dbh->setMod( $this )->tableInit();
					}
				}
				catch ( \Exception $e ) {
				}
			}
			$this->aDbHandlers[ $dbhKey ] = $dbh;
		}

		return $dbh;
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
		return $this->loadModElement( 'Upgrade' );
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

	private function verifyModuleMeetRequirements() :bool {
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
		$opts = $this->getOptions();
		if ( $opts->getFeatureProperty( 'auto_load_processor' ) ) {
			$this->loadProcessor();
		}
		try {
			$bSkip = (bool)$opts->getFeatureProperty( 'skip_processor' );
			if ( !$bSkip && !$this->isUpgrading() && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->doExecuteProcessor();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return !is_null( $this->getProcessor() );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpInit() {

		$shieldAction = $this->getCon()->getShieldAction();
		if ( !empty( $shieldAction ) ) {
			do_action( $this->getCon()->prefix( 'shield_action' ), $shieldAction );
		}

		add_action( 'cli_init', function () {
			try {
				$this->getWpCli()->execute();
			}
			catch ( \Exception $e ) {
			}
		} );

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
				catch ( \Exception $e ) {
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

		$this->loadDebug();
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
	 * @return Shield\Modules\Base\Processor|mixed
	 */
	protected function loadProcessor() {
		if ( !isset( $this->oProcessor ) ) {
			try {
				$class = $this->findElementClass( 'Processor', true );
			}
			catch ( \Exception $e ) {
				return null;
			}
			$this->oProcessor = new $class( $this );
		}
		return $this->oProcessor;
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

	public function isUpgrading() :bool {
		return $this->getCon()->cfg->rebuilt || $this->getOptions()->getRebuildFromFile();
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

	public function getOptionsStorageKey() :string {
		return $this->getCon()->prefixOption( $this->sOptionsStoreKey ).'_options';
	}

	/**
	 * @return Shield\Modules\Base\Processor|\FernleafSystems\Utilities\Logic\ExecOnce|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	public function getUrl_AdminPage() :string {
		return Services::WpGeneral()
					   ->getUrl_AdminPage(
						   $this->getModSlug(),
						   $this->getCon()->getIsWpmsNetworkAdminOnly()
					   );
	}

	public function buildAdminActionNonceUrl( string $action ) :string {
		$nonce = $this->getNonceActionData( $action );
		$nonce[ 'ts' ] = Services::Request()->ts();
		return add_query_arg( $nonce, $this->getUrl_AdminPage() );
	}

	protected function getModActionParams( string $action ) :array {
		$con = $this->getCon();
		return [
			'action'     => $con->prefix(),
			'exec'       => $action,
			'mod_slug'   => $this->getModSlug(),
			'ts'         => Services::Request()->ts(),
			'exec_nonce' => substr(
				hash_hmac( 'md5', $action.Services::Request()->ts(), $con->getSiteInstallationId() ), 0, 6
			)
		];
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function verifyModActionRequest() :bool {
		$valid = false;

		$con = $this->getCon();
		$req = Services::Request();

		$exec = $req->request( 'exec' );
		if ( !empty( $exec ) && $req->request( 'action' ) == $con->prefix() ) {


			if ( wp_verify_nonce( $req->request( 'exec_nonce' ), $exec ) && $con->getMeetsBasePermissions() ) {
				$valid = true;
			}
			else {
				$valid = $req->request( 'exec_nonce' ) ===
						 substr( hash_hmac( 'md5', $exec.$req->request( 'ts' ), $con->getSiteInstallationId() ), 0, 6 );
			}
			if ( !$valid ) {
				throw new \Exception( 'Invalid request' );
			}
		}

		return $valid;
	}

	public function getUrl_DirectLinkToOption( string $key ) :string {
		$url = $this->getUrl_AdminPage();
		$def = $this->getOptions()->getOptDefinition( $key );
		if ( !empty( $def[ 'section' ] ) ) {
			$url = $this->getUrl_DirectLinkToSection( $def[ 'section' ] );
		}
		return $url;
	}

	public function getUrl_DirectLinkToSection( string $section ) :string {
		if ( $section == 'primary' ) {
			$section = $this->getOptions()->getPrimarySection()[ 'slug' ];
		}
		return $this->getUrl_AdminPage().'#tab-'.$section;
	}

	/**
	 * TODO: Get rid of this crap and/or handle the \Exception thrown in loadFeatureHandler()
	 * @return Modules\Email\ModCon
	 * @throws \Exception
	 * @deprecated 10.2
	 */
	public function getEmailHandler() {
		return $this->getCon()->getModule_Email();
	}

	/**
	 * @return Modules\Email\Processor
	 */
	public function getEmailProcessor() {
		return $this->getEmailHandler()->getProcessor();
	}

	/**
	 * @param bool $enable
	 * @return $this
	 */
	public function setIsMainFeatureEnabled( bool $enable ) {
		$this->getOptions()->setOpt( 'enable_'.$this->getSlug(), $enable ? 'Y' : 'N' );
		return $this;
	}

	public function isModuleEnabled() :bool {
		/** @var Shield\Modules\Plugin\Options $pluginOpts */
		$pluginOpts = $this->getCon()->getModule_Plugin()->getOptions();

		if ( $this->getOptions()->getFeatureProperty( 'auto_enabled' ) === true ) {
			// Auto enabled modules always run regardless
			$enabled = true;
		}
		elseif ( $pluginOpts->isPluginGloballyDisabled() ) {
			$enabled = false;
		}
		elseif ( $this->getCon()->getIfForceOffActive() ) {
			$enabled = false;
		}
		elseif ( $this->getOptions()->getFeatureProperty( 'premium' ) === true
				 && !$this->isPremium() ) {
			$enabled = false;
		}
		else {
			$enabled = $this->isModOptEnabled();
		}

		return $enabled;
	}

	public function isModOptEnabled() :bool {
		$opts = $this->getOptions();
		return $opts->isOpt( $this->getEnableModOptKey(), 'Y' )
			   || $opts->isOpt( $this->getEnableModOptKey(), true, true );
	}

	public function getEnableModOptKey() :string {
		return 'enable_'.$this->getSlug();
	}

	public function getMainFeatureName() :string {
		return __( $this->getOptions()->getFeatureProperty( 'name' ), 'wp-simple-firewall' );
	}

	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? $this->prefix( $this->getSlug() ) : $this->getSlug();
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
	 * @param array $items
	 * @return array
	 */
	public function supplySubMenuItem( $items ) {

		$title = $this->getOptions()->getFeatureProperty( 'menu_title' );
		$title = empty( $title ) ? $this->getMainFeatureName() : __( $title, 'wp-simple-firewall' );

		if ( !empty( $title ) ) {
			$highlightedTemplate = '<span class="icwp_highlighted">%s</span>';
			$humanName = $this->getCon()->getHumanName();

			if ( $this->getOptions()->getFeatureProperty( 'highlight_menu_item' ) ) {
				$title = sprintf( $highlightedTemplate, $title );
			}

			$menuPageTitle = $title.' - '.$humanName;
			$items[ $menuPageTitle ] = [
				$title,
				$this->getModSlug(),
				[ $this, 'displayModuleAdminPage' ],
				$this->getIfShowModuleMenuItem()
			];

			foreach ( $this->getOptions()->getAdditionalMenuItems() as $menuItem ) {

				// special case: don't show go pro if you're pro.
				if ( $menuItem[ 'slug' ] !== 'pro-redirect' || !$this->isPremium() ) {

					$title = __( $menuItem[ 'title' ], 'wp-simple-firewall' );
					$menuPageTitle = $humanName.' - '.$title;
					$isHighlighted = $menuItem[ 'highlight' ] ?? false;
					$items[ $menuPageTitle ] = [
						$isHighlighted ? sprintf( $highlightedTemplate, $title ) : $title,
						$this->prefix( $menuItem[ 'slug' ] ),
						[ $this, $menuItem[ 'callback' ] ?? '' ],
						true
					];
				}
			}
		}
		return $items;
	}

	/**
	 * Handles the case where we want to redirect certain menu requests to other pages
	 * of the plugin automatically. It lets us create custom menu items.
	 * This can of course be extended for any other types of redirect.
	 */
	public function handleAutoPageRedirects() {
		$cfg = $this->getOptions()->getRawData_FullFeatureConfig();
		if ( !empty( $cfg[ 'custom_redirects' ] ) && $this->getCon()->isValidAdminArea() ) {
			foreach ( $cfg[ 'custom_redirects' ] as $redirect ) {
				if ( Services::Request()->query( 'page' ) == $this->prefix( $redirect[ 'source_mod_page' ] ) ) {
					Services::Response()->redirect(
						$this->getCon()->getModule( $redirect[ 'target_mod_page' ] )->getUrl_AdminPage(),
						$redirect[ 'query_args' ],
						true,
						false
					);
				}
			}
		}
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

	public function buildSummaryData() :array {
		$opts = $this->getOptions();
		$menuTitle = $opts->getFeatureProperty( 'menu_title' );

		$sections = $opts->getSections();
		foreach ( $sections as $slug => $section ) {
			try {
				$strings = $this->getStrings()->getSectionStrings( $section[ 'slug' ] );
				foreach ( $strings as $key => $val ) {
					unset( $section[ $key ] );
					$section[ $key ] = $val;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$summary = [
			'slug'          => $this->getSlug(),
			'enabled'       => $this->getUIHandler()->isEnabledForUiSummary(),
			'active'        => $this->isThisModulePage() || $this->isPage_InsightsThisModule(),
			'name'          => $this->getMainFeatureName(),
			'sidebar_name'  => $opts->getFeatureProperty( 'sidebar_name' ),
			'menu_title'    => empty( $menuTitle ) ? $this->getMainFeatureName() : __( $menuTitle, 'wp-simple-firewall' ),
			'href'          => network_admin_url( 'admin.php?page='.$this->getModSlug() ),
			'sections'      => $sections,
			'options'       => [],
			'show_mod_opts' => $this->getIfShowModuleOpts(),
		];

		foreach ( $opts->getVisibleOptionsKeys() as $optKey ) {
			try {
				$optData = $this->getStrings()->getOptionStrings( $optKey );
				$optData[ 'href' ] = $this->getUrl_DirectLinkToOption( $optKey );
				$summary[ 'options' ][ $optKey ] = $optData;
			}
			catch ( \Exception $e ) {
			}
		}

		$summary[ 'tooltip' ] = sprintf(
			'%s',
			empty( $summary[ 'sidebar_name' ] ) ? $summary[ 'name' ] : __( $summary[ 'sidebar_name' ], 'wp-simple-firewall' )
		);
		return $summary;
	}

	public function getIfShowModuleMenuItem() :bool {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_menu_item' );
	}

	public function getIfShowModuleOpts() :bool {
		return (bool)$this->getOptions()->getFeatureProperty( 'show_module_options' );
	}

	/**
	 * Get config 'definition'.
	 * @param string $key
	 * @return mixed|null
	 */
	public function getDef( string $key ) {
		return $this->getOptions()->getDef( $key );
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
		$errors = $this->getOptions()->getOpt( 'last_errors' );
		if ( !is_array( $errors ) ) {
			$errors = [];
		}
		return $bAsString ? implode( $sGlue, $errors ) : $errors;
	}

	public function hasLastErrors() :bool {
		return count( $this->getLastErrors( false ) ) > 0;
	}

	public function getTextOpt( string $key ) :string {
		$sValue = $this->getOptions()->getOpt( $key, 'default' );
		if ( $sValue == 'default' ) {
			$sValue = $this->getTextOptDefault( $key );
		}
		return __( $sValue, 'wp-simple-firewall' );
	}

	public function getTextOptDefault( string $key ) :string {
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
		$this->getOptions()->setOpt( 'last_errors', $mErrors );
		return $this;
	}

	public function setOptions( array $options ) {
		$opts = $this->getOptions();
		foreach ( $options as $key => $value ) {
			$opts->setOpt( $key, $value );
		}
	}

	public function isModuleRequest() :bool {
		return $this->getModSlug() === Services::Request()->request( 'mod_slug' );
	}

	/**
	 * @param string $action
	 * @param bool   $asJson
	 * @return array|string
	 */
	public function getAjaxActionData( string $action = '', $asJson = false ) {
		$data = $this->getNonceActionData( $action );
		$data[ 'ajaxurl' ] = admin_url( 'admin-ajax.php' );
		return $asJson ? json_encode( (object)$data ) : $data;
	}

	/**
	 * @param string $action
	 * @return array
	 */
	public function getNonceActionData( $action = '' ) {
		$data = $this->getCon()->getNonceActionData( $action );
		$data[ 'mod_slug' ] = $this->getModSlug();
		return $data;
	}

	/**
	 * @return string[]
	 */
	public function getDismissedNotices() :array {
		$notices = $this->getOptions()->getOpt( 'dismissed_notices' );
		return is_array( $notices ) ? $notices : [];
	}

	/**
	 * @return string[]
	 */
	public function getUiTrack() :array {
		$a = $this->getOptions()->getOpt( 'ui_track' );
		return is_array( $a ) ? $a : [];
	}

	public function setDismissedNotices( array $dis ) {
		$this->getOptions()->setOpt( 'dismissed_notices', $dis );
	}

	public function setUiTrack( array $UI ) {
		$this->getOptions()->setOpt( 'ui_track', $UI );
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
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	public function onPluginDeactivate() {
	}

	public function onPluginDelete() {
		foreach ( $this->getDbHandlers( true ) as $dbh ) {
			if ( !empty( $dbh ) ) {
				$dbh->tableDelete();
			}
		}
		$this->getOptions()->deleteStorage();
	}

	/**
	 * @return array - map of each option to its option type
	 */
	protected function getAllFormOptionsAndTypes() {
		$opts = [];

		foreach ( $this->getUIHandler()->buildOptions() as $aOptionsSection ) {
			if ( !empty( $aOptionsSection ) ) {
				foreach ( $aOptionsSection[ 'options' ] as $aOption ) {
					$opts[ $aOption[ 'key' ] ] = $aOption[ 'type' ];
				}
			}
		}

		return $opts;
	}

	protected function handleModAction( string $action ) {
		switch ( $action ) {
			case 'file_download':
				$id = Services::Request()->query( 'download_id', '' );
				if ( !empty( $id ) ) {
					header( 'Set-Cookie: fileDownload=true; path=/' );
					$this->handleFileDownload( $id );
				}
				break;
			default:
				break;
		}
	}

	protected function handleFileDownload( string $downloadID ) {
	}

	public function createFileDownloadLink( string $downloadID, array $additionalParams = [] ) :string {
		$additionalParams[ 'download_id' ] = $downloadID;
		return add_query_arg(
			array_merge( $this->getNonceActionData( 'file_download' ), $additionalParams ),
			$this->getUrl_AdminPage()
		);
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
	 * @param string $msg
	 * @param bool   $isError
	 * @param bool   $bShowOnLogin
	 * @return $this
	 */
	public function setFlashAdminNotice( $msg, $isError = false, $bShowOnLogin = false ) {
		$this->getCon()
			 ->getAdminNotices()
			 ->addFlash(
				 sprintf( '[%s] %s', $this->getCon()->getHumanName(), $msg ),
				 $isError,
				 $bShowOnLogin
			 );
		return $this;
	}

	protected function isThisModAdminPage() :bool {
		return is_admin() && !Services::WpGeneral()->isAjax()
			   && Services::Request()->isGet() && $this->isThisModulePage();
	}

	public function isPremium() :bool {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @throws \Exception
	 */
	private function doSaveStandardOptions() {
		// standard options use b64 and fail-over to lz-string
		$form = FormParams::Retrieve( FormParams::ENC_BASE64 );

		foreach ( $this->getAllFormOptionsAndTypes() as $sKey => $sOptType ) {

			$sOptionValue = isset( $form[ $sKey ] ) ? $form[ $sKey ] : null;
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

					$sConfirm = isset( $form[ $sKey.'_confirm' ] ) ? $form[ $sKey.'_confirm' ] : null;
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
				$this->getOptions()->setOpt( $sKey, $sOptionValue );
			}
		}

		// Handle Import/Export exclusions
		if ( $this->isPremium() ) {
			( new Shield\Modules\Plugin\Lib\ImportExport\Options\SaveExcludedOptions() )
				->setMod( $this )
				->save( $form );
		}
	}

	protected function runWizards() {
		if ( $this->isWizardPage() && $this->hasWizard() ) {
			$wiz = $this->getWizardHandler();
			if ( $wiz instanceof \ICWP_WPSF_Wizard_Base ) {
				$wiz->init();
			}
		}
	}

	public function isThisModulePage() :bool {
		return $this->getCon()->isModulePage()
			   && Services::Request()->query( 'page' ) == $this->getModSlug();
	}

	public function isPage_Insights() :bool {
		return Services::Request()->query( 'page' ) == $this->getCon()->getModule_Insights()->getModSlug();
	}

	public function isPage_InsightsThisModule() :bool {
		return $this->isPage_Insights()
			   && Services::Request()->query( 'subnav' ) == $this->getSlug();
	}

	protected function isModuleOptionsRequest() :bool {
		return Services::Request()->post( 'mod_slug' ) === $this->getModSlug();
	}

	protected function isWizardPage() :bool {
		return $this->getCon()->getShieldAction() == 'wizard' && $this->isThisModulePage();
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
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		echo $this->renderModulePage();
	}

	/**
	 * Override this to customize anything with the display of the page
	 * @param array $data
	 * @return string
	 */
	protected function renderModulePage( array $data = [] ) :string {
		return $this->renderTemplate(
			'index.php',
			Services::DataManipulation()->mergeArraysRecursive( $this->getUIHandler()->getBaseDisplayData(), $data )
		);
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
	 * @param string $wizardSlug
	 * @return string
	 * @uses nonce
	 */
	public function getUrl_Wizard( string $wizardSlug ) :string {
		$def = $this->getWizardDefinition( $wizardSlug );
		if ( empty( $def[ 'min_user_permissions' ] ) ) { // i.e. no login/minimum perms
			$url = Services::WpGeneral()->getHomeUrl();
		}
		else {
			$url = Services::WpGeneral()->getAdminUrl( 'admin.php' );
		}

		return add_query_arg(
			[
				'page'          => $this->getCon()->getModule_Insights()->getModSlug(),
				'inav'			=> 'wizard',
				'shield_action' => 'wizard',
				'wizard'        => $wizardSlug,
				'nonwizard'     => wp_create_nonce( 'wizard'.$wizardSlug )
			],
			$url
		);
	}

	/**
	 * @return string
	 */
	public function getUrl_WizardLanding() {
		return $this->getUrl_Wizard( 'landing' );
	}

	/**
	 * @param string $wizardSlug
	 * @return array
	 */
	public function getWizardDefinition( string $wizardSlug ) {
		$def = null;
		if ( $this->hasWizardDefinition( $wizardSlug ) ) {
			$def = $this->getWizardDefinitions()[ $wizardSlug ];
		}
		return $def;
	}

	public function getWizardDefinitions() :array {
		return is_array( $this->getDef( 'wizards' ) ) ? $this->getDef( 'wizards' ) : [];
	}

	public function hasWizard() :bool {
		return count( $this->getWizardDefinitions() ) > 0;
	}

	public function hasWizardDefinition( string $wizardSlug ) :bool {
		return !empty( $this->getWizardDefinitions()[ $wizardSlug ] );
	}

	public function getIsShowMarketing() :bool {
		return (bool)apply_filters( $this->prefix( 'show_marketing' ), !$this->isPremium() );
	}

	/**
	 * @return string
	 */
	public function renderOptionsForm() {

		if ( $this->canDisplayOptionsForm() ) {
			$template = 'components/options_form/main.twig';
		}
		else {
			$template = 'subfeature-access_restricted';
		}

		try {
			return $this->getCon()
						->getRenderer()
						->setTemplate( $template )
						->setRenderVars( $this->getUIHandler()->getBaseDisplayData() )
						->setTemplateEngineTwig()
						->render();
		}
		catch ( \Exception $e ) {
			return 'Error rendering options form: '.$e->getMessage();
		}
	}

	public function canDisplayOptionsForm() :bool {
		return $this->getOptions()->isAccessRestricted() ? $this->getCon()->isPluginAdmin() : true;
	}

	public function getScriptLocalisations() :array {
		return [
			[
				'plugin',
				'icwp_wpsf_vars_base',
				[
					'ajax' => [
						'mod_options'          => $this->getAjaxActionData( 'mod_options' ),
						'mod_opts_form_render' => $this->getAjaxActionData( 'mod_opts_form_render' ),
					]
				]
			]
		];
	}

	public function getCustomScriptEnqueues() :array {
		return [];
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

	public function renderTemplate( string $template, array $data = [], bool $isTwig = false ) :string {
		if ( empty( $data[ 'unique_render_id' ] ) ) {
			$data[ 'unique_render_id' ] = 'noticeid-'.substr( md5( mt_rand() ), 0, 5 );
		}
		try {
			$oRndr = $this->getCon()->getRenderer();
			if ( $isTwig || preg_match( '#^.*\.twig$#i', $template ) ) {
				$oRndr->setTemplateEngineTwig();
			}

			$data[ 'strings' ] = Services::DataManipulation()
										 ->mergeArraysRecursive(
											 $this->getStrings()->getDisplayStrings(),
											 $data[ 'strings' ] ?? []
										 );

			$render = $oRndr->setTemplate( $template )
							->setRenderVars( $data )
							->render();
		}
		catch ( \Exception $e ) {
			$render = $e->getMessage();
			error_log( $e->getMessage() );
		}

		return (string)$render;
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

	public function getMainWpData() :array {
		return [
			'options' => $this->getOptions()->getTransferableOptions()
		];
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
	 * @return null|Shield\Modules\Base\Options|mixed
	 */
	public function getOptions() {
		$opts = $this->opts ?? $this->oOpts;
		if ( !$opts instanceof Options ) {
			$con = $this->getCon();
			$this->opts = $this->loadModElement( 'Options' );
			$this->opts->setPathToConfig( $con->getPath_ConfigFile( $this->getSlug() ) )
					   ->setRebuildFromFile( $con->cfg->rebuilt )
					   ->setOptionsStorageKey( $this->getOptionsStorageKey() )
					   ->setIfLoadOptionsFromStorage( !$con->getIsResetPlugin() );
			$opts = $this->opts;
			/** @deprecated 11.2 */
		}
		return $opts;
	}

	/**
	 * @return RestHandler|mixed
	 */
	public function getRestHandler() {
		return $this->loadModElement( 'RestHandler' );
	}

	/**
	 * @return Shield\Modules\Base\WpCli
	 * @throws \Exception
	 */
	public function getWpCli() {
		if ( !isset( $this->oWpCli ) ) {
			$this->oWpCli = $this->loadModElement( 'WpCli' );
			if ( !$this->oWpCli instanceof Shield\Modules\Base\WpCli ) {
				throw new \Exception( 'WP-CLI not supported' );
			}
		}
		return $this->oWpCli;
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		return $this->loadStrings()->setMod( $this );
	}

	/**
	 * @return Shield\Modules\Base\UI
	 */
	public function getUIHandler() {
		if ( !isset( $this->oUI ) ) {
			$this->oUI = $this->loadModElement( 'UI' );
			if ( !$this->oUI instanceof Shield\Modules\Base\UI ) {
				// TODO: autoloader for base classes
				$this->oUI = $this->loadModElement( 'ShieldUI' );
			}
		}
		return $this->oUI;
	}

	/**
	 * @return Shield\Modules\Base\Reporting|mixed|false
	 */
	public function getReportingHandler() {
		if ( !isset( $this->oReporting ) ) {
			$this->oReporting = $this->loadModElement( 'Reporting' );
		}
		return $this->oReporting;
	}

	protected function loadAdminNotices() {
		$N = $this->loadModElement( 'AdminNotices' );
		if ( $N instanceof Shield\Modules\Base\AdminNotices ) {
			$N->run();
		}
	}

	protected function loadAjaxHandler() {
		$this->loadModElement( 'AjaxHandler' );
	}

	protected function loadDebug() {
		$req = Services::Request();
		if ( $req->query( 'debug' ) && $req->query( 'mod' ) == $this->getModSlug()
			 && $this->getCon()->isPluginAdmin() ) {
			/** @var Shield\Modules\Base\Debug $debug */
			$debug = $this->loadModElement( 'Debug' );
			$debug->run();
		}
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings' );
	}

	/**
	 * @param string $class
	 * @return false|Shield\Modules\ModConsumer
	 */
	private function loadModElement( string $class ) {
		$element = false;
		try {
			$C = $this->findElementClass( $class, true );
			/** @var Shield\Modules\ModConsumer $element */
			$element = @class_exists( $C ) ? new $C() : false;
			if ( method_exists( $element, 'setMod' ) ) {
				$element->setMod( $this );
			}
		}
		catch ( \Exception $e ) {
		}
		return $element;
	}

	/**
	 * @param string $element
	 * @param bool   $bThrowException
	 * @return string|null
	 * @throws \Exception
	 */
	protected function findElementClass( string $element, $bThrowException = true ) {
		$theClass = null;

		$roots = array_map( function ( $root ) {
			return rtrim( $root, '\\' ).'\\';
		}, $this->getNamespaceRoots() );

		foreach ( $roots as $NS ) {
			$maybe = $NS.$element;
			if ( @class_exists( $maybe ) ) {
				if ( ( new \ReflectionClass( $maybe ) )->isInstantiable() ) {
					$theClass = $maybe;
					break;
				}
			}
		}

		if ( $bThrowException && is_null( $theClass ) ) {
			throw new \Exception( sprintf( 'Could not find class for element "%s".', $element ) );
		}
		return $theClass;
	}

	protected function getBaseNamespace() {
		return __NAMESPACE__;
	}

	protected function getNamespace() :string {
		return ( new \ReflectionClass( $this ) )->getNamespaceName();
	}

	protected function getNamespaceRoots() :array {
		return [
			$this->getNamespace(),
			$this->getBaseNamespace()
		];
	}

	/**
	 * Saves the options to the WordPress Options store.
	 * @return void
	 * @deprecated 8.4
	 */
	public function savePluginOptions() {
		$this->saveModOptions();
	}
}