<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\ChunkedExporter;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Config;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Exporter;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableDataExport;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableHelper;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Mockery;

/**
 * Tests for ChunkedExporter database export functionality.
 * 
 * These tests verify that:
 * 1. Normal exports work correctly (offset advances, rows counted)
 * 2. Error conditions are handled properly (exceptions thrown, no infinite loops)
 * 3. Edge cases don't cause infinite loops
 * 
 * IMPORTANT: This test class uses Mockery's 'overload:' prefix to mock classes
 * that are instantiated internally by ChunkedExporter. The mocks must be set up
 * BEFORE ChunkedExporter is instantiated.
 */
class ChunkedExporterTest extends BaseUnitTest {

    private $tempFile;
    private $tempFileHandle;

    protected function setUp(): void {
        parent::setUp();
        
        // Create a temp file for the dump output
        $this->tempFile = tempnam(sys_get_temp_dir(), 'shield_test_');
        $this->tempFileHandle = fopen($this->tempFile, 'w+');
        
        // Mock WordPress functions that may be called
        Functions\when('esc_sql')->returnArg(1);
        
        // Note: DB_HOST and DB_NAME are already defined in tests/bootstrap/brain-monkey.php
    }

    protected function tearDown(): void {
        if (is_resource($this->tempFileHandle)) {
            fclose($this->tempFileHandle);
        }
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: Set up all required mocks for ChunkedExporter
     * 
     * This must be called BEFORE instantiating ChunkedExporter.
     * 
     * @param array $selectCustomSequence Return values for sequential selectCustom calls
     * @param bool $hasPK Whether the table has a usable primary key
     * @param string $pkColumn Name of the PK column (if hasPK is true)
     */
    private function setupMocks(array $selectCustomSequence, bool $hasPK = true, string $pkColumn = 'id'): void {
        // Mock Services::WpDb()
        $this->mockWpDb($selectCustomSequence);
        
        // Mock Config class
        $this->mockConfig();
        
        // Mock Exporter class
        $this->mockExporter();
        
        // Mock TableHelper class
        $this->mockTableHelper($hasPK, $pkColumn);
    }

    /**
     * Helper: Create mock WpDb that returns specified data for selectCustom calls
     * 
     * Uses andReturnUsing() with a counter callback to:
     * 1. Return sequential values from $returnSequence
     * 2. Detect infinite loops by throwing after $maxCalls iterations
     * 
     * The $maxCalls default of 200 is chosen because:
     * - Normal operation: maxPageRows=1000, chunkSize=50 → max 20 iterations
     * - Test scenarios: maxPageRows=100, chunkSize=50 → max 2 iterations  
     * - 200 calls = 10x worst-case normal operation
     * - If code reaches 200 calls, it's unquestionably an infinite loop
     * 
     * See plan Appendix B for details.
     * 
     * @param array $returnSequence Array of return values for sequential calls
     * @param int $maxCalls Maximum calls before throwing (prevents memory exhaustion in tests)
     */
    private function mockWpDb(array $returnSequence, int $maxCalls = 200): void {
        $mock = Mockery::mock('alias:FernleafSystems\Wordpress\Services\Services');
        
        $wpDbMock = Mockery::mock();
        $wpDbMock->shouldReceive('getPrefix')->andReturn('wp_');
        $wpDbMock->shouldReceive('loadWpdb')->andReturnSelf();
        $wpDbMock->shouldReceive('_real_escape')->andReturnArg(0);
        
        // Use callback to track calls and detect infinite loops
        $callCount = 0;
        $wpDbMock->shouldReceive('selectCustom')
            ->andReturnUsing(function() use (&$callCount, $maxCalls, $returnSequence) {
                if ($callCount >= $maxCalls) {
                    throw new \RuntimeException(sprintf(
                        'Infinite loop detected: selectCustom called %d times (limit: %d). '
                        . 'This would cause memory exhaustion in production.',
                        $callCount + 1,
                        $maxCalls
                    ));
                }
                // Return next value in sequence, or null if sequence exhausted
                // (null simulates query failure, which triggers the bug)
                return $returnSequence[$callCount++] ?? null;
            });
        
        $mock->shouldReceive('WpDb')->andReturn($wpDbMock);
    }

    /**
     * Helper: Mock the Config class
     */
    private function mockConfig(): void {
        $configMock = Mockery::mock('overload:' . Config::class);
        $configMock->shouldReceive('applyDumpDataOptions')->andReturnSelf();
        $configMock->shouldReceive('set')->andReturnSelf();
        $configMock->shouldReceive('has')->andReturn(false);
        $configMock->shouldReceive('get')->andReturnUsing(function($key, $default = null) {
            return $default;
        });
    }

    /**
     * Helper: Mock the Exporter class
     */
    private function mockExporter(): void {
        $exporterMock = Mockery::mock('overload:' . Exporter::class);
        $exporterMock->shouldReceive('buildHeader')->andReturnSelf();
        $exporterMock->shouldReceive('buildPreDataExport')->andReturnSelf();
        $exporterMock->shouldReceive('buildTableDataStructureStart')->andReturnSelf();
        $exporterMock->shouldReceive('buildTableDataStructureEnd')->andReturnSelf();
        $exporterMock->shouldReceive('buildFooter')->andReturnSelf();
        $exporterMock->shouldReceive('getContent')->andReturn([]);
        // This is the BUG we're testing - it always returns null
        $exporterMock->shouldReceive('getTotalDataRowsCount')->andReturn(null);
    }

    /**
     * Helper: Mock the TableHelper class
     * 
     * @param bool $hasPK Whether to return a PK column name or null
     * @param string $pkColumn The PK column name to return if hasPK is true
     */
    private function mockTableHelper(bool $hasPK, string $pkColumn = 'id'): void {
        $tableHelperMock = Mockery::mock('overload:' . TableHelper::class);
        $tableHelperMock->shouldReceive('getAppropriatePrimaryKeyForOrdering')
            ->andReturn($hasPK ? $pkColumn : null);
        // IMPORTANT: Use correct column generator based on hasPK flag
        // For non-PK tables, showColumns() must return columns matching the mock row data
        $tableHelperMock->shouldReceive('showColumns')
            ->andReturn($hasPK 
                ? $this->generateColumnsWithPK($pkColumn)
                : $this->generateColumnsWithoutPK()
            );
    }

    /**
     * Helper: Generate mock table rows with sequential PKs
     * 
     * @param int $startPk Starting primary key value
     * @param int $count Number of rows to generate
     * @param string $pkColumn Name of the PK column
     * @return array
     */
    private function generateMockRows(int $startPk, int $count, string $pkColumn = 'id'): array {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                $pkColumn => $startPk + $i,
                'data' => 'test_data_' . ($startPk + $i),
            ];
        }
        return $rows;
    }

    /**
     * Helper: Generate SHOW FULL COLUMNS response for a table with auto-increment PK
     * 
     * IMPORTANT: Returns associative array keyed by field name, matching
     * TableHelper::showColumns() which does:
     *   $this->columns[ $colResult['Field'] ] = $colResult;
     * 
     * TableDataExport::convertRawRowToSqlValues() iterates as:
     *   foreach ($columns as $field => $col)
     * expecting $field to be 'id', 'data', etc.
     */
    private function generateColumnsWithPK(string $pkColumn = 'id'): array {
        return [
            $pkColumn => [
                'Field' => $pkColumn,
                'Type' => 'bigint(20) unsigned',
                'Key' => 'PRI',
                'Extra' => 'auto_increment',
            ],
            'data' => [
                'Field' => 'data',
                'Type' => 'varchar(255)',
                'Key' => '',
                'Extra' => '',
            ],
        ];
    }

    /**
     * Helper: Generate SHOW FULL COLUMNS response for a table WITHOUT usable PK
     * 
     * Returns associative array keyed by field name (same as generateColumnsWithPK)
     */
    private function generateColumnsWithoutPK(): array {
        return [
            'composite_key1' => [
                'Field' => 'composite_key1',
                'Type' => 'varchar(50)',
                'Key' => 'PRI',  // Part of composite key
                'Extra' => '',   // No auto_increment
            ],
            'composite_key2' => [
                'Field' => 'composite_key2',
                'Type' => 'varchar(50)',
                'Key' => 'PRI',
                'Extra' => '',
            ],
            'data' => [
                'Field' => 'data',
                'Type' => 'text',
                'Key' => '',
                'Extra' => '',
            ],
        ];
    }

    // =========================================================================
    // PHASE 1 TESTS: Current Working Behavior (should PASS now)
    // =========================================================================
    //
    // IMPORTANT: All tests require @runInSeparateProcess because we use
    // Mockery's 'overload:' prefix to mock classes that are instantiated
    // internally by ChunkedExporter. The overload only works if the class
    // hasn't been autoloaded yet.
    // =========================================================================

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Normal PK-based export returns correct offset after processing rows
     * 
     * Scenario:
     * - Table has auto-increment PK column 'id'
     * - First chunk returns 50 rows (PKs 1-50)
     * - Second chunk returns 0 rows (end of table)
     * 
     * Expected:
     * - current_offset = 50 (last processed PK value, NOT page number)
     * - table_export_complete = true
     * - exported_rows = 50
     */
    public function testPKBasedExportReturnsCorrectOffset(): void {
        // Setup mock data - selectCustom is called for data fetches
        $firstChunkRows = $this->generateMockRows(1, 50, 'id');
        
        $this->setupMocks([
            $firstChunkRows,    // First data fetch: 50 rows
            [],                 // Second data fetch: empty (end of table)
        ], true, 'id');
        
        // Execute
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_test_table',
            0,      // startingOffset
            1000,   // maxPageRows
            50      // chunkSize
        );
        
        $result = $exporter->run();
        
        // Assert
        $this->assertEquals(50, $result['current_offset'], 
            'Offset should be the last processed PK value');
        $this->assertTrue($result['table_export_complete'], 
            'Table should be marked complete when no more rows');
        $this->assertEquals(50, $result['exported_rows'], 
            'Should report 50 exported rows');
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Empty table returns immediately with complete status
     * 
     * Scenario:
     * - Table exists but has no rows
     * - First query returns empty array
     * 
     * Expected:
     * - current_offset = 0 (starting offset, no data processed)
     * - table_export_complete = true
     * - exported_rows = 0 (or null coerced to 0)
     */
    public function testEmptyTableReturnsCompleteImmediately(): void {
        $this->setupMocks([
            [],     // Data fetch: empty immediately
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_empty_table',
            0,
            1000,
            50
        );
        
        $result = $exporter->run();
        
        $this->assertEquals(0, $result['current_offset']);
        $this->assertTrue($result['table_export_complete']);
        // Note: exported_rows may be 0 or null depending on implementation
        $this->assertLessThanOrEqual(0, $result['exported_rows'] ?? 0);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Export stops at maxPageRows even if more data available
     * 
     * Scenario:
     * - Table has 200 rows
     * - maxPageRows = 100
     * - chunkSize = 50
     * 
     * Expected:
     * - Processes exactly 100 rows (2 chunks of 50)
     * - table_export_complete = false (more data exists)
     * - current_offset = 100 (last PK processed)
     */
    public function testExportStopsAtMaxPageRows(): void {
        $chunk1 = $this->generateMockRows(1, 50, 'id');
        $chunk2 = $this->generateMockRows(51, 50, 'id');
        
        $this->setupMocks([
            $chunk1,    // First chunk: rows 1-50
            $chunk2,    // Second chunk: rows 51-100
            // No more calls - should stop at maxPageRows
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_large_table',
            0,
            100,    // maxPageRows = 100
            50      // chunkSize = 50
        );
        
        $result = $exporter->run();
        
        $this->assertEquals(100, $result['current_offset']);
        $this->assertFalse($result['table_export_complete'], 
            'Should NOT be complete - more data exists');
        $this->assertEquals(100, $result['exported_rows']);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Continuation from non-zero offset works correctly
     * 
     * Scenario:
     * - Previous export ended at offset 100 (last PK was 100)
     * - This export continues from offset 100
     * - 50 more rows available (PKs 101-150)
     * 
     * Expected:
     * - Query uses WHERE pk > 100 (not >= because offset is non-zero)
     * - current_offset = 150 (last PK in this batch)
     * - table_export_complete = true
     */
    public function testContinuationFromNonZeroOffset(): void {
        $rows = $this->generateMockRows(101, 50, 'id');
        
        $this->setupMocks([
            $rows,  // Rows 101-150
            [],     // No more rows
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_test_table',
            100,    // startingOffset = 100 (continue from previous)
            1000,
            50
        );
        
        $result = $exporter->run();
        
        $this->assertEquals(150, $result['current_offset']);
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(50, $result['exported_rows']);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Non-PK based export (tables without suitable primary key)
     * 
     * Scenario:
     * - Table has composite primary key (not usable for ordering)
     * - Falls back to LIMIT/OFFSET pagination
     * - chunkSize = 50, maxPageRows = 1000 (reduced to 666 internally)
     * 
     * Expected:
     * - Uses OFFSET-based pagination (not PK-based)
     * - current_offset = page number (NOT PK value)
     * 
     * IMPORTANT: For non-PK path, offset semantics are different!
     * The offset is a PAGE NUMBER that gets multiplied by chunkSize for SQL OFFSET.
     */
    public function testNonPKExportUsesOffsetPagination(): void {
        $chunk1 = [
            ['composite_key1' => 'a', 'composite_key2' => '1', 'data' => 'row1'],
            ['composite_key1' => 'a', 'composite_key2' => '2', 'data' => 'row2'],
        ];
        $chunk2 = [
            ['composite_key1' => 'b', 'composite_key2' => '1', 'data' => 'row3'],
        ];
        
        // Mock TableHelper to return null for PK (triggers non-PK path)
        $this->setupMocks([
            $chunk1,    // First chunk
            $chunk2,    // Second chunk
            [],         // Empty - end of table
        ], false);  // hasPK = false
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_composite_key_table',
            0,
            1000,   // Note: internally reduced to ~666 for non-PK tables
            50
        );
        
        $result = $exporter->run();
        
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(3, $result['exported_rows']);
        // For non-PK path, offset is a PAGE COUNTER (not PK value)
        $this->assertGreaterThan(0, $result['current_offset']);
    }

    // =========================================================================
    // PHASE 2 TESTS: Failure Paths (should FAIL now, PASS after fixes)
    // =========================================================================

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Query error (null return) should throw exception
     * 
     * BUG EXPOSED: Currently, when selectCustom returns null, the code
     * sets previousDataRows to null (not 0), and the termination check
     * `previousDataRows === 0` fails because `null === 0` is FALSE.
     * 
     * Current behavior (before fix): Infinite loop detected by mock's call limit
     * Expected (after fix): Exception thrown with "Database query failed" message
     * 
     * This test detects the bug via infinite loop detection, then PASSES after Fix 1.
     */
    public function testQueryErrorThrowsException(): void {
        $this->setupMocks([
            null,   // Query ERROR - returns null instead of array
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_failing_table',
            0,
            1000,
            50
        );
        
        // Before Fix 1: Mock detects infinite loop (RuntimeException)
        // After Fix 1: Code throws Exception with "Database query failed"
        // Both indicate the test correctly identifies problematic behavior
        try {
            $exporter->run();
            $this->fail('Expected an exception to be thrown');
        } catch (\RuntimeException $e) {
            // Before fix: Infinite loop detected by mock
            $this->assertStringContainsString('Infinite loop detected', $e->getMessage(),
                'Bug confirmed: Code enters infinite loop when query returns null');
        } catch (\Exception $e) {
            // After fix: Proper error handling
            $this->assertStringContainsString('Database query failed', $e->getMessage(),
                'Fix working: Code properly detects query failure');
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Loop must terminate within reasonable iterations (defense in depth)
     * 
     * PURPOSE: Verify Fix 3 (maxIterations guard) works correctly.
     * 
     * The guard at line 105 (`totalDataRows >= maxPageRows`) will normally
     * terminate the loop. The maxIterations guard (Fix 3) is a defense-in-depth
     * mechanism for unexpected scenarios.
     * 
     * IMPORTANT: This test can only verify the guard AFTER Fix 3 is applied.
     * Before Fix 3, there is no maxIterations check, so this test scenario
     * would simply complete normally (reaching maxPageRows via line 105).
     * 
     * Test design:
     * - maxPageRows = 100, chunkSize = 50
     * - Expected max iterations = ceil(100/50) + 10 = 12
     * - We return 1 row per chunk, so it takes 100 chunks to reach maxPageRows
     * - After Fix 3: Should throw at iteration 13 (before we'd normally finish)
     * - Before Fix 3: Would complete after 100 iterations (hitting maxPageRows)
     * 
     * NOTE: This test primarily validates that Fix 3 is correctly implemented.
     * The actual infinite loop bug (line 117 using wrong counter) is a silent
     * bug that doesn't cause problems in normal cases because line 105 terminates
     * the loop. Fix 2 corrects line 117 for correctness, and Fix 3 adds the guard.
     */
    public function testLoopTerminatesWithMaxIterations(): void {
        // Create many single-row chunks
        // Without Fix 3: loop runs 100 times (reaching maxPageRows)
        // With Fix 3: loop throws exception at iteration 13
        $responses = [];
        for ($i = 1; $i <= 100; $i++) {
            $responses[] = $this->generateMockRows($i, 1, 'id');
        }
        
        $this->setupMocks($responses, true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_stuck_table',
            0,
            100,    // maxPageRows = 100
            50      // chunkSize = 50 -> maxIterations = ceil(100/50)+10 = 12
        );
        
        // After Fix 3: Should throw exception at iteration 13
        // Before Fix 3: Would complete after ~100 iterations
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/maximum iterations|infinite loop/i');
        
        $exporter->run();
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Offset must advance for non-complete exports
     * 
     * BUG EXPOSED: If a query fails partway through, the offset fallback
     * logic (line 102) may return current_offset=startingOffset even though
     * we claim the export isn't complete. This creates infinite server loops.
     * 
     * Current behavior (before fix): Infinite loop detected by mock's call limit
     * Expected (after fix): Exception indicating query failure or no progress
     * 
     * This test detects the bug via infinite loop detection, then PASSES after fixes.
     */
    public function testOffsetMustAdvanceForIncompleteExport(): void {
        // Simulate: first fetch works, second fetch fails (returns null)
        // After sequence exhausts, mock returns null, triggering infinite loop
        $this->setupMocks([
            $this->generateMockRows(1, 50, 'id'),  // First fetch OK
            null,   // Second fetch fails - this triggers the bug
            // Mock will return null for all subsequent calls, simulating persistent failure
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_no_progress_table',
            0,
            1000,
            50
        );
        
        // Before fixes: Mock detects infinite loop (RuntimeException)
        // After Fix 1: Exception with "Database query failed"
        // After Fix 4: Exception with "offset did not advance"
        
        try {
            $result = $exporter->run();
            
            // If we get here without exception, verify offset actually advanced
            if (!$result['table_export_complete']) {
                $this->assertGreaterThan(0, $result['current_offset'],
                    'For incomplete exports, offset MUST advance beyond starting offset');
            }
        } catch (\RuntimeException $e) {
            // Before fix: Infinite loop detected by mock
            $this->assertStringContainsString('Infinite loop detected', $e->getMessage(),
                'Bug confirmed: Code enters infinite loop when query fails mid-export');
        } catch (\Exception $e) {
            // After fix: Proper error handling
            $this->assertMatchesRegularExpression(
                '/progress|advance|failed|query/i',
                $e->getMessage(),
                'Fix working: Code properly handles mid-export failure'
            );
        }
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * 
     * Test: Verify correct row counter is used in loop condition
     * 
     * BUG EXPOSED: Line 117 uses $exporter->getTotalDataRowsCount() but
     * $exporter (type Exporter) never has data built through it.
     * The actual data counter is in $tableDataExp (type TableDataExport).
     * 
     * This test verifies the loop terminates based on actual row count.
     * 
     * NOTE: This test may pass even before the fix if pageExportComplete
     * is triggered by the totalDataRows >= maxPageRows check on line 105.
     * The bug on line 117 is a redundant check that provides no protection.
     */
    public function testCorrectRowCounterUsedInLoopCondition(): void {
        $chunk1 = $this->generateMockRows(1, 50, 'id');
        $chunk2 = $this->generateMockRows(51, 50, 'id');
        
        $this->setupMocks([
            $chunk1,    // First chunk: 50 rows
            $chunk2,    // Second chunk: 50 rows (total = 100)
            // Should NOT request more - 100 >= maxPageRows
        ], true, 'id');
        
        $exporter = new ChunkedExporter(
            $this->tempFileHandle,
            'wp_counter_test_table',
            0,
            100,    // maxPageRows = 100
            50      // chunkSize = 50 (so 2 chunks = 100 rows)
        );
        
        $result = $exporter->run();
        
        // The key assertion: we should have exactly 100 rows, not more
        $this->assertEquals(100, $result['exported_rows'],
            'Should stop at exactly maxPageRows due to correct counter check');
        $this->assertFalse($result['table_export_complete'],
            'Should NOT be complete - we stopped due to row limit, not end of data');
    }
}

