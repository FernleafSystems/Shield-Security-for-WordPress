<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\ModConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDeactivate;
use FernleafSystems\Wordpress\Plugin\Shield\Extensions\ExtensionsCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Autoupdates,
	Base,
	CommentsFilter,
	Comms,
	Data,
	Events,
	Firewall,
	HackGuard,
	Headers,
	Integrations,
	IPs,
	License,
	Lockdown,
	LoginGuard,
	Plugin,
	SecurityAdmin,
	Traffic,
	UserManagement,
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

/**
 * @property Config\ConfigVO                          $cfg
 * @property Config\OptsHandler                       $opts
 * @property Shield\Rules\RulesController             $rules
 * @property ActionRoutingController                  $action_router
 * @property ExtensionsCon                            $extensions_controller
 * @property Database\DbCon                           $db_con
 * @property Email\EmailCon                           $email_con
 * @property Shield\Utilities\AdminNotices\Controller $admin_notices
 * @property Shield\Controller\Plugin\PluginURLs      $plugin_urls
 * @property Assets\Urls                              $urls
 * @property Assets\Paths                             $paths
 * @property Assets\Svgs                              $svgs
 * @property Shield\Request\ThisRequest               $this_req
 * @property License\Lib\Capabilities                 $caps
 * @property Config\Labels                            $labels
 * @property Shield\Controller\Plugin\PluginLabels    $plugin_labels
 * @property array                                    $prechecks
 * @property array                                    $flags
 * @property bool                                     $is_activating
 * @property bool                                     $is_mode_debug
 * @property bool                                     $is_mode_staging
 * @property bool                                     $is_mode_live
 * @property bool                                     $is_my_upgrade
 * @property bool                                     $is_rest_enabled
 * @property bool                                     $modules_loaded
 * @property bool                                     $plugin_deactivating
 * @property bool                                     $plugin_deleting
 * @property bool                                     $plugin_reset
 * @property Shield\Utilities\CacheDirHandler         $cache_dir_handler
 * @property bool                                     $user_can_base_permissions
 * @property string                                   $base_file
 * @property string                                   $root_file
 * @property Integrations\Lib\MainWP\Common\MainWPVO  $mwpVO
 * @property Shield\Utilities\MU\MUHandler            $mu_handler
 * @property Shield\Events\EventsService              $service_events
 * @property Shield\Users\UserMetas                   $user_metas
 * @property Base\ModCon[]|mixed                      $modules
 * @property Shield\Crons\HourlyCron                  $cron_hourly
 * @property Shield\Crons\DailyCron                   $cron_daily
 * @property string[]                                 $reqs_not_met
 */
class Controller extends DynPropertiesClass {

	/**
	 * @var Controller
	 */
	public static $oInstance;

	public function fireEvent( string $event, array $meta = [] ) :self {
		$this->service_events->fireEvent( $event, $meta );
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	public static function GetInstance( ?string $rootFile = null ) :Controller {
		if ( !isset( static::$oInstance ) ) {
			if ( empty( $rootFile ) ) {
				throw new \Exception( 'Empty root file provided for instantiation' );
			}
			static::$oInstance = new static( $rootFile );
		}
		return static::$oInstance;
	}

	/**
	 * @throws \Exception
	 */
	protected function __construct( string $rootFile ) {
		$this->root_file = $rootFile;
		$this->base_file = plugin_basename( $this->getRootFile() );
		$this->modules = [];
	}

	/**
	 * @throws \Exception
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );

		switch ( $key ) {

			case 'caps':
				if ( \is_null( $val ) ) {
					$this->caps = $val = new License\Lib\Capabilities();
				}
				break;

			case 'flags':
				if ( !\is_array( $val ) ) {
					$this->flags = $val = [];
				}
				break;

			case 'labels':
				if ( \is_null( $val ) ) {
					$this->labels = $val = $this->labels();
				}
				break;

			case 'db_con':
				if ( empty( $val ) ) {
					$this->db_con = $val = new Database\DbCon();
				}
				break;

			case 'email_con':
				if ( empty( $val ) ) {
					$this->email_con = $val = new Email\EmailCon();
				}
				break;

			case 'service_events':
				if ( empty( $val ) ) {
					$this->service_events = $val = new Shield\Events\EventsService();
				}
				break;

			case 'admin_notices':
				if ( empty( $val ) ) {
					$this->admin_notices = $val = new Shield\Utilities\AdminNotices\Controller();
				}
				break;

			case 'extensions_controller':
				if ( empty( $val ) ) {
					$this->extensions_controller = $val = new ExtensionsCon();
				}
				break;

			case 'opts':
				if ( empty( $val ) ) {
					$this->opts = $val = new Config\OptsHandler();
				}
				break;

			case 'plugin_labels':
				if ( empty( $val ) ) {
					$this->plugin_labels = $val = new Shield\Controller\Plugin\PluginLabels();
				}
				break;

			case 'this_req':
				if ( $val === null ) {
					if ( !$this->modules_loaded ) {
						throw new \Exception( 'Modules must be loaded before $this_req is queried.' );
					}
					$this->this_req = $val = new Shield\Request\ThisRequest();
				}
				break;

			case 'is_activating':
			case 'is_my_upgrade':
			case 'modules_loaded':
			case 'plugin_deactivating':
			case 'plugin_deleting':
			case 'user_can_base_permissions':
				$val = (bool)$val;
				break;

			case 'plugin_reset':
				if ( $val === null ) {
					$this->plugin_reset = $val = Services::WpFs()->isAccessibleFile( $this->paths->forFlag( 'reset' ) );
				}
				break;

			case 'is_rest_enabled':
				if ( $val === null ) {
					$restReqs = $this->cfg->reqs_rest;
					$val = Services::WpGeneral()->getWordpressIsAtLeastVersion( $restReqs[ 'wp' ] )
						   && Services::Data()->getPhpVersionIsAtLeast( $restReqs[ 'php' ] );
					$this->is_rest_enabled = $val;
				}
				break;

			case 'cache_dir_handler':
				if ( empty( $val ) ) {
					throw new \Exception( 'Accessing Cache Dir Handler too early.' );
				}
				break;

			case 'is_mode_debug':
				if ( $val === null ) {
					$this->is_mode_debug = $val = ( new Shield\Controller\Modes\DebugMode() )->isModeActive();
				}
				break;

			case 'is_mode_live':
				if ( $val === null ) {
					$val = $this->is_mode_live = !$this->is_mode_staging && !$this->is_mode_debug;
				}
				break;

			case 'is_mode_staging':
				if ( $val === null ) {
					$val = ( new Shield\Controller\Modes\StagingMode() )->isModeActive();
					$this->is_mode_staging = $val;
				}
				break;

			case 'mu_handler':
				if ( $val === null ) {
					$this->mu_handler = $val = new Shield\Utilities\MU\MUHandler();
				}
				break;

			case 'action_router':
				if ( $val === null ) {
					$this->action_router = $val = new Shield\ActionRouter\ActionRoutingController();
				}
				break;

			case 'plugin_urls':
				if ( $val === null ) {
					$this->plugin_urls = $val = new Shield\Controller\Plugin\PluginURLs();
				}
				break;

			case 'paths':
				if ( $val === null ) {
					$this->paths = $val = new Shield\Controller\Assets\Paths();
				}
				break;

			case 'svgs':
				if ( $val === null ) {
					$this->svgs = $val = new Shield\Controller\Assets\Svgs();
				}
				break;

			case 'urls':
				if ( $val === null ) {
					$this->urls = $val = new Shield\Controller\Assets\Urls();
				}
				break;

			case 'reqs_not_met':
				if ( !\is_array( $val ) ) {
					$val = [];
					$this->reqs_not_met = $val;
				}
				break;

			case 'user_metas':
				if ( $val === null ) {
					$this->user_metas = $val = new Shield\Users\UserMetas();
				}
				break;

			case 'rules':
			default:
				break;
		}

		return $val;
	}

	/**
	 * @throws \Exception
	 */
	private function checkMinimumRequirements() {
		$FS = Services::WpFs();

		$flag = $this->paths->forFlag( 'reqs_met.flag' );
		if ( !$FS->isAccessibleFile( $flag )
			 || Services::Request()->carbon()->subHour()->timestamp > $FS->getModifiedTime( $flag ) ) {
			$reqsMsg = [];

			$minPHP = $this->cfg->requirements[ 'php' ];
			if ( !empty( $minPHP ) && \version_compare( Services::Data()->getPhpVersion(), $minPHP, '<' ) ) {
				$reqsMsg[] = sprintf( 'PHP does not meet minimum version. Your version: %s.  Required Version: %s.', \PHP_VERSION, $minPHP );
			}

			$wp = $this->cfg->requirements[ 'wordpress' ];
			if ( !empty( $wp ) && \version_compare( Services::WpGeneral()->getVersion( true ), $wp, '<' ) ) {
				$reqsMsg[] = sprintf( 'WordPress does not meet minimum version. Required Version: %s.', $wp );
			}

			$mysql = $this->cfg->requirements[ 'mysql' ];
			if ( !empty( $mysql ) && !( new Checks\Requirements() )->isMysqlVersionSupported( $mysql ) ) {
				$reqsMsg[] = sprintf( "Your MySQL database server doesn't support IPv6 addresses. Your Version: %s; Required MySQL Version: %s;",
					Services::WpDb()->getMysqlServerInfo(), $mysql );
			}

			if ( !empty( $reqsMsg ) ) {
				$this->reqs_not_met = $reqsMsg;
				add_action( 'admin_notices', [ $this, 'adminNoticeDoesNotMeetRequirements' ] );
				add_action( 'network_admin_notices', [ $this, 'adminNoticeDoesNotMeetRequirements' ] );
				throw new \Exception( 'Plugin does not meet minimum requirements' );
			}

			$FS->touch( $this->paths->forFlag( 'reqs_met.flag' ) );
		}
	}

	public function adminNoticeDoesNotMeetRequirements() {
		if ( !empty( $this->reqs_not_met ) ) {
			$this->getRenderer()
				 ->setTemplate( '/notices/does-not-meet-requirements.twig' )
				 ->setTemplateEngineTwig()
				 ->setRenderVars( [
					 'strings' => [
						 'not_met'          => 'Shield Security Plugin - minimum site requirements are not met',
						 'requirements'     => $this->reqs_not_met,
						 'summary_title'    => "Your web hosting doesn't meet the minimum requirements for the Shield Security Plugin.",
						 'recommend'        => "We highly recommend upgrading your web hosting components as they're probably quite out-of-date.",
						 'more_information' => 'Click here for more information on requirements'
					 ],
					 'hrefs'   => [
						 'more_information' => 'https://shsec.io/shieldsystemrequirements'
					 ]
				 ] )
				 ->display();
		}
	}

	/**
	 * This is where everything happens that runs the plugin.
	 * 1) Modules are all loaded.
	 * 2) Upgrades are run.
	 * 3) Rules Engine is initiated
	 * 4) If Rules Engine is ready, they're executed and then processors are kicked off.
	 * @throws \Exception
	 */
	public function boot() {
		if ( $this->mu_handler->isActiveMU() && !Services::WpPlugins()->isActive( $this->base_file ) ) {
			Services::WpPlugins()->activate( $this->base_file );
		}
		$this->loadConfig();
		$this->checkMinimumRequirements();

		( new Shield\Controller\I18n\LoadTextDomain() )->run();

		$this->loadModules();

		$this->extensions_controller->execute();

		$this->db_con->execute();

		( new Updates\HandleUpgrade() )->execute();

		// Should execute after modules have initiated
		$this->rules = new Shield\Rules\RulesController();
		$this->rules
			->setThisRequest( $this->this_req )
			->execute();

		if ( !$this->cfg->rebuilt ) {

			$this->rules->processRules();

			foreach ( $this->modules as $module ) {
				$module->onRunProcessors();
			}

			// This is where any rules responses will execute (i.e. after processors are run):
			do_action( $this->prefix( 'after_run_processors' ) );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function loadModules() {
		if ( !$this->modules_loaded ) {

			$this->modules_loaded = true;

			$configuration = $this->cfg->configuration;
			if ( empty( $configuration ) || $this->cfg->rebuilt ) {
				$this->cfg->configuration = $configuration = ( new Config\Modules\LoadModuleConfigs() )->run();
			}

			$enum = [
				SecurityAdmin\ModCon::class,
				AuditTrail\ModCon::class,
				Autoupdates\ModCon::class,
				CommentsFilter\ModCon::class,
				Data\ModCon::class,
				Firewall\ModCon::class,
				HackGuard\ModCon::class,
				Headers\ModCon::class,
				Integrations\ModCon::class,
				IPs\ModCon::class,
				License\ModCon::class,
				Lockdown\ModCon::class,
				LoginGuard\ModCon::class,
				Plugin\ModCon::class,
				Traffic\ModCon::class,
				UserManagement\ModCon::class,
			];

			$modules = $this->modules ?? [];
			foreach ( $this->cfg->configuration->modules as $slug => $moduleProps ) {

				$cfg = new ModConfigVO();
				$cfg->slug = $slug;
				$cfg->properties = $moduleProps;
				$cfg->sections = $configuration->sectionsForModule( $cfg->slug );
				$cfg->options = $configuration->optsForModule( $cfg->slug );

				/** @var ModConfigVO $cfg */
				$cfg = apply_filters( 'shield/load_mod_cfg', $cfg, $slug );

				$slug = $cfg->properties[ 'slug' ];
				$theModClass = null;
				foreach ( $enum as $idx => $modClass ) {
					/** @var string|Base\ModCon $modClass */
					if ( @\class_exists( $modClass ) && $slug === $modClass::SLUG ) {
						$theModClass = $modClass;
						unset( $enum[ $idx ] );
						break;
					}
				}
				if ( empty( $theModClass ) ) {
					// Prevent fatal errors if the plugin doesn't install/upgrade correctly
					throw new \Exception( sprintf( 'Class for module "%s" is not defined.', $slug ) );
				}

				$modules[ $slug ] = new $theModClass( $cfg );
				$this->modules = $modules;
			}

			$this->prechecks = ( new Checks\PreModulesBootCheck() )->run();

			// Register the Controller hooks
			$this->doRegisterHooks();

			foreach ( $this->modules as $module ) {
				$module->boot();
			}
		}
	}

	/**
	 * All our module page names are prefixed
	 * @see PluginAdminPageHandler - All Plugin admin pages go through the plugin modules, see:
	 */
	public function isPluginAdminPageRequest() :bool {
		return Services::Request()->query( 'page' ) === $this->plugin_urls->rootAdminPageSlug();
	}

	public function onWpDeactivatePlugin() {
		do_action( $this->prefix( 'pre_deactivate_plugin' ) );
		if ( $this->isPluginAdmin() ) {

			$this->plugin_deactivating = true;

			( new PluginDeactivate() )->run();

			if ( apply_filters( $this->prefix( 'delete_on_deactivate' ), false ) ) {
				$this->deletePlugin();
			}
		}
	}

	public function deletePlugin() {
		$this->plugin_deleting = true;
		do_action( $this->prefix( 'delete_plugin' ) );
		( new Shield\Controller\Plugin\PluginDelete() )->execute();
	}

	/**
	 * @throws \TypeError - Potentially. Not sure how the plugin hasn't initiated by that stage.
	 */
	public function onWpActivatePlugin() {
		$this->getModule_Plugin()->setActivatedAt();
		$this->is_activating = true;
		do_action( 'shield/plugin_activated' );
	}

	protected function doRegisterHooks() {
		register_deactivation_hook( $this->getRootFile(), [ $this, 'onWpDeactivatePlugin' ] );

		add_action( 'init', [ $this, 'onWpInit' ], Shield\Controller\Plugin\HookTimings::INIT_MAIN_CONTROLLER );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 5 );
		add_action( 'admin_init', [ $this, 'onWpAdminInit' ] );
		add_action( 'shutdown', [ $this, 'onWpShutdown' ], \PHP_INT_MIN );

		$this->plugin_labels->execute();

		/**
		 * Support for WP-CLI and it marks the cli as plugin admin
		 */
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), function ( $byPass ) {
			if ( Services::WpGeneral()->isWpCli() && $this->isPremiumActive() ) {
				$byPass = true;
			}
			return $byPass;
		}, \PHP_INT_MAX );
	}

	public function onWpAdminInit() {
		if ( !$this->this_req->wp_is_ajax ) {
			( new Admin\DashboardWidget() )->execute();
			( new Admin\PluginsPageSupplements() )->execute();
			( new Privacy\PrivacyPolicy() )->execute();
		}
	}

	public function onWpInit() {
		$this->getMeetsBasePermissions();

		try {
			$this->action_router->execute();
			$this->action_router->action( Actions\PluginAdmin\PluginAdminPageHandler::class, \array_merge(
				Services::Request()->query,
				Services::Request()->post
			) );
		}
		catch ( ActionException $e ) {
		}

		( new Shield\Controller\Assets\Enqueue() )->execute();
		( new Privacy\PrivacyExport() )->execute();
		( new Privacy\PrivacyEraser() )->execute();
	}

	public function onWpLoaded() {
		$this->admin_notices->execute();
		$this->initCrons();
		( new Admin\AdminBarMenu() )->execute();
		( new Updates\CaptureFirstDetected() )->execute();
		( new Updates\CaptureMyUpgrade() )->execute();
		( new Updates\AdjustAuto() )->execute();
	}

	protected function initCrons() :void {
		$this->cron_hourly = new Shield\Crons\HourlyCron();
		$this->cron_hourly->execute();
		$this->cron_daily = new Shield\Crons\DailyCron();
		$this->cron_daily->execute();

		( new Shield\Utilities\RootHtaccess() )->execute();
	}

	public function onWpShutdown() {
		do_action( $this->prefix( 'pre_plugin_shutdown' ) );
		$this->opts->store();
		do_action( $this->prefix( 'plugin_shutdown' ) );
		$this->saveCurrentPluginControllerOptions();
		$this->deleteFlags();
	}

	protected function deleteFlags() {
		$FS = Services::WpFs();
		foreach ( [ 'rebuild', 'reset' ] as $flag ) {
			if ( $FS->exists( $this->paths->forFlag( $flag ) ) ) {
				$FS->deleteFile( $this->paths->forFlag( $flag ) );
			}
		}
	}

	public function prefix( string $suffix = '', string $glue = '-' ) :string {
		$prefix = $this->getPluginPrefix( $glue );

		if ( $suffix == $prefix || \strpos( $suffix, $prefix.$glue ) === 0 ) { //it already has the full prefix
			return $suffix;
		}

		return sprintf( '%s%s%s', $prefix, empty( $suffix ) ? '' : $glue, empty( $suffix ) ? '' : $suffix );
	}

	/**
	 * @throws \Exception
	 */
	private function loadConfig() {
		$this->cfg = ( new Config\Ops\LoadConfig( $this->paths->forPluginItem( 'plugin.json' ), $this->getConfigStoreKey() ) )->run();
		$this->plugin_urls;
		$this->saveCurrentPluginControllerOptions();
	}

	public function isValidAdminArea( bool $checkUserPerms = false ) :bool {
		if ( $checkUserPerms && did_action( 'init' ) && !$this->isPluginAdmin() ) {
			return false;
		}

		$WP = Services::WpGeneral();
		if ( !$WP->isMultisite() && is_admin() ) {
			return true;
		}
		elseif ( $WP->isMultisite() && $this->cfg->properties[ 'wpms_network_admin_only' ] && ( is_network_admin() || $WP->isAjax() ) ) {
			return true;
		}
		return false;
	}

	/**
	 * only ever consider after WP INIT (when a logged-in user is recognised)
	 */
	public function isPluginAdmin() :bool {
		return apply_filters( $this->prefix( 'bypass_is_plugin_admin' ), false )
			   || apply_filters( $this->prefix( 'is_plugin_admin' ), $this->getMeetsBasePermissions() );
	}

	/**
	 * DO NOT CHANGE THIS IMPLEMENTATION.
	 * We call this as early as possible so that the
	 * current_user_can() never gets caught up in an infinite loop of permissions checking
	 */
	public function getMeetsBasePermissions() :bool {
		if ( did_action( 'init' ) && !isset( $this->user_can_base_permissions ) ) {
			$this->user_can_base_permissions = current_user_can( $this->cfg->properties[ 'base_permissions' ] );
		}
		return $this->user_can_base_permissions;
	}

	public function getOptionStoragePrefix() :string {
		return $this->getPluginPrefix( '_' ).'_';
	}

	public function getPluginPrefix( string $glue = '-' ) :string {
		return sprintf( '%s%s%s', $this->cfg->properties[ 'slug_parent' ], $glue, $this->cfg->properties[ 'slug_plugin' ] );
	}

	/**
	 * Default is to take the 'Name' from the labels section but can override with "human_name" from property section.
	 * @return string
	 */
	public function getHumanName() {
		return $this->labels->Name;
	}

	public function getIsPage_PluginAdmin() :bool {
		return \strpos( Services::WpGeneral()->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0;
	}

	public function getPath_Languages() :string {
		return trailingslashit( \path_join( $this->getRootDir(), $this->cfg->paths[ 'languages' ] ) );
	}

	public function getPath_Templates() :string {
		return trailingslashit( \path_join( $this->getRootDir(), $this->cfg->paths[ 'templates' ] ) );
	}

	public function getRootDir() :string {
		return trailingslashit( \dirname( $this->getRootFile() ) );
	}

	public function getRootFile() :string {
		if ( empty( $this->root_file ) ) {
			$VO = ( new \FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files() )->findPluginFromFile( __FILE__ );
			if ( $VO instanceof \FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo ) {
				$this->root_file = path_join( WP_PLUGIN_DIR, $VO->file );
			}
			else {
				$this->root_file = __FILE__;
			}
		}
		return $this->root_file;
	}

	public function getTextDomain() :string {
		return $this->cfg->properties[ 'text_domain' ];
	}

	public function isPremiumActive() :bool {
		return isset( $this->modules[ License\ModCon::SLUG ] )
			   && $this->getModule_License()->getLicenseHandler()->hasValidWorkingLicense();
	}

	protected function saveCurrentPluginControllerOptions() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );

		if ( $this->plugin_deleting ) {
			Services::WpGeneral()->deleteOption( $this->getConfigStoreKey() );
			Transient::Delete( $this->getConfigStoreKey() );
		}
		elseif ( isset( $this->cfg ) ) {
			Services::WpGeneral()->updateOption( $this->getConfigStoreKey(), $this->cfg->getRawData() );
		}
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
	}

	private function getConfigStoreKey() :string {
		return 'aptoweb_controller_'.\substr( \md5( \get_class( $this ) ), 0, 6 );
	}

	public function setFlag( string $flag, $value ) {
		$flags = $this->flags;
		$flags[ $flag ] = $value;
		$this->flags = $flags;
	}

	/**
	 * @return Base\ModCon|null|mixed
	 */
	public function getModule( string $slug ) {
		return $this->modules[ $slug ] ?? null;
	}

	public function getModule_AuditTrail() :AuditTrail\ModCon {
		return $this->modules[ AuditTrail\ModCon::SLUG ];
	}

	public function getModule_Autoupdates() :Autoupdates\ModCon {
		return $this->modules[ Autoupdates\ModCon::SLUG ];
	}

	public function getModule_Comments() :CommentsFilter\ModCon {
		return $this->modules[ CommentsFilter\ModCon::SLUG ];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getModule_Comms() :Comms\ModCon {
		return $this->getModule( Comms\ModCon::SLUG );
	}

	public function getModule_Data() :Data\ModCon {
		return $this->modules[ Data\ModCon::SLUG ];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getModule_Events() :Events\ModCon {
		return $this->getModule( Events\ModCon::SLUG );
	}

	public function getModule_Firewall() :Firewall\ModCon {
		return $this->modules[ Firewall\ModCon::SLUG ];
	}

	public function getModule_Lockdown() :Lockdown\ModCon {
		return $this->modules[ Lockdown\ModCon::SLUG ];
	}

	public function getModule_HackGuard() :HackGuard\ModCon {
		return $this->modules[ HackGuard\ModCon::SLUG ];
	}

	public function getModule_Headers() :Headers\ModCon {
		return $this->modules[ Headers\ModCon::SLUG ];
	}

	public function getModule_Integrations() :Integrations\ModCon {
		return $this->modules[ Integrations\ModCon::SLUG ];
	}

	public function getModule_IPs() :IPs\ModCon {
		return $this->modules[ IPs\ModCon::SLUG ];
	}

	public function getModule_License() :License\ModCon {
		return $this->modules[ License\ModCon::SLUG ];
	}

	public function getModule_LoginGuard() :LoginGuard\ModCon {
		return $this->modules[ LoginGuard\ModCon::SLUG ];
	}

	public function getModule_Plugin() :Plugin\ModCon {
		return $this->modules[ Plugin\ModCon::SLUG ];
	}

	public function getModule_SecAdmin() :SecurityAdmin\ModCon {
		return $this->modules[ SecurityAdmin\ModCon::SLUG ];
	}

	public function getModule_Traffic() :Traffic\ModCon {
		return $this->modules[ Traffic\ModCon::SLUG ];
	}

	public function getModule_UserManagement() :UserManagement\ModCon {
		return $this->modules[ UserManagement\ModCon::SLUG ];
	}

	public function getRenderer() :\FernleafSystems\Wordpress\Services\Utilities\Render {
		$render = Services::Render();
		foreach ( ( new Shield\Render\LocateTemplateDirs() )->run() as $dir ) {
			$render->setTwigTemplateRoot( $dir );
		}
		$render->setTemplateRoot( $this->getPath_Templates() );
		return $render;
	}

	private function labels() :Config\Labels {
		$labels = \array_map( '\stripslashes', $this->cfg->labels );

		foreach (
			[
				'icon_url_16x16',
				'icon_url_32x32',
				'icon_url_128x128',
				'url_img_pagebanner',
				'url_img_logo_small',
			] as $img
		) {
			if ( !empty( $labels[ $img ] ) && !Services::Data()->isValidWebUrl( $labels[ $img ] ) ) {
				$labels[ $img ] = $this->urls->forImage( $labels[ $img ] );
			}
		}

		$labels = ( new Config\Labels() )->applyFromArray( $labels );
		$labels->url_secadmin_forgotten_key = 'https://shsec.io/gc';
		$labels->url_helpdesk = 'https://shsec.io/shieldhelpdesk';
		$labels->is_whitelabelled = false;

		return $this->isPremiumActive() ? apply_filters( $this->prefix( 'labels' ), $labels ) : $labels;
	}
}