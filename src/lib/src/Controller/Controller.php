<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDeactivate;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

/**
 * Class Controller
 * @package FernleafSystems\Wordpress\Plugin\Shield\Controller
 * @property Config\ConfigVO                                        $cfg
 * @property Shield\Controller\Assets\Urls                          $urls
 * @property Shield\Controller\Assets\Paths                         $paths
 * @property Shield\Controller\Assets\Svgs                          $svgs
 * @property bool                                                   $cache_dir_ready
 * @property bool                                                   $is_activating
 * @property bool                                                   $is_mode_debug
 * @property bool                                                   $is_mode_staging
 * @property bool                                                   $is_mode_live
 * @property bool                                                   $is_my_upgrade
 * @property bool                                                   $modules_loaded
 * @property bool                                                   $plugin_deactivating
 * @property bool                                                   $plugin_deleting
 * @property bool                                                   $plugin_reset
 * @property bool                                                   $rebuild_options
 * @property bool                                                   $user_can_base_permissions
 * @property false|string                                           $file_forceoff
 * @property string                                                 $base_file
 * @property string                                                 $root_file
 * @property Shield\Modules\Integrations\Lib\MainWP\Common\MainWPVO $mwpVO
 * @property Shield\Utilities\Nonce\Handler                         $nonce_handler
 * @property Shield\Modules\Events\Lib\EventsService                $service_events
 * @property array|Shield\Modules\Base\ModCon[]                     $modules
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

	/**
	 * @var Shield\Modules\Events\Lib\EventsService
	 */
	private $oEventsService;

	public function fireEvent( string $event, array $meta = [] ) :self {
		$this->loadEventsService()->fireEvent( $event, $meta );
		return $this;
	}

	/**
	 * @return Shield\Modules\Events\Lib\EventsService
	 */
	public function loadEventsService() {
		if ( !isset( $this->oEventsService ) ) {
			$this->oEventsService = ( new Shield\Modules\Events\Lib\EventsService() )
				->setCon( $this );
			$this->service_events = $this->oEventsService;
		}
		return $this->oEventsService;
	}

	/**
	 * @param string $rootFile
	 * @return Controller
	 * @throws \Exception
	 */
	public static function GetInstance( $rootFile = null ) {
		if ( !isset( static::$oInstance ) ) {
			if ( empty( $rootFile ) ) {
				throw new \Exception( 'Empty root file provided for instantiation' );
			}
			static::$oInstance = new static( $rootFile );
		}
		return static::$oInstance;
	}

	/**
	 * @param string $rootFile
	 * @throws \Exception
	 */
	protected function __construct( string $rootFile ) {
		$this->root_file = $rootFile;
		$this->base_file = plugin_basename( $this->getRootFile() );
		$this->modules = [];

		$this->loadServices();
		$this->loadConfig();

		$this->checkMinimumRequirements();
		$this->doRegisterHooks();

		( new Shield\Controller\I18n\LoadTextDomain() )
			->setCon( $this )
			->run();
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );

		switch ( $key ) {

			case 'cache_dir_ready':
			case 'is_activating':
			case 'is_my_upgrade':
			case 'modules_loaded':
			case 'plugin_deactivating':
			case 'plugin_deleting':
			case 'plugin_reset':
			case 'rebuild_options':
			case 'user_can_base_permissions':
				$val = (bool)$val;
				break;

			case 'cfg':
				if ( !$val instanceof Config\ConfigVO ) {
					$val = $this->loadConfig();
				}
				break;

			case 'urls':
				if ( !$val instanceof Shield\Controller\Assets\Urls ) {
					$val = ( new Shield\Controller\Assets\Urls() )->setCon( $this );
				}
				break;

			case 'svgs':
				if ( !$val instanceof Shield\Controller\Assets\Svgs ) {
					$val = ( new Shield\Controller\Assets\Svgs() )->setCon( $this );
				}
				break;

			case 'paths':
				if ( !$val instanceof Shield\Controller\Assets\Paths ) {
					$val = ( new Shield\Controller\Assets\Paths() )->setCon( $this );
					$this->paths = $val;
				}
				break;

			case 'is_mode_debug':
				if ( is_null( $val ) ) {
					$val = ( new Shield\Controller\Modes\DebugMode() )
						->setCon( $this )
						->isModeActive();
					$this->is_mode_debug = $val;
				}
				break;

			case 'is_mode_live':
				if ( is_null( $val ) ) {
					$val = $this->is_mode_live = !$this->is_mode_staging && $this->is_mode_debug;
				}
				break;

			case 'is_mode_staging':
				if ( is_null( $val ) ) {
					$val = ( new Shield\Controller\Modes\StagingMode() )
						->setCon( $this )
						->isModeActive();
					$this->is_mode_staging = $val;
				}
				break;

			case 'nonce_handler':
				if ( is_null( $val ) ) {
					$val = ( new Shield\Utilities\Nonce\Handler() )
						->setCon( $this );
					$this->nonce_handler = $val;
				}
				break;

			case 'reqs_not_met':
				if ( !is_array( $val ) ) {
					$val = [];
					$this->reqs_not_met = $val;
				}
				break;

			default:
				break;
		}

		return $val;
	}

	/**
	 * @throws \Exception
	 */
	private function loadServices() {
		Services::GetInstance();
	}

	/**
	 * @throws \Exception
	 */
	private function checkMinimumRequirements() {
		$FS = Services::WpFs();

		$flag = $this->paths->forFlag( 'reqs_met.flag' );
		if ( !$FS->isFile( $flag )
			 || Services::Request()->carbon()->subDays( 1 )->timestamp > $FS->getModifiedTime( $flag ) ) {
			$reqsMsg = [];

			$minPHP = $this->cfg->requirements[ 'php' ];
			if ( !empty( $minPHP ) && version_compare( Services::Data()->getPhpVersion(), $minPHP, '<' ) ) {
				$reqsMsg[] = sprintf( 'PHP does not meet minimum version. Your version: %s.  Required Version: %s.', PHP_VERSION, $minPHP );
			}

			$wp = $this->cfg->requirements[ 'wordpress' ];
			if ( !empty( $wp ) && version_compare( Services::WpGeneral()->getVersion( true ), $wp, '<' ) ) {
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

	private function isMysqlVersionSupported( string $versionToSupport ) :bool {
		$mysqlInfo = Services::WpDb()->getMysqlServerInfo();
		$supported = empty( $versionToSupport )
					 || empty( $mysqlInfo )
					 || ( stripos( $mysqlInfo, 'MariaDB' ) !== false )
					 || version_compare( preg_replace( '/[^0-9.].*/', '', $mysqlInfo ), $versionToSupport, '>=' );
		if ( !$supported ) {
			$miscFunctions = Services::WpDb()->selectCustom( "HELP miscellaneous_functions" );
			if ( !empty( $miscFunctions ) && is_array( $miscFunctions ) ) {
				foreach ( $miscFunctions as $func ) {
					if ( strtoupper( $func[ 'name' ] ?? '' ) === 'INET6_ATON' ) {
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
				 ->setTemplate( 'notices/does-not-meet-requirements.twig' )
				 ->setTemplateEngineTwig()
				 ->setRenderVars( [
					 'strings' => [
						 'not_met'          => 'Shield Security Plugin - minimum site requirements are not met',
						 'requirements'     => $this->reqs_not_met,
						 'summary_title'    => sprintf( 'Web Hosting requirements for Plugin "%s" are not met and you should deactivate the plugin.', $this->getHumanName() ),
						 'more_information' => 'Click here for more information on requirements'
					 ],
					 'hrefs'   => [
						 'more_information' => 'https://shsec.io/shieldsystemrequirements'
					 ]
				 ] )
				 ->display();
		}
	}

	public function adminNoticePluginFailedToLoad() {
		$this->getRenderer()
			 ->setTemplate( 'notices/plugin-failed-to-load' )
			 ->setRenderVars( [
				 'strings' => [
					 'summary_title'    => 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.',
					 'more_information' => $this->sAdminNoticeError
				 ]
			 ] )
			 ->display();
	}

	/**
	 * All our module page names are prefixed
	 * @return bool
	 */
	public function isThisPluginModuleRequest() {
		return strpos( Services::Request()->query( 'page' ), $this->prefix() ) === 0;
	}

	/**
	 * @return array
	 */
	protected function getRequirementsMessages() {
		if ( !isset( $this->aRequirementsMessages ) ) {
			$this->aRequirementsMessages = [
				'<h4>Shield Security Plugin - minimum site requirements are not met:</h4>'
			];
		}
		return $this->aRequirementsMessages;
	}

	public function onWpDeactivatePlugin() {
		do_action( $this->prefix( 'pre_deactivate_plugin' ) );
		if ( $this->isPluginAdmin() ) {

			$this->plugin_deactivating = true;
			do_action( $this->prefix( 'deactivate_plugin' ) );

			( new PluginDeactivate() )
				->setCon( $this )
				->execute();

			if ( apply_filters( $this->prefix( 'delete_on_deactivate' ), false ) ) {
				$this->deletePlugin();
			}
		}
	}

	public function deletePlugin() {
		$this->plugin_deleting = true;
		do_action( $this->prefix( 'delete_plugin' ) );
		( new Plugin\PluginDelete() )
			->setCon( $this )
			->execute();
	}

	public function onWpActivatePlugin() {
		$this->is_activating = true;
		$modPlugin = $this->getModule_Plugin();
		if ( $modPlugin instanceof Shield\Modules\Base\ModCon ) {
			$modPlugin->setActivatedAt();
			do_action( 'shield/plugin_activated' );
		}
	}

	public function getPluginCachePath( $cachePath = '' ) :string {
		$cacheDir = ( new Shield\Utilities\CacheDir() )
			->setCon( $this )
			->build();
		return empty( $cacheDir ) ? '' : path_join( $cacheDir, $cachePath );
	}

	public function hasCacheDir() :bool {
		return !empty( $this->getPluginCachePath() );
	}

	protected function doRegisterHooks() {
		register_deactivation_hook( $this->getRootFile(), [ $this, 'onWpDeactivatePlugin' ] );

		add_action( 'init', [ $this, 'onWpInit' ], -1000 );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( 'admin_init', [ $this, 'onWpAdminInit' ] );

		add_filter( 'all_plugins', [ $this, 'filter_hidePluginFromTableList' ] );
		add_filter( 'all_plugins', [ $this, 'doPluginLabels' ] );
		add_filter( 'plugin_action_links_'.$this->base_file, [ $this, 'onWpPluginActionLinks' ], 50 );
		add_filter( 'plugin_row_meta', [ $this, 'onPluginRowMeta' ], 50, 2 );
		add_filter( 'site_transient_update_plugins', [ $this, 'filter_hidePluginUpdatesFromUI' ] );
		add_action( 'in_plugin_update_message-'.$this->base_file, [ $this, 'onWpPluginUpdateMessage' ] );
		add_filter( 'site_transient_update_plugins', [ $this, 'blockIncompatibleUpdates' ] );
		add_filter( 'auto_update_plugin', [ $this, 'onWpAutoUpdate' ], 500, 2 );
		add_filter( 'set_site_transient_update_plugins', [ $this, 'setUpdateFirstDetectedAt' ] );

		add_action( 'shutdown', [ $this, 'onWpShutdown' ], PHP_INT_MIN );
		add_action( 'wp_logout', [ $this, 'onWpLogout' ] );

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
		}, PHP_INT_MAX );
	}

	public function onWpAdminInit() {
		( new Admin\AdminBarMenu() )
			->setCon( $this )
			->execute();
		( new Admin\DashboardWidget() )
			->setCon( $this )
			->execute();

		if ( Services::Request()->query( $this->prefix( 'runtests' ) ) && $this->isPluginAdmin() ) {
			$this->runTests();
		}

		if ( !empty( $this->modules_loaded ) && !Services::WpGeneral()->isAjax()
			 && function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( $this->getHumanName(), $this->buildPrivacyPolicyContent() );
		}
	}

	/**
	 * In order to prevent certain errors when the back button is used
	 * @param array $headers
	 * @return array
	 */
	public function adjustNocacheHeaders( $headers ) {
		if ( is_array( $headers ) && !empty( $headers[ 'Cache-Control' ] ) ) {
			$Hs = array_map( 'trim', explode( ',', $headers[ 'Cache-Control' ] ) );
			$Hs[] = 'no-store';
			$headers[ 'Cache-Control' ] = implode( ', ', array_unique( $Hs ) );
		}
		return $headers;
	}

	public function onWpInit() {
		$this->getMeetsBasePermissions();
		if ( $this->isModulePage() ) {
			add_filter( 'nocache_headers', [ $this, 'adjustNocacheHeaders' ] );
		}
		$this->processShieldNonceActions();
		( new Ajax\Init() )
			->setCon( $this )
			->execute();
	}

	private function processShieldNonceActions() {
		$shieldNonceAction = $this->getShieldNonceAction();
		$shieldNonce = Services::Request()->request( 'shield_nonce' );
		if ( !empty( $shieldNonceAction ) && !empty( $shieldNonce ) ) {
			$shieldNonce = Services::Request()->request( 'shield_nonce' );
			if ( $this->nonce_handler->verify( $shieldNonceAction, $shieldNonce ) ) {
				do_action( $this->prefix( 'shield_nonce_action' ), $shieldNonceAction );
			}
			else {
				wp_die( 'It appears that this action and nonce has expired. Please retry the action.' );
			}
		}
	}

	/**
	 * Only set to rebuild as required if you're doing so at the same point in the WordPress load each time.
	 * Certain plugins can modify the ID at different points in the load.
	 * @return string - the unique, never-changing site install ID.
	 */
	public function getSiteInstallationId() {
		$WP = Services::WpGeneral();
		$optKey = $this->prefixOption( 'install_id' );

		$mStoredID = $WP->getOption( $optKey );
		if ( is_array( $mStoredID ) && !empty( $mStoredID[ 'id' ] ) ) {
			$ID = $mStoredID[ 'id' ];
			$update = true;
		}
		elseif ( is_string( $mStoredID ) && strpos( $mStoredID, ':' ) ) {
			$ID = explode( ':', $mStoredID, 2 )[ 1 ];
			$update = true;
		}
		else {
			$ID = $mStoredID;
			$update = false;
		}

		if ( empty( $ID ) || !is_string( $ID ) || ( strlen( $ID ) !== 40 && !\Ramsey\Uuid\Uuid::isValid( $ID ) ) ) {
			try {
				$ID = \Ramsey\Uuid\Uuid::uuid4()->toString();
			}
			catch ( \Exception $e ) {
				$ID = sha1( uniqid( $WP->getHomeUrl( '', true ), true ) );
			}
			$update = true;
		}

		if ( $update ) {
			$WP->updateOption( $optKey, $ID );
		}

		return $ID;
	}

	public function onWpLoaded() {
		$this->getSiteInstallationId();
		$this->getAdminNotices();
		$this->initCrons();
		( new Shield\Controller\Assets\Enqueue() )
			->setCon( $this )
			->execute();
		( new Utilities\CaptureMyUpgrade() )
			->setCon( $this )
			->execute();

		if ( is_admin() || is_network_admin() ) {
			( new Admin\MainAdminMenu() )
				->setCon( $this )
				->execute();
		}
	}

	protected function initCrons() {
		$this->cron_hourly = ( new Shield\Crons\HourlyCron() )->setCon( $this );
		$this->cron_hourly->execute();
		$this->cron_daily = ( new Shield\Crons\DailyCron() )->setCon( $this );
		$this->cron_daily->execute();

		( new Shield\Utilities\Htaccess\RootHtaccess() )
			->setCon( $this )
			->execute();
	}

	/**
	 * @return Shield\Utilities\AdminNotices\Controller
	 */
	public function getAdminNotices() {
		if ( !isset( $this->oNotices ) ) {
			if ( $this->getIsPage_PluginAdmin() ) {
				remove_all_filters( 'admin_notices' );
				remove_all_filters( 'network_admin_notices' );
			}
			$this->oNotices = ( new Shield\Utilities\AdminNotices\Controller() )->setCon( $this );
		}
		return $this->oNotices;
	}

	/**
	 * @param string $action
	 * @return array
	 * @throws \Exception
	 */
	public function getNonceActionData( string $action = '' ) :array {
		if ( empty( $action ) ) {
			throw new \Exception( 'Empty actions are not allowed.' );
		}
		return [
			'action'     => $this->prefix(), //wp ajax doesn't work without this.
			'exec'       => $action,
			'exec_nonce' => wp_create_nonce( $action ),
			//			'rand'       => wp_rand( 10000, 99999 )
		];
	}

	/**
	 * @param array  $pluginMeta
	 * @param string $pluginFile
	 * @return array
	 */
	public function onPluginRowMeta( $pluginMeta, $pluginFile ) {

		if ( $pluginFile === $this->base_file ) {
			$template = '<strong><a href="%s" target="_blank">%s</a></strong>';
			foreach ( $this->cfg->plugin_meta as $aHref ) {
				array_push( $pluginMeta, sprintf( $template, $aHref[ 'href' ], $aHref[ 'name' ] ) );
			}
		}
		return $pluginMeta;
	}

	/**
	 * @param array $actionLinks
	 * @return array
	 */
	public function onWpPluginActionLinks( $actionLinks ) {

		if ( $this->isValidAdminArea() ) {

			if ( array_key_exists( 'edit', $actionLinks ) ) {
				unset( $actionLinks[ 'edit' ] );
			}

			$links = $this->cfg->action_links[ 'add' ];
			if ( is_array( $links ) ) {

				$isPro = $this->isPremiumActive();
				$DP = Services::Data();
				$sLinkTemplate = '<a href="%s" target="%s" title="%s">%s</a>';
				foreach ( $links as $aLink ) {
					$aLink = array_merge(
						[
							'highlight' => false,
							'show'      => 'always',
							'name'      => '',
							'title'     => '',
							'href'      => '',
							'target'    => '_top',
						],
						$aLink
					);

					$show = $aLink[ 'show' ];
					$bShow = ( $show == 'always' ) || ( $isPro && $show == 'pro' ) || ( !$isPro && $show == 'free' );
					if ( !$DP->isValidWebUrl( $aLink[ 'href' ] ) && method_exists( $this, $aLink[ 'href' ] ) ) {
						$aLink[ 'href' ] = $this->{$aLink[ 'href' ]}();
					}

					if ( !$bShow || !$DP->isValidWebUrl( $aLink[ 'href' ] )
						 || empty( $aLink[ 'name' ] ) || empty( $aLink[ 'href' ] ) ) {
						continue;
					}

					$aLink[ 'name' ] = __( $aLink[ 'name' ], 'wp-simple-firewall' );

					$sLink = sprintf( $sLinkTemplate, $aLink[ 'href' ], $aLink[ 'target' ], $aLink[ 'title' ], $aLink[ 'name' ] );
					if ( $aLink[ 'highlight' ] ) {
						$sLink = sprintf( '<span style="font-weight: bold;">%s</span>', $sLink );
					}

					$actionLinks = array_merge(
						[ $this->prefix( sanitize_key( $aLink[ 'name' ] ) ) => $sLink ],
						$actionLinks
					);
				}
			}
		}
		return $actionLinks;
	}

	/**
	 * Displays a message in the plugins listing when a plugin has an update available.
	 */
	public function onWpPluginUpdateMessage() {
		echo sprintf(
			' <span class="%s plugin_update_message">%s</span>',
			$this->getPluginPrefix(),
			__( 'Update Now To Keep Your Security Current With The Latest Features.', 'wp-simple-firewall' )
		);
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
			if ( is_array( $reqs ) ) {
				foreach ( $reqs as $shieldVer => $verReqs ) {
					$toHide = version_compare( $updates->response[ $file ]->new_version, $shieldVer, '>=' )
							  && (
								  !Services::Data()->getPhpVersionIsAtLeast( $verReqs[ 'php' ] )
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
				if ( count( $updates ) > 3 ) {
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
		$oWpPlugins = Services::WpPlugins();

		$file = $WP->getFileFromAutomaticUpdateItem( $mItem );

		// The item in question is this plugin...
		if ( $file === $this->base_file ) {
			$autoupdateSelf = $this->cfg->properties[ 'autoupdate' ];

			if ( !$WP->isRunningAutomaticUpdates() && $autoupdateSelf == 'confidence' ) {
				$autoupdateSelf = 'yes'; // so that we appear to be automatically updating
			}

			$new = $oWpPlugins->getUpdateNewVersion( $file );

			switch ( $autoupdateSelf ) {

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
										&& $availableFor > DAY_IN_SECONDS*$this->cfg->properties[ 'autoupdate_days' ];
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
	 * @param array $aPlugins
	 * @return array
	 */
	public function doPluginLabels( $aPlugins ) {
		$labels = $this->getLabels();
		if ( empty( $labels ) ) {
			return $aPlugins;
		}

		$file = $this->base_file;
		// For this plugin, overwrite any specified settings
		if ( array_key_exists( $file, $aPlugins ) ) {
			foreach ( $labels as $sLabelKey => $sLabel ) {
				$aPlugins[ $file ][ $sLabelKey ] = $sLabel;
			}
		}

		return $aPlugins;
	}

	public function getLabels() :array {

		$labels = array_map(
			'stripslashes',
			apply_filters( $this->prefix( 'plugin_labels' ), $this->cfg->labels )
		);

		$oDP = Services::Data();
		foreach ( [ '16x16', '32x32', '128x128' ] as $dimension ) {
			$key = 'icon_url_'.$dimension;
			if ( !empty( $labels[ $key ] ) && !$oDP->isValidWebUrl( $labels[ $key ] ) ) {
				$labels[ $key ] = $this->urls->forImage( $labels[ $key ] );
			}
		}

		return $labels;
	}

	public function onWpShutdown() {
		do_action( $this->prefix( 'pre_plugin_shutdown' ) );
		do_action( $this->prefix( 'plugin_shutdown' ) );
		$this->saveCurrentPluginControllerOptions();
		$this->deleteFlags();
	}

	public function onWpLogout() {
		if ( $this->hasSessionId() ) {
			$this->clearSession();
		}
	}

	protected function deleteFlags() {
		$FS = Services::WpFs();
		if ( $FS->exists( $this->paths->forFlag( 'rebuild' ) ) ) {
			$FS->deleteFile( $this->paths->forFlag( 'rebuild' ) );
		}
		if ( $this->getIsResetPlugin() ) {
			$FS->deleteFile( $this->paths->forFlag( 'reset' ) );
		}
	}

	/**
	 * Added to a WordPress filter ('all_plugins') which will remove this particular plugin from the
	 * list of all plugins based on the "plugin file" name.
	 * @param array $plugins
	 * @return array
	 */
	public function filter_hidePluginFromTableList( $plugins ) {
		if ( apply_filters( $this->prefix( 'hide_plugin' ), false ) ) {
			unset( $plugins[ $this->base_file ] );
		}
		return $plugins;
	}

	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates
	 * from the WordPress Admin UI.
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 * @param \stdClass $plugins
	 * @return \stdClass
	 */
	public function filter_hidePluginUpdatesFromUI( $plugins ) {
		if ( !Services::WpGeneral()->isCron() && apply_filters( $this->prefix( 'hide_plugin_updates' ), false ) ) {
			unset( $plugins->response[ $this->base_file ] );
		}
		return $plugins;
	}

	/**
	 * @param string $suffix
	 * @param string $glue
	 * @return string
	 */
	public function prefix( $suffix = '', $glue = '-' ) {
		$prefix = $this->getPluginPrefix( $glue );

		if ( $suffix == $prefix || strpos( $suffix, $prefix.$glue ) === 0 ) { //it already has the full prefix
			return $suffix;
		}

		return sprintf( '%s%s%s', $prefix, empty( $suffix ) ? '' : $glue, empty( $suffix ) ? '' : $suffix );
	}

	public function prefixOption( string $suffix = '' ) :string {
		return $this->prefix( $suffix, '_' );
	}

	/**
	 * @return Config\ConfigVO
	 * @throws \Exception
	 */
	private function loadConfig() :Config\ConfigVO {
		try {
			$this->cfg = ( new Config\Ops\LoadConfig( $this->getPathPluginSpec( true ), $this->getConfigStoreKey() ) )
				->setCon( $this )
				->run();
		}
		catch ( \Exception $e ) {
			$this->cfg = ( new Config\Ops\LoadConfig( $this->getPathPluginSpec( false ), $this->getConfigStoreKey() ) )
				->setCon( $this )
				->run();
		}
		$this->rebuild_options = $this->cfg->rebuilt;
		return $this->cfg;
	}

	/**
	 * @param string $key
	 * @return string|null
	 */
	public function getPluginSpec_Path( string $key ) {
		return $this->cfg->paths[ $key ] ?? null;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	protected function getCfgProperty( string $key ) {
		return $this->cfg->properties[ $key ] ?? null;
	}

	public function getBasePermissions() :string {
		if ( isset( $this->cfg ) ) {
			return $this->cfg->properties[ 'base_permissions' ];
		}
		return $this->getCfgProperty( 'base_permissions' );
	}

	public function isValidAdminArea( bool $bCheckUserPerms = false ) :bool {
		if ( $bCheckUserPerms && did_action( 'init' ) && !$this->isPluginAdmin() ) {
			return false;
		}

		$WP = Services::WpGeneral();
		if ( !$WP->isMultisite() && is_admin() ) {
			return true;
		}
		elseif ( $WP->isMultisite() && $this->getIsWpmsNetworkAdminOnly() && ( is_network_admin() || $WP->isAjax() ) ) {
			return true;
		}
		return false;
	}

	public function isModulePage() :bool {
		return strpos( Services::Request()->query( 'page' ), $this->prefix() ) === 0;
	}

	/**
	 * only ever consider after WP INIT (when a logged-in user is recognised)
	 */
	public function isPluginAdmin() :bool {
		return apply_filters( $this->prefix( 'bypass_is_plugin_admin' ), false )
			   || ( $this->getMeetsBasePermissions() // takes care of did_action('init)
					&& apply_filters( $this->prefix( 'is_plugin_admin' ), true )
			   );
	}

	/**
	 * DO NOT CHANGE THIS IMPLEMENTATION.
	 * We call this as early as possible so that the
	 * current_user_can() never gets caught up in an infinite loop of permissions checking
	 */
	public function getMeetsBasePermissions() :bool {
		if ( did_action( 'init' ) && !isset( $this->user_can_base_permissions ) ) {
			$this->user_can_base_permissions = current_user_can( $this->getBasePermissions() );
		}
		return $this->user_can_base_permissions;
	}

	public function getOptionStoragePrefix() :string {
		return $this->getPluginPrefix( '_' ).'_';
	}

	public function getPluginPrefix( string $glue = '-' ) :string {
		return sprintf( '%s%s%s', $this->getParentSlug(), $glue, $this->getPluginSlug() );
	}

	/**
	 * Default is to take the 'Name' from the labels section but can override with "human_name" from property section.
	 * @return string
	 */
	public function getHumanName() {
		$labels = $this->getLabels();
		return empty( $labels[ 'Name' ] ) ? $this->getCfgProperty( 'human_name' ) : $labels[ 'Name' ];
	}

	public function getIsPage_PluginAdmin() :bool {
		return strpos( Services::WpGeneral()->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0;
	}

	public function getIsResetPlugin() :bool {
		if ( !isset( $this->plugin_reset ) ) {
			$this->plugin_reset = Services::WpFs()->isFile( $this->paths->forFlag( 'reset' ) );
		}
		return $this->plugin_reset;
	}

	public function getIsWpmsNetworkAdminOnly() :bool {
		return (bool)$this->getCfgProperty( 'wpms_network_admin_only' );
	}

	public function getParentSlug() :string {
		return $this->getCfgProperty( 'slug_parent' );
	}

	public function getPluginSlug() :string {
		return $this->getCfgProperty( 'slug_plugin' );
	}

	public function getPluginUrl( string $path = '' ) :string {
		return add_query_arg( [ 'ver' => $this->getVersion() ], plugins_url( $path, $this->getRootFile() ) );
	}

	public function getPluginUrl_DashboardHome() :string {
		return $this->getModule_Insights()->getUrl_SubInsightsPage( 'overview' );
	}

	public function getPluginUrl_AdminMainPage() :string {
		return $this->getModule_Plugin()->getUrl_AdminPage();
	}

	public function getPath_Languages() :string {
		return trailingslashit( path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'languages' ) ) );
	}

	public function getPath_Templates() :string {
		return path_join( $this->getRootDir(), $this->getPluginSpec_Path( 'templates' ) ).'/';
	}

	private function getPathPluginSpec( bool $asJSON = true ) :string {
		return path_join( $this->getRootDir(), $asJSON ? 'plugin.json' : 'plugin-spec.php' );
	}

	public function getRootDir() :string {
		return dirname( $this->getRootFile() ).DIRECTORY_SEPARATOR;
	}

	public function getRootFile() :string {
		if ( empty( $this->root_file ) ) {
			$VO = ( new \FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files() )
				->findPluginFromFile( __FILE__ );
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
		return $this->getCfgProperty( 'text_domain' );
	}

	public function getVersion() :string {
		return $this->getCfgProperty( 'version' );
	}

	public function getVersionNumeric() :int {
		$parts = explode( '.', $this->getVersion() );
		return (int)( $parts[ 0 ]*100 + $parts[ 1 ]*10 + $parts[ 2 ] );
	}

	public function getShieldAction() :string {
		$action = sanitize_key( Services::Request()->query( 'shield_action', '' ) );
		return empty( $action ) ? '' : $action;
	}

	public function getShieldNonceAction() :string {
		$action = sanitize_key( Services::Request()->query( 'shield_nonce_action', '' ) );
		return empty( $action ) ? '' : $action;
	}

	public function isPremiumExtensionsEnabled() :bool {
		return (bool)$this->getCfgProperty( 'enable_premium' );
	}

	public function isPremiumActive() :bool {
		return $this->getModule_License()->getLicenseHandler()->hasValidWorkingLicense();
	}

	public function isRelabelled() :bool {
		return (bool)apply_filters( $this->prefix( 'is_relabelled' ), false );
	}

	protected function saveCurrentPluginControllerOptions() {
		add_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );

		if ( $this->plugin_deleting ) {
			Transient::Delete( $this->getConfigStoreKey() );
		}
		elseif ( isset( $this->cfg ) ) {
			Config\Ops\Save::ToWp( $this->cfg, $this->getConfigStoreKey() );
		}
		remove_filter( $this->prefix( 'bypass_is_plugin_admin' ), '__return_true' );
	}

	private function getConfigStoreKey() :string {
		return 'aptoweb_controller_'.substr( md5( get_class() ), 0, 6 );
	}

	public function clearSession() {
		Services::Response()->cookieDelete( $this->getSessionCookieID() );
		self::$sSessionId = null;
	}

	/**
	 * @return $this
	 */
	public function deleteForceOffFile() {
		if ( $this->getIfForceOffActive() ) {
			Services::WpFs()->deleteFile( $this->getForceOffFilePath() );
			unset( $this->file_forceoff );
			clearstatcache();
		}
		return $this;
	}

	public function getIfForceOffActive() :bool {
		return $this->getForceOffFilePath() !== false;
	}

	/**
	 * @return false|string
	 */
	protected function getForceOffFilePath() {
		if ( !isset( $this->file_forceoff ) ) {
			$FS = Services::WpFs();
			$file = $FS->findFileInDir( 'forceoff', $this->getRootDir(), false, false );
			$this->file_forceoff = empty( $file ) ? false : $file;
		}
		return $this->file_forceoff;
	}

	/**
	 * @param bool $setIfNeeded
	 * @return string
	 */
	public function getSessionId( $setIfNeeded = true ) {
		if ( empty( self::$sSessionId ) ) {
			$req = Services::Request();
			self::$sSessionId = $req->cookie( $this->getSessionCookieID(), '' );
			if ( empty( self::$sSessionId ) && $setIfNeeded ) {
				self::$sSessionId = md5( uniqid( $this->getPluginPrefix() ) );
				$this->setSessionCookie();
			}
		}
		return self::$sSessionId;
	}

	public function hasSessionId() :bool {
		return !empty( $this->getSessionId( false ) );
	}

	protected function setSessionCookie() {
		Services::Response()->cookieSet(
			$this->getSessionCookieID(),
			$this->getSessionId(),
			Services::Request()->ts() + DAY_IN_SECONDS*30,
			Services::WpGeneral()->getCookiePath(),
			Services::WpGeneral()->getCookieDomain()
		);
	}

	private function getSessionCookieID() :string {
		return 'wp-'.$this->getPluginPrefix();
	}

	/**
	 * We let the \Exception from the core plugin feature to bubble up because it's critical.
	 * @return Shield\Modules\Plugin\ModCon
	 * @throws \Exception from loadFeatureHandler()
	 */
	public function loadCorePluginFeatureHandler() {
		$plugin = $this->modules[ 'plugin' ] ?? null;
		if ( !$plugin instanceof Shield\Modules\Plugin\ModCon ) {
			$this->loadFeatureHandler(
				[
					'slug'          => 'plugin',
					'storage_key'   => 'plugin',
					'load_priority' => 10
				]
			);
		}
		return $this->modules[ 'plugin' ];
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function loadAllFeatures() :bool {
		foreach ( array_keys( $this->loadCorePluginFeatureHandler()->getActivePluginFeatures() ) as $slug ) {
			try {
				$this->getModule( $slug );
			}
			catch ( \Exception $e ) {
				if ( $this->isValidAdminArea() && $this->isPluginAdmin() ) {
					$this->sAdminNoticeError = $e->getMessage();
					add_action( 'admin_notices', [ $this, 'adminNoticePluginFailedToLoad' ] );
					add_action( 'network_admin_notices', [ $this, 'adminNoticePluginFailedToLoad' ] );
				}
			}
		}

		$this->modules_loaded = true;

		// Upgrade modules
		( new Shield\Controller\Utilities\Upgrade() )
			->setCon( $this )
			->execute();

		do_action( $this->prefix( 'modules_loaded' ) );
		do_action( $this->prefix( 'run_processors' ) );
		return true;
	}

	/**
	 * @param string $slug
	 * @return Shield\Modules\Base\ModCon|null|mixed
	 */
	public function getModule( string $slug ) {
		$mod = $this->modules[ $slug ] ?? null;
		if ( !$mod instanceof Shield\Modules\Base\ModCon ) {
			try {
				$mods = $this->loadCorePluginFeatureHandler()->getActivePluginFeatures();
				if ( isset( $mods[ $slug ] ) ) {
					$mod = $this->loadFeatureHandler( $mods[ $slug ] );
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $mod;
	}

	public function getModule_AuditTrail() :Shield\Modules\AuditTrail\ModCon {
		return $this->getModule( 'audit_trail' );
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

	public function getModule_Email() :Shield\Modules\Email\ModCon {
		return $this->getModule( 'email' );
	}

	public function getModule_Events() :Shield\Modules\Events\ModCon {
		return $this->getModule( 'events' );
	}

	public function getModule_Firewall() :Shield\Modules\Firewall\ModCon {
		return $this->getModule( 'firewall' );
	}

	public function getModule_HackGuard() :Shield\Modules\HackGuard\ModCon {
		return $this->getModule( 'hack_protect' );
	}

	public function getModule_Insights() :Shield\Modules\Insights\ModCon {
		return $this->getModule( 'insights' );
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
		return $this->loadCorePluginFeatureHandler();
	}

	public function getModule_Reporting() :Shield\Modules\Reporting\ModCon {
		return $this->getModule( 'reporting' );
	}

	public function getModule_SecAdmin() :Shield\Modules\SecurityAdmin\ModCon {
		return $this->getModule( 'admin_access_restriction' );
	}

	public function getModule_Sessions() :Shield\Modules\Sessions\ModCon {
		return $this->getModule( 'sessions' );
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

	/**
	 * @param array $modProps
	 * @return Shield\Modules\Base\ModCon|mixed
	 * @throws \Exception
	 */
	public function loadFeatureHandler( array $modProps ) {
		$modSlug = $modProps[ 'slug' ];
		$mod = $this->modules[ $modSlug ] ?? null;
		if ( $mod instanceof Shield\Modules\Base\ModCon ) {
			return $mod;
		}

		if ( empty( $modProps[ 'storage_key' ] ) ) {
			$modProps[ 'storage_key' ] = $modSlug;
		}
		if ( empty( $modProps[ 'namespace' ] ) ) {
			$modProps[ 'namespace' ] = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $modSlug ) ) );
		}

		if ( !empty( $modProps[ 'min_php' ] )
			 && !Services::Data()->getPhpVersionIsAtLeast( $modProps[ 'min_php' ] ) ) {
			return null;
		}

		$modName = $modProps[ 'namespace' ];

		$className = $this->getModulesNamespace().sprintf( '\\%s\\ModCon', $modName );
		if ( !class_exists( $className ) ) {
			// All this to prevent fatal errors if the plugin doesn't install/upgrade correctly
			throw new \Exception( sprintf( 'Class "%s" is missing', $className ) );
		}

		$modules = $this->modules;
		$modules[ $modSlug ] = new $className( $this, $modProps );
		$this->modules = $modules;

		return $this->modules[ $modSlug ];
	}

	/**
	 * @return Shield\Users\ShieldUserMeta
	 */
	public function getCurrentUserMeta() {
		return $this->getUserMeta( Services::WpUsers()->getCurrentWpUser() );
	}

	/**
	 * @param \WP_User $user
	 * @return Shield\Users\ShieldUserMeta|null
	 */
	public function getUserMeta( $user ) {
		$meta = null;
		try {
			if ( $user instanceof \WP_User ) {
				/** @var Shield\Users\ShieldUserMeta $meta */
				$meta = Shield\Users\ShieldUserMeta::Load( $this->prefix(), $user->ID );
				if ( !$meta instanceof Shield\Users\ShieldUserMeta ) {
					// Weird: user reported an error where it wasn't of the correct type
					$meta = new Shield\Users\ShieldUserMeta( $this->prefix(), $user->ID );
					Shield\Users\ShieldUserMeta::AddToCache( $meta );
				}
				$meta->setPasswordStartedAt( $user->user_pass )
					 ->updateFirstSeenAt();
				Services::WpUsers()
						->updateUserMeta( $this->prefix( 'meta-version' ), $this->getVersionNumeric(), $user->ID );
			}
		}
		catch ( \Exception $e ) {
		}
		return $meta;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Services\Utilities\Render
	 */
	public function getRenderer() {
		$render = Services::Render();
		$locator = ( new Shield\Render\LocateTemplateDirs() )->setCon( $this );
		foreach ( $locator->run() as $dir ) {
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
		if ( !is_array( $registered ) ) {
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
		if ( !is_array( $registered ) ) {
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

	/**
	 * @return string
	 */
	private function buildPrivacyPolicyContent() {
		try {
			if ( $this->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
				$name = $this->getHumanName();
				$href = $this->getLabels()[ 'PluginURI' ];
			}
			else {
				$name = $this->cfg->menu[ 'title' ];
				$href = $this->cfg->meta[ 'privacy_policy_href' ];
			}

			/** @var Shield\Modules\AuditTrail\Options $opts */
			$opts = $this->getModule_AuditTrail()->getOptions();

			$content = $this->getRenderer()
							->setTemplate( 'snippets/privacy_policy' )
							->setTemplateEngineTwig()
							->setRenderVars(
								[
									'name'             => $name,
									'href'             => $href,
									'audit_trail_days' => $opts->getAutoCleanDays()
								]
							)
							->render();
		}
		catch ( \Exception $e ) {
			$content = '';
		}
		return empty( $content ) ? '' : wp_kses_post( wpautop( $content, false ) );
	}

	private function runTests() {
		die();
		( new Shield\Tests\VerifyUniqueEvents() )->setCon( $this )->run();
		foreach ( $this->modules as $oModule ) {
			( new \FernleafSystems\Wordpress\Plugin\Shield\Tests\VerifyConfig() )
				->setOpts( $oModule->getOptions() )
				->run();
		}
	}
}