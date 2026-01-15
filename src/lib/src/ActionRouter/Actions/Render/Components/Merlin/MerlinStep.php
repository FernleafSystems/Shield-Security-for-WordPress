<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class MerlinStep extends BaseRender {

	public const SLUG = 'render_merlin_step';
	public const TEMPLATE = '/components/merlin/steps/%s.twig';

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		parent::checkAvailableData();

		$slug = $this->action_data[ 'vars' ][ 'step_slug' ] ?? null;
		if ( !\preg_match( '#^[a-z0-9_]+$#', (string)$slug ) ) {
			throw new ActionException( __( 'Invalid Step Slug', 'wp-simple-firewall' ) );
		}
	}

	protected function getRenderData() :array {
		$pluginName = self::con()->labels->Name;
		$silentCaptcha = self::con()->labels->getBrandName( 'silentcaptcha' );
		$stepSlug = $this->action_data[ 'vars' ][ 'step_slug' ] ?? '';
		$strings = [];

		if ( $stepSlug === 'license' ) {
			$strings[ 'license_check_text' ] = sprintf( __( 'If you already have a %s license assigned to this site,', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'check_license_button' ] = sprintf( __( 'Check For %s License', 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'free_trial' ) {
			$strings[ 'free_trial_text' ] = sprintf( __( 'Get the risk-free, no obligation free trial of %s today.', 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'security_badge' ) {
			$strings[ 'security_badge_text_1' ] = sprintf( __( 'The %s Badge demonstrates to your customers that you take security seriously.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'security_badge_text_2' ] = __( 'Turning this on adds a translucent badge to the footer of your website.', 'wp-simple-firewall' );
			$strings[ 'security_badge_text_button' ] = __( 'Set Security Badge.', 'wp-simple-firewall' );
		}

		if ( $stepSlug === 'security_admin' ) {
			$strings[ 'security_admin_text_1' ] = __( 'Security Admin provides an additional authentication layer that ensures only vetted admins may make changes to your site security.', 'wp-simple-firewall' );
			$strings[ 'security_admin_text_2' ] = __( 'It also helps lockdown critical WordPress settings.', 'wp-simple-firewall' );
			$strings[ 'security_admin_text_3' ] = sprintf( __( 'This feature relies on a secret PIN (which you create below), that you will use to verify your identity with the %s plugin.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'security_admin_text_button' ] = __( 'Turn On Security Admin', 'wp-simple-firewall' );
		}

		if ( $stepSlug === 'opt_in' ) {
			$strings[ 'opt_in_text' ] = sprintf( __( 'Jump into our %s Facebook group where you can ask questions from our active community and keep up-to-date on our latest news', 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'newsletter_subscribe' ) {
			$strings[ 'newsletter_button' ] = sprintf( __( "Join our %s Newsletter", 'wp-simple-firewall' ), 'ShieldNOTES' );
		}

		if ( $stepSlug === 'integrations' ) {
			$strings[ 'integrations_text' ] = sprintf( __( '%s provides built-in integrations to protect the forms of 3rd party plugins against SPAM Bots.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'integrations_text2' ] = sprintf( __( "Protection for WordPress forms is included as-standard, and you'll have access to some, or all, 3rd party integrations, depending on your %s plan.", 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'guided_setup_welcome' ) {
			$strings[ 'welcome_text1' ] = sprintf( __( '%s does a lot, so it can be hard to know where to begin.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'welcome_text2' ] = sprintf( __( "This wizard will walk you through some of %s's key features, to get you up and running in under 3 minutes.", 'wp-simple-firewall' ), $pluginName );
			$strings[ 'welcome_video_title' ] = sprintf( __( '%s, Founder Introduction', 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'ip_detect' ) {
			$strings[ 'ip_detect_able' ] = __( 'Detecting the correct visitor IP address is critical for distinguishing each site visitor from the next.', 'wp-simple-firewall' );
			$strings[ 'ip_detect_text' ] = sprintf( __( 'This step might be the most important as it helps ensure %s can always find the correct Visitor IP.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'ip_detect_auto' ] = sprintf( __( '%s tries to do this automatically and while it works 99% of the time, sometimes a strange webhost configuration mix it up a little..', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'ip_detect_resolve' ] = sprintf( __( 'To resolve this, you can inform %s what your IP address is right now, and this allows it to configure the optimal IP source.', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'ip_detect_warning' ] = sprintf( __( "%s couldn't detect any IP addresses. This represents a critical web hosting configuration problem.", 'wp-simple-firewall' ), $pluginName );
			$strings[ 'ip_detect_not_required' ] = sprintf( __( "Configuration isn't required as only 1 distinct IP address was detected.", 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'ip_blocking' ) {
			$strings[ 'ip_blocking_text' ] = sprintf( __( 'We recommend somewhere between 5 and 10. The smaller the limit, the stricter %s will be.', 'wp-simple-firewall' ), $pluginName );
		}

		if ( $stepSlug === 'comment_spam' ) {
			/* translators: %1$s: Shield Security name, %2$s: bot detection technology name */
			$strings[ 'comment_spam_text' ] = sprintf( __( '%1$s blocks all automated bot SPAM using our %2$s, our exclusive, invisible, GDPR-compliant bot-detection technology.', 'wp-simple-firewall' ), $pluginName, $silentCaptcha );
			$strings[ 'silentcaptcha_detect_bots' ] = sprintf( __( '%s will detect bots that attempt to post comment SPAM to your site, without challenging your legitimate visitors.', 'wp-simple-firewall' ), $silentCaptcha );
		}

		if ( $stepSlug === 'login_protection' ) {
			$strings[ 'silentcaptcha_detect_login_bots' ] = sprintf( __( '%s will detect bots that attempt to login to your site, without challenging your legitimate users.', 'wp-simple-firewall' ), $silentCaptcha );
		}

		$videoId = $this->action_data[ 'vars' ][ 'video_id' ] ?? '';

		return [
			'hrefs'   => [
				'dashboard' => self::con()->plugin_urls->adminHome(),
				'gopro'     => 'https://clk.shldscrty.com/ap',
			],
			'imgs'    => [
				'play_button' => self::con()->urls->svg( 'play-circle' ),
				'video_thumb' => $this->getVideoThumbnailUrl( $videoId )
			],
			'strings' => $strings,
		];
	}

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'step_slug' ] );
	}

	/**
	 * @see https://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo
	 */
	private function getVideoThumbnailUrl( string $videoID ) :string {
		$thumbnail = '';
		if ( !empty( $videoID ) ) {
			$raw = Services::HttpRequest()->getContent( sprintf( 'https://vimeo.com/api/v2/video/%s.json', $videoID ) );
			if ( !empty( $raw ) ) {
				$thumbnail = \json_decode( $raw, true )[ 0 ][ 'thumbnail_large' ] ?? '';
			}
		}
		return $thumbnail;
	}
}
