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
	HackGuard,
	Integrations,
	IPs,
	License,
	LoginGuard,
	Plugin,
	Plugin\Lib\Ops\ResetPlugin,
	SecurityAdmin
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

/**
 * @property Config\ConfigVO                          $cfg
 * @property Config\OptsHandler                       $opts
 * @property Shield\Rules\RulesController             $rules
 * @property ActionRoutingController                  $action_router
 * @property Shield\Components\ComponentLoader        $comps
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
 * @property bool                                     $modules_loaded
 * @property bool                                     $plugin_deactivating
 * @property bool                                     $plugin_deleting
 * @property bool                                     $plugin_reset
 * @property Shield\Utilities\CacheDirHandler         $cache_dir_handler
 * @property bool                                     $user_can_base_permissions
 * @property string                                   $base_file
 * @property string                                   $root_file
 * @property Integrations\Lib\MainWP\Common\MainWPVO  $mwpVO
 * @property Shield\Users\UserMetas                   $user_metas
 * @property ModConfigVO[]|mixed                      $modules
 * @property Shield\Crons\HourlyCron                  $cron_hourly
 * @property Shield\Crons\DailyCron                   $cron_daily
 * @property string[]                                 $reqs_not_met
 */
class Controller extends DynPropertiesClass {

	public static Controller $oInstance;

	public ?Plugin\ModCon $plugin = null;

	/**
	 * @deprecated 19.2
	 */
	public function fireEvent( string $event, array $meta = [] ) :self {
		$this->comps->events->fireEvent( $event, $meta );
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
				if ( $val === null ) {
					$this->caps = $val = new License\Lib\Capabilities();
				}
				break;

			case 'flags':
				if ( !\is_array( $val ) ) {
					$this->flags = $val = [];
				}
				break;

			case 'labels':
				if ( $val === null ) {
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

			case 'admin_notices':
				if ( empty( $val ) ) {
					$this->admin_notices = $val = new Shield\Utilities\AdminNotices\Controller();
				}
				break;

			case 'comps':
				if ( empty( $val ) ) {
					$this->comps = $val = new Shield\Components\ComponentLoader();
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
			$this->comps
				->render
				->setTemplate( '/notices/does-not-meet-requirements.twig' )
				->setData( [
					'strings' => [
						'not_met'          => 'Shield Security Plugin - minimum site requirements are not met',
						'requirements'     => $this->reqs_not_met,
						'summary_title'    => "Your web hosting doesn't meet the minimum requirements for the Shield Security Plugin.",
						'recommend'        => "We highly recommend upgrading your web hosting components as they're probably quite out-of-date.",
						'more_information' => 'Click here for more information on requirements'
					],
					'hrefs'   => [
						'more_information' => 'https://clk.shldscrty.com/shieldsystemrequirements'
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
		if ( $this->comps->mu->isActiveMU() && !Services::WpPlugins()->isActive( $this->base_file ) ) {
			Services::WpPlugins()->activate( $this->base_file );
		}
		$this->loadConfig();
		$this->checkMinimumRequirements();

		( new Shield\Controller\I18n\LoadTextDomain() )->run();

		$this->extensions_controller->execute();

		$this->loadModules();

		if ( $this->plugin_reset ) {
			( new ResetPlugin() )->run();
		}

		$this->prechecks = ( new Checks\PreModulesBootCheck() )->run();
		$this->db_con->execute();
		$this->comps->execute();

		( new Updates\HandleUpgrade() )->execute();
	}

	/**
	 * @throws \Exception
	 */
	private function loadModules() {
		if ( !$this->modules_loaded ) {

			$configuration = $this->cfg->configuration;
			if ( empty( $configuration ) || $this->cfg->rebuilt ) {
				$this->cfg->configuration = ( new Config\Modules\LoadModuleConfigs() )->run();
			}

			// Extensions jump in here to augment options/sections
			do_action( 'shield/modules_configuration' );

			$this->modules_loaded = true;

			$this->doRegisterHooks();

			$this->plugin = new Plugin\ModCon();
			$this->plugin->boot();
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
		( new Shield\Controller\Plugin\PluginDelete() )->run();
	}

	/**
	 * @throws \TypeError - Potentially. Not sure how the plugin hasn't initiated by that stage.
	 */
	public function onWpActivatePlugin() {
		$this->opts->optSet( 'activated_at', Services::Request()->ts() );
		$this->is_activating = true;
		do_action( 'shield/plugin_activated' );
	}

	protected function doRegisterHooks() {
		register_deactivation_hook( $this->getRootFile(), [ $this, 'onWpDeactivatePlugin' ] );

		add_action( 'after_setup_theme', [ $this, 'onWpAfterSetupTheme' ], \PHP_INT_MIN );
		add_action( 'init', [ $this, 'onWpInit' ], Shield\Controller\Plugin\HookTimings::INIT_MAIN_CONTROLLER );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 5 );
		add_action( 'admin_init', [ $this, 'onWpAdminInit' ] );
		add_action( 'shutdown', [ $this, 'onWpShutdown' ], \PHP_INT_MIN );

		$this->plugin_labels->execute();

		/**
		 * Support for WP-CLI and it marks the cli as plugin admin
		 */
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), function ( $byPass ) {
			return $byPass || ( Services::WpGeneral()->isWpCli() && $this->isPremiumActive() );
		}, \PHP_INT_MAX );
	}

	public function onWpAfterSetupTheme() {
		$this->execRules();
	}

	/**
	 * Since WP 6.7+ the earliest we can run this is 'after_setup_theme' due to the changes with translations.
	 */
	private function execRules() {
		// Should execute after modules have initiated
		$this->rules = new Shield\Rules\RulesController();
		$this->rules
			->setThisRequest( $this->this_req )
			->execute();

		if ( !$this->cfg->rebuilt ) {
			try {
				$this->rules->processRules();
				$this->plugin->getProcessor()->execute();
			}
			catch ( \Exception $e ) {
			}
			// This is where any rules responses will execute (i.e. after processors are run):
			do_action( $this->prefix( 'after_run_processors' ) );
		}
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

		( new Assets\Enqueue() )->execute();
		( new Privacy\PrivacyExport() )->execute();
		( new Privacy\PrivacyEraser() )->execute();
	}

	public function onWpLoaded() {
		$this->admin_notices->execute();
		$this->initCrons();
		( new Admin\AdminBarMenu() )->execute();
		( new Updates\CaptureMyUpgrade() )->execute();
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
		$this->cfg->builtHash = \hash( 'md5', \serialize( $this->cfg->getRawData() ) );
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

	public function getPluginPrefix( string $glue = '-' ) :string {
		return sprintf( '%s%s%s', $this->cfg->properties[ 'slug_parent' ], $glue, $this->cfg->properties[ 'slug_plugin' ] );
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

	/**
	 * @throws Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException
	 */
	public function includePrefixedVendor() :void {
		$auto = path_join( $this->getRootDir(), 'src/lib/vendor_prefixed/autoload.php' );
		if ( !Services::WpFs()->isAccessibleFile( $auto ) ) {
			throw new Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException( 'Prefixed Autoload Missing' );
		}
		require_once( $auto );
	}

	public function isPremiumActive() :bool {
		return $this->comps->license->hasValidWorkingLicense();
	}

	protected function saveCurrentPluginControllerOptions() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );

		if ( $this->plugin_deleting ) {
			Services::WpGeneral()->deleteOption( $this->getConfigStoreKey() );
			Transient::Delete( $this->getConfigStoreKey() );
		}
		elseif ( isset( $this->cfg ) ) {
			$serial = \serialize( $this->cfg->getRawData() );
			if ( empty( $this->cfg->builtHash ) || !\hash_equals( $this->cfg->builtHash, \hash( 'md5', $serial ) ) ) {
				$data = $this->cfg->getRawData();
				if ( \function_exists( '\gzdeflate' ) && \function_exists( '\gzinflate' ) ) {
					$zip = @\gzdeflate( $serial );
					if ( !empty( $zip ) && \gzinflate( $zip ) === $serial ) {
						$enc = \base64_encode( $zip );
						if ( !empty( $enc ) ) {
							$data = $enc;
						}
					}
				}
				Services::WpGeneral()->updateOption( $this->getConfigStoreKey(), $data );
			}
		}
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
	}

	private function getConfigStoreKey() :string {
		return 'aptoweb_controller_'.\substr( \hash( 'md5', \get_class( $this ) ), 0, 6 );
	}

	public function modCfg( string $slug = '' ) :ModConfigVO {
		$modules = $this->modules ?? [];
		if ( !isset( $modules[ $slug ] ) ) {
			$configuration = $this->cfg->configuration;
			$cfg = new ModConfigVO();
			$cfg->slug = $slug;
			$cfg->properties = $configuration->modules[ $slug ];
			$cfg->sections = $configuration->sectionsForModule( $cfg->slug );
			$cfg->options = $configuration->optsForModule( $cfg->slug );
			$modules[ $slug ] = $cfg;
			$this->modules = $modules;
		}
		return $modules[ $slug ];
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
		$labels->url_secadmin_forgotten_key = 'https://clk.shldscrty.com/gc';
		$labels->url_helpdesk = 'https://clk.shldscrty.com/shieldhelpdesk';
		$labels->is_whitelabelled = false;

		return $this->isPremiumActive() ? apply_filters( $this->prefix( 'labels' ), $labels ) : $labels;
	}

	/**
	 * Default is to take the 'Name' from the labels section but can override with "human_name" from property section.
	 * @return string
	 * @deprecated 20.1
	 */
	public function getHumanName() {
		return $this->labels->Name;
	}
}