<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseWpCliCmd extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getMod()
					->getWpCli()
					->getCfg()[ 'enabled' ];
	}

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
	}

	protected function run() {
		try {
			$this->addCmds();
		}
		catch ( \Exception $e ) {
		}
	}

	protected function buildCmd( array $parts ) :string {
		return implode( ' ',
			array_filter( array_merge( $this->getCmdBase(), $parts ) )
		);
	}

	/**
	 * @return string[]
	 */
	protected function getCmdBase() :array {
		$cfg = $this->getMod()->getWpCli()->getCfg();
		return [
			$cfg[ 'cmd_root' ],
			$cfg[ 'cmd_base' ]
		];
	}

	protected function mergeCommonCmdArgs( array $args ) :array {
		return array_merge( $this->getCommonCmdArgs(), $args );
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

	/**
	 * @throws \WP_CLI\ExitException
	 */
	protected function loadUserFromArgs( array $args ) :\WP_User {
		$WPU = Services::WpUsers();

		$user = null;
		if ( isset( $args[ 'uid' ] ) ) {
			$user = $WPU->getUserById( $args[ 'uid' ] );
		}
		elseif ( isset( $args[ 'email' ] ) ) {
			$user = $WPU->getUserByEmail( $args[ 'email' ] );
		}
		elseif ( isset( $args[ 'username' ] ) ) {
			$user = $WPU->getUserByUsername( $args[ 'username' ] );
		}

		if ( !$user instanceof \WP_User || $user->ID < 1 ) {
			\WP_CLI::error( "Couldn't find that user." );
		}

		return $user;
	}

	protected function isForceFlag( array $args ) :bool {
		return (bool)\WP_CLI\Utils\get_flag_value( $args, 'force', false );
	}
}