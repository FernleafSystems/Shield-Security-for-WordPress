<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxBatchRequests;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BaseActionSetActionOverrideTest extends BaseUnitTest {

	public function testSetActionOverrideDoesNotTriggerDynamicPropertyWriteWarnings() :void {
		$action = new AjaxBatchRequests();

		\set_error_handler( function ( int $severity, string $message, string $file = '', int $line = 0 ) :bool {
			throw new \ErrorException( $message, 0, $severity, $file, $line );
		} );

		try {
			$returned = $action->setActionOverride( Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED, false );
			$this->assertSame( $action, $returned );
		}
		finally {
			\restore_error_handler();
		}

		$this->assertFalse(
			$action->action_data[ 'action_overrides' ][ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED ]
		);
	}

	public function testSetActionOverrideMergesWithExistingOverrides() :void {
		$action = new AjaxBatchRequests( [
			'action_overrides' => [
				'custom_override' => true,
			],
		] );

		$action->setActionOverride( Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED, false );

		$this->assertTrue( $action->action_data[ 'action_overrides' ][ 'custom_override' ] );
		$this->assertFalse(
			$action->action_data[ 'action_overrides' ][ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED ]
		);
	}
}
