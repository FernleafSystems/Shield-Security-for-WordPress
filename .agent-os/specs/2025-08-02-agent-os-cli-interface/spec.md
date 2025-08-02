# Spec Requirements Document

> Spec: Agent OS CLI Interface
> Created: 2025-08-02

## Overview

Implement a unified command-line interface for Agent OS to streamline developer workflow and improve task visibility. This single-script solution will replace manual file browsing with intuitive commands for checking task status, finding next actions, and managing specifications.

## User Stories

### Developer Task Management

As a developer, I want to quickly check what tasks need to be done, so that I can start working without manually browsing through multiple files.

The developer runs a single command like `aos status` and immediately sees all incomplete tasks across the project, organized by priority and spec. They can then use `aos next` to get the highest priority task and begin work immediately.

### Team Progress Visibility

As a team lead, I want to see overall project progress at a glance, so that I can make informed decisions about resource allocation and timelines.

Running `aos progress` shows a dashboard-style view with completion percentages for each spec, blocking issues, and estimated completion based on current velocity.

### Quick Spec Navigation

As a developer working on a specific feature, I want to quickly access all files related to that spec, so that I can understand context without searching through directories.

Using `aos show docker-testing` instantly displays the spec summary, current task status, technical details location, and recent changes, providing complete context in seconds.

## Spec Scope

1. **Unified CLI Script** - Single PowerShell script with subcommands for all Agent OS operations
2. **Task Discovery Commands** - Find incomplete tasks, show blocking issues, display next priority
3. **Progress Visualization** - Show completion percentages, progress bars, and timeline estimates
4. **Spec Management** - Quick access to spec files, status checks, and context loading
5. **Integration Helpers** - Commands to help with AI assistant integration and workflow execution

## Out of Scope

- Modification of core Agent OS structure or workflows
- Direct task execution (still handled by AI assistants)
- Git operations or PR management
- Cross-platform support (PowerShell only for now)

## Expected Deliverable

1. Single `aos.ps1` script in `.agent-os/tools/` with intuitive subcommands
2. Installation script that adds the tool to PATH or creates an alias
3. Comprehensive help system with examples for each command