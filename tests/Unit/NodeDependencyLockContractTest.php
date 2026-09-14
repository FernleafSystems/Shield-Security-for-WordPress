<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class NodeDependencyLockContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testRootBuildDependencyLockFloors() :void {
		$this->assertLockSatisfiesPolicies(
			$this->decodePluginJsonFile( 'package-lock.json', 'Root package-lock.json' ),
			'Root package-lock.json',
			[
				'@babel/core' => '7.29.6',
				'js-yaml'     => '4.3.0',
				'qs'          => '6.15.2',
			]
		);
	}

	public function testPlaygroundDependencyLockFloors() :void {
		$this->assertLockSatisfiesPolicies(
			$this->decodePluginJsonFile( 'tools/playground/package-lock.json', 'Playground package-lock.json' ),
			'Playground package-lock.json',
			[
				'@wp-playground/cli' => '3.1.41',
				'qs'                 => '6.15.2',
				'tmp'                => '0.2.7',
			]
		);
	}

	public function testLockPackageDiscoveryFindsEveryExactResolution() :void {
		$packages = [
			'node_modules/qs'                              => 'hoisted qs',
			'node_modules/parent/node_modules/qs'          => 'nested qs',
			'node_modules/qs-extra'                        => 'qs decoy',
			'node_modules/@babel/core'                     => 'hoisted babel',
			'node_modules/parent/node_modules/@babel/core' => 'nested babel',
			'node_modules/@babel/core-extra'               => 'babel decoy',
		];

		$this->assertSame(
			[
				'node_modules/qs',
				'node_modules/parent/node_modules/qs',
			],
			\array_keys( $this->findPackageEntries( $packages, 'qs' ) )
		);
		$this->assertSame(
			[
				'node_modules/@babel/core',
				'node_modules/parent/node_modules/@babel/core',
			],
			\array_keys( $this->findPackageEntries( $packages, '@babel/core' ) )
		);
	}

	public function testLockPolicyViolationsReportEveryInvalidResolution() :void {
		$valid = [
			'version'   => '1.2.3',
			'resolved'  => 'https://registry.example.test/package.tgz',
			'integrity' => 'sha512-digest',
		];
		$fixtures = [
			[
				'packages' => [],
				'fragments' => [ 'no matching lock entry' ],
			],
			[
				'packages' => [
					'node_modules/pkg'                         => $valid,
					'node_modules/parent/node_modules/pkg'     => \array_merge( $valid, [ 'version' => '1.2.2' ] ),
				],
				'fragments' => [ 'node_modules/parent/node_modules/pkg', 'version "1.2.2" is below required >= 1.2.3' ],
			],
			[
				'packages' => [
					'node_modules/pkg'                     => 'not metadata',
					'node_modules/parent/node_modules/pkg' => null,
				],
				'fragments' => [ 'node_modules/pkg', 'node_modules/parent/node_modules/pkg', 'malformed metadata' ],
			],
			[
				'packages' => [
					'node_modules/pkg'                         => \array_merge( $valid, [ 'version' => '' ] ),
					'node_modules/parent/node_modules/pkg'     => \array_merge( $valid, [ 'version' => '1.2.1' ] ),
				],
				'fragments' => [ 'version "" is invalid', 'version "1.2.1" is below required >= 1.2.3' ],
			],
			[
				'packages' => [
					'node_modules/pkg'                                     => \array_merge( $valid, [ 'resolved' => '' ] ),
					'node_modules/parent/node_modules/pkg'                 => \array_merge( $valid, [ 'resolved' => 'http://registry.example.test/package.tgz' ] ),
					'node_modules/grandparent/node_modules/parent/node_modules/pkg' => \array_merge( $valid, [ 'resolved' => 'https://' ] ),
				],
				'fragments' => [ 'resolved "" is invalid', 'resolved "http://', 'resolved "https://" is invalid' ],
			],
			[
				'packages' => [
					'node_modules/pkg'                                     => [
						'version'  => $valid[ 'version' ],
						'resolved' => $valid[ 'resolved' ],
					],
					'node_modules/parent/node_modules/pkg'                 => \array_merge( $valid, [ 'integrity' => 'sha256-digest' ] ),
					'node_modules/grandparent/node_modules/parent/node_modules/pkg' => \array_merge( $valid, [ 'integrity' => 'sha512-' ] ),
				],
				'fragments' => [ 'integrity <missing> is invalid', 'integrity "sha256-digest" is invalid', 'integrity "sha512-" is invalid' ],
			],
		];

		foreach ( $fixtures as $fixture ) {
			$message = \implode(
				"\n",
				$this->collectLockPolicyViolations( 'Synthetic lock', $fixture[ 'packages' ], [ 'pkg' => '1.2.3' ] )
			);
			foreach ( $fixture[ 'fragments' ] as $fragment ) {
				$this->assertStringContainsString( $fragment, $message );
			}
		}
	}

	/**
	 * @param array<string, mixed>  $lock
	 * @param array<string, string> $policies
	 */
	private function assertLockSatisfiesPolicies( array $lock, string $lockLabel, array $policies ) :void {
		$this->assertArrayHasKey( 'packages', $lock, sprintf( '%s must contain a packages map.', $lockLabel ) );
		$this->assertIsArray( $lock[ 'packages' ], sprintf( '%s packages must be an array.', $lockLabel ) );

		$violations = $this->collectLockPolicyViolations( $lockLabel, $lock[ 'packages' ], $policies );
		$this->assertSame( [], $violations, \implode( "\n", $violations ) );
	}

	/**
	 * @param array<string, mixed> $packages
	 * @return array<string, mixed>
	 */
	private function findPackageEntries( array $packages, string $packageName ) :array {
		$packagePath = 'node_modules/'.$packageName;
		$nestedSuffix = '/'.$packagePath;
		$nestedSuffixLength = \strlen( $nestedSuffix );
		$matches = [];

		foreach ( $packages as $path => $metadata ) {
			if ( !\is_string( $path ) ) {
				continue;
			}
			if ( $path === $packagePath
				 || ( \strlen( $path ) >= $nestedSuffixLength
					  && \substr( $path, -$nestedSuffixLength ) === $nestedSuffix ) ) {
				$matches[ $path ] = $metadata;
			}
		}

		return $matches;
	}

	/**
	 * @param array<string, mixed>  $packages
	 * @param array<string, string> $policies
	 * @return string[]
	 */
	private function collectLockPolicyViolations( string $lockLabel, array $packages, array $policies ) :array {
		$violations = [];

		foreach ( $policies as $packageName => $minimumVersion ) {
			$entries = $this->findPackageEntries( $packages, $packageName );
			if ( empty( $entries ) ) {
				$violations[] = sprintf(
					'%s: package %s (minimum %s), no matching lock entry; expected node_modules/%s or nested /node_modules/%s.',
					$lockLabel,
					$packageName,
					$minimumVersion,
					$packageName,
					$packageName
				);
				continue;
			}

			foreach ( $entries as $path => $metadata ) {
				$prefix = sprintf(
					'%s: %s for package %s (minimum %s)',
					$lockLabel,
					$path,
					$packageName,
					$minimumVersion
				);
				if ( !\is_array( $metadata ) ) {
					$violations[] = sprintf(
						'%s has malformed metadata %s; required an object with version, absolute HTTPS resolved URL, and sha512- integrity.',
						$prefix,
						$this->describeValue( $metadata )
					);
					continue;
				}

				$version = $metadata[ 'version' ] ?? null;
				if ( !\is_string( $version ) || \trim( $version ) === '' ) {
					$violations[] = sprintf(
						'%s version %s is invalid; required non-empty string >= %s.',
						$prefix,
						$this->describeEntryValue( $metadata, 'version' ),
						$minimumVersion
					);
				}
				elseif ( !\version_compare( $version, $minimumVersion, '>=' ) ) {
					$violations[] = sprintf(
						'%s version %s is below required >= %s.',
						$prefix,
						$this->describeValue( $version ),
						$minimumVersion
					);
				}

				$resolved = $metadata[ 'resolved' ] ?? null;
				if ( !\is_string( $resolved )
					 || \filter_var( $resolved, FILTER_VALIDATE_URL ) === false
					 || \strtolower( (string)\parse_url( $resolved, PHP_URL_SCHEME ) ) !== 'https' ) {
					$violations[] = sprintf(
						'%s resolved %s is invalid; required an absolute HTTPS URL.',
						$prefix,
						$this->describeEntryValue( $metadata, 'resolved' )
					);
				}

				$integrity = $metadata[ 'integrity' ] ?? null;
				if ( !\is_string( $integrity )
					 || \substr( $integrity, 0, 7 ) !== 'sha512-'
					 || \trim( \substr( $integrity, 7 ) ) === '' ) {
					$violations[] = sprintf(
						'%s integrity %s is invalid; required sha512- with a non-empty digest.',
						$prefix,
						$this->describeEntryValue( $metadata, 'integrity' )
					);
				}
			}
		}

		return $violations;
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function describeEntryValue( array $metadata, string $key ) :string {
		return \array_key_exists( $key, $metadata ) ? $this->describeValue( $metadata[ $key ] ) : '<missing>';
	}

	/**
	 * @param mixed $value
	 */
	private function describeValue( $value ) :string {
		if ( \is_string( $value ) ) {
			return sprintf( '"%s"', $value );
		}
		if ( $value === null ) {
			return 'null';
		}
		if ( \is_scalar( $value ) ) {
			return \var_export( $value, true );
		}
		return '<'.\gettype( $value ).'>';
	}
}
