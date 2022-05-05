<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	protected function getAdditionalDisplayStrings() :array {
		$name = $this->getCon()->getHumanName();
		return [
			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $name ),
			'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'box_welcome_title'   => sprintf( __( 'Welcome To %s Security Insights Dashboard', 'wp-simple-firewall' ), $name ),
			'options'             => __( 'Options', 'wp-simple-firewall' ),
			'not_enabled'         => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade'      => __( 'You can get this feature (along with loads more) by going Pro.', 'wp-simple-firewall' ),
			'please_enable'       => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'no_security_notices' => __( 'There are no important security notices at this time.', 'wp-simple-firewall' ),
			'this_is_wonderful'   => __( 'This is wonderful!', 'wp-simple-firewall' ),
			'yyyymmdd'            => __( 'YYYY-MM-DD', 'wp-simple-firewall' ),
		];
	}
}