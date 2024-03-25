<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops;

class Insert extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Insert {

	/**
	 * @inheritDoc
	 */
	public function setInsertData( array $data ) {
		$data[ 'title' ] = $data[ 'title' ] ? \trim( \preg_replace( '#[^\w\s,._:-]#i', '', $data[ 'title' ] ) ) : 'No Title Provided';
		return parent::setInsertData( $data );
	}
}