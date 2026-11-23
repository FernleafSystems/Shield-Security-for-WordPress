<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;
use FernleafSystems\Wordpress\Services\Services;

class NewsletterSubscribe extends Base {

	public const SLUG = 'newsletter_subscribe';

	public function getName() :string {
		return __( 'Join Us', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$user = Services::WpUsers()->getCurrentWpUser();
		return [
			'hrefs'   => [
				'email' => 'https://clk.shldscrty.com/pluginshieldsecuritynewsletter',
			],
			'strings' => [
				/* translators: %s: plugin name */
				'step_title'           => sprintf( __( 'Join The %s Newsletter', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'intro'                => __( 'Half the battle in securing your WordPress site is awareness of the issues and keeping on top of the latest news.', 'wp-simple-firewall' ),
				'email_label'          => __( 'Email', 'wp-simple-firewall' ),
				'email_placeholder'    => __( 'Email address', 'wp-simple-firewall' ),
				'first_name'           => __( 'First name', 'wp-simple-firewall' ),
				'last_name'            => __( 'Last name', 'wp-simple-firewall' ),
				'gdpr_title'           => __( 'GDPR & Terms', 'wp-simple-firewall' ),
				'gdpr_agree'           => __( 'I agree to GDPR and Privacy Policies', 'wp-simple-firewall' ),
				'gdpr_help'            => __( 'By subscribing you agree to our storage and processing of your information. You can unsubscribe at any time.', 'wp-simple-firewall' ),
				/* translators: %s: name of the newsletter */
				'newsletter_button'    => sprintf( __( "Join our %s Newsletter", 'wp-simple-firewall' ), 'ShieldNOTES' ),
				/* translators: %s: a "sign up directly through our site" link */
				'signup_fallback'      => __( 'If there is an error in automatically subscribing you to the newsletter, please %s.', 'wp-simple-firewall' ),
				'signup_fallback_link' => __( 'sign up directly through our site', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			],
		];
	}

	public function processStepFormSubmit( array $form ) :Response {
		$resp = new Response();

		if ( ( $form[ 'gdpr_agree' ] ?? '' ) !== 'Y' ) {
			$resp->success = false;
			$resp->error = __( 'We ask that you agree to our privacy policy.', 'wp-simple-firewall' );
		}
		else {
			$resp->success = ( new \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools\NewsletterSubscribe() )
				->run( $form );
			if ( $resp->success ) {
				$meta = self::con()->user_metas->current();
				$flags = $meta->flags;
				$flags[ 'merlin_step_newsletter_subscribe' ] = Services::Request()->ts();
				$meta->flags = $flags;
				$resp->message = __( "Thanks! - Please check your email for confirmation.", 'wp-simple-firewall' );
			}
			else {
				$resp->error = __( 'There was a problem automatically subscribing you to the newsletter.', 'wp-simple-firewall' );
			}
		}
		return $resp;
	}

	public function skipStep() :bool {
		return isset( self::con()->user_metas->current()->flags[ 'merlin_step_newsletter_subscribe' ] )
			   || !self::con()->comps->shieldnet->canHandshake();
	}
}