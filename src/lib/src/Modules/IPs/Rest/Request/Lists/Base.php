<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;

abstract class Base extends Process {

	protected function getIpData( string $ip, string $list ) :array {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon $mod */
		$mod = $this->getMod();

		$retriever = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $ip );
		if ( $list === 'block' ) {
			$retriever->setListTypeBlock();
		}
		else {
			$retriever->setListTypeBypass();
		}

		$IP = $retriever->lookup( true );

		if ( empty( $IP ) ) {
			throw new \Exception( 'IP address not found on list' );
		}

		return array_intersect_key(
			$IP->getRawData(),
			array_flip( [
				'ip',
				'label',
				'list',
				'transgressions',
				'blocked_at',
			] )
		);
	}
}