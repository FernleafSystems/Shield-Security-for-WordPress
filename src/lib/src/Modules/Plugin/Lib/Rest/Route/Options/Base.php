<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class Base extends RouteBase {

	private static $allOpts;

	public function getRoutePathPrefix() :string {
		return '/options';
	}

	protected function getRouteArgsDefaults() :array {
		$optFields = array_unique( array_merge(
			[
				'all', // special case
				'value',
				'module',
			],
			array_keys( $this->getMod()->getOptions()->getOptDefinition( 'global_enable_plugin_features' ) )
		) );

		return [
			'filter_fields' => [
				'description' => '[Filter] Comma-separated fields to include in option info.',
				'type'        => 'array', // WordPress kindly converts CSV to array
				'pattern'     => sprintf( '^(((%s),?)+)?$', implode( '|', $optFields ) ),
				'required'    => false,
			],
		];
	}

	protected function getPropertySchema( string $key ) :array {
		switch ( $key ) {
			case 'key':
				$sch = [
					'description' => 'Option key',
					'type'        => 'string',
					'enum'        => $this->getAllPossibleOptKeys(),
					'required'    => true,
					'readonly'    => true,
				];
				break;

			case 'value':
				$sch = [
					'description' => 'Option value',
					'required'    => true,
					'type'        => [
						'object',
						'string',
						'number',
						'null'
					],
				];
				break;

			default:
				$sch = [];
				break;
		}
		return $sch;
	}

	protected function getAllPossibleOptKeys() :array {
		if ( !isset( self::$allOpts ) ) {
			$allOpts = [];
			foreach ( ( new Export() )->setMod( $this->getMod() )->getRawOptionsExport() as $modOpts ) {
				$allOpts = array_merge( $allOpts, array_keys( $modOpts ) );
			}
			natsort( $allOpts );
			self::$allOpts = array_values( $allOpts );
		}
		return self::$allOpts;
	}
}