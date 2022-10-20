<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;

class WpReplicaLoginIntentPage extends Base {

	const SLUG = 'render_login_intent_wploginreplica';
	const TEMPLATE = '/components/wplogin_replica/wp_login.twig';

	protected function preExec() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/login2fa';
			$enqueues[ Enqueue::CSS ][] = 'shield/login2fa';
			return $enqueues;
		} );
	}

	protected function getRenderData() :array {
		$con = $this->getCon();
		$AR = $con->getModule_Insights()->getActionRouter();
		return [
			'content' => [
				'header' => $AR->render( Components\WpLoginReplicaHeader::SLUG, [
					'title' => __( 'Login 2FA Verification', 'wp-simple-firewall' )
				] ),
				'body'   => $this->action_data[ 'include_body' ] ?
					$AR->render( Components\WpLoginReplicaBody::SLUG, $this->action_data ) : '',
				'footer' => $AR->render( Components\WpLoginReplicaFooter::SLUG ),
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