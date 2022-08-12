<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	public function incrementTransgressions( Record $IP, int $increment = 1 ) :bool {
		return $this->updateTransgressions( $IP, $IP->offenses + $increment );
	}

	/**
	 * @param Record $record
	 * @param array  $updateData
	 * @return bool
	 */
	public function updateRecord( $record, $updateData = [] ) :bool {
		$success = false;

		if ( $record instanceof Record ) {

			$sch = $this->getDbH()->getTableSchema();
			$skipCols = [];
			foreach ( $updateData as $col => $value ) {

				$skipUpdate = ( $record->{$col} === $value )
							  || ( is_numeric( $value ) && stripos( $sch->getColumnType( $col ), 'int' ) !== false && $record->{$col} == $value );
				if ( $skipUpdate ) {
					$skipCols[] = $col;
				}
			}

			$updateData = array_diff_key( $updateData, array_flip( $skipCols ) );

			if ( empty( $updateData ) ) {
				$success = true;
			}
			elseif ( $this->updateById( $record->id, $updateData ) ) {
				$record->applyFromArray( array_merge( $record->getRawData(), $updateData ) );
				$success = true;
			}
		}

		return $success;
	}

	public function updateTransgressions( Record $IP, int $offenses ) :bool {
		return $this->updateRecord( $IP, [
			'offenses'       => max( 0, $offenses ),
			'last_access_at' => Services::Request()->ts()
		] );
	}

	public function updateLabel( Record $IP, string $label ) :bool {
		return $this->updateRecord( $IP, [ 'label' => trim( $label ) ] );
	}

	/**
	 * @param Record|IpRuleRecord $record
	 */
	public function updateLastAccessAt( $record ) :bool {
		return $this->updateById( $record->id, [ 'last_access_at' => Services::Request()->ts() ] );
	}

	public function setBlocked( Record $IP ) :bool {
		return $this->updateRecord( $IP, [
			'blocked_at'     => Services::Request()->ts(),
			'last_access_at' => Services::Request()->ts()
		] );
	}
}