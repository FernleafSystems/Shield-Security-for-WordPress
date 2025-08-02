
# Shield Security Plugin for WordPress

[![Build Status](https://travis-ci.org/FernleafSystems/Shield-Security-for-WordPress.svg?branch=develop)](https://travis-ci.org/FernleafSystems/Shield)


**Don't Leave Your Site At Risk**
> If your site is vulnerable to attack, you're putting your business and your reputation at serious risk. Getting hacked can mean you're locked out of your site, client data stolen, your website defaced or offline, and Google *will* penalise you.
>
**Why take the risk?**
>
> Download and install Shield now for FREE so that you have the most powerful WordPress security system working for you and protecting your site.
>
**Shield + iControlWP**
> If you have multiple sites, then Shield [combined with iControlWP](https://clk.shldscrty.com/shld8), takes the pain out of managing your websites, and covers your security, daily backup (and restore), and updating plugins/themes

## Development

### Testing

**Docker (Recommended)**: `.\bin\run-tests.ps1 all -Docker` - Zero setup required, runs in isolated containers

**Native**: `.\bin\run-tests.ps1 all` or `composer test` - Uses your local PHP/MySQL setup

**Package Testing**: `.\bin\run-tests.ps1 all -Docker -Package` - Tests built production package

See [TESTING.md](TESTING.md) for complete testing documentation.

### Docker CI/CD Integration

Shield Security includes **optional Docker CI/CD testing** following evidence-based patterns from established WordPress plugins:

**Manual Docker CI Workflow**:
- **Trigger**: GitHub Actions `workflow_dispatch` (manual trigger only)
- **Pattern**: Based on Easy Digital Downloads' optional Docker testing approach
- **Configuration**: Configurable PHP (7.4-8.4) and WordPress versions
- **Architecture**: Simple MariaDB + test-runner setup following proven patterns
- **Build Dependencies**: Includes Node.js, npm, and asset building (validated)
- **Status**: **Fully implemented and validated** ✅

**Evidence-Based Implementation**:
- Build steps copied from working `minimal.yml` workflow
- Node.js setup, npm dependencies, and asset building are mandatory
- All dependencies included following successful CI patterns
- Validation checklist ensures workflow will execute successfully

**Why Manual Trigger Only?**
Research of successful WordPress plugins (Yoast SEO, Easy Digital Downloads, WooCommerce) revealed that:
- Most established plugins use native GitHub Actions without Docker
- Optional Docker testing provides flexibility without CI/CD overhead
- Manual triggers allow testing specific version combinations when needed

**Usage**:
1. Go to Actions tab in GitHub repository
2. Select "Docker Tests" workflow
3. Click "Run workflow" and configure PHP/WordPress versions
4. Monitor test execution in containerized environment

**Production Ready**: Docker CI/CD workflow has been tested and validated with comprehensive build pipeline including asset compilation.

### Quick Start Testing

```powershell
# Docker testing (recommended - no setup required)
.\bin\run-tests.ps1 all -Docker

# Native testing (requires local PHP/MySQL)
.\bin\run-tests.ps1 all

# Package testing (tests production build)
.\bin\run-tests.ps1 all -Docker -Package
```

See readme.txt for full details and changelog