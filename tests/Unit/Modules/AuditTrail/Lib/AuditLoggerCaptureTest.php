<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\AuditTrail\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AuditLoggerCaptureTest extends BaseUnitTest {

	private function makeLogger() :AuditLogger {
		$ref = new \ReflectionClass( AuditLogger::class );
		/** @var AuditLogger $logger */
		$logger = $ref->newInstanceWithoutConstructor();
		return $logger;
	}

	private function captureEvent( AuditLogger $logger, string $event, array $meta, array $def ) :void {
		$method = new \ReflectionMethod( $logger, 'captureEvent' );
		$method->setAccessible( true );
		$method->invoke( $logger, $event, $meta, $def );
	}

	private function getAuditLogs( AuditLogger $logger ) :array {
		$prop = new \ReflectionProperty( $logger, 'auditLogs' );
		$prop->setAccessible( true );
		return (array)$prop->getValue( $logger );
	}

	private function setupApplyFiltersPassthrough() :void {
		Functions\when( 'apply_filters' )->alias( function ( string $tag, $value = null ) {
			return $value;
		} );
	}

	public function test_non_multiple_events_with_same_slug_overwrite_each_other() :void {
		$this->setupApplyFiltersPassthrough();
		$logger = $this->makeLogger();
		$eventDef = [
			'audit'          => true,
			'audit_multiple' => false,
			'level'          => 'info',
		];

		$this->captureEvent( $logger, 'report_generated', [
			'audit_params' => [
				'type'     => 'Alert',
				'interval' => 'hourly',
			],
		], $eventDef );
		$this->captureEvent( $logger, 'report_generated', [
			'audit_params' => [
				'type'     => 'Info',
				'interval' => 'hourly',
			],
		], $eventDef );

		$this->assertSame( 'Info', $this->getAuditLogs( $logger )[ 'report_generated' ][ 'audit_params' ][ 'type' ] ?? '' );
	}

	public function test_suppress_audit_prevents_overwrite_for_internal_follow_up_event() :void {
		$this->setupApplyFiltersPassthrough();
		$logger = $this->makeLogger();
		$eventDef = [
			'audit'          => true,
			'audit_multiple' => false,
			'level'          => 'info',
		];

		$this->captureEvent( $logger, 'report_generated', [
			'audit_params' => [
				'type'     => 'Alert',
				'interval' => 'hourly',
			],
		], $eventDef );
		$this->captureEvent( $logger, 'report_generated', [
			'suppress_audit' => true,
			'audit_params'   => [
				'type'     => 'Info',
				'interval' => 'hourly',
			],
		], $eventDef );

		$this->assertSame( 'Alert', $this->getAuditLogs( $logger )[ 'report_generated' ][ 'audit_params' ][ 'type' ] ?? '' );
	}
}
