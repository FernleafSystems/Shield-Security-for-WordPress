<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class WordfenceLoginSecuritySupplierContractIntegrationTest extends ShieldIntegrationTestCase {

	public function test_expected_supplier_contract_markers_are_present() :void {
		$repoPath = $this->resolveSupplierRepoPath();
		if ( $repoPath === '' ) {
			$this->markTestSkipped( 'Wordfence supplier repo path is unavailable.' );
		}

		$db = $this->readSupplierFile( $repoPath.'/modules/login-security/classes/controller/db.php' );
		$totp = $this->readSupplierFile( $repoPath.'/modules/login-security/classes/controller/totp.php' );

		$this->assertStringContainsString( "const TABLE_2FA_SECRETS = 'wfls_2fa_secrets';", $db );
		$this->assertStringContainsString( "`secret` tinyblob NOT NULL", $db );
		$this->assertStringContainsString( "`recovery` blob NOT NULL", $db );
		$this->assertStringContainsString( "`mode` enum(\\'authenticator\\') NOT NULL DEFAULT \\'authenticator\\'", $db );

		$this->assertStringContainsString( "\$secret = bin2hex(\$record['secret']);", $totp );
		$this->assertStringContainsString( "\$recoveryCodes = str_split(strtolower(bin2hex(\$record['recovery'])), 16);", $totp );
	}

	private function resolveSupplierRepoPath() :string {
		$envPath = (string)\getenv( 'SHIELD_WORDFENCE_REPO_PATH' );
		if ( $envPath !== '' && \is_dir( $envPath ) ) {
			return $envPath;
		}

		$repoRoot = \dirname( \dirname( \dirname( __DIR__ ) ) );
		$fallback = \dirname( \dirname( $repoRoot ) ).'/wp.org/WP_Plugin-Wordfence';
		return \is_dir( $fallback ) ? $fallback : '';
	}

	private function readSupplierFile( string $path ) :string {
		$this->assertFileExists( $path, 'Supplier contract file should exist: '.$path );
		return (string)\file_get_contents( $path );
	}
}
