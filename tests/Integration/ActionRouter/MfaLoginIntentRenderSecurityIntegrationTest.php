<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components\LoginIntentFormShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class MfaLoginIntentRenderSecurityIntegrationTest extends ShieldIntegrationTestCase {

	public function test_shield_login_intent_error_message_is_escaped_in_rendered_output() :void {
		$userId = $this->loginAsAdministrator();
		$payload = '<img src=x onerror=alert(1)>';

		$output = self::con()->action_router->render(
			LoginIntentFormShield::class,
			LoginRequestValues::buildLoginIntentRenderData( [
				'user_id'           => $userId,
				'include_body'      => true,
				'plain_login_nonce' => 'login-nonce',
				'msg_error'         => $payload,
			], '/wp-login.php' )
		);

		$this->assertStringNotContainsString( '<img src=x', $output );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $output );
	}
}
