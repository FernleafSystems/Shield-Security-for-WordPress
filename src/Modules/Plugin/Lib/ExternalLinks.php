<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ExternalLinks {

	use PluginControllerConsumer;

	public const HELPDESK = 'helpdesk';
	public const HOME = 'home';
	public const FACEBOOK_GROUP = 'facebook_group';
	public const NEWSLETTER = 'newsletter';
	public const GOPRO = 'gopro';
	public const FREE_TRIAL = 'free_trial';
	public const REVIEW = 'review';
	public const TESTIMONIALS = 'testimonials';
	public const CROWDSEC = 'crowdsec';

	/**
	 * @return string[]
	 */
	public function all() :array {
		return [
			self::HELPDESK       => self::con()->labels->url_helpdesk,
			self::HOME           => 'https://clk.shldscrty.com/shieldsecurityhome',
			self::FACEBOOK_GROUP => 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',
			self::NEWSLETTER     => 'https://clk.shldscrty.com/emailsubscribe',
			self::GOPRO          => 'https://getshieldsecurity.com/pricing/',
			self::FREE_TRIAL     => 'https://getshieldsecurity.com/free-trial/',
			self::REVIEW         => 'https://clk.shldscrty.com/l1',
			self::TESTIMONIALS   => 'https://clk.shldscrty.com/l2',
			self::CROWDSEC       => 'https://crowdsec.net/',
		];
	}

	public function url( string $key, string $default = '' ) :string {
		return (string)( $this->all()[ $key ] ?? $default );
	}
}

