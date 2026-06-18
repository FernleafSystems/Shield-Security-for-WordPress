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
							sprintf( __( "An upgrade is available for the %s plugin.", 'wp-simple-firewall' ), self::con()->labels->Name ),
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
			Transient::Set( $con->prefix( 'releases' ), $versions, \HOUR_IN_SECONDS*6 );
		}

		if ( !empty( $versions ) ) {
			$tooOld = $this->hasAtLeastTwoNewerMajorVersions( $versions, $con->cfg->version() );
		}

		return $tooOld;
	}

	private function hasAtLeastTwoNewerMajorVersions( array $versions, string $currentVersion ) :bool {
		$tooOld = false;
		$currentMajor = $this->extractMajorVersion( $currentVersion );

		if ( !empty( $currentMajor ) ) {
			$majorVersionsNewerThanCurrent = \array_filter(
				\array_unique( \array_map(
					function ( $version ) {
						return \is_string( $version ) ? $this->extractMajorVersion( $version ) : null;
					},
					$versions
				) ),
				function ( $version ) use ( $currentMajor ) {
					return \is_int( $version ) && $version > $currentMajor;
				},
			);

			$tooOld = \count( $majorVersionsNewerThanCurrent ) >= 2;
		}

		return $tooOld;
	}

	private function extractMajorVersion( string $version ) :?int {
		$matches = [];
		return \preg_match( '#^(\d+)\.#', $version, $matches ) === 1 ? \intval( $matches[ 1 ] ) : null;
	}
}
