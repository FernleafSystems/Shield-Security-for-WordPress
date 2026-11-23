<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionDoesNotExistException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests ActionProcessor: action resolution and proper exception handling.
 */
class ActionProcessorTest extends ShieldIntegrationTestCase {

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	public function test_invalid_slug_throws_action_does_not_exist() {
		$this->expectException( ActionDoesNotExistException::class );
		$this->processor()->processAction( 'completely_invalid_action_slug_xyz' );
	}

	public function test_get_action_invalid_slug_throws() {
		$this->expectException( ActionDoesNotExistException::class );
		$this->processor()->getAction( 'nonexistent_action_class', [] );
	}
}
