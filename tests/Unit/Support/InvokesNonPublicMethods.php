<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

trait InvokesNonPublicMethods {

	protected function invokeNonPublicMethod( object $subject, string $methodName, array $args = [] ) {
		$method = new \ReflectionMethod( $subject, $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $subject, $args );
	}
}
