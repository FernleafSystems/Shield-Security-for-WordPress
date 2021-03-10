<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

class Update extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Update {

	/**
	 * @param EntryVO $entry
	 * @param int     $increase
	 * @return bool
	 */
	public function updateCount( $entry, $increase = 1 ) :bool {
		return $this->updateEntry( $entry, [
			'count' => $entry->count + $increase,
		] );
	}
}