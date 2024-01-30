<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseExtension {

	use ExecOnce;
	use PluginControllerConsumer;

	public const SLUG = '';

	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var ExtensionConfigVO
	 */
	private $cfg;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $file, array $cfg ) {
		$this->file = $file;
		$this->cfg = ( new ExtensionConfigVO() )->applyFromArray( $cfg );
		if ( empty( static::SLUG ) || static::SLUG !== $this->cfg->slug ) {
			throw new \Exception( 'Invalid Shield extension configuration' );
		}
	}

	public function cfg() :ExtensionConfigVO {
		return $this->cfg;
	}

	protected function canRun() :bool {
		return $this->isAvailable();
	}

	protected function run() {
		add_action( 'init', function () {
			$this->initUpgrades();
		} );
	}

	public function isAvailable() :bool {
		return $this->requirementsMet();
	}

	protected function initUpgrades() {
	}

	public function canExtendRules() :bool {
		return true;
	}

	public function getRuleBuilders() :array {
		return [];
	}

	public function getRuleConditions() :array {
		return [];
	}

	public function getRuleResponses() :array {
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
				'min' => '18.5',
			],
			'wp'     => [
				'min' => '5.7',
			],
		];
	}

	protected function getUpgradeConfig() :array {
		return [
			'file' => $this->file,
			'slug' => sprintf( 'shield-ext-%s', static::SLUG ),
		];
	}
}
