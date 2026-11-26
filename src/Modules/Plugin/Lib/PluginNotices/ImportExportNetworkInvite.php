<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\NetworkInviteRepository;

class ImportExportNetworkInvite extends Base {

	/**
	 * @return array{id:string,type:string,text:list<string>,locations:list<string>,can_dismiss:bool}|null
	 */
	public function check() :?array {
		$invite = ( new NetworkInviteRepository() )->first();
		if ( $invite === null ) {
			return null;
		}

		return [
			'id'          => 'importexport_network_invite',
			'type'        => 'warning',
			'text'        => [
				sprintf(
					'%s %s: <code>%s</code>. <a href="%s">%s</a>',
					esc_html__( 'You have been invited to join a network.', 'wp-simple-firewall' ),
					esc_html__( 'Site URL', 'wp-simple-firewall' ),
					esc_html( (string)$invite[ 'master_url' ] ),
					esc_url( (string)$invite[ 'review_url' ] ),
					esc_html__( 'Click to review', 'wp-simple-firewall' )
				),
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => false,
		];
	}
}
