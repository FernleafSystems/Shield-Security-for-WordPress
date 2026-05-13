<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class GetRequestMeta {

	use PluginControllerConsumer;

	/**
	 * @return array{rid: string, is_valid: bool, values: array<string, string>, fields: array<int, array{key: string, value: string}>}
	 */
	public function retrieveContract( string $reqID ) :array {
		$reqID = sanitize_key( $reqID );
		$meta = $this->getRawMeta( $reqID );

		$values = [];
		if ( !empty( $meta ) ) {
			$meta[ 'rid' ] = $reqID;
			foreach ( $this->getContractKeys() as $metaKey ) {
				if ( \array_key_exists( $metaKey, $meta ) && $meta[ $metaKey ] !== null ) {
					$values[ $metaKey ] = $this->normaliseContractValue( $metaKey, $meta[ $metaKey ] );
				}
			}
		}

		return [
			'rid'      => $reqID,
			'is_valid' => !empty( $meta ),
			'values'   => $values,
			'fields'   => \array_map(
				static fn( string $key, string $value ) :array => [
					'key'   => $key,
					'value' => $value,
				],
				\array_keys( $values ),
				\array_values( $values )
			),
		];
	}

	public function retrieve( string $reqID ) :string {
		$reqID = sanitize_key( $reqID );
		$meta = $this->getRawMeta( $reqID );

		if ( empty( $meta ) ) {
			$content = __( 'Invalid Request ID', 'wp-simple-firewall' );
		}
		else {
			$metaDefs = [
				'rid'  => [
					'name'      => __( 'Request ID', 'wp-simple-firewall' ),
					'formatter' => function ( $metaDatum ) {
						return sprintf( '<code>%s</code>', esc_html( $metaDatum ) );
					},
				],
				'type' => [
					'name'      => __( 'Request Type', 'wp-simple-firewall' ),
					'formatter' => function ( $metaDatum ) {
						return esc_html( Ops\Handler::GetTypeName( $metaDatum ) );
					}
				],
				'uid'  => [
					'name' => __( 'User ID', 'wp-simple-firewall' ),
				],
				'ts'   => [
					'name' => __( 'Timestamp', 'wp-simple-firewall' ),
				],
				'verb' => [
					'name'      => __( 'Method', 'wp-simple-firewall' ),
					'formatter' => function ( $metaDatum ) {
						return \strtoupper( $metaDatum );
					}
				],
				'path' => [
					'name' => __( 'Path', 'wp-simple-firewall' ),
				],
				'code' => [
					'name' => __( 'Response Code', 'wp-simple-firewall' ),
				],
				'ua'   => [
					'name' => __( 'User Agent', 'wp-simple-firewall' ),
				],
			];

			$lines = [];
			$meta[ 'rid' ] = $reqID;
			foreach ( $metaDefs as $metaKey => $metaDef ) {
				if ( !empty( $meta[ $metaKey ] ) ) {
					$lines[] = sprintf(
						'<li><strong>%s</strong>: <span>%s</span></li>',
						esc_html( $metaDef[ 'name' ] ?? $metaKey ),
						isset( $metaDef[ 'formatter' ] ) ? $metaDef[ 'formatter' ]( $meta[ $metaKey ] ) : esc_html( $meta[ $metaKey ] )
					);
				}
			}

			$content = empty( $lines ) ? __( 'No meta available', 'wp-simple-firewall' ) : sprintf( '<ul>%s</ul>', \implode( '', $lines ) );
		}

		return $content;
	}

	/**
	 * @return string[]
	 */
	private function getContractKeys() :array {
		return [
			'rid',
			'type',
			'uid',
			'ts',
			'verb',
			'path',
			'code',
			'ua',
		];
	}

	private function normaliseContractValue( string $key, $value ) :string {
		return $key === 'verb' ? \strtoupper( (string)$value ) : (string)$value;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function getRawMeta( string $RID ) :array {
		$meta = [];
		if ( !empty( $RID ) ) {
			/** @var Ops\Select $selector */
			$selector = self::con()->db_con->req_logs->getQuerySelector();
			$record = $selector->filterByReqID( $RID )->first();
			if ( !empty( $record ) ) {
				$meta = \array_merge( $record->meta, $record->getRawData() );
			}
		}
		return $meta;
	}
}
