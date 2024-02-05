<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks\Requirements;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BlockIncompatible {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return false;
	}

	protected function run() {
		add_filter( 'site_transient_update_plugins', [ $this, 'blockIncompatibleUpdates' ] );
	}

	public function blockIncompatibleUpdates( $updates ) {
		$file = self::con()->base_file;
		if ( \is_object( $updates ) && !empty( $updates->response[ $file ] ) ) {
			foreach ( self::con()->cfg->upgrade_reqs as $shieldVer => $verReqs ) {
				$toHide = \version_compare( $updates->response[ $file ]->new_version, $shieldVer, '>=' )
						  && (
							  !Services::Data()->getPhpVersionIsAtLeast( (string)$verReqs[ 'php' ] )
							  || !Services::WpGeneral()->getWordpressIsAtLeastVersion( $verReqs[ 'wp' ] )
							  || ( !empty( $verReqs[ 'mysql' ] ) && !( new Requirements() )->isMysqlVersionSupported( $verReqs[ 'mysql' ] ) )
						  );
				if ( $toHide ) {
					unset( $updates->response[ $file ] );
					break;
				}
			}
		}
		return $updates;
	}
}