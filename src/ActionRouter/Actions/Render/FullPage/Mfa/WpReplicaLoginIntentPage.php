<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

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
						return $this->getLoginIntentJavascript();
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
				'header' => $con->action_router->render( Components\WpLoginReplicaHeader::class,
					\array_merge( $this->action_data, [
						'title' => __( 'Login 2FA Verification', 'wp-simple-firewall' )
					] )
				),
				'body'   => $this->action_data[ 'include_body' ] ?
					$con->action_router->render( Components\WpLoginReplicaBody::class, $this->action_data ) : '',
				'footer' => $con->action_router->render( Components\WpLoginReplicaFooter::class, $this->action_data ),
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