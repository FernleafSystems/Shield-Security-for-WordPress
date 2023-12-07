<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits;

use FernleafSystems\Wordpress\Services\Utilities\Strings;

trait AutoSnakeCaseSlug {

	public function getSlug() :string {
		return Strings::CamelToSnake( ( new \ReflectionClass( static::class ) )->getShortName() );
	}
}