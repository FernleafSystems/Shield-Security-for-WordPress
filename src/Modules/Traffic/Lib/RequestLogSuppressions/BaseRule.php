<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions;

abstract class BaseRule {

	abstract public function matches( Context $context ) :bool;
}
