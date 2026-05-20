package cmd

import (
	"encoding/json"
	"fmt"

	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

var epicsCmd = &cobra.Command{
	Use:               "epics",
	Short:             "Manage epics",
	PersistentPreRunE: requireClient,
}

var epicsListCmd = &cobra.Command{
	Use:   "list",
	Short: "List epics",
	Example: `  tm epics list
  tm epics list --repo git@github.com:user/repo.git`,
	RunE: func(cmd *cobra.Command, args []string) error {
		repo, _ := cmd.Flags().GetString("repo")
		epics, err := apiClient.ListEpics(repo)
		if err != nil {
			return err
		}
		output.Epics(epics, jsonFlag)
		return nil
	},
}

var epicsGetCmd = &cobra.Command{
	Use:   "get <id>",
	Short: "Show a single epic",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		epic, err := apiClient.GetEpic(args[0])
		if err != nil {
			return err
		}
		output.Item(epic, jsonFlag)
		return nil
	},
}

var epicsUpdateCmd = &cobra.Command{
	Use:   "update <id>",
	Short: "Update an epic",
	Example: `  tm epics update abc123 --name "New name"
  tm epics update abc123 --repo git@github.com:user/repo.git
  tm epics update abc123 --status active`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "description", "status")
		if repo, _ := cmd.Flags().GetString("repo"); cmd.Flags().Changed("repo") {
			fields["repository_url"] = repo
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		epic, err := apiClient.UpdateEpic(args[0], fields)
		if err != nil {
			return err
		}
		if jsonFlag {
			output.JSON(epic)
		} else {
			fmt.Println("Epic updated.")
		}
		return nil
	},
}

var epicsQueueCmd = &cobra.Command{
	Use:   "queue <epic-id>",
	Short: "Show the AI execution queue for an epic",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		tasks, err := apiClient.GetEpicQueue(args[0])
		if err != nil {
			return err
		}
		output.Queue(tasks, jsonFlag)
		return nil
	},
}

var epicsHistoryCmd = &cobra.Command{
	Use:   "history <epic-id>",
	Short: "Show history for an epic",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		entries, err := apiClient.GetEpicHistory(args[0])
		if err != nil {
			return err
		}
		output.History(entries, jsonFlag)
		return nil
	},
}

var epicsNoteCmd = &cobra.Command{
	Use:   "note <epic-id>",
	Short: "Add a note to an epic's history",
	Example: `  tm epics note abc123 --message "Started scoping"
  tm epics note abc123 --metadata '{"message":"done","model":"claude-sonnet-4-6"}'`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		body, meta, err := buildNote(cmd)
		if err != nil {
			return err
		}
		entry, err := apiClient.AddEpicNote(args[0], body, meta)
		if err != nil {
			return err
		}
		if jsonFlag {
			output.JSON(entry)
		} else {
			fmt.Println("Note added.")
		}
		return nil
	},
}

// buildNote returns the body text and optional metadata map from note flags.
func buildNote(cmd *cobra.Command) (string, map[string]any, error) {
	message, _ := cmd.Flags().GetString("message")
	metaStr, _ := cmd.Flags().GetString("metadata")

	var meta map[string]any
	if metaStr != "" {
		if err := json.Unmarshal([]byte(metaStr), &meta); err != nil {
			return "", nil, fmt.Errorf("--metadata must be valid JSON: %w", err)
		}
	}

	if message == "" && metaStr == "" {
		return "", nil, fmt.Errorf("--message or --metadata is required")
	}

	return message, meta, nil
}

func addNoteFlags(cmd *cobra.Command) {
	cmd.Flags().String("message", "", "Note body text")
	cmd.Flags().String("metadata", "", "Extra metadata as JSON (model, duration_ms, etc.)")
}

// collectFlags returns a map of only the flags that were explicitly set.
func collectFlags(cmd *cobra.Command, names ...string) map[string]any {
	fields := map[string]any{}
	for _, name := range names {
		if cmd.Flags().Changed(name) {
			val, _ := cmd.Flags().GetString(name)
			fields[name] = val
		}
	}
	return fields
}

func init() {
	epicsListCmd.Flags().String("repo", "", "Filter by repository URL")

	epicsUpdateCmd.Flags().String("name", "", "Epic name")
	epicsUpdateCmd.Flags().String("description", "", "Epic description")
	epicsUpdateCmd.Flags().String("repo", "", "Repository URL (git@github.com:user/repo.git or https://...)")
	epicsUpdateCmd.Flags().String("status", "", "Epic status")

	addNoteFlags(epicsNoteCmd)

	epicsCmd.AddCommand(epicsListCmd, epicsGetCmd, epicsUpdateCmd, epicsQueueCmd, epicsHistoryCmd, epicsNoteCmd)
	rootCmd.AddCommand(epicsCmd)
}
