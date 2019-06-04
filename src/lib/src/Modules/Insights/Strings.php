<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		$sName = $this->getCon()->getHumanName();
		return [
			'page_title'          => sprintf( __( '%s Security Insights', 'wp-simple-firewall' ), $sName ),
			'recommendation'      => ucfirst( __( 'recommendation', 'wp-simple-firewall' ) ),
			'suggestion'          => ucfirst( __( 'suggestion', 'wp-simple-firewall' ) ),
			'box_welcome_title'   => sprintf( __( 'Welcome To %s Security Insights Dashboard', 'wp-simple-firewall' ), $sName ),
			'box_receve_subtitle' => sprintf( __( 'Some of the most recent %s events', 'wp-simple-firewall' ), $sName ),

			'never'          => __( 'Never', 'wp-simple-firewall' ),
			'go_pro'         => 'Go Pro!',
			'options'        => __( 'Options', 'wp-simple-firewall' ),
			'not_available'  => __( 'Sorry, this feature would typically be used by professionals and so is a Pro-only feature.', 'wp-simple-firewall' ),
			'not_enabled'    => __( "This feature isn't currently enabled.", 'wp-simple-firewall' ),
			'please_upgrade' => __( 'You can activate this feature (along with many others) and support development of this plugin for just $12.', 'wp-simple-firewall' ),
			'please_enable'  => __( 'Please turn on this feature in the options.', 'wp-simple-firewall' ),
			'only_1_dollar'  => __( 'for just $1/month', 'wp-simple-firewall' ),
		];
	}
}