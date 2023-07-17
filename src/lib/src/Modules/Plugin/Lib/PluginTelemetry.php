<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools\SendPluginTelemetry;
use FernleafSystems\Wordpress\Services\Services;

class PluginTelemetry {

	use ModConsumer;

	public function collectAndSend( bool $forceSend = false ) {
		if ( $forceSend || $this->canSend() ) {
			$data = $this->collectTrackingData();
			if ( !empty( $data ) ) {
				$this->opts()->setOpt( 'tracking_last_sent_at', Services::Request()->ts() );
				$this->mod()->saveModOptions();
				( new SendPluginTelemetry() )->send( $data );
			}
		}
	}

	private function canSend() :bool {
		$opts = $this->opts();
		return ( $opts->isTrackingEnabled() || !$opts->isTrackingPermissionSet() )
			   && Services::Request()
						  ->carbon()
						  ->subDay()->timestamp > $opts->getOpt( 'tracking_last_sent_at', 0 );
	}

	/**
	 * @return array[]
	 */
	public function collectTrackingData() :array {
		$con = $this->con();

		$data = $this->getBaseTrackingData();
		foreach ( $con->modules as $mod ) {
			$data[ $mod->cfg->slug ] = $this->buildOptionsDataForMod( $mod );
		}

		if ( !empty( $data[ 'events' ] ) ) {
			/** @var Select $select */
			$select = $con->getModule_Events()
						  ->getDbHandler_Events()
						  ->getQuerySelector();
			$data[ 'events' ][ 'stats' ] = $select->sumAllEvents();
		}

		if ( !empty( $data[ 'login_protect' ] ) ) {
			$data[ 'login_protect' ][ 'options' ][ 'email_can_send_verified_at' ] =
				$data[ 'login_protect' ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ? 1 : 0;
		}

		if ( !empty( $data[ 'admin_access_restriction' ] ) ) {
			$keys = [
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
			$data[ 'plugin' ][ 'options' ][ 'unique_installation_id' ] = $this->con()->getInstallationID()[ 'id' ];
		}

		return $data;
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	private function buildOptionsDataForMod( $mod ) :array {
		$data = [];

		$opts = $mod->getOptions();
		$optionsData = $opts->getOptionsForTracking();
		foreach ( $optionsData as $opt => $mValue ) {
			unset( $optionsData[ $opt ] );
			// some cleaning to ensure we don't have disallowed characters
			$opt = \preg_replace( '#[^_a-z]#', '', \strtolower( $opt ) );
			if ( $opts->getOptionType( $opt ) == 'checkbox' ) { // only want a boolean 1 or 0
				$optionsData[ $opt ] = $mValue == 'Y' ? 1 : 0;
			}
			else {
				$optionsData[ $opt ] = $mValue;
			}
		}

		$data[ 'options' ] = $optionsData;

		return $data;
	}

	private function getBaseTrackingData() :array {
		$con = $this->con();
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();
		return [
			'env' => [
				'slug'             => $con->getPluginSlug(),
				'installation_id'  => $con->getInstallationID()[ 'id' ],
				'unique_site_hash' => \sha1( network_home_url( '/' ) ),
				'php'              => Services::Data()->getPhpVersionCleaned(),
				'wordpress'        => $WP->getVersion(),
				'version'          => $con->getVersion(),
				'plugin_version'   => $con->getVersion(),
				'is_wpms'          => $WP->isMultisite() ? 1 : 0,
				'is_cp'            => $WP->isClassicPress() ? 1 : 0,
				'ssl'              => is_ssl() ? 1 : 0,
				'locale'           => get_locale(),
				'plugins_total'    => \count( $WPP->getPlugins() ),
				'plugins_active'   => \count( $WPP->getActivePlugins() ),
				'plugins_updates'  => \count( $WPP->getUpdates() ),
			]
		];
	}
}
