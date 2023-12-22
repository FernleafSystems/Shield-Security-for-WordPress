<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits\AutoSnakeCaseSlug;
use FernleafSystems\Wordpress\Services\Utilities\Strings;

abstract class Base {

	use AutoSnakeCaseSlug;
	use PluginControllerConsumer;

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

	protected function setParams( array $params ) {
		$this->params = $params;
		foreach ( $this->getParamsDef() as $key => $def ) {
			if ( !isset( $this->params[ $key ] ) && isset( $def[ 'default' ] ) ) {
				$this->params[ $key ] = $def[ 'default' ];
			}
		}
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setRule( RuleVO $rule ) :self {
		return $this;
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setConditionTriggerMeta( array $meta ) :self {
		$this->conditionTriggerMeta = $meta;
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	abstract public function execResponse() :bool;

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