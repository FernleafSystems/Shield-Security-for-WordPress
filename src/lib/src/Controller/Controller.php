<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDeactivate;
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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config\LoadConfig;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

/**
 * @property Config\ConfigVO                         $cfg
 * @property Config\OptsHandler                      $opts
 * @property ActionRoutingController                 $action_router
 * @property ExtensionsCon                           $extensions_controller
 * @property Database\DbCon                          $db_con
 * @property Email\EmailCon                          $email_con
 * @property Shield\Controller\Plugin\PluginURLs     $plugin_urls
 * @property Shield\Controller\Assets\Urls           $urls
 * @property Shield\Controller\Assets\Paths          $paths
 * @property Shield\Controller\Assets\Svgs           $svgs
 * @property Shield\Request\ThisRequest              $this_req
 * @property Shield\Modules\License\Lib\Capabilities $caps
 * @property Config\Labels                           $labels
 * @property array                                   $prechecks
 * @property array                                                  $flags
 * @property bool                                                   $is_activating
 * @property bool                                                   $is_mode_debug
 * @property bool                                                   $is_mode_staging
 * @property bool                                                   $is_mode_live
 * @property bool                                                   $is_my_upgrade
 * @property bool                                                   $is_rest_enabled
 * @property bool                                                   $modules_loaded
 * @property bool                                                   $plugin_deactivating
 * @property bool                                                   $plugin_deleting
 * @property bool                                                   $plugin_reset
 * @property Shield\Utilities\CacheDirHandler                       $cache_dir_handler
 * @property bool                                                   $user_can_base_permissions
 * @property string                                                 $base_file
 * @property string                                                 $root_file
 * @property Shield\Modules\Integrations\Lib\MainWP\Common\MainWPVO $mwpVO
 * @property Shield\Rules\RulesController                           $rules
 * @property Shield\Utilities\MU\MUHandler                          $mu_handler
 * @property Shield\Modules\Events\Lib\EventsService                $service_events
 * @property Shield\Users\UserMetas                                 $user_metas
 * @property Shield\Modules\Base\ModCon[]                           $modules
 * @property Shield\Crons\HourlyCron                                $cron_hourly
 * @property Shield\Crons\DailyCron                                 $cron_daily
 * @property string[]                                               $reqs_not_met
 */
class Controller extends DynPropertiesClass {

	/**
	 * @var Controller
	 */
	public static $oInstance;

	/**
	 * @var string
	 */
	protected static $sSessionId;

	/**
	 * @var string
	 */
	protected $sAdminNoticeError = '';

	/**
	 * @var Shield\Utilities\AdminNotices\Controller
	 */
	protected $oNotices;

	public function fireEvent( string $event, array $meta = [] ) :self {
		$this->loadEventsService()->fireEvent( $event, $meta );
		return $this;
	}

	public function loadEventsService() :Shield\Modules\Events\Lib\EventsService {
		return $this->service_events ?? $this->service_events = new Shield\Modules\Events\Lib\EventsService();
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

		$FS = Services::WpFs();

		switch ( $key ) {

			case 'caps':
				if ( \is_null( $val ) ) {
					$this->caps = $val = new Shield\Modules\License\Lib\Capabilities();
				}
				break;

			case 'flags':
				if ( !\is_array( $val ) ) {
					$val = [];
					$this->flags = $val;
				}
				break;

			case 'labels':
				if ( \is_null( $val ) ) {
					$val = $this->labels();
					$this->labels = $val;
				}
				break;

			case 'db_con':
				if ( !$val instanceof Database\DbCon ) {
					$val = new Database\DbCon();
					$this->db_con = $val;
				}
				break;

			case 'email_con':
				if ( !$val instanceof Email\EmailCon ) {
					$val = new Email\EmailCon();
					$this->email_con = $val;
				}
				break;

			case 'extensions_controller':
				if ( !$val instanceof ExtensionsCon ) {
					$this->extensions_controller = $val = new ExtensionsCon();
				}
				break;

			case 'opts':
				if ( !$val instanceof Config\OptsHandler ) {
					$val = new Config\OptsHandler();
					$this->opts = $val;
				}
				break;

			case 'this_req':
				if ( \is_null( $val ) ) {
					$val = new Shield\Request\ThisRequest();
					$this->this_req = $val;
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
					$val = $FS->isAccessibleFile( $this->paths->forFlag( 'reset' ) );
					$this->plugin_reset = $val;
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
				if ( \is_null( $val ) ) {
					$val = ( new Shield\Controller\Modes\DebugMode() )->isModeActive();
					$this->is_mode_debug = $val;
				}
				break;

			case 'is_mode_live':
				if ( \is_null( $val ) ) {
					$val = $this->is_mode_live = !$this->is_mode_staging && !$this->is_mode_debug;
				}
				break;

			case 'is_mode_staging':
				if ( \is_null( $val ) ) {
					$val = ( new Shield\Controller\Modes\StagingMode() )->isModeActive();
					$this->is_mode_staging = $val;
				}
				break;

			case 'mu_handler':
				if ( \is_null( $val ) ) {
					$val = new Shield\Utilities\MU\MUHandler();
					$this->mu_handler = $val;
				}
				break;

			case 'action_router':
				if ( \is_null( $val ) ) {
					$val = new Shield\ActionRouter\ActionRoutingController();
					$this->action_router = $val;
				}
				break;

			case 'plugin_urls':
				if ( !$val instanceof Shield\Controller\Plugin\PluginURLs ) {
					$val = new Shield\Controller\Plugin\PluginURLs();
					$this->plugin_urls = $val;
				}
				break;

			case 'paths':
				if ( !$val instanceof Shield\Controller\Assets\Paths ) {
					$val = new Shield\Controller\Assets\Paths();
					$this->paths = $val;
				}
				break;

			case 'svgs':
				if ( !$val instanceof Shield\Controller\Assets\Svgs ) {
					$val = new Shield\Controller\Assets\Svgs();
					$this->svgs = $val;
				}
				break;

			case 'urls':
				if ( !$val instanceof Shield\Controller\Assets\Urls ) {
					$val = new Shield\Controller\Assets\Urls();
					$this->urls = $val;
				}
				break;

			case 'reqs_not_met':
				if ( !\is_array( $val ) ) {
					$val = [];
					$this->reqs_not_met = $val;
				}
				break;

			case 'user_metas':
				if ( empty( $val ) ) {
					$val = new Shield\Users\UserMetas();
					$this->user_metas = $val;
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
				$reqsMsg[] = sprintf( 'PHP does not meet minimum version. Your version: %s.  Required Version: %s.', PHP_VERSION, $minPHP );
			}

			$wp = $this->cfg->requirements[ 'wordpress' ];
			if ( !empty( $wp ) && \version_compare( Services::WpGeneral()->getVersion( true ), $wp, '<' ) ) {
				$reqsMsg[] = sprintf( 'WordPress does not meet minimum version. Required Version: %s.', $wp );
			}

			$mysql = $this->cfg->requirements[ 'mysql' ];
			if ( !empty( $mysql ) && !$this->isMysqlVersionSupported( $mysql ) ) {
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

	/**
	 * Supported if:
	 * - the mysql version is at least the minimum version
	 * - OR: it's mariaDB and it doesn't match the pattern: 5.5.xx-MariaDB
	 * - OR: we can find the function 'INET6_ATON'
	 */
	private function isMysqlVersionSupported( string $versionToSupport ) :bool {
		$mysqlInfo = Services::WpDb()->getMysqlServerInfo();
		$supported = empty( $versionToSupport )
					 || empty( $mysqlInfo )
					 || \version_compare( \preg_replace( '/[^\d.].*/', '', $mysqlInfo ), $versionToSupport, '>=' )
					 || ( \stripos( $mysqlInfo, 'MariaDB' ) !== false && !\preg_match( '#5.5.\d+-MariaDB#i', $mysqlInfo ) );

		if ( !$supported ) {
			$miscFunctions = Services::WpDb()->selectCustom( "HELP miscellaneous_functions" );
			if ( \is_array( $miscFunctions ) ) {
				foreach ( $miscFunctions as $fn ) {
					if ( \is_array( $fn ) && \strtoupper( $fn[ 'name' ] ?? '' ) === 'INET6_ATON' ) {
						$supported = true;
						break;
					}
				}
			}
		}
		return $supported;
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

		// Upgrade modules
		( new Shield\Controller\Utilities\Upgrade() )->execute();

		$this->rules = new Shield\Rules\RulesController();
		$this->rules->execute();

		if ( !$this->cfg->rebuilt && $this->rules->isRulesEngineReady() ) {

			$this->rules->processRules();

			foreach ( $this->modules as $module ) {
				$module->onRunProcessors();
			}

			$this->labels; // Ensures labels are created.

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

			$enum = [
				SecurityAdmin\ModCon::class,
				AuditTrail\ModCon::class,
				Autoupdates\ModCon::class,
				CommentsFilter\ModCon::class,
				Comms\ModCon::class,
				Data\ModCon::class,
				Events\ModCon::class,
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
			foreach ( $this->cfg->mods_cfg as $cfg ) {

				$slug = $cfg->properties[ 'slug' ];
				$theModClass = null;
				foreach ( $enum as $key => $modClass ) {
					/** @var string|Base\ModCon $modClass */
					if ( @\class_exists( $modClass ) && $slug === $modClass::SLUG ) {
						$theModClass = $modClass;
						unset( $enum[ $key ] );
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
			do_action( $this->prefix( 'deactivate_plugin' ) );

			( new PluginDeactivate() )->execute();

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

		add_filter( 'all_plugins', [ $this, 'doPluginLabels' ] );
		add_filter( 'site_transient_update_plugins', [ $this, 'blockIncompatibleUpdates' ] );
		add_filter( 'auto_update_plugin', [ $this, 'onWpAutoUpdate' ], 500, 2 );
		add_filter( 'set_site_transient_update_plugins', [ $this, 'setUpdateFirstDetectedAt' ] );

		add_action( 'shutdown', [ $this, 'onWpShutdown' ], \PHP_INT_MIN );

		// GDPR
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'onWpPrivacyRegisterExporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'onWpPrivacyRegisterEraser' ] );

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
		( new Admin\DashboardWidget() )->execute();
		( new Admin\PluginsPageSupplements() )->execute();

		if ( !empty( $this->modules_loaded ) && !Services::WpGeneral()->isAjax()
			 && \function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( $this->getHumanName(), $this->buildPrivacyPolicyContent() );
		}
	}

	/**
	 * In order to prevent certain errors when the back button is used
	 * @param array $headers
	 * @return array
	 */
	public function adjustNocacheHeaders( $headers ) {
		if ( \is_array( $headers ) && !empty( $headers[ 'Cache-Control' ] ) ) {
			$Hs = \array_map( '\trim', \explode( ',', $headers[ 'Cache-Control' ] ) );
			$Hs[] = 'no-store';
			$headers[ 'Cache-Control' ] = \implode( ', ', \array_unique( $Hs ) );
		}
		return $headers;
	}

	public function onWpInit() {
		$this->getMeetsBasePermissions();
		if ( $this->isPluginAdminPageRequest() ) {
			add_filter( 'nocache_headers', [ $this, 'adjustNocacheHeaders' ] );
		}

		$this->action_router->execute();

		try {
			$this->action_router->action( Actions\PluginAdmin\PluginAdminPageHandler::class, \array_merge(
				Services::Request()->query,
				Services::Request()->post
			) );
		}
		catch ( ActionException $e ) {
		}

		( new Shield\Controller\Assets\Enqueue() )->execute();
	}

	/**
	 * @return array{id: string, ts: int, install_at: int}
	 */
	public function getInstallationID() :array {
		$WP = Services::WpGeneral();
		$urlParts = wp_parse_url( $WP->getWpUrl() );
		$url = $urlParts[ 'host' ].\trim( $urlParts[ 'path' ] ?? '', '/' );
		$optKey = $this->prefixOption( 'shield_site_id' );

		$IDs = $WP->getOption( $optKey );
		if ( !\is_array( $IDs ) ) {
			$IDs = [];
		}
		if ( empty( $IDs[ $url ] ) || !\is_array( $IDs[ $url ] ) ) {
			$IDs[ $url ] = [];
		}

		if ( empty( $IDs[ $url ][ 'id' ] ) || !\Ramsey\Uuid\Uuid::isValid( $IDs[ $url ][ 'id' ] ) ) {
			$id = ( new \FernleafSystems\Wordpress\Services\Utilities\Uuid() )->V4();
			$IDs[ $url ] = [
				'id'         => \strtolower( $id ),
				'ts'         => Services::Request()->ts(),
				'install_at' => $this->getModule_Plugin()->storeRealInstallDate(),
			];
			$WP->updateOption( $optKey, $IDs );
		}

		return $IDs[ $url ];
	}

	public function onWpLoaded() {
		$this->getInstallationID();
		$this->getAdminNotices();
		$this->initCrons();
		( new Utilities\CaptureMyUpgrade() )->execute();
		( new Admin\AdminBarMenu() )->execute();
	}

	protected function initCrons() {
		$this->cron_hourly = new Shield\Crons\HourlyCron();
		$this->cron_hourly->execute();
		$this->cron_daily = new Shield\Crons\DailyCron();
		$this->cron_daily->execute();

		( new Shield\Utilities\RootHtaccess() )->execute();
	}

	/**
	 * @return Shield\Utilities\AdminNotices\Controller
	 */
	public function getAdminNotices() :Shield\Utilities\AdminNotices\Controller {
		if ( !isset( $this->oNotices ) ) {
			if ( $this->getIsPage_PluginAdmin() ) {
				remove_all_filters( 'admin_notices' );
				remove_all_filters( 'network_admin_notices' );
			}
			$this->oNotices = new Shield\Utilities\AdminNotices\Controller();
		}
		return $this->oNotices;
	}

	/**
	 * Prevents upgrades to Shield versions when the system PHP version is too old.
	 * @param \stdClass $updates
	 * @return \stdClass
	 */
	public function blockIncompatibleUpdates( $updates ) {
		$file = $this->base_file;
		if ( !empty( $updates->response ) && isset( $updates->response[ $file ] ) ) {
			$reqs = $this->cfg->upgrade_reqs;
			if ( \is_array( $reqs ) ) {
				foreach ( $reqs as $shieldVer => $verReqs ) {
					$toHide = \version_compare( $updates->response[ $file ]->new_version, $shieldVer, '>=' )
							  && (
								  !Services::Data()->getPhpVersionIsAtLeast( (string)$verReqs[ 'php' ] )
								  || !Services::WpGeneral()->getWordpressIsAtLeastVersion( $verReqs[ 'wp' ] )
								  || ( !empty( $verReqs[ 'mysql' ] ) && !$this->isMysqlVersionSupported( $verReqs[ 'mysql' ] ) )
							  );
					if ( $toHide ) {
						unset( $updates->response[ $file ] );
						break;
					}
				}
			}
		}
		return $updates;
	}

	/**
	 * This will hook into the saving of plugin update information and if there is an update for this plugin, it'll add
	 * a data stamp to state when the update was first detected.
	 * @param \stdClass $data
	 * @return \stdClass
	 */
	public function setUpdateFirstDetectedAt( $data ) {

		if ( !empty( $data ) && !empty( $data->response ) && isset( $data->response[ $this->base_file ] ) ) {
			// i.e. update available

			$new = Services::WpPlugins()->getUpdateNewVersion( $this->base_file );
			if ( !empty( $new ) && isset( $this->cfg ) ) {
				$updates = $this->cfg->update_first_detected;
				if ( \count( $updates ) > 3 ) {
					$updates = [];
				}
				if ( !isset( $updates[ $new ] ) ) {
					$updates[ $new ] = Services::Request()->ts();
				}
				$this->cfg->update_first_detected = $updates;
			}
		}

		return $data;
	}

	/**
	 * This is a filter method designed to say whether WordPress plugin upgrades should be permitted,
	 * based on the plugin settings.
	 * @param bool          $isAutoUpdate
	 * @param string|object $mItem
	 * @return bool
	 */
	public function onWpAutoUpdate( $isAutoUpdate, $mItem ) {
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();

		$file = $WP->getFileFromAutomaticUpdateItem( $mItem );

		// The item in question is this plugin...
		if ( $file === $this->base_file ) {
			$autoUpdateSelf = $this->cfg->properties[ 'autoupdate' ];

			if ( !$WP->isRunningAutomaticUpdates() && $autoUpdateSelf == 'confidence' ) {
				$autoUpdateSelf = 'yes'; // so that we appear to be automatically updating
			}

			$new = $WPP->getUpdateNewVersion( $file );

			switch ( $autoUpdateSelf ) {

				case 'yes' :
					$isAutoUpdate = true;
					break;

				case 'block' :
					$isAutoUpdate = false;
					break;

				case 'confidence' :
					$isAutoUpdate = false;
					if ( !empty( $new ) ) {
						$firstDetected = $this->cfg->update_first_detected[ $new ] ?? 0;
						$availableFor = Services::Request()->ts() - $firstDetected;
						$isAutoUpdate = $firstDetected > 0
										&& $availableFor > \DAY_IN_SECONDS*$this->cfg->properties[ 'autoupdate_days' ];
					}
					break;

				case 'pass' :
				default:
					break;
			}
		}
		return $isAutoUpdate;
	}

	/**
	 * @param array $plugins
	 * @return array
	 */
	public function doPluginLabels( $plugins ) {
		$plugins[ $this->base_file ] = \array_merge( $plugins[ $this->base_file ] ?? [], $this->labels->getRawData() );
		return $plugins;
	}

	public function onWpShutdown() {
		do_action( $this->prefix( 'pre_plugin_shutdown' ) );
		$this->opts->commit();
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

	public function prefixOption( string $suffix = '' ) :string {
		return $this->prefix( $suffix, '_' );
	}

	/**
	 * @throws \Exception
	 */
	private function loadConfig() {
		$this->cfg = ( new Config\Ops\LoadConfig( $this->paths->forPluginItem( 'plugin.json' ), $this->getConfigStoreKey() ) )->run();

		$this->plugin_urls;
		$this->loadModConfigs();
		$this->saveCurrentPluginControllerOptions();
	}

	/**
	 * @throws Exceptions\PluginConfigInvalidException
	 */
	private function loadModConfigs() {
		if ( empty( $this->cfg->modules ) ) {
			throw new Exceptions\PluginConfigInvalidException( 'No modules specified in the plugin config.' );
		}

		$modConfigs = empty( $this->cfg->mods_cfg ) ? [] : $this->cfg->mods_cfg;

		// First load all module Configs
		foreach ( $this->cfg->modules as $slug ) {
			try {
				$modCfg = ( new LoadConfig( $slug, $modConfigs[ $slug ] ?? null ) )->run();
			}
			catch ( \Exception $e ) {
				throw new Exceptions\PluginConfigInvalidException( sprintf( "Exception loading config for module '%s': %s",
					$slug, $e->getMessage() ) );
			}

			if ( !isset( $modCfg->properties ) || !\is_array( $modCfg->properties ) ) {
				throw new Exceptions\PluginConfigInvalidException( sprintf( "Loading config for module '%s' failed.", $slug ) );
			}

			$modConfigs[ $slug ] = $modCfg;
		}

		// Order Modules
		\uasort( $modConfigs, function ( $a, $b ) {
			/** @var Base\Config\ModConfigVO $a */
			/** @var Base\Config\ModConfigVO $b */
			if ( $a->properties[ 'load_priority' ] == $b->properties[ 'load_priority' ] ) {
				return 0;
			}
			return ( $a->properties[ 'load_priority' ] < $b->properties[ 'load_priority' ] ) ? -1 : 1;
		} );

		$this->cfg->mods_cfg = $modConfigs;
		// Sanity checking: count to ensure that when we set the cfgs, they were correctly set.
		if ( \count( $this->cfg->getRawData()[ 'mods_cfg' ] ?? [] ) !== \count( $modConfigs ) ) {
			throw new Exceptions\PluginConfigInvalidException( 'Building and storing module configurations failed.' );
		}
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
		return isset( $this->modules[ Shield\Modules\License\ModCon::SLUG ] )
			   && $this->getModule_License()->getLicenseHandler()->hasValidWorkingLicense();
	}

	public function isRelabelled() :bool {
		return (bool)apply_filters( $this->prefix( 'is_relabelled' ), false );
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

	public function deleteForceOffFile() {
		if ( $this->this_req->is_force_off ) {
			$file = Services::WpFs()->findFileInDir( 'forceoff', $this->getRootDir(), false );
			if ( !empty( $file ) ) {
				Services::WpFs()->deleteFile( $file );
				\clearstatcache();
			}
			$this->this_req->is_force_off = false;
		}
	}

	public function setFlag( string $flag, $value ) {
		$flags = $this->flags;
		$flags[ $flag ] = $value;
		$this->flags = $flags;
	}

	/**
	 * @return Shield\Modules\Base\ModCon|null|mixed
	 */
	public function getModule( string $slug ) {
		return $this->modules[ $slug ] ?? null;
	}

	public function getModule_AuditTrail() :Shield\Modules\AuditTrail\ModCon {
		return $this->getModule( Shield\Modules\AuditTrail\ModCon::SLUG );
	}

	public function getModule_Autoupdates() :Shield\Modules\Autoupdates\ModCon {
		return $this->getModule( 'autoupdates' );
	}

	public function getModule_Comments() :Shield\Modules\CommentsFilter\ModCon {
		return $this->getModule( 'comments_filter' );
	}

	public function getModule_Comms() :Shield\Modules\Comms\ModCon {
		return $this->getModule( 'comms' );
	}

	public function getModule_Data() :Shield\Modules\Data\ModCon {
		return $this->getModule( 'data' );
	}

	public function getModule_Events() :Shield\Modules\Events\ModCon {
		return $this->getModule( 'events' );
	}

	public function getModule_Firewall() :Shield\Modules\Firewall\ModCon {
		return $this->getModule( 'firewall' );
	}

	public function getModule_Lockdown() :Shield\Modules\Lockdown\ModCon {
		return $this->getModule( 'lockdown' );
	}

	public function getModule_HackGuard() :Shield\Modules\HackGuard\ModCon {
		return $this->getModule( Shield\Modules\HackGuard\ModCon::SLUG );
	}

	public function getModule_Headers() :Shield\Modules\Headers\ModCon {
		return $this->getModule( 'headers' );
	}

	public function getModule_Integrations() :Shield\Modules\Integrations\ModCon {
		return $this->getModule( 'integrations' );
	}

	public function getModule_IPs() :Shield\Modules\IPs\ModCon {
		return $this->getModule( 'ips' );
	}

	public function getModule_License() :Shield\Modules\License\ModCon {
		return $this->getModule( 'license' );
	}

	public function getModule_LoginGuard() :Shield\Modules\LoginGuard\ModCon {
		return $this->getModule( 'login_protect' );
	}

	public function getModule_Plugin() :Shield\Modules\Plugin\ModCon {
		return $this->getModule( Shield\Modules\Plugin\ModCon::SLUG );
	}

	public function getModule_SecAdmin() :Shield\Modules\SecurityAdmin\ModCon {
		return $this->getModule( 'admin_access_restriction' );
	}

	public function getModule_Traffic() :Shield\Modules\Traffic\ModCon {
		return $this->getModule( 'traffic' );
	}

	public function getModule_UserManagement() :Shield\Modules\UserManagement\ModCon {
		return $this->getModule( 'user_management' );
	}

	public function getModulesNamespace() :string {
		return '\FernleafSystems\Wordpress\Plugin\Shield\Modules';
	}

	public function getRenderer() :\FernleafSystems\Wordpress\Services\Utilities\Render {
		$render = Services::Render();
		foreach ( ( new Shield\Render\LocateTemplateDirs() )->run() as $dir ) {
			$render->setTwigTemplateRoot( $dir );
		}
		$render->setTemplateRoot( $this->getPath_Templates() );
		return $render;
	}

	/**
	 * @param array[] $registered
	 * @return array[]
	 */
	public function onWpPrivacyRegisterExporter( $registered ) {
		if ( !\is_array( $registered ) ) {
			$registered = []; // account for crap plugins that do-it-wrong.
		}

		$registered[] = [
			'exporter_friendly_name' => $this->getHumanName(),
			'callback'               => [ $this, 'wpPrivacyExport' ],
		];
		return $registered;
	}

	/**
	 * @param array[] $registered
	 * @return array[]
	 */
	public function onWpPrivacyRegisterEraser( $registered ) {
		if ( !\is_array( $registered ) ) {
			$registered = []; // account for crap plugins that do-it-wrong.
		}

		$registered[] = [
			'eraser_friendly_name' => $this->getHumanName(),
			'callback'             => [ $this, 'wpPrivacyErase' ],
		];
		return $registered;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function wpPrivacyExport( $email, $page = 1 ) :array {
		$valid = Services::Data()->validEmail( $email )
				 && ( Services::WpUsers()->getUserByEmail( $email ) instanceof \WP_User );
		return [
			'data' => $valid ? apply_filters( $this->prefix( 'wpPrivacyExport' ), [], $email, $page ) : [],
			'done' => true,
		];
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function wpPrivacyErase( $email, $page = 1 ) {
		$valid = Services::Data()->validEmail( $email )
				 && ( Services::WpUsers()->getUserByEmail( $email ) instanceof \WP_User );

		$result = [
			'items_removed'  => $valid,
			'items_retained' => false,
			'messages'       => $valid ? [] : [ 'Email address not valid or does not belong to a user.' ],
			'done'           => true,
		];
		if ( $valid ) {
			$result = apply_filters( $this->prefix( 'wpPrivacyErase' ), $result, $email, $page );
		}
		return $result;
	}

	private function buildPrivacyPolicyContent() :string {
		return wp_kses_post( wpautop(
			$this->action_router->render( Actions\Render\Components\PrivacyPolicy::SLUG ),
			false
		) );
	}

	private function labels() :Config\Labels {
		$labels = \array_map( 'stripslashes', $this->cfg->labels );

		foreach ( [ 'icon_url_16x16', 'icon_url_32x32', 'icon_url_128x128', 'url_img_pagebanner' ] as $img ) {
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