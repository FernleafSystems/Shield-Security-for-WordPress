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
				'step_title' => __( 'Join The Shield Security Newsletter', 'wp-simple-firewall' ),
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

		if ( empty( $form[ 'gdpr_agree' ] ) ) {
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