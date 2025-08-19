# Option A Rollback Procedures

## Overview

This document provides comprehensive rollback procedures for Option A (GitHub Actions Compatibility Fix) if issues arise during or after implementation. The rollback strategy is designed to quickly restore functionality while preserving the 40% performance improvement achieved in Phase 2.

## Rollback Scenarios

### Scenario 1: Local Tests Fail After Implementation
**Symptoms**: `./bin/run-docker-tests.sh` fails to execute or produces errors
**Impact**: Development workflow disrupted
**Priority**: HIGH - Immediate rollback required

### Scenario 2: GitHub Actions Still Fail After Fix
**Symptoms**: GitHub Actions continue to exit with error codes
**Impact**: CI/CD pipeline broken
**Priority**: MEDIUM - Investigate first, rollback if needed

### Scenario 3: Performance Degradation
**Symptoms**: Test execution time significantly exceeds 3m 28s baseline
**Impact**: Performance regression
**Priority**: MEDIUM - Analyze root cause before rollback

### Scenario 4: Service Discovery Issues
**Symptoms**: Docker Compose cannot find services with new names
**Impact**: Container orchestration broken
**Priority**: HIGH - Immediate rollback required

## Immediate Rollback (Git-Based)

### Quick Git Revert
For immediate restoration when entire commit needs reversal:

```bash
# Navigate to project root
cd /mnt/d/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield

# Identify the Option A commit
git log --oneline -n 5

# Revert the most recent commit (if it's Option A)
git revert HEAD

# Or revert specific commit hash
git revert <commit-hash>

# Push revert to restore GitHub Actions
git push origin docker-matrix-testing-research
```

### Verification After Git Revert
```bash
# Test local functionality immediately
./bin/run-docker-tests.sh

# Expected results:
# - Version-specific service names restored (mysql-wp682, test-runner-wp682)
# - Parallel execution still works
# - Performance around 3m 28s maintained
# - Both WordPress versions test successfully

# Monitor GitHub Actions
# 1. Check workflow run status in GitHub
# 2. Verify both WordPress versions test in CI
# 3. Confirm no exit code 4 errors
```

## Selective File Rollback

### When Git Revert Is Not Suitable
If the commit contains other changes that should be preserved:

```bash
# Restore individual files to previous state
git checkout HEAD~1 -- tests/docker/docker-compose.yml
git checkout HEAD~1 -- tests/docker/docker-compose.package.yml  
git checkout HEAD~1 -- tests/docker/docker-compose.ci.yml
git checkout HEAD~1 -- bin/run-docker-tests.sh
git checkout HEAD~1 -- .github/workflows/docker-tests.yml

# Stage the rollback changes
git add tests/docker/docker-compose.yml
git add tests/docker/docker-compose.package.yml
git add tests/docker/docker-compose.ci.yml
git add bin/run-docker-tests.sh
git add .github/workflows/docker-tests.yml

# Commit the rollback
git commit -m "Rollback Option A service name changes

- Restore version-specific service names (mysql-wp682, test-runner-wp682)
- Revert GitHub Actions workflow to hardcoded service selection
- Maintain Phase 2 parallel execution architecture
- Preserve 40% performance improvement

Issue: Option A implementation caused [describe specific issue]
Rollback maintains Phase 2 functionality while investigating root cause"

# Push to trigger GitHub Actions with restored configuration
git push origin docker-matrix-testing-research
```

## Manual File Restoration

### Emergency Manual Rollback
If git operations are not available or suitable:

#### 1. docker-compose.yml Restoration
```bash
# Backup current file first
cp tests/docker/docker-compose.yml tests/docker/docker-compose.yml.option-a-backup

# Restore version-specific service names
sed -i 's/mysql-latest:/mysql-wp682:/' tests/docker/docker-compose.yml
sed -i 's/mysql-previous:/mysql-wp673:/' tests/docker/docker-compose.yml
sed -i 's/test-runner-latest:/test-runner-wp682:/' tests/docker/docker-compose.yml
sed -i 's/test-runner-previous:/test-runner-wp673:/' tests/docker/docker-compose.yml

# Restore database names
sed -i 's/wordpress_test_latest/wordpress_test_wp682/' tests/docker/docker-compose.yml
sed -i 's/wordpress_test_previous/wordpress_test_wp673/' tests/docker/docker-compose.yml

# Restore volume names
sed -i 's/mysql_latest_data/mysql_wp682_data/' tests/docker/docker-compose.yml
sed -i 's/mysql_previous_data/mysql_wp673_data/' tests/docker/docker-compose.yml

# Restore command arguments
sed -i 's/mysql-latest/mysql-wp682/g' tests/docker/docker-compose.yml
sed -i 's/mysql-previous/mysql-wp673/g' tests/docker/docker-compose.yml
```

#### 2. docker-compose.package.yml Restoration
```bash
# Restore service references
sed -i 's/test-runner-latest:/test-runner-wp682:/' tests/docker/docker-compose.package.yml
sed -i 's/test-runner-previous:/test-runner-wp673:/' tests/docker/docker-compose.package.yml
```

#### 3. docker-compose.ci.yml Restoration
```bash
# Restore service references
sed -i 's/test-runner-latest:/test-runner-wp682:/' tests/docker/docker-compose.ci.yml
sed -i 's/test-runner-previous:/test-runner-wp673:/' tests/docker/docker-compose.ci.yml
```

#### 4. bin/run-docker-tests.sh Restoration
```bash
# Backup current file
cp bin/run-docker-tests.sh bin/run-docker-tests.sh.option-a-backup

# Restore service references in startup commands
sed -i 's/mysql-latest mysql-previous/mysql-wp682 mysql-wp673/' bin/run-docker-tests.sh
sed -i 's/test-runner-latest test-runner-previous/test-runner-wp682 test-runner-wp673/' bin/run-docker-tests.sh

# Restore service references in docker compose run commands
sed -i 's/test-runner-latest/test-runner-wp682/' bin/run-docker-tests.sh
sed -i 's/test-runner-previous/test-runner-wp673/' bin/run-docker-tests.sh
```

#### 5. GitHub Actions Workflow Restoration
```bash
# Restore original service selection logic
cat > /tmp/github-actions-service-logic.txt << 'EOF'
        # Determine service name based on WordPress version
        WP_VERSION="${{ matrix.wordpress }}"
        if [[ "$WP_VERSION" == "${{ needs.detect-wp-versions.outputs.latest }}" ]]; then
          SERVICE_NAME="test-runner-wp682"
        elif [[ "$WP_VERSION" == "${{ needs.detect-wp-versions.outputs.previous }}" ]]; then
          SERVICE_NAME="test-runner-wp673"
        else
          # Fallback to legacy service for any other version
          SERVICE_NAME="test-runner"
        fi
EOF

# Apply to workflow file (requires manual editing or script assistance)
# This section needs manual replacement in .github/workflows/docker-tests.yml
# around lines 236-244
```

## Rollback Verification Checklist

### Local Testing Verification
```bash
# 1. Verify service names are restored
docker compose -f tests/docker/docker-compose.yml \
  -f tests/docker/docker-compose.ci.yml \
  -f tests/docker/docker-compose.package.yml \
  config --services | grep -E "(mysql-wp|test-runner-wp)"

# Expected output should include:
# mysql-wp682
# mysql-wp673  
# test-runner-wp682
# test-runner-wp673

# 2. Test local execution
time ./bin/run-docker-tests.sh

# Expected results:
# - Execution time: ~3m 28s (40% improvement maintained)
# - Both WordPress versions test successfully
# - Parallel execution works
# - No service discovery errors

# 3. Verify database isolation
docker ps --filter "name=mysql-wp" --format "table {{.Names}}\t{{.Ports}}"

# Expected output:
# NAMES           PORTS
# mysql-wp682     0.0.0.0:3309->3306/tcp
# mysql-wp673     0.0.0.0:3310->3306/tcp
```

### GitHub Actions Verification
```bash
# 1. Push rollback changes
git add -A
git commit -m "Emergency rollback: Restore version-specific service names"
git push origin docker-matrix-testing-research

# 2. Monitor GitHub Actions workflow
# - Check workflow starts successfully
# - Verify service discovery works
# - Confirm both WordPress versions test
# - Validate no exit code 4 errors

# 3. Compare CI results with local
# - Test counts should match local execution
# - Pass/fail status should be identical
# - No regression in test results
```

## Rollback Success Criteria

### Primary Objectives
- ✅ Local tests execute successfully with ~3m 28s runtime
- ✅ GitHub Actions pipeline passes without exit code 4  
- ✅ Both WordPress versions test in parallel locally and in CI
- ✅ Database isolation maintained (separate MySQL containers)
- ✅ 40% performance improvement preserved

### Secondary Objectives  
- ✅ Version-specific service names restored (mysql-wp682, test-runner-wp682)
- ✅ Original GitHub Actions service selection logic functional
- ✅ No regression in test results or functionality
- ✅ Docker Compose service discovery working correctly

## Post-Rollback Analysis

### Investigation Steps
After successful rollback, investigate the root cause:

```bash
# 1. Review rollback changes
git diff HEAD~1 HEAD

# 2. Analyze what specifically failed
# - Check error messages from failed implementation
# - Review GitHub Actions logs
# - Examine Docker Compose service resolution

# 3. Test individual components
# - Verify service names work in isolation
# - Test Docker Compose service resolution
# - Validate environment variable propagation

# 4. Plan corrective action
# - Identify specific failure point
# - Develop targeted fix
# - Plan incremental testing approach
```

### Alternative Approaches
If Option A consistently fails:

1. **Option B**: Modify GitHub Actions to use dynamic service discovery
2. **Option C**: Implement environment-based service selection in Docker Compose
3. **Option D**: Create hybrid approach with both generic and version-specific services

## Recovery Planning

### Preparation for Next Attempt
Before attempting Option A implementation again:

```bash
# 1. Create comprehensive backup
git branch option-a-rollback-point

# 2. Test rollback procedures
# - Verify all rollback commands work
# - Practice rollback timing
# - Validate rollback verification steps

# 3. Plan incremental implementation
# - Test one file at a time
# - Verify each step before proceeding
# - Maintain rollback checkpoints

# 4. Prepare monitoring
# - Set up automated testing
# - Plan failure detection
# - Prepare emergency response
```

### Rollback Documentation Updates
After any rollback:

1. Update LESSONS_LEARNED.md with failure analysis
2. Document specific rollback steps used
3. Record rollback timing and effectiveness
4. Update risk assessment in option-a-github-fix.md
5. Plan prevention strategies for future attempts

## Emergency Contacts and Resources

### Quick Reference Commands
```bash
# Emergency full rollback
git revert HEAD && git push origin docker-matrix-testing-research

# Emergency service check
docker compose -f tests/docker/docker-compose.yml config --services

# Emergency local test
./bin/run-docker-tests.sh

# Emergency GitHub Actions check
gh run list --limit 1
```

### Rollback Decision Matrix
| Symptom | Severity | Action | Timeline |
|---------|----------|--------|----------|
| Local tests fail completely | HIGH | Immediate git revert | < 5 minutes |
| GitHub Actions exit code 4 | MEDIUM | Investigate first, rollback if needed | < 15 minutes |
| Performance degradation >50% | MEDIUM | Analyze, rollback if significant | < 30 minutes |
| Service discovery errors | HIGH | Immediate selective rollback | < 10 minutes |
| Partial test failures | LOW | Investigate, monitor trends | < 1 hour |

This rollback procedure ensures rapid recovery while maintaining the architectural benefits achieved in Phase 2.