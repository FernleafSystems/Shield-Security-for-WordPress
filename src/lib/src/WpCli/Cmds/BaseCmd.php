<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseCmd {

	use ExecOnce;
	use PluginControllerConsumer;

	protected $execCmdFlags;

	protected $execCmdArgs;

	/**
	 * License checking WP-CLI cmds may be run if you're not premium,
	 * or you're premium and you haven't switched it off (parent).
	 */
	protected function canRun() :bool {
		return self::con()->caps->canWpcliLevel2();
	}

	protected function run() {
		try {
			$this->declareCmd();
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function declareCmd() :void {
		\WP_CLI::add_command(
			$this->cmdBuildParts(),
			$this->cmdExec(),
			$this->mergeCommonCmdArgs( [
				'shortdesc' => $this->cmdShortDescription(),
				'synopsis'  => $this->cmdSynopsis(),
			] )
		);
	}

	abstract protected function cmdShortDescription() :string;

	protected function cmdExec() :callable {
		return [ $this, 'execCmd' ];
	}

	abstract protected function cmdParts() :array;

	protected function cmdSynopsis() :array {
		return [];
	}

	/**
	 * @throws \Exception
	 */
	public function execCmd( array $flags, array $args ) :void {
		$this->execCmdFlags = $flags;
		$this->execCmdArgs = $args;
		$this->preRunCmd();
		$this->runCmd();
		$this->postRunCmd();
	}

	protected function preRunCmd() {
	}

	/**
	 * @throws \WP_CLI\ExitException
	 */
	abstract protected function runCmd() :void;

	protected function postRunCmd() {
	}

	protected function cmdBuildParts() :string {
		return \implode( ' ', \array_merge( $this->getCmdBase(), $this->cmdParts() ) );
	}

	protected function getCmdBase() :array {
		return [
			'shield'
		];
	}

	protected function mergeCommonCmdArgs( array $args ) :array {
		return \array_merge( $this->getCommonCmdArgs(), $args );
	}

	protected function getCommonCmdArgs() :array {
		return [
			'before_invoke' => function () {
				$this->beforeInvokeCmd();
			},
			'after_invoke'  => function () {
				$this->afterInvokeCmd();
			},
			'when'          => 'before_wp_load',
		];
	}

	protected function afterInvokeCmd() {
	}

	protected function beforeInvokeCmd() {
	}

	protected function isForceFlag() :bool {
		return (bool)\WP_CLI\Utils\get_flag_value( $this->execCmdArgs, 'force', false );
	}
}