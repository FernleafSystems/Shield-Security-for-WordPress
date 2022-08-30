<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property bool $is_booted
 */
abstract class ModCon extends DynPropertiesClass {

	use Modules\PluginControllerConsumer;
	use Shield\Crons\PluginCronsConsumer;

	/**
	 * @var Config\ModConfigVO
	 */
	public $cfg;

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
	private $reporting;

	/**
	 * @var Shield\Modules\Base\UI
	 */
	private $UI;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $opts;

	/**
	 * @var Shield\Modules\Base\WpCli
	 */
	private $wpCli;

	/**
	 * @var Shield\Modules\Base\AdminPage
	 */
	private $adminPage;

	/**
	 * @var Shield\Databases\Base\Handler[]
	 */
	private $aDbHandlers;

	/**
	 * @var Databases
	 */
	private $dbHandler;

	/**
	 * @var AdminNotices
	 */
	private $adminNotices;

	/**
	 * @throws \Exception
	 */
	public function __construct( Shield\Controller\Controller $pluginCon, Config\ModConfigVO $cfg ) {
		$this->setCon( $pluginCon );
		$this->cfg = $cfg;
	}

	/**
	 * @throws \Exception
	 */
	public function boot() {
		if ( !$this->is_booted ) {
			$this->is_booted = true;
			$this->handleAutoPageRedirects();
			$this->doPostConstruction();
			$this->setupHooks();
		}
	}

	protected function moduleReadyCheck() :bool {
		try {
			$ready = ( new Lib\CheckModuleRequirements() )
				->setMod( $this )
				->run();
		}
		catch ( \Exception $e ) {
			$ready = false;
		}
		return $ready;
	}

	protected function setupHooks() {
		$con = $this->getCon();

		add_action( $con->prefix( 'modules_loaded' ), function () {
			$this->onModulesLoaded();
		} );

		add_action( 'init', [ $this, 'onWpInit' ], 1 );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );

		add_action( $con->prefix( 'plugin_shutdown' ), [ $this, 'onPluginShutdown' ] );
		add_action( $con->prefix( 'deactivate_plugin' ), [ $this, 'onPluginDeactivate' ] );
		add_action( $con->prefix( 'delete_plugin' ), [ $this, 'onPluginDelete' ] );

//		if ( $this->isAdminOptionsPage() ) {
//			add_action( 'current_screen', array( $this, 'onSetCurrentScreen' ) );
//		}
		$this->collateRuleBuilders();
		$this->setupCronHooks();
		$this->setupCustomHooks();
	}

	protected function collateRuleBuilders() {
		add_filter( 'shield/collate_rule_builders', function ( array $builders ) {
			return array_merge( $builders, array_map(
				function ( $class ) {
					/** @var Shield\Rules\Build\BuildRuleBase $theClass */
					$theClass = new $class();
					$theClass->setMod( $this );
					return $theClass;
				},
				array_filter( $this->enumRuleBuilders() )
			) );
		} );
	}

	protected function enumRuleBuilders() :array {
		return [];
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
	public function getDbHandlers( $bInitAll = false ) {
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

	protected function onModulesLoaded() {
	}

	public function onRunProcessors() {
		if ( $this->cfg->properties[ 'auto_load_processor' ] ) {
			$this->loadProcessor();
		}
		try {
			if ( !$this->cfg->properties[ 'skip_processor' ] && $this->isModuleEnabled() && $this->isReadyToExecute() ) {
				$this->doExecuteProcessor();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return !is_null( $this->getProcessor() );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpLoaded() {
		if ( is_admin() || is_network_admin() ) {
			$this->getAdminPage()->execute();
		}
		if ( $this->getCon()->is_rest_enabled ) {
			$this->initRestApi();
		}
	}

	protected function initRestApi() {
		$cfg = $this->getOptions()->getDef( 'rest_api' );
		if ( !empty( $cfg[ 'publish' ] ) ) {
			add_action( 'rest_api_init', function () use ( $cfg ) {
				try {
					$restClass = $this->findElementClass( 'Rest' );
					/** @var Shield\Modules\Base\Rest $rest */
					if ( @class_exists( $restClass ) ) {
						$rest = new $restClass( $cfg );
						$rest->setMod( $this )->init();
					}
				}
				catch ( \Exception $e ) {
				}
			} );
		}
	}

	public function onWpInit() {
		$con = $this->getCon();

		$shieldAction = $con->getShieldAction();
		if ( !empty( $shieldAction ) ) {
			do_action( $con->prefix( 'shield_action' ), $shieldAction );
			$this->handleShieldAction( $shieldAction );
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
			add_filter( $con->prefix( 'wpPrivacyExport' ), [ $this, 'onWpPrivacyExport' ], 10, 3 );
			add_filter( $con->prefix( 'wpPrivacyErase' ), [ $this, 'onWpPrivacyErase' ], 10, 3 );
		}

		if ( is_admin() || is_network_admin() ) {
			$this->getAdminNotices()->execute();
		}

		$this->loadDebug();
	}

	protected function handleShieldAction( string $action ) {
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
				$class = $this->findElementClass( 'Processor' );
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

	public function onPluginShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			$this->saveModOptions();
		}
	}

	public function getOptionsStorageKey() :string {
		return $this->getCon()->prefixOption( $this->sOptionsStoreKey ?? $this->cfg->properties[ 'storage_key' ] )
			   .'_options';
	}

	/**
	 * @return Shield\Modules\Base\Processor|\FernleafSystems\Utilities\Logic\ExecOnce|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	public function getUrl_OptionsConfigPage() :string {
		return $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'settings', $this->getSlug() );
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
		$def = $this->getOptions()->getOptDefinition( $key );
		return empty( $def[ 'section' ] ) ?
			$this->getUrl_OptionsConfigPage()
			: $this->getUrl_DirectLinkToSection( $def[ 'section' ] );
	}

	public function getUrl_DirectLinkToSection( string $section ) :string {
		if ( $section == 'primary' ) {
			$section = $this->getOptions()->getPrimarySection()[ 'slug' ];
		}
		return $this->getUrl_OptionsConfigPage().'#tab-'.$section;
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

		if ( !$this->moduleReadyCheck() ) {
			$enabled = false;
		}
		elseif ( $this->cfg->properties[ 'auto_enabled' ] ) {
			// Auto enabled modules always run regardless
			$enabled = true;
		}
		elseif ( $pluginOpts->isPluginGloballyDisabled() ) {
			$enabled = false;
		}
		elseif ( $this->getCon()->this_req->is_force_off ) {
			$enabled = false;
		}
		elseif ( $this->cfg->properties[ 'premium' ] && !$this->isPremium() ) {
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
		return __( $this->cfg->properties[ 'name' ], 'wp-simple-firewall' );
	}

	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? $this->getCon()->prefix( $this->getSlug() ) : $this->getSlug();
	}

	public function getSlug() :string {
		return $this->sModSlug ?? $this->cfg->slug;
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
				if ( Services::Request()->query( 'page' )
					 == $this->getCon()->prefix( $redirect[ 'source_mod_page' ] ) ) {
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

	public function getIfShowModuleMenuItem() :bool {
		return $this->cfg->properties[ 'show_module_menu_item' ];
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors( [] );
	}

	/**
	 * @return string|array
	 */
	public function getLastErrors( bool $asString = false, string $glue = " " ) {
		$errors = $this->getOptions()->getOpt( 'last_errors' );
		if ( !is_array( $errors ) ) {
			$errors = [];
		}
		return $asString ? implode( $glue, $errors ) : $errors;
	}

	public function hasLastErrors() :bool {
		return count( $this->getLastErrors( false ) ) > 0;
	}

	public function getTextOpt( string $key ) :string {
		$txt = $this->getOptions()->getOpt( $key, 'default' );
		if ( $txt == 'default' ) {
			$txt = $this->getTextOptDefault( $key );
		}
		return __( $txt, 'wp-simple-firewall' );
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

	public function isModuleRequest() :bool {
		return $this->getModSlug() === Services::Request()->request( 'mod_slug' );
	}

	/**
	 * @return array|string
	 */
	public function getAjaxActionData( string $action = '', bool $asJson = false ) {
		$data = $this->getNonceActionData( $action );
		$data[ 'ajaxurl' ] = admin_url( 'admin-ajax.php' );
		return $asJson ? json_encode( (object)$data ) : $data;
	}

	public function getNonceActionData( string $action = '' ) :array {
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

	public function getUiTrack() :Lib\Components\UiTrack {
		$a = $this->getOptions()->getOpt( 'ui_track' );
		return ( new Lib\Components\UiTrack() )
			->setCon( $this->getCon() )
			->applyFromArray( is_array( $a ) ? $a : [] );
	}

	public function setDismissedNotices( array $dis ) {
		$this->getOptions()->setOpt( 'dismissed_notices', $dis );
	}

	public function setUiTrack( Lib\Components\UiTrack $UI ) {
		$this->getOptions()->setOpt( 'ui_track', $UI->getRawData() );
	}

	/**
	 * @return \ICWP_WPSF_Wizard_Base|null
	 */
	public function getWizardHandler() {
		if ( !isset( $this->oWizard ) ) {
			$class = $this->getWizardClassName();
			if ( !class_exists( $class ) ) {
				return null;
			}
			$this->oWizard = new $class();
			$this->oWizard->setMod( $this );
		}
		return $this->oWizard;
	}

	/**
	 * @return $this
	 */
	public function saveModOptions( bool $preProcessOptions = false ) {

		if ( $preProcessOptions ) {
			$this->preProcessOptions();
		}

		$this->doPrePluginOptionsSave();
		if ( apply_filters( $this->getCon()->prefix( 'force_options_resave' ), false ) ) {
			$this->getOptions()
				 ->setNeedSave( true );
		}

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		if ( $this->getOptions()->getNeedSave() ) {
			$this->bImportExportWhitelistNotify = true;
			do_action( $this->getCon()->prefix( 'pre_options_store' ), $this );
		}
		$this->store();
		return $this;
	}

	protected function preProcessOptions() {
	}

	private function store() {
		$con = $this->getCon();
		add_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		$this->getOptions()
			 ->doOptionsSave( $con->plugin_reset, $this->isPremium() );
		remove_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
	}

	public function onPluginDeactivate() {
	}

	public function onPluginDelete() {
		$this->getOptions()->deleteStorage();
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
	 * @param string        $msg
	 * @param \WP_User|null $user
	 * @param bool          $isError
	 * @param bool          $bShowOnLogin
	 * @return $this
	 */
	public function setFlashAdminNotice( $msg, $user = null, $isError = false, $bShowOnLogin = false ) {
		$this->getCon()
			 ->getAdminNotices()
			 ->addFlash(
				 sprintf( '[%s] %s', $this->getCon()->getHumanName(), $msg ),
				 $user,
				 $isError,
				 $bShowOnLogin
			 );
		return $this;
	}

	public function isPremium() :bool {
		return $this->getCon()->isPremiumActive();
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
			   && Services::Request()->query( 'inav' ) == $this->getSlug();
	}

	protected function isWizardPage() :bool {
		return $this->getCon()->getShieldAction() == 'wizard' && $this->isThisModulePage();
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
				'inav'          => 'wizard',
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
		$wiz = $this->getOptions()->getDef( 'wizards' );
		return is_array( $wiz ) ? $wiz : [];
	}

	public function hasWizard() :bool {
		return count( $this->getWizardDefinitions() ) > 0;
	}

	public function hasWizardDefinition( string $wizardSlug ) :bool {
		return !empty( $this->getWizardDefinitions()[ $wizardSlug ] );
	}

	public function getIsShowMarketing() :bool {
		return (bool)apply_filters( 'shield/show_marketing', !$this->isPremium() );
	}

	public function isAccessRestricted() :bool {
		return $this->cfg->properties[ 'access_restricted' ] && !$this->getCon()->isPluginAdmin();
	}

	public function canDisplayOptionsForm() :bool {
		return !$this->cfg->properties[ 'access_restricted' ] || $this->getCon()->isPluginAdmin();
	}

	public function getScriptLocalisations() :array {
		return [];
	}

	public function getCustomScriptEnqueues() :array {
		return [];
	}

	public function renderTemplate( string $template, array $data = [] ) :string {
		return $this->getRenderer()
					->setTemplate( $template )
					->setRenderData( $data )
					->render();
	}

	public function getMainWpData() :array {
		return [
			'options' => $this->getOptions()->getTransferableOptions()
		];
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyExport()
	 * @param array  $exportItems
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyExport( $exportItems, $email, $page = 1 ) {
		return $exportItems;
	}

	/**
	 * See plugin controller for the nature of $aData wpPrivacyErase()
	 * @param array  $data
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function onWpPrivacyErase( $data, $email, $page = 1 ) {
		return $data;
	}

	/**
	 * @return null|Shield\Modules\Base\Options|mixed
	 */
	public function getOptions() {
		if ( empty( $this->opts ) ) {
			$this->opts = $this->loadModElement( 'Options' );
		}
		return $this->opts;
	}

	/**
	 * @return AdminPage
	 */
	public function getAdminPage() {
		if ( !isset( $this->adminPage ) ) {
			$this->adminPage = $this->loadModElement( 'AdminPage' );
		}
		return $this->adminPage;
	}

	/**
	 * @return Shield\Modules\Base\WpCli
	 */
	public function getWpCli() {
		if ( !isset( $this->wpCli ) ) {
			$this->wpCli = $this->loadModElement( 'WpCli' );
		}
		return $this->wpCli;
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		return $this->loadStrings()->setMod( $this );
	}

	/**
	 * @return mixed|Shield\Modules\Base\Renderer
	 */
	public function getRenderer() {
		/** @var Renderer $r */
		$r = $this->loadModElement( 'Renderer' );
		return $r->setMod( $this );
	}

	/**
	 * @return Shield\Modules\Base\UI
	 */
	public function getUIHandler() {
		if ( !isset( $this->UI ) ) {
			$this->UI = $this->loadModElement( 'UI' );
		}
		return $this->UI;
	}

	/**
	 * @return Shield\Modules\Base\Reporting|mixed|false
	 */
	public function getReportingHandler() {
		if ( !isset( $this->reporting ) ) {
			$this->reporting = $this->loadModElement( 'Reporting' );
		}
		return $this->reporting;
	}

	public function getAdminNotices() {
		if ( !isset( $this->adminNotices ) ) {
			$this->adminNotices = $this->loadModElement( 'AdminNotices' );
		}
		return $this->adminNotices;
	}

	protected function loadAjaxHandler() {
		try {
			$class = $this->findElementClass( 'AjaxHandler', true );
			/** @var Shield\Modules\ModConsumer $AH */
			if ( !empty( $class ) && @class_exists( $class ) ) {
				new $class( $this );
			}
		}
		catch ( \Exception $e ) {
		}
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
	 * @return Shield\Modules\Base\Databases|mixed
	 */
	public function getDbHandler() {
		if ( empty( $this->dbHandler ) ) {
			$this->dbHandler = $this->loadModElement( 'Databases' );
		}
		return $this->dbHandler;
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings' );
	}

	/**
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