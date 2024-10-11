<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\ListTagsFromGithub;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class SelfVersion extends Base {

	public function check() :?array {
		$con = self::con();
		$issue = null;

		if ( Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ) {
			if ( $this->isPluginTooOld() ) {
				$issue = [
					'id'        => 'self_update_available',
					'type'      => 'info',
					'text'      => [
						sprintf(
							'%s %s',
							sprintf( __( 'There are at least 2 major upgrades to the %s plugin since your version.', 'wp-simple-firewall' ), $con->labels->Name ),
							sprintf( '<a href="%s" class="">%s</a>',
								Services::WpPlugins()->getUrl_Upgrade( self::con()->base_file ),
								__( 'Upgrade Now', 'wp-simple-firewall' )
							)
						)
					],
					'locations' => [
						'shield_admin_top_page',
						'wp_admin',
					]
				];
			}
			else {
				$issue = [
					'id'        => 'self_update_available',
					'type'      => 'info',
					'text'      => [
						sprintf(
							'%s %s',
							__( "An upgrade is available for the Shield plugin.", 'wp-simple-firewall' ),
							sprintf( '<a href="%s" class="">%s</a>',
								Services::WpPlugins()->getUrl_Upgrade( self::con()->base_file ),
								__( 'Upgrade Now', 'wp-simple-firewall' )
							)
						)
					],
					'locations' => [
						'shield_admin_top_page',
					]
				];
			}
		}

		return $issue;
	}

	private function isPluginTooOld() :bool {
		$tooOld = false;
		$con = self::con();
		$versions = Transient::Get( $con->prefix( 'releases' ) );

		if ( !\is_array( $versions ) ) {
			$versions = ( new ListTagsFromGithub() )->run( 'FernleafSystems/Shield-Security-for-WordPress' );
			Transient::Set( $con->prefix( 'releases' ), $versions, \WEEK_IN_SECONDS );
		}

		$currentMajor = \intval( \substr( $con->cfg->version(), 0, \strpos( $con->cfg->version(), '.' ) ) );
		if ( !empty( $versions ) && !empty( $currentMajor ) ) {

			$majorVersionsNewerThanCurrent = \array_filter(
				\array_unique( \array_map(
					function ( $version ) {
						/** 1. Convert all versions to major releases */
						return \intval( \substr( $version, 0, \strpos( $version, '.' ) ) );
					},
					$versions
				) ),
				function ( $version ) use ( $currentMajor ) {
					/** 2. Find all major versions newer than current */
					return $version > $currentMajor;
				}
			);

			/** 3. Suggest upgrade needed  */
			$tooOld = \count( $majorVersionsNewerThanCurrent ) >= 2;
		}

		return $tooOld;
	}
}