<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files;

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
	 * @var UpgradeHandlersBase
	 */
	protected $upgradesHandler;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $file, array $cfg ) {
		if ( empty( static::SLUG ) ) {
			throw new \Exception( 'Invalid Shield extension configuration' );
		}
		$this->file = $file;
		$this->cfg = ( new ExtensionConfigVO() )->applyFromArray( $cfg );
		$this->cfg->file = $file;
		$this->cfg->slug = static::SLUG;
	}

	public function cfg() :ExtensionConfigVO {
		return $this->cfg;
	}

	protected function canRun() :bool {
		return $this->isAvailable();
	}

	protected function run() {
		add_action( 'init', function () {
			$this->getUpgradesHandler();
		} );
	}

	public function isAvailable() :bool {
		return $this->requirementsMet();
	}

	/**
	 * @return ?UpgradeHandlersBase
	 */
	public function getUpgradesHandler() :?UpgradeHandlersBase {
		return null;
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
				'min' => '20.0',
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

	public function version() :string {
		return ( new Files() )->findPluginFromFile( $this->file )->Version ?? '';
	}
}