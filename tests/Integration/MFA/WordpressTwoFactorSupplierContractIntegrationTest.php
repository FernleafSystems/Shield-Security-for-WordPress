<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class WordpressTwoFactorSupplierContractIntegrationTest extends ShieldIntegrationTestCase {

	public function test_expected_supplier_contract_markers_are_present() :void {
		$repoPath = $this->resolveSupplierRepoPath();
		if ( $repoPath === '' ) {
			$this->markTestSkipped( 'WordPress two-factor supplier repo path is unavailable.' );
		}

		$core = $this->readSupplierFile( $repoPath.'/class-two-factor-core.php' );
		$bootstrap = $this->readSupplierFile( $repoPath.'/two-factor.php' );
		$totp = $this->readSupplierFile( $repoPath.'/providers/class-two-factor-totp.php' );
		$backup = $this->readSupplierFile( $repoPath.'/providers/class-two-factor-backup-codes.php' );
		$email = $this->readSupplierFile( $repoPath.'/providers/class-two-factor-email.php' );

		$this->assertStringContainsString( "const ENABLED_PROVIDERS_USER_META_KEY = '_two_factor_enabled_providers';", $core );
		$this->assertStringContainsString( 'Two_Factor_Email', $core );
		$this->assertStringContainsString( 'Two_Factor_Totp', $core );
		$this->assertStringContainsString( 'Two_Factor_Backup_Codes', $core );

		$this->assertStringContainsString( 'two_factor_enabled_providers', $bootstrap );

		$this->assertStringContainsString( "const SECRET_META_KEY = '_two_factor_totp_key';", $totp );
		$this->assertStringContainsString( 'base32_encode(', $totp );

		$this->assertStringContainsString( "const BACKUP_CODES_META_KEY = '_two_factor_backup_codes';", $backup );
		$this->assertStringContainsString( 'wp_hash_password(', $backup );

		$this->assertStringContainsString( 'Two_Factor_Email', $email );
	}

	private function resolveSupplierRepoPath() :string {
		$envPath = (string)\getenv( 'SHIELD_WP2FA_REPO_PATH' );
		if ( $envPath !== '' && \is_dir( $envPath ) ) {
			return $envPath;
		}

		$repoRoot = \dirname( \dirname( \dirname( __DIR__ ) ) );
		$fallback = \dirname( \dirname( $repoRoot ) ).'/ThirdParties/WP_2FA_Plugins/WP2FA';
		return \is_dir( $fallback ) ? $fallback : '';
	}

	private function readSupplierFile( string $path ) :string {
		$this->assertFileExists( $path, 'Supplier contract file should exist: '.$path );
		return (string)\file_get_contents( $path );
	}
}
