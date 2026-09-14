<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class DashboardEventIcons {

	use PluginControllerConsumer;

	private const ICONS = [
		'firewall_block'           => 'shield-fill-exclamation',
		'login_success'            => 'person-check-fill',
		'login_block'              => 'key-fill',
		'conn_kill'                => 'x-octagon-fill',
		'ip_blocked'               => 'shield-fill-x',
		'ip_offense'               => 'shield-fill-exclamation',
		'block_register'           => 'person-x-fill',
		'block_xml'                => 'file-earmark-x-fill',
		'comment_spam_block'       => 'chat-left-text-fill',
		'scan_run'                 => 'search',
		'scan_items_found'         => 'exclamation-triangle-fill',
		'scan_item_repair_success' => 'file-earmark-check-fill',
	];

	public function iconClassForKey( string $key ) :string {
		return self::con()->svgs->iconClass( self::ICONS[ $key ] );
	}
}
