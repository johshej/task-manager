# tm — Task Manager CLI

A Go CLI client for the Task Manager API. Works from the terminal or as a tool for AI agents.

## Install

Requires [Go 1.22+](https://go.dev/dl/).

```bash
cd cli
make install        # builds and copies to ~/.local/bin/tm
```

Or build manually:
```bash
cd cli
go mod tidy
go build -o tm .
```

## Configuration

```bash
# Set up a default profile
tm config set --url https://your-task-manager.com --token <api-token>

# Add a named profile
tm config set --profile work --url https://work.example.com --token <token>

# Show active config
tm config show

# List all profiles
tm config list
```

Config is stored at `~/.config/tm/config.yaml`.

Environment variable overrides: `TM_URL`, `TM_TOKEN`, `TM_PROFILE`.

## Usage

Use `--profile <name>` to switch profiles, `--json` for machine-readable output.

```bash
# Epics
tm epics list
tm epics list --repo git@github.com:user/repo.git
tm epics get <id>
tm epics history <id>
tm epics note <id> --message "Started scoping"

# Features
tm features list <epic-id>
tm features get <id>
tm features history <id>
tm features note <id> --message "Reviewed scope"

# Tasks
tm tasks list <feature-id>
tm tasks get <id>
tm tasks status <id> done
tm tasks history <id>
tm tasks note <id> --message "Implemented"
tm tasks note <id> --metadata '{"message":"done","model":"claude-sonnet-4-6","duration_ms":4500}'
```

## AI agent usage

All commands support `--json` for structured output:

```bash
tm tasks list <feature-id> --json
tm tasks note <id> --json --metadata '{"message":"Analysis complete","model":"claude-sonnet-4-6"}'
```

## Test matrices

A test matrix has scenarios as rows and contexts (named combinations such as "Log in / Desktop / Offline") as columns, with a result in every cell. A comment is a task given to one or more contexts.

```bash
tm test-matrices create --name "Roles and devices"
tm test-matrices contexts create <matrix-id> --code LDO --name "Log in / Desktop / Offline"
tm test-matrices scenarios create <matrix-id> --title "Log in" --contexts <context-id>,<context-id>
tm test-matrices results set <matrix-id> <scenario-id> <context-id> --status failed
tm test-matrices comments create <scenario-id> --contexts <context-id>,<context-id> --title "Log in fails"
tm test-matrices automated-tests create <scenario-id> --name "logs in" --file tests/Browser/LoginTest.php --kind browser
tm test-matrices get <matrix-id>     # the grid: . untested, ok passed, FAIL failed, - not relevant
```
