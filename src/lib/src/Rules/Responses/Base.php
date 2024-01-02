<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	RuleVO,
	Traits
};
use FernleafSystems\Wordpress\Services\Utilities\Strings;

abstract class Base {

	use PluginControllerConsumer;
	use Traits\AutoSnakeCaseSlug;
	use Traits\ParamsConsumer;
	use Traits\ThisRequestConsumer;

	public const SLUG = '';

	/**
	 * @var array
	 */
	protected $params;

	/**
	 * @var array
	 */
	protected $conditionTriggerMeta;

	public function __construct( array $params = [], array $conditionTriggerMeta = [] ) {
		$this->setParams( $params );
		$this->conditionTriggerMeta = $conditionTriggerMeta;
	}

	public static function Slug() :string {
		return Strings::CamelToSnake( ( new \ReflectionClass( static::class ) )->getShortName() );
	}

	/**
	 * @deprecated 18.6
	 */
	public function setRule( RuleVO $rule ) :self {
		return $this;
	}

	/**
	 * @deprecated 18.6
	 */
	public function setConditionTriggerMeta( array $meta ) :self {
		$this->conditionTriggerMeta = $meta;
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	abstract public function execResponse() :void;

	protected function getConsolidatedConditionMeta() :array {
		return $this->conditionTriggerMeta;
	}

	public function getName() :string {
		$name = \ucwords( \str_replace( '_', ' ', $this->getSlug() ) );
		return \str_ireplace( [ 'Wp ', 'Ip ', 'ajax', 'wpcli' ], [ 'WP ', 'IP ', 'AJAX', 'WP-CLI' ], $name );
	}

	public function getParamsDef() :array {
		return [];
	}

	public function isTerminating() :bool {
		return false;
	}
}