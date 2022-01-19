<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops;

trait Common {


	/**
	 * @param string $event
	 * @return $this
	 */
	public function filterByUser( int $userID ) {
		return $this->addWhereEquals( 'user_id', base64_encode( $file ) );
	}
}