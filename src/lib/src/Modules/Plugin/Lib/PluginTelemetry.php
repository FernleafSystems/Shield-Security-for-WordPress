<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class PluginTelemetry {

	use ModConsumer;
	use OneTimeExecute;

	protected function canRun() {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		return $opts->isTrackingEnabled() || !$opts->isTrackingPermissionSet();
	}

	protected function run() {
		$con = $this->getCon();
		switch ( $con->getShieldAction() ) {
			case 'dump_tracking_data':
				add_action( 'wp_loaded', function () {
					if ( $this->getCon()->isPluginAdmin() ) {
						echo sprintf( '<pre><code>%s</code></pre>',
							print_r( $this->collectTrackingData(), true ) );
						die();
					}
				} );
				break;
			default:
				break;
		}

		add_action( $this->getCon()->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
	}

	public function runDailyCron() {
		$this->sendTrackingData();
	}

	private function sendTrackingData() {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$success = false;

		$bCanSend = Services::Request()
							->carbon()
							->subWeek()->timestamp
					> (int)$opts->getOpt( 'tracking_last_sent_at', 0 );
		if ( $bCanSend && $opts->isTrackingEnabled() ) {

			$data = $this->collectTrackingData();
			if ( !empty( $data ) ) {
				$opts->setOpt( 'tracking_last_sent_at', Services::Request()->ts() );
				$success = Services::HttpRequest()->post(
					$opts->getDef( 'tracking_post_url' ),
					[
						'timeout'     => 20,
						'redirection' => 5,
						'httpversion' => '1.1',
						'blocking'    => true,
						'body'        => [ 'tracking_data' => $data ],
						'user-agent'  => 'SHIELD/'.$this->getCon()->getVersion().';'
					]
				);
			}
		}

		return $success;
	}

	/**
	 * @return array[]
	 */
	public function collectTrackingData() :array {
		$con = $this->getCon();

		$data = $this->getBaseTrackingData();
		foreach ( $con->modules as $mod ) {
			$data[ $mod->getSlug() ] = $this->buildOptionsDataForMod( $mod );
		}

		if ( !empty( $data[ 'events' ] ) ) {
			$data[ 'events' ][ 'stats' ] = $con->getModule_Events()
											   ->getDbHandler_Events()
											   ->getQuerySelector()
											   ->sumAllEvents();
		}
		if ( !empty( $data[ 'login_protect' ] ) ) {
			$data[ 'login_protect' ][ 'options' ][ 'email_can_send_verified_at' ] =
				$data[ 'login_protect' ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ? 1 : 0;
		}
		if ( !empty( $data[ 'admin_access_restriction' ] ) ) {
			$keys= [
				'admin_access_restrict_plugins',
				'admin_access_restrict_themes',
				'admin_access_restrict_posts'
			];
			foreach ( $keys as $key ) {
				$data[ 'admin_access_restriction' ][ 'options' ][ $key ]
					= empty( $data[ 'admin_access_restriction' ][ 'options' ][ $key ] ) ? 0 : 1;
			}
		}
		if ( !empty( $data[ 'plugin' ] ) ) {
			/** @var Plugin\ModCon $mod */
			$mod = $this->getMod();
			$data[ 'plugin' ][ 'options' ][ 'unique_installation_id' ] = $mod->getPluginInstallationId();
			$data[ 'plugin' ][ 'options' ][ 'new_unique_installation_id' ] = $con->getSiteInstallationId();
		}

		return $data;
	}

	/**
	 * @param ModCon $mod
	 * @return array
	 */
	private function buildOptionsDataForMod( $mod ) :array {
		$data = [];

		$opts = $mod->getOptions();
		$optionsData = $opts->getOptionsForTracking();
		foreach ( $optionsData as $opt => $mValue ) {
			unset( $optionsData[ $opt ] );
			// some cleaning to ensure we don't have disallowed characters
			$opt = preg_replace( '#[^_a-z]#', '', strtolower( $opt ) );
			if ( $opts->getOptionType( $opt ) == 'checkbox' ) { // only want a boolean 1 or 0
				$optionsData[ $opt ] = (int)( $mValue == 'Y' );
			}
			else {
				$optionsData[ $opt ] = $mValue;
			}
		}

		$data[ 'options' ] = $optionsData;

		return $data;
	}

	private function getBaseTrackingData() :array {
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();
		return [
			'env' => [
				'options' => [
					'php'             => Services::Data()->getPhpVersionCleaned(),
					'wordpress'       => $WP->getVersion(),
					'slug'            => $this->getCon()->getPluginSlug(),
					'version'         => $this->getCon()->getVersion(),
					'is_wpms'         => $WP->isMultisite() ? 1 : 0,
					'is_cp'           => $WP->isClassicPress() ? 1 : 0,
					'ssl'             => is_ssl() ? 1 : 0,
					'locale'          => get_locale(),
					'plugins_total'   => count( $WPP->getPlugins() ),
					'plugins_active'  => count( $WPP->getActivePlugins() ),
					'plugins_updates' => count( $WPP->getUpdates() )
				]
			]
		];
	}
}
