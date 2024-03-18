<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData {

	use PluginControllerConsumer;

	public function build() :array {
		return [
			'options' => [
				'file_type' => $this->buildForFileTypes(),
				'status'    => $this->buildForFileStatus(),
			]
		];
	}

	private function buildForFileTypes() :array {
		$exts = [];
		foreach ( $this->runQueryForFileTypes() as $item ) {
			$item = $item[ 'item_id' ] ?? '';
			if ( !empty( $item ) && \strpos( $item, '.' ) > 0 ) {
				$ext = \explode( '.', $item )[ 1 ];
				if ( empty( $exts[ $ext ] ) ) {
					$exts[ $ext ] = [
						'label' => \strtoupper( $ext ),
						'value' => $ext,
					];
				}
			}
		}
		return \array_values( $exts );
	}

	private function buildForFileStatus() :array {
		return [
			[
				'label' => __( 'Malware', 'wp-simple-firewall' ),
				'value' => 'is_mal',
			],
			[
				'label' => __( 'Unrecognised', 'wp-simple-firewall' ),
				'value' => 'is_unrecognised',
			],
			[
				'label' => __( 'Modified From Original', 'wp-simple-firewall' ),
				'value' => 'is_checksumfail',
			],
			[
				'label' => __( 'Missing', 'wp-simple-firewall' ),
				'value' => 'is_missing',
			],
		];
	}

	private function runQueryForFileTypes() :array {
		$results = Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `ri`.`item_id`
						FROM `%s` as `ri`
						WHERE `ri`.`item_type`='f'
							AND `ri`.`ignored_at`=0
							AND `ri`.`auto_filtered_at`!=0
							AND `ri`.`item_repaired_at`=0
							AND `ri`.`item_deleted_at`=0
							AND `ri`.`deleted_at`=0
				",
				self::con()->db_con->scan_result_items->getTable()
			)
		);
		return \is_array( $results ) ? $results : [];
	}
}