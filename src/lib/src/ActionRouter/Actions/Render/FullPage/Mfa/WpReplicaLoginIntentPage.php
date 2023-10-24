<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaWebauthnAuthenticationStart;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaWebauthnAuthenticationVerify;
use FernleafSystems\Wordpress\Services\Services;

class WpReplicaLoginIntentPage extends BaseLoginIntentPage {

	public const SLUG = 'render_login_intent_wploginreplica';
	public const TEMPLATE = '/components/wplogin_replica/wp_login.twig';

	protected function preExec() {
		add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {

			add_filter( 'shield/custom_localisations/components', function ( array $components ) {
				$components[ 'login_2fa' ] = [
					'key'     => 'login_2fa',
					'handles' => [
						'login_2fa',
					],
					'data'    => function () {
						return [
							'ajax' => [
								'wan_auth_start'  => ActionData::Build( MfaWebauthnAuthenticationStart::class, true, [
									'active_wp_user' => $this->action_data[ 'user_id' ],
								] ),
								'wan_auth_verify' => ActionData::Build( MfaWebauthnAuthenticationVerify::class, true, [
									'active_wp_user' => $this->action_data[ 'user_id' ],
								] ),
							],
						];
					},
				];
				return $components;
			} );

			return \array_merge( $assets, [
				'login_2fa'
			] );
		} );
	}

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'header' => $con->action_router->render( Components\WpLoginReplicaHeader::SLUG,
					\array_merge( $this->action_data, [
						'title' => __( 'Login 2FA Verification', 'wp-simple-firewall' )
					] )
				),
				'body'   => $this->action_data[ 'include_body' ] ?
					$con->action_router->render( Components\WpLoginReplicaBody::SLUG, $this->action_data ) : '',
				'footer' => $con->action_router->render( Components\WpLoginReplicaFooter::SLUG, $this->action_data ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id',
			'include_body',
		];
	}
}