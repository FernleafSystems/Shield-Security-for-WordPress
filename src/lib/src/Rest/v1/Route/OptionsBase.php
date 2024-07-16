<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;

abstract class OptionsBase extends Base {

	private static $allOpts;

	public function getRoutePathPrefix() :string {
		return '/options';
	}

	protected function getRouteArgsDefaults() :array {
		$optFields = \array_unique( \array_merge(
			[
				'all', // special case
				'value',
				'module',
			],
			\array_keys( self::con()->opts->optDef( 'global_enable_plugin_features' ) )
		) );

		return [
			'filter_fields' => [
				'description' => '[Filter] Comma-separated fields to include in option info.',
				'type'        => 'array', // WordPress kindly converts CSV to array
				'pattern'     => sprintf( '^(((%s),?)+)?$', \implode( '|', $optFields ) ),
				'required'    => false,
			],
		];
	}

	protected function getRouteArgSchema( string $key ) :array {
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
				$sch = parent::getRouteArgSchema( $key );
				break;
		}
		return $sch;
	}

	protected function getAllPossibleOptKeys() :array {
		if ( !isset( self::$allOpts ) ) {
			$allOpts = \array_keys( ( new Export() )->getRawOptionsExport() );
			\natsort( $allOpts );
			self::$allOpts = \array_values( $allOpts );
		}
		return self::$allOpts;
	}
}