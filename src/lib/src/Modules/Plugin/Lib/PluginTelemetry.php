<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
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
				self::con()
					->opts
					->optSet( 'tracking_last_sent_at', Services::Request()->ts() )
					->store();
				( new SendPluginTelemetry() )->send( $data );
			}
		}
	}

	private function canSend() :bool {
		return apply_filters( 'shield/can_send_telemetry', self::con()->comps->opts_lookup->enabledTelemetry() )
			   && Services::Request()
						  ->carbon()
						  ->subDay()->timestamp > self::con()->opts->optGet( 'tracking_last_sent_at' );
	}

	/**
	 * @return array[]
	 */
	public function collectTrackingData() :array {
		$con = self::con();

		$data = $this->getBaseTrackingData();
		foreach ( $con->modules as $mod ) {
			$data[ $mod->cfg->slug ] = $this->buildOptionsDataForMod( $mod );
		}

		if ( !empty( $data[ 'events' ] ) ) {
			/** @var EventsDB\Select $select */
			$select = self::con()->db_con->dbhEvents()->getQuerySelector();
			$data[ 'events' ][ 'stats' ] = $select->sumAllEvents();
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
			$data[ 'plugin' ][ 'options' ][ 'unique_installation_id' ] = ( new InstallationID() )->id();
		}

		return $data;
	}

	/**
	 * @param ModCon|mixed $mod
	 */
	private function buildOptionsDataForMod( $mod ) :array {
		$con = self::con();
		$config = $con->cfg->configuration;

		$data = [];

		$options = \array_intersect_key( $con->opts->values(), $config->optsForModule( $mod->cfg->slug ) );
		foreach ( $config->optsForModule( $mod->cfg->slug ) as $key => $optDef ) {
			if ( !isset( $options[ $key ] ) ) {
				$options[ $key ] = $con->opts->optDefault( $key );
			}
			if ( $con->opts->optType( $key ) === 'checkbox ' ) {
				$options[ $key ] = $options[ $key ] == 'Y' ? 1 : 0;
			}

			if ( !empty( $optDef[ 'sensitive' ] ) || !empty( $optDef[ 'tracking_exclude' ] ) ) {
				unset( $options[ $key ] );
			}
		}

		$data[ 'options' ] = $options;
		$data[ 'dbs' ] = [];

		return $data;
	}

	private function getBaseTrackingData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();
		return [
			'env' => [
				'slug'             => $con->cfg->properties[ 'slug_plugin' ],
				'installation_id'  => ( new InstallationID() )->id(),
				'unique_site_hash' => \sha1( network_home_url( '/' ) ),
				'php'              => Services::Data()->getPhpVersionCleaned(),
				'wordpress'        => $WP->getVersion(),
				'version'          => $con->cfg->version(),
				'plugin_version'   => $con->cfg->version(),
				'is_wpms'          => $WP->isMultisite() ? 1 : 0,
				'ssl'              => is_ssl() ? 1 : 0,
				'locale'           => get_locale(),
				'can_ajax_rest'    => $con->opts->optGet( 'test_rest_data' )[ 'success_test_at' ] ?? -1,
				'plugins_total'    => \count( $WPP->getPlugins() ),
				'plugins_active'   => \count( $WPP->getActivePlugins() ),
				'plugins_updates'  => \count( $WPP->getUpdates() ),
			]
		];
	}
}
