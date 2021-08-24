<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getEventName( string $eventKey ) :string {
		$con = $this->getCon();
		$name = $con->getModule( $con->loadEventsService()->getEventDef( $eventKey )[ 'context' ] )
					->getStrings()
					->getEventStrings()[ $eventKey ][ 'name' ] ?? '';
		return empty( $name ) ? ( $this->getEventNames()[ $eventKey ] ?? '' ) : $name;
	}

	/**
	 * @param bool $auto
	 * @return string[]
	 */
	public function getEventNames( bool $auto = true ) :array {
		$names = [
			//			'firewall_block'               => __( 'Firewall Block', 'wp-simple-firewall' ),
			//			'block_param'                  => __( 'Firewall Blocked Request Parameter', 'wp-simple-firewall' ),
			//			'blockparam_dirtraversal'      => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'Directory Traversal', 'wp-simple-firewall' )
			//			),
			//			'blockparam_wpterms'           => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'WordPress Terms', 'wp-simple-firewall' )
			//			),
			//			'blockparam_fieldtruncation'   => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'Field Truncation', 'wp-simple-firewall' )
			//			),
			//			'blockparam_sqlqueries'        => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'SQL Queries', 'wp-simple-firewall' )
			//			),
			//			'blockparam_schema'            => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'Leading Schema', 'wp-simple-firewall' )
			//			),
			//			'blockparam_aggressive'        => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'Aggressive Rules', 'wp-simple-firewall' )
			//			),
			//			'blockparam_phpcode'           => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'PHP Code', 'wp-simple-firewall' )
			//			),
			//			'block_exefile'                => sprintf( '%s: %s',
			//				__( 'Firewall', 'wp-simple-firewall' ),
			//				__( 'EXE File Uploads', 'wp-simple-firewall' )
			//			),
		];

		if ( $auto ) {
			foreach ( $names as $key => $name ) {
				if ( empty( $name ) ) {
					$names[ $key ] = ucwords( str_replace( '_', ' ', $key ) );
				}
			}
		}

		return $names;
	}
}