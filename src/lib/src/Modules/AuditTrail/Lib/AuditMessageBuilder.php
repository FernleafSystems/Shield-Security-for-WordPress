<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail\EntryVO;

class AuditMessageBuilder {

	public static function Build( EntryVO $entry, array $msgStructure ) :string {

		$substitutions = $entry->meta;
		$rawString = implode( "\n", $msgStructure );

		// In-case we're working with an older audit message without as much data substitutions
		$missingCount = substr_count( $rawString, '%s' ) - count( $substitutions );

		if ( $missingCount > 0 ) {
			$substitutions = array_merge(
				$substitutions,
				array_fill( 0, $missingCount, 'data missing for older audit logs' )
			);
		}
		return stripslashes( sanitize_textarea_field( vsprintf( $rawString, $substitutions ) ) );
	}
}