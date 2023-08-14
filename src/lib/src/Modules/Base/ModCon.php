<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

/**
 * @property bool $is_booted
 */
abstract class ModCon extends DynPropertiesClass {

	use Modules\PluginControllerConsumer;
	use Shield\Crons\PluginCronsConsumer;

	public const SLUG = '';

	/**
	 * @var Config\ModConfigVO
	 */
	public $cfg;

	/**
	 * @var bool
	 * @deprecated 18.2.5
	 */
	protected $bImportExportWhitelistNotify = false;

	/**
	 * @var Shield\Modules\Base\Processor
	 */
	private $oProcessor;

	/**
	 * @var Shield\Modules\Base\Options
	 */
	private $opts;

	/**
	 * @var Shield\Modules\Base\WpCli
	 */
	private $wpCli;

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
	public function __construct( Config\ModConfigVO $cfg ) {
		$this->cfg = $cfg;
	}

	/**
	 * @throws \Exception
	 */
	public function boot() {
		if ( !$this->is_booted ) {
			$this->is_booted = true;
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
		$con = $this->con();

		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_MOD_CON_DEFAULT );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );

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
			return \array_merge( $builders, \array_map(
				function ( $class ) {
					/** @var Shield\Rules\Build\BuildRuleBase $theClass */
					$theClass = new $class();
					$theClass->setMod( $this );
					return $theClass;
				},
				\array_filter( $this->enumRuleBuilders() )
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
	 * @param bool $initAll
	 * @return Shield\Databases\Base\Handler[]
	 */
	public function getDbHandlers( $initAll = false ) {
		if ( $initAll ) {
			foreach ( $this->getAllDbClasses() as $dbSlug => $dbClass ) {
				$this->getDbH( $dbSlug );
			}
		}
		return \is_array( $this->aDbHandlers ) ? $this->aDbHandlers : [];
	}

	/**
	 * @param string $dbhKey
	 * @return Shield\Databases\Base\Handler|mixed|false
	 */
	protected function getDbH( $dbhKey ) {
		$dbh = false;

		if ( !\is_array( $this->aDbHandlers ) ) {
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
					$dbh->setMod( $this )->execute();
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
		return \is_array( $classes ) ? $classes : [];
	}

	/**
	 * @return false|Shield\Modules\Base\Upgrade|mixed
	 */
	public function getUpgradeHandler() {
		return $this->loadModElement( 'Upgrade' );
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
		return !\is_null( $this->getProcessor() );
	}

	protected function doExecuteProcessor() {
		$this->getProcessor()->execute();
	}

	public function onWpLoaded() {
		if ( $this->con()->is_rest_enabled ) {
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
					if ( @\class_exists( $restClass ) ) {
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
		$con = $this->con();

		add_action( 'cli_init', function () {
			try {
				$this->getWpCli()->execute();
			}
			catch ( \Exception $e ) {
			}
		} );

		// GDPR
		if ( $con->isPremiumActive() ) {
			add_filter( $con->prefix( 'wpPrivacyExport' ), [ $this, 'onWpPrivacyExport' ], 10, 3 );
			add_filter( $con->prefix( 'wpPrivacyErase' ), [ $this, 'onWpPrivacyErase' ], 10, 3 );
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
		if ( $this->con()->isValidAdminArea() ) {
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
	 * @deprecated 18.2.5
	 */
	public function onPluginShutdown() {
		if ( !$this->con()->plugin_deleting && \version_compare( $this->con()->cfg->version(), '18.2.5', '<' ) ) {
			$this->saveModOptions();
		}
	}

	public function getOptionsStorageKey() :string {
		return $this->con()->prefixOption( $this->cfg->properties[ 'storage_key' ] ).'_options';
	}

	/**
	 * @return Shield\Modules\Base\Processor|\FernleafSystems\Utilities\Logic\ExecOnce|mixed
	 */
	public function getProcessor() {
		return $this->loadProcessor();
	}

	public function getUrl_OptionsConfigPage() :string {
		return $this->con()->plugin_urls->modCfg( $this );
	}

	/**
	 * TODO: Get rid of this crap and/or handle the \Exception thrown in loadFeatureHandler()
	 * @return Modules\Email\ModCon
	 * @throws \Exception
	 * @deprecated 10.2
	 */
	public function getEmailHandler() {
		return $this->con()->getModule_Email();
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
		$this->getOptions()->setOpt( $this->getEnableModOptKey(), $enable ? 'Y' : 'N' );
		return $this;
	}

	public function isModuleEnabled() :bool {
		$con = $this->con();
		/** @var Shield\Modules\Plugin\Options $pluginOpts */
		$pluginOpts = $con->getModule_Plugin()->getOptions();

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
		elseif ( $this->con()->this_req->is_force_off ) {
			$enabled = false;
		}
		elseif ( $this->cfg->properties[ 'premium' ] && !$con->isPremiumActive() ) {
			$enabled = false;
		}
		else {
			$enabled = $this->isModOptEnabled();
		}

		return $enabled;
	}

	public function isModOptEnabled() :bool {
		$opts = $this->getOptions();
		return $opts->isOpt( $this->getEnableModOptKey(), 'Y' ) || $opts->isOpt( $this->getEnableModOptKey(), true, true );
	}

	public function getEnableModOptKey() :string {
		return 'enable_'.$this->cfg->slug;
	}

	public function getMainFeatureName() :string {
		return __( $this->cfg->properties[ 'name' ], 'wp-simple-firewall' );
	}

	/**
	 * @return array{title: string, subtitle: string, description: array}
	 */
	public function getDescriptors() :array {
		return [
			'title'       => $this->getMainFeatureName(),
			'subtitle'    => __( $this->cfg->properties[ 'tagline' ] ?? '', 'wp-simple-firewall' ),
			'description' => [],
		];
	}

	public function getModSlug( bool $prefix = true ) :string {
		return $prefix ? $this->con()->prefix( $this->cfg->slug ) : $this->cfg->slug;
	}

	/**
	 * @return $this
	 */
	public function clearLastErrors() {
		return $this->setLastErrors();
	}

	/**
	 * @return string|array
	 */
	public function getLastErrors( bool $asString = false, string $glue = " " ) {
		$errors = $this->getOptions()->getOpt( 'last_errors' );
		if ( !\is_array( $errors ) ) {
			$errors = [];
		}
		return $asString ? \implode( $glue, $errors ) : $errors;
	}

	public function hasLastErrors() :bool {
		return \count( $this->getLastErrors() ) > 0;
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
		if ( !\is_array( $mErrors ) ) {
			if ( \is_string( $mErrors ) ) {
				$mErrors = [ $mErrors ];
			}
			else {
				$mErrors = [];
			}
		}
		$this->getOptions()->setOpt( 'last_errors', $mErrors );
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getDismissedNotices() :array {
		$notices = $this->getOptions()->getOpt( 'dismissed_notices' );
		return \is_array( $notices ) ? $notices : [];
	}

	public function getUiTrack() :Lib\Components\UiTrack {
		$a = $this->getOptions()->getOpt( 'ui_track' );
		return ( new Lib\Components\UiTrack() )->applyFromArray( \is_array( $a ) ? $a : [] );
	}

	public function setDismissedNotices( array $dis ) {
		$this->getOptions()->setOpt( 'dismissed_notices', $dis );
	}

	public function setUiTrack( Lib\Components\UiTrack $UI ) {
		$this->getOptions()->setOpt( 'ui_track', $UI->getRawData() );
	}

	/**
	 * @deprecated 18.2.5
	 */
	public function saveModOptions( bool $preProcessOptions = false, bool $store = true ) {

		if ( $preProcessOptions ) {
			$this->preProcessOptions();
		}

		$this->doPrePluginOptionsSave();

		// we set the flag that options have been updated. (only use this flag if it's a MANUAL options update)
		if ( $this->getOptions()->getNeedSave() ) {
			do_action( $this->con()->prefix( 'pre_options_store' ), $this );
		}

		if ( $store ) {
			self::con()->opts === null ? $this->store() : self::con()->opts->store();
		}
	}

	protected function preProcessOptions() {
	}

	/**
	 * @deprecated 18.2.5
	 */
	private function store() {
		$con = $this->con();
		add_filter( $con->prefix( 'bypass_is_plugin_admin' ), '__return_true', 1000 );
		$this->getOptions()->doOptionsSave( false, $con->isPremiumActive() );
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

	protected function buildContextualHelp() {
		if ( !\function_exists( 'get_current_screen' ) ) {
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

	public function isAccessRestricted() :bool {
		return $this->cfg->properties[ 'access_restricted' ] && !$this->con()->isPluginAdmin();
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
	 * @deprecated 18.2.4
	 */
	public function getOptions() {
		return \method_exists( $this, 'opts' ) ? $this->opts() : $this->opts;
	}

	/**
	 * @return null|Shield\Modules\Base\Options|mixed
	 */
	public function opts() {
		return $this->opts ?? $this->opts = $this->loadModElement( 'Options' );
	}

	/**
	 * @return Shield\Modules\Base\WpCli|mixed
	 */
	public function getWpCli() {
		return $this->wpCli ?? $this->wpCli = $this->loadModElement( 'WpCli' );
	}

	/**
	 * @return null|Shield\Modules\Base\Strings
	 */
	public function getStrings() {
		return $this->loadStrings()->setMod( $this );
	}

	public function getAdminNotices() {
		return $this->adminNotices ?? $this->adminNotices = $this->loadModElement( 'AdminNotices' );
	}

	/**
	 * @return Shield\Modules\Base\Databases|mixed
	 */
	public function getDbHandler() {
		return $this->dbHandler ?? $this->dbHandler = $this->loadModElement( 'Databases' );
	}

	/**
	 * @return Shield\Modules\Base\Strings|mixed
	 */
	protected function loadStrings() {
		return $this->loadModElement( 'Strings' );
	}

	/**
	 * @return false|Shield\Modules\ModConsumer|mixed
	 */
	private function loadModElement( string $class ) {
		$element = false;
		try {
			$C = $this->findElementClass( $class );
			/** @var Shield\Modules\ModConsumer $element */
			$element = @\class_exists( $C ) ? new $C() : false;
			if ( \method_exists( $element, 'setMod' ) ) {
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

		$roots = \array_map( function ( $root ) {
			return \rtrim( $root, '\\' ).'\\';
		}, $this->getNamespaceRoots() );

		foreach ( $roots as $NS ) {
			$maybe = $NS.$element;
			if ( @\class_exists( $maybe ) ) {
				if ( ( new \ReflectionClass( $maybe ) )->isInstantiable() ) {
					$theClass = $maybe;
					break;
				}
			}
		}

		if ( $bThrowException && \is_null( $theClass ) ) {
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