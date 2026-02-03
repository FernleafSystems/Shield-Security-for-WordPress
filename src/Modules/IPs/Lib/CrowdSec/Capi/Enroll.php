<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi;

use AptowebDeps\CrowdSec\CapiClient\ClientException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Enroll {

	use PluginControllerConsumer;

	public function clearEnrollment() :void {
		try {
			$csCon = self::con()->comps->crowdsec;
			$auth = $csCon->getCApiStore()->getAuth();
			$auth[ 'machine_enrolled' ] = false;
			$auth[ 'enrolled_id' ] = '';
			$csCon->getCApiStore()->storeAuth( $auth );
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws LibraryPrefixedAutoloadNotFoundException
	 */
	public function enroll() :void {
		$csCon = self::con()->comps->crowdsec;
		$auth = $csCon->getCApiStore()->getAuth();

		// Enroll if we have the ID
		$enrollID = \preg_replace( '#[^a-z\d]#i', '', self::con()->opts->optGet( 'cs_enroll_id' ) );
		if ( empty( $enrollID ) ) {
			if ( empty( $auth[ 'machine_enrolled' ] ) ) {
				$auth[ 'enrolled_id' ] = '';
			}
		}
		elseif ( empty( $auth[ 'machine_enrolled' ] ) || $auth[ 'enrolled_id' ] !== $enrollID ) {

			$defaultTags = [ 'shield', 'wp', ];
			$defaultName = \preg_replace( '#^http(s)?://#i', '', $auth[ 'url' ] );
			if ( self::con()->isPremiumActive() ) {
				$tags = apply_filters( 'shield/crowdsec/enroll_tags', $defaultTags );
				$name = (string)apply_filters( 'shield/crowdsec/enroll_name', $defaultName );
				if ( empty( $name ) ) {
					$name = $defaultName;
				}
			}
			else {
				$tags = $defaultTags;
				$name = $defaultName;
			}

			try {
				$csCon->getCApiWatcher()->enroll( $name, true, $enrollID, \is_array( $tags ) ? $tags : [] );
				$auth[ 'machine_enrolled' ] = true;
				$auth[ 'enrolled_id' ] = $enrollID;

				self::con()->comps->events->fireEvent( 'crowdsec_mach_enroll', [
					'audit_params' => [
						'id'   => $enrollID,
						'name' => $name,
					]
				] );
			}
			catch ( ClientException $e ) {
				$auth[ 'machine_enrolled' ] = false;
				$auth[ 'enrolled_id' ] = '';
			}
		}

		$csCon->getCApiStore()->storeAuth( $auth );
	}
}