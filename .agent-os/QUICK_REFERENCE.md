# Agent OS Quick Reference

## Essential Commands

### 🚀 Starting Work
```bash
# Check what needs doing
cat .agent-os/specs/TASKS_INDEX.md

# Work on next task
/execute-tasks .agent-os/specs/[spec-directory]
```

### 📝 Creating New Features
```bash
# Create new spec with proper structure
/create-spec "Feature Name"

# AI will ask clarifying questions and create:
# - spec.md (requirements)
# - tasks.md (implementation tasks)
# - spec-lite.md (summary)
# - sub-specs/ (technical details)
```

### 🔍 Finding Information
```bash
# Overall progress
.agent-os/specs/TASKS_INDEX.md

# Specific spec status
.agent-os/specs/YYYY-MM-DD-spec-name/tasks.md

# Quick spec summary
.agent-os/specs/YYYY-MM-DD-spec-name/spec-lite.md

# Technical details
.agent-os/specs/YYYY-MM-DD-spec-name/sub-specs/technical-spec.md
```

## File Purposes

| File | Purpose | When to Check |
|------|---------|---------------|
| `TASKS_INDEX.md` | Overview of all specs | Starting work |
| `spec.md` | Full requirements | Understanding feature |
| `spec-lite.md` | 1-3 sentence summary | Quick context |
| `tasks.md` | Implementation checklist | Tracking progress |
| `technical-spec.md` | Technical details | Implementation |

## Status Symbols

- `[ ]` - Task pending
- `[x]` - Task complete
- `⚠️` - Blocking issue
- `✅` - Spec complete
- `🟡` - In progress
- `📋` - Planning stage

## Task Structure

```markdown
- [ ] 1. Major task
  - [ ] 1.1 Write tests
  - [ ] 1.2 Implement feature
  - [ ] 1.3 Verify tests pass
```

## Best Practices

1. **Check index first** - Always start with TASKS_INDEX.md
2. **One spec at a time** - Complete before starting next
3. **Test first** - Follow TDD approach in tasks
4. **Update as you go** - Mark tasks complete immediately
5. **Document blockers** - Add ⚠️ with explanation

## Common Workflows

### Starting Your Day
1. Check `TASKS_INDEX.md` for priorities
2. Pick highest priority incomplete spec
3. Run `/execute-tasks` on that spec
4. Follow the task list

### Completing a Task
1. Work completes successfully
2. Mark task `[x]` in tasks.md
3. Run tests
4. Move to next task

### Hitting a Blocker
1. Add `⚠️` to the task
2. Document issue in tasks.md
3. Update TASKS_INDEX.md if needed
4. Move to different spec or resolve

---
*Quick tip: Keep this file open while working!*