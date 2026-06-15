<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

enum PluginType :string {
	case Standard = 'plugin';
	case MustUse = 'mu-plugin';

	public function label() :string {
		return match ( $this ) {
			self::Standard => __( 'Plugin', 'wp-simple-firewall' ),
			self::MustUse  => __( 'Must-Use Plugin', 'wp-simple-firewall' ),
		};
	}
}
