# Agent OS Workflow Guide - Shield Security

## Quick Start

This guide helps you use Agent OS properly for Shield Security development.

## Directory Structure

```
.agent-os/
├── product/              # Product-level documentation
│   ├── mission.md        # Product vision and purpose
│   ├── mission-lite.md   # Condensed version for AI
│   ├── tech-stack.md     # Technology decisions
│   ├── roadmap.md        # Development phases
│   └── decisions.md      # Decision log
├── specs/                # Feature specifications
│   ├── TASKS_INDEX.md    # Central task overview
│   └── YYYY-MM-DD-spec-name/
│       ├── spec.md       # Main specification
│       ├── spec-lite.md  # Quick summary
│       ├── tasks.md      # Task breakdown
│       └── sub-specs/    # Technical details
├── standards/            # Coding standards (if needed)
└── knowledge/            # Shared knowledge base

```

## Core Workflows

### 1. Creating a New Feature Spec

```bash
# Use the Agent OS create-spec command
/create-spec "Feature Name"
```

This will:
- Create proper directory structure
- Generate spec.md with required sections
- Create tasks.md with TDD structure
- Add spec-lite.md for quick context

### 2. Working on Tasks

```bash
# Execute tasks for a spec
/execute-tasks .agent-os/specs/YYYY-MM-DD-spec-name
```

This will:
- Load the spec context
- Check current task status
- Execute tasks sequentially
- Update progress in tasks.md
- Create PR when complete

### 3. Checking Overall Progress

```bash
# View the task index
cat .agent-os/specs/TASKS_INDEX.md
```

Shows:
- All specs and their status
- Completion percentages
- Next priority tasks
- Blocking issues

## Best Practices

### Task Management
- ✅ Always use separate tasks.md files
- ✅ Mark tasks as `[x]` when complete
- ✅ Use `⚠️` for blocking issues
- ✅ Follow TDD: test first, implement, verify

### Documentation
- ✅ Keep spec.md focused on requirements
- ✅ Extract technical details to sub-specs/
- ✅ Update spec-lite.md if goals change
- ✅ Document decisions in decisions.md

### Workflow
- ✅ Use Agent OS commands, not manual editing
- ✅ Review TASKS_INDEX.md before starting work
- ✅ Complete one spec before starting another
- ✅ Keep roadmap.md updated with progress

## Common Commands

### View Current Tasks
```bash
# See what needs work
cat .agent-os/specs/TASKS_INDEX.md

# Check specific spec tasks
cat .agent-os/specs/*/tasks.md | grep -E "^\s*- \[ \]"
```

### Update Task Status
```bash
# Mark task complete in tasks.md
# Change - [ ] to - [x]
```

### Find Blocking Issues
```bash
# Search for warnings
grep -r "⚠️" .agent-os/specs/
```

## Spec Status Indicators

- ✅ **COMPLETE** - All tasks finished
- 🟡 **IN PROGRESS** - Actively being worked on
- 📋 **PLANNING** - Spec created, tasks not started
- ⚠️ **BLOCKED** - Has blocking issues

## Tips for AI Assistants

When working with Claude or other AI:

1. **Point to spec directory**: `.agent-os/specs/YYYY-MM-DD-spec-name/`
2. **AI loads in order**: spec-lite.md → tasks.md → spec.md → technical-spec.md
3. **Use commands**: `/execute-tasks` for systematic work
4. **Check index first**: Always review TASKS_INDEX.md

## Troubleshooting

### Can't find next task?
→ Check `.agent-os/specs/TASKS_INDEX.md`

### Spec seems incomplete?
→ Look for `⚠️` markers in tasks.md

### Need technical details?
→ Check `sub-specs/technical-spec.md`

### Task tracking unclear?
→ Count `[x]` vs `[ ]` in tasks.md

## Migration from Old Structure

We've migrated from flat spec files to proper directories:

**Old**: `.agent-os/specs/spec-name.md`
**New**: `.agent-os/specs/YYYY-MM-DD-spec-name/spec.md`

All existing specs have been properly restructured with:
- Extracted tasks
- Technical sub-specs
- Quick summaries
- Proper dating

---

*Last Updated: 2025-08-02*
*For Agent OS documentation, see: `C:\Users\paulg\.agent-os\instructions\`*