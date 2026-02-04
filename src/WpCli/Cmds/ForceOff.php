<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Services\Services;

class ForceOff extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'forceoff' ];
	}

	protected function cmdShortDescription() :string {
		return 'Manage the `forceoff` file.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'action',
				'options'     => [
					'create',
					'delete',
					'status',
				],
				'default'     => 'status',
				'optional'    => false,
				'description' => 'Action to take with the `forceoff` file.',
			],
		];
	}

	public function runCmd() :void {
		$FS = Services::WpFs();
		$path = path_join( self::con()->getRootDir(), 'forceoff' );

		switch ( $this->execCmdArgs[ 'action' ] ) {

			case 'create':
				$FS->touch( $path );
				$this->forceOffExists() ?
					\WP_CLI::success( '`forceoff` file created successfully.' )
					: \WP_CLI::error( '`forceoff` file could not be created.' );
				break;

			case 'delete':
				if ( !$this->forceOffExists() ) {
					\WP_CLI::success( "`forceoff` doesn't exist." );
				}
				else {
					$FS->deleteFile( $path );
					$this->forceOffExists() ?
						\WP_CLI::error( "`forceoff` file couldn't be deleted." )
						: \WP_CLI::success( '`forceoff` file deleted successfully.' );
				}
				break;

			case 'status':
			default:
				\WP_CLI::log( $this->forceOffExists() ? '`forceoff` file is present.' : "`forceoff` file isn't present." );
				break;
		}
	}

	private function forceOffExists() :bool {
		return (bool)Services::WpFs()->exists( path_join( self::con()->getRootDir(), 'forceoff' ) );
	}
}