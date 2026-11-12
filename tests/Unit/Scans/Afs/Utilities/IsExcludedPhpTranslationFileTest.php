<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\IsExcludedPhpTranslationFile;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class IsExcludedPhpTranslationFileTest extends BaseUnitTest {

	public function testMatchesWordpressLanguagePhpFilenames() :void {
		$subject = new IsExcludedPhpTranslationFile();

		foreach ( [
			'w3-total-cache-en_GB.l10n.php',
			'wp-simple-firewall-tr_TR.l10n.php',
			'admin-network-bel.l10n.php',
			'bel.l10n.php',
			'admin-en_GB.l10n.php',
			'continents-cities-fr_FR.l10n.php',
		] as $filename ) {
			$this->assertTrue(
				$this->invokeProtected( $subject, 'isLanguagePhpTranslationFilename', [ $filename ] ),
				sprintf( 'Expected filename to match: %s', $filename )
			);
		}
	}

	public function testRejectsInvalidLanguagePhpFilenames() :void {
		$subject = new IsExcludedPhpTranslationFile();

		foreach ( [
			'evil.php',
			'en_GB.mo',
			'foo-english.l10n.php',
			'bad name-en_GB.l10n.php',
			'abc-zh_Hans.l10n.php',
		] as $filename ) {
			$this->assertFalse(
				$this->invokeProtected( $subject, 'isLanguagePhpTranslationFilename', [ $filename ] ),
				sprintf( 'Expected filename to be rejected: %s', $filename )
			);
		}
	}

	public function testAcceptsValidPayloadWithShortArraySyntax() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['A'=>'B']];";

		$this->assertTrue(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testAcceptsValidPayloadWithLegacyArraySyntax() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return array('messages'=>array('A'=>'B'));";

		$this->assertTrue(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testAcceptsHeaderlessValidPayload() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return ['messages'=>['A'=>'B']];";

		$this->assertTrue(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testAcceptsPluralFormsJoinedWithNulSeparator() :void {
		$subject = new IsExcludedPhpTranslationFile();

		foreach ( [
			"<?php\nreturn ['messages'=>['%d day'=>'%d day' . \"\\0\" . '%d days','Other'=>'Autre']];\n",
			"<?php\nreturn ['messages'=>['%d day'=>'%d dan' . \"\\0\" . '%d dana' . \"\\0\" . '%d dana']];\n",
		] as $content ) {
			$this->assertTrue(
				$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] ),
				sprintf( 'Expected payload to be accepted: %s', $content )
			);
		}
	}

	public function testRejectsConcatenationOfAnythingOtherThanStringLiterals() :void {
		$subject = new IsExcludedPhpTranslationFile();

		foreach ( [
			"<?php return ['messages'=>['A'=>'B' . system('id')]];",
			"<?php return ['messages'=>['A'=>system('id') . 'B']];",
			"<?php return ['messages'=>['A'=>'B' . \$_GET['c']]];",
			"<?php return ['messages'=>['A'=>'B' . \"\$c\"]];",
			"<?php return ['messages'=>['A'=>'B' . PHP_VERSION]];",
			"<?php return ['messages'=>['A'=>'B' . 1]];",
			"<?php return ['messages'=>['A'=>'B' . true]];",
			"<?php return ['messages'=>['A'=>'B' . ['C']]];",
			"<?php return ['messages'=>['A'=>1 . 'B']];",
			"<?php return 'A' . 'B';",
		] as $content ) {
			$this->assertFalse(
				$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] ),
				sprintf( 'Expected payload to be rejected: %s', $content )
			);
		}
	}

	public function testRejectsExecutablePayload() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['A'=>'B']]; system('id');";

		$this->assertFalse(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testRejectsFunctionCallsInsideReturnedArrayPayload() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return ['messages'=>['A'=>system('id')]];";

		$this->assertFalse(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testRejectsPayloadMissingMessagesKey() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01'];";

		$this->assertFalse(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testAcceptsPayloadWithNumericKeysWhenNonExecutable() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "<?php return array('messages'=>array('A'=>array(0=>'B',1=>'C')));";

		$this->assertTrue(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	public function testAcceptsUtf8BomPrefixedPayload() :void {
		$subject = new IsExcludedPhpTranslationFile();
		$content = "\xEF\xBB\xBF<?php return ['messages'=>['A'=>'B']];";

		$this->assertTrue(
			$this->invokeProtected( $subject, 'isSafeTranslationPhpPayload', [ $content ] )
		);
	}

	/**
	 * @return mixed
	 */
	private function invokeProtected( object $subject, string $method, array $args = [] ) {
		$reflection = new \ReflectionClass( $subject );
		$target = $reflection->getMethod( $method );
		$target->setAccessible( true );
		return $target->invokeArgs( $subject, $args );
	}
}
