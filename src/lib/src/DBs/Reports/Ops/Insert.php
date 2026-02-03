<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops;

class Insert extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Insert {

	/**
	 * @inheritDoc
	 */
	public function setInsertData( array $data ) {
		$data[ 'title' ] = $data[ 'title' ] ? \trim( \preg_replace( '#[^\w\s,._:-]#i', '', $data[ 'title' ] ) ) : __( 'No title provided', 'wp-simple-firewall' );
		return parent::setInsertData( $data );
	}
}
