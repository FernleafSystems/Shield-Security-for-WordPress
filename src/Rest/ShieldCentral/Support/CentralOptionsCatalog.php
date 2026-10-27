<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Profiles\ProfileOptionsCatalog;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops\Read;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * The Shield options ShieldCentral reads and writes: the profile options, less the credentials, which Central never
 * sees, and less the options Shield's extension plugins add.
 */
class CentralOptionsCatalog {

	use PluginControllerConsumer;

	private const CREDENTIAL_KEYS = [
		'admin_access_key',
		'yubikey_app_id',
		'yubikey_api_key',
	];

	private ?array $catalogue = null;

	/**
	 * @return string[]
	 */
	public function keys() :array {
		return \array_column( $this->catalogue(), 'key' );
	}

	/**
	 * Built from plugin.json rather than the runtime configuration, which drops the labels: the catalogue must be the
	 * same for every site on the same version, whatever its licence or language.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function catalogue() :array {
		if ( $this->catalogue === null ) {
			$spec = Read::FromFile( self::con()->paths->forPluginItem( 'plugin.json' ) )[ 'config_spec' ];

			$moduleOrder = \array_flip( \array_keys( $spec[ 'modules' ] ) );
			$sections = [];
			foreach ( $spec[ 'sections' ] as $order => $section ) {
				$sections[ $section[ 'slug' ] ] = $section + [ 'order' => $order ];
			}

			$profileable = ( new ProfileOptionsCatalog() )->profileableOptions();
			$this->catalogue = [];
			// Only Shield's own options, from plugin.json. The profile options also hold those extension plugins add
			// (ExtensionsCon), which depend on the extensions a site has and their versions, so are left out.
			foreach ( $spec[ 'options' ] as $option ) {
				if ( isset( $profileable[ $option[ 'key' ] ] ) && !\in_array( $option[ 'key' ], self::CREDENTIAL_KEYS, true ) ) {
					$section = $sections[ $option[ 'section' ] ];
					$this->catalogue[] = [
						'key'           => $option[ 'key' ],
						'type'          => $option[ 'type' ],
						'default'       => $option[ 'default' ],
						'value_options' => $option[ 'value_options' ] ?? [],
						'min'           => $option[ 'min' ] ?? null,
						'max'           => $option[ 'max' ] ?? null,
						'module'        => [
							'slug'  => $section[ 'module' ],
							'name'  => $spec[ 'modules' ][ $section[ 'module' ] ][ 'name' ],
							'order' => $moduleOrder[ $section[ 'module' ] ],
						],
						'section'       => [
							'slug'  => $section[ 'slug' ],
							'title' => $section[ 'title' ],
							'order' => $section[ 'order' ],
						],
						'name'          => $option[ 'name' ],
						'summary'       => $option[ 'summary' ],
						'link_info'     => $option[ 'link_info' ] ?? '',
					];
				}
			}
		}
		return $this->catalogue;
	}

	/**
	 * Differs for every release, even one whose options are unchanged, so a write prepared for one version is
	 * never applied to another.
	 */
	public function hash() :string {
		return \hash( 'sha256', (string)\wp_json_encode( [ self::con()->cfg->version(), $this->catalogue() ] ) );
	}
}
