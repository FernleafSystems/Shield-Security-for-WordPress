<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

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

	protected function canRun() :bool {
		return $this->isAvailable();
	}

	public function isAvailable() :bool {
		return self::con()->isPremiumActive() && $this->requirementsMet();
	}

	public function getRuleBuilders() :array {
		return [];
	}

	protected function requirementsMet() :bool {
		$met = true;
		foreach ( $this->requirements() as $component => $req ) {
			if ( $met && $component === 'php' ) {
				if ( isset( $req[ 'min' ] ) ) {
					$met = Services::Data()->getPhpVersionIsAtLeast( $req[ 'min' ] );
				}
			}
			elseif ( $met && $component === 'wp' ) {
				if ( isset( $req[ 'min' ] ) ) {
					$met = Services::WpGeneral()->getWordpressIsAtLeastVersion( $req[ 'min' ] );
				}
			}
			elseif ( $met && $component === 'shield' ) {
				if ( isset( $req[ 'min' ] ) ) {
					$met = \version_compare( self::con()->cfg->version(), $req[ 'min' ], '>=' );
				}
			}
		}
		return $met;
	}

	protected function requirements() :array {
		return [
			'php'    => [
				'min' => '7.4',
			],
			'shield' => [
				'min' => '18.5.8',
			],
			'wp'     => [
				'min' => '5.7',
			],
		];
	}
}
