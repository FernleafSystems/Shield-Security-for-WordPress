<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals\PushSignalsToCS;

class CrowdsecSignals extends CrowdsecBase {

	protected function cmdParts() :array {
		return [ 'signals' ];
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'action',
				'options'     => [
					'list',
					'push',
				],
				'optional'    => false,
				'description' => 'Action to take with the signals.',
			],
		];
	}

	protected function cmdShortDescription() :string {
		return 'Perform actions with pending CrowdSec signals.';
	}

	public function runCmd() :void {
		switch ( $this->execCmdArgs[ 'action' ] ) {
			case 'list':
//				var_dump(
//					\array_map( fn( $record ) => $record->getRawData(), ( new PushSignalsToCS() )->getRecordsByScope() )
//				);
				break;
			case 'push':
				try {
					( new PushSignalsToCS() )->push();
				}
				catch ( LibraryPrefixedAutoloadNotFoundException $e ) {
				}
				break;
			default:
				\WP_CLI::error( 'Provide a valid action.' );
				break;
		}
	}
}