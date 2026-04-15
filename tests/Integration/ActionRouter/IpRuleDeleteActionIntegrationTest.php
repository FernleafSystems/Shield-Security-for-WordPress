<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\IpRuleDelete
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class IpRuleDeleteActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->loginAsSecurityAdmin();
	}

	public function test_ip_rule_delete_action_uses_audited_delete_path() :void {
		$record = ( new AddRule() )
			->setIP( '10.11.12.13' )
			->toManualBlacklist( 'delete-test' );

		$this->captureShieldEvents();
		$response = ( new ActionProcessor() )->processAction( IpRuleDelete::SLUG, [
			'rid' => $record->id,
		] );

		$this->assertTrue( $response->payload()[ 'success' ] ?? false );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( 'ip_unblock' ) );
		$this->assertEmpty( self::con()->db_con->ip_rules->getQuerySelector()->byId( $record->id ) );
	}
}
