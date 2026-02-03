<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\{
	CacheDirHandler,
	Integration\WhitelistUs
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\{
	RequestIpDetect,
	VisitorIpDetection
};

class ModCon {

	use PluginControllerConsumer;
	use PluginCronsConsumer;

	public const SLUG = \FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules::PLUGIN;

	private bool $is_booted = false;

	private Processor $processor;

	/**
	 * @var Lib\TrackingVO
	 */
	private $tracking;

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

	/**
	 * @throws \Exception
	 */
	public function getProcessor() :Processor {
		return $this->processor ??= new Processor();
	}

	protected function doPostConstruction() {
		$this->setVisitorIpSource();
		$this->setupCacheDir();
		$this->declareWooHposCompat();
		$this->storeRealInstallDate();
	}

	public function onWpInit() {
		if ( self::con()->cfg->previous_version !== self::con()->cfg->version() ) {
			$this->getTracking()->last_upgrade_at = Services::Request()->ts();
		}
		self::con()->comps->assets_customizer->execute();
	}

	protected function setupCacheDir() {
		$con = self::con();
		$url = Services::WpGeneral()->getWpUrl();

		$lastKnownDirs = $con->opts->optGet( 'last_known_cache_basedirs' );
		$lastKnownDirs = \array_merge( [
			$url => '',
		], \is_array( $lastKnownDirs ) ? $lastKnownDirs : [] );

		$cacheDirFinder = new CacheDirHandler( $lastKnownDirs[ $url ], $con->opts->optGet( 'preferred_temp_dir' ) );
		$lastKnownDirs[ $url ] = \dirname( $cacheDirFinder->dir() );
		$con->opts->optSet( 'last_known_cache_basedirs', $lastKnownDirs );

		$con->cache_dir_handler = $cacheDirFinder;
	}

	/**
	 * https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
	 */
	private function declareWooHposCompat() {
		add_action( 'before_woocommerce_init', function () {
			if ( \class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', self::con()->root_file, true );
			}
		} );
	}

	public function deleteAllPluginCrons() {
		$con = self::con();
		$wpCrons = Services::WpCron();

		foreach ( $wpCrons->getCrons() as /** $key => */ $cronArgs ) {
			foreach ( $cronArgs as $hook => $cron ) {
				if ( \strpos( (string)$hook, $con->prefix() ) === 0 || \strpos( (string)$hook, $con->prefix( '', '_' ) ) === 0 ) {
					$wpCrons->deleteCronJob( $hook );
				}
			}
		}
	}

	/**
	 * Forcefully sets preferred Visitor IP source in the Data component for use throughout the plugin
	 */
	private function setVisitorIpSource() {
		$con = self::con();
		$source = $con->comps->opts_lookup->ipSource();
		if ( $source !== 'AUTO_DETECT_IP' ) {
			Services::Request()->setIpDetector(
				( new RequestIpDetect() )->setPreferredSource( $source )
			);
			Services::IP()->setIpDetector(
				( new VisitorIpDetection() )->setPreferredSource( $source )
			);
		}
		$con->this_req->ip = Services::Request()->ip();
		$con->this_req->ip_is_public = !empty( $con->this_req->ip )
									   && Services::IP()->isValidIp_PublicRemote( $con->this_req->ip );
	}

	/**
	 * @throws \Exception
	 */
	public function canSiteLoopback() :bool {
		$can = false;
		if ( \class_exists( '\WP_Site_Health' ) && \method_exists( '\WP_Site_Health', 'get_instance' ) ) {
			$can = \WP_Site_Health::get_instance()->get_test_loopback_requests()[ 'status' ] === 'good';
		}
		if ( !$can ) {
			$can = Services::HttpRequest()->post( site_url( 'wp-cron.php' ), [
				'timeout' => 10
			] );
		}
		return $can;
	}

	public function storeRealInstallDate() :int {
		$key = self::con()->prefix( 'install_date', '_' );
		$wpDate = Services::WpGeneral()->getOption( $key );
		if ( empty( $wpDate ) ) {
			$wpDate = Services::Request()->ts();
		}

		$date = self::con()->comps->opts_lookup->getInstalledAt();
		if ( $date == 0 ) {
			$date = Services::Request()->ts();
		}

		$finalDate = (int)\min( $date, $wpDate );
		Services::WpGeneral()->updateOption( $key, $finalDate );
		self::con()->opts->optSet( 'installation_time', $date );

		return $finalDate;
	}

	public function runDailyCron() {
		( new WhitelistUs() )->all();
		( new Lib\CleanOldOptions() )->execute();
	}

	protected function setupHooks() {
		$this->setupCronHooks();

		add_action( 'init', [ $this, 'onWpInit' ], HookTimings::INIT_MOD_CON_DEFAULT );

		add_action( 'admin_footer', function () {
			if ( self::con()->isPluginAdminPageRequest() ) {
				echo self::con()->action_router->render( Actions\Render\Components\ToastPlaceholder::SLUG );
			}
		}, 100, 0 );
	}

	public function getTracking() :Lib\TrackingVO {
		if ( !isset( $this->tracking ) ) {
			$this->tracking = ( new Lib\TrackingVO() )->applyFromArray( self::con()->opts->optGet( 'transient_tracking' ) );
			add_action( self::con()->prefix( 'pre_options_store' ), function () {
				self::con()->opts->optSet( 'transient_tracking', $this->tracking->getRawData() );
			} );
		}
		return $this->tracking;
	}
}