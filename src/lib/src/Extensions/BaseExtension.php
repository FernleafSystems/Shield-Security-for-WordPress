<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseExtension {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var ExtensionConfigVO
	 */
	private $cfg;

	public function __construct( array $cfg ) {
		$this->cfg = ( new ExtensionConfigVO() )->applyFromArray( $cfg );
	}

	public function cfg() :ExtensionConfigVO {
		return $this->cfg;
	}

	protected function run() {
	}

	public function getRuleBuilders() :array {
		return [];
	}

	public function isAvailable() :bool {
		return self::con()->isPremiumActive();
	}
}
