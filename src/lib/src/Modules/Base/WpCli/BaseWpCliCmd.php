<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseWpCliCmd {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\ExecOnce;

	protected function canRun() :bool {
		/** @var Options $pluginModOpts */
		$pluginModOpts = $this->getCon()
							  ->getModule_Plugin()
							  ->getOptions();
		return $this->getOptions()->getWpCliCfg()[ 'enabled' ] && $pluginModOpts->isEnabledWpcli();
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
			array_filter( array_merge( $this->getBaseCmdParts(), $parts ) )
		);
	}

	/**
	 * @return string[]
	 */
	protected function getBaseCmdParts() :array {
		return [ 'shield', $this->getBaseCmdKey() ];
	}

	protected function getBaseCmdKey() :string {
		$root = $this->getOptions()->getWpCliCfg()[ 'root' ];
		return empty( $root ) ? $this->getMod()->getModSlug( false ) : $root;
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
	 * @param array $args
	 * @return \WP_User
	 * @throws \WP_CLI\ExitException
	 */
	protected function loadUserFromArgs( array $args ) :\WP_User {
		$oWpUsers = Services::WpUsers();

		$user = null;
		if ( isset( $args[ 'uid' ] ) ) {
			$user = $oWpUsers->getUserById( $args[ 'uid' ] );
		}
		elseif ( isset( $args[ 'email' ] ) ) {
			$user = $oWpUsers->getUserByEmail( $args[ 'email' ] );
		}
		elseif ( isset( $args[ 'username' ] ) ) {
			$user = $oWpUsers->getUserByUsername( $args[ 'username' ] );
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