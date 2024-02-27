<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Common\BaseConditionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumConditions,
	EnumResponses
};

class FindFromSlug {

	/**
	 * @return ?Base|string
	 */
	public static function Condition( string $slug ) :?string {
		return self::Find( $slug, EnumConditions::Conditions() );
	}

	public static function Response( string $slug ) :?string {
		return self::Find( $slug, EnumResponses::Responses() );
	}

	private static function Find( string $slug, array $collection ) :?string {
		$theClass = null;
		foreach ( $collection as $item ) {
			/** @var BaseConditionResponse|string $item */
			if ( $item::Slug() === $slug ) {
				$theClass = $item;
				break;
			}
		}
		return $theClass;
	}
}