<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

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
		$stepSlug = $this->action_data[ 'vars' ][ 'step_slug' ] ?? '';
		$strings = [];

		if ( $stepSlug === 'license' ) {
			$strings[ 'license_check_text' ] = sprintf( __( 'If you already have a %s license assigned to this site,', 'wp-simple-firewall' ), $pluginName );
			$strings[ 'check_license_button' ] = sprintf( __( 'Check For %s License', 'wp-simple-firewall' ), $pluginName );
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

		return [
			'hrefs'   => [
				'dashboard' => self::con()->plugin_urls->adminHome(),
			],
			'strings' => $strings,
		];
	}

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'step_slug' ] );
	}
}
