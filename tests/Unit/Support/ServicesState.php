<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Services;

class ServicesState {

	public static function snapshot() :array {
		return [
			'items'    => self::getServicesProperty( 'items' )->getValue(),
			'services' => self::getServicesProperty( 'services' )->getValue(),
		];
	}

	public static function restore( array $snapshot ) :void {
		self::getServicesProperty( 'items' )->setValue( null, $snapshot[ 'items' ] ?? null );
		self::getServicesProperty( 'services' )->setValue( null, $snapshot[ 'services' ] ?? null );
	}

	public static function installItems( array $items ) :void {
		self::getServicesProperty( 'items' )->setValue( null, $items );
		self::getServicesProperty( 'services' )->setValue( null, null );
	}

	private static function getServicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}
}
