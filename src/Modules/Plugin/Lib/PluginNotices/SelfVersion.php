<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\WordPressOrg\PluginVersions;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class SelfVersion extends Base {

	private ?PluginVersions $versions = null;

	public function __construct( ?PluginVersions $versions = null ) {
		$this->versions = $versions;
	}

	public function check() :?array {
		$con = self::con();
		$issue = null;

		if ( Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ) {
			try {
				$isPluginTooOld = $this->isPluginTooOld();
			}
			catch ( \Throwable $e ) {
				$isPluginTooOld = false;
			}

			if ( $isPluginTooOld ) {
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
		$con = self::con();
		$versions = $this->versions;
		if ( $versions === null ) {
			$thisPlugin = Services::WpPlugins()->getPluginAsVo( $con->base_file );
			$slugRaw = $thisPlugin instanceof WpPluginVo ? $thisPlugin->slug : '';
			$versions = new PluginVersions( \is_scalar( $slugRaw ) ? (string)$slugRaw : '' );
		}

		return $versions->hasAtLeastTwoNewerMajorVersions( $con->cfg->version() );
	}
}
