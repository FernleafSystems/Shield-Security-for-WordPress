<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits\{
	AutoSnakeCaseSlug,
	ParamsConsumer,
	RuleConsumer,
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\GenerateNameFromSlug;
use FernleafSystems\Wordpress\Services\Utilities\Strings;

abstract class BaseConditionResponse extends \FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass {

	use PluginControllerConsumer;
	use AutoSnakeCaseSlug;
	use ParamsConsumer;
	use RuleConsumer;
	use ThisRequestConsumer;

	public static function Slug() :string {
		return Strings::CamelToSnake( ( new \ReflectionClass( static::class ) )->getShortName() );
	}

	public function getDescription() :string {
		return $this->getName();
	}

	public function getName() :string {
		return ( new GenerateNameFromSlug() )->gen( $this->getSlug() );
	}

	public function getParamsDef() :array {
		return [];
	}
}