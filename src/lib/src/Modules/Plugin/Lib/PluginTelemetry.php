<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools\SendPluginTelemetry;
use FernleafSystems\Wordpress\Services\Services;

class PluginTelemetry {

	use PluginControllerConsumer;

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
		$data = $this->getBaseTrackingData();

		/** @var EventsDB\Select $select */
		$select = self::con()->db_con->events->getQuerySelector();
		$data[ 'events' ][ 'stats' ] = $select->sumAllEvents();

		$data[ 'options' ] = $this->buildOptionsData();
		\ksort( $data[ 'options' ] );

		return $data;
	}

	private function buildOptionsData() :array {
		$opts = self::con()->opts;
		return \array_map(
			function ( array $optDef ) use ( $opts ) {
				$value = $opts->optGet( $optDef[ 'key' ] );
				if ( $optDef[ 'type' ] === 'checkbox ' ) {
					$value = $value === 'Y' ? 1 : 0;
				}

				if ( \in_array( $optDef[ 'key' ], [
					'admin_access_restrict_plugins',
					'admin_access_restrict_themes',
					'admin_access_restrict_posts'
				] ) ) {
					$value = (int) !empty( $value );
				}

				return $value;
			},
			\array_filter(
				self::con()->cfg->configuration->options,
				function ( array $optDef ) {
					return empty( $optDef[ 'sensitive' ] ) && empty( $optDef[ 'tracking_exclude' ] );
				}
			)
		);
	}

	private function getBaseTrackingData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		$WPP = Services::WpPlugins();
		return [
			'env'    => [
				'slug'             => $con->cfg->properties[ 'slug_plugin' ],
				'installation_id'  => ( new InstallationID() )->id(),
				'unique_site_hash' => \hash( 'sha1', network_home_url( '/' ) ),
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
				'opts_structure'   => 'flat',
			],
			'events' => [],
		];
	}
}
