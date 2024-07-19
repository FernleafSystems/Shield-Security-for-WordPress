<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InstallationID {

	use PluginControllerConsumer;

	public function id() :string {
		return $this->retrieve()[ 'id' ];
	}

	/**
	 * @return array{id: string, ts: int, install_at: int}
	 */
	public function retrieve() :array {
		$WP = Services::WpGeneral();
		$urlParts = wp_parse_url( $WP->getWpUrl() );
		$url = $urlParts[ 'host' ].\trim( $urlParts[ 'path' ] ?? '', '/' );
		$optKey = self::con()->prefix( 'shield_site_id', '_' );

		$IDs = $WP->getOption( $optKey );
		if ( !\is_array( $IDs ) ) {
			$IDs = [];
		}
		if ( !\is_array( $IDs[ $url ] ?? null ) ) {
			$IDs[ $url ] = [];
		}

		if ( empty( $IDs[ $url ][ 'id' ] ) || !\Ramsey\Uuid\Uuid::isValid( $IDs[ $url ][ 'id' ] ) ) {
			$id = ( new \FernleafSystems\Wordpress\Services\Utilities\Uuid() )->V4();
			$IDs[ $url ] = [
				'id'         => \strtolower( $id ),
				'ts'         => Services::Request()->ts(),
				'install_at' => self::con()->plugin->storeRealInstallDate(),
			];
			$WP->updateOption( $optKey, $IDs );
		}

		return $IDs[ $url ];
	}
}