<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Services\Services;

class SystemOutOfDate extends Base {

	public function check() :?array {
		$con = self::con();
		$DP = Services::Data();
		$WP = Services::WpGeneral();

		$issue = null;

		if ( \is_array( $con->cfg->upgrade_reqs ) ) {
			foreach ( $con->cfg->upgrade_reqs as $futureVersion => $req ) {
				if ( \version_compare( $futureVersion, $con->cfg->version(), '>' ) ) {
					if ( ( !empty( $req[ 'php' ] ) && !$DP->getPhpVersionIsAtLeast( $req[ 'php' ] ) )
						 || ( !empty( $req[ 'wp' ] ) && !$WP->getWordpressIsAtLeastVersion( $req[ 'wp' ] ) )
					) {
						$issue = [
							'id'        => 'system_out_of_date',
							'type'      => 'warning',
							'text'      => [
								sprintf( '[<strong>%s</strong>] %s %s ',
									__( 'Action Required', 'wp-simple-firewall' ),
									__( "It appears that your WordPress hosting isn't compatible with an upcoming Shield upgrade.", 'wp-simple-firewall' ),
									sprintf( '[<a href="%s" target="_blank">%s</a>]', 'https://shsec.io/lj', __( 'more info' ) )
								)
							],
							'locations' => [
								'shield_admin_top_page',
							]
						];
					}
					break;
				}
			}
		}

		return $issue;
	}
}
