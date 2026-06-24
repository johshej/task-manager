package cmd

import (
	"fmt"

	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

var tasksCmd = &cobra.Command{
	Use:               "tasks",
	Short:             "Manage tasks",
	PersistentPreRunE: requireClient,
}

var tasksListCmd = &cobra.Command{
	Use:   "list [feature-id]",
	Short: "List tasks for a feature, or all tasks in the project epic",
	Example: `  tm tasks list
  tm tasks list --status in_progress
  tm tasks list --status building_automated_tests
  tm tasks list --description`,
	Args: cobra.RangeArgs(0, 1),
	RunE: func(cmd *cobra.Command, args []string) error {
		statusFilter, _ := cmd.Flags().GetString("status")
		showDesc, _ := cmd.Flags().GetBool("description")

		filterAndSort := func(tasks []map[string]any) []map[string]any {
			if statusFilter != "" {
				filtered := tasks[:0]
				for _, t := range tasks {
					if strField(t, "status") == statusFilter {
						filtered = append(filtered, t)
					}
				}
				tasks = filtered
			}
			return tasks
		}

		if len(args) == 1 {
			tasks, err := apiClient.ListTasks(args[0])
			if err != nil {
				return err
			}
			tasks = filterAndSort(tasks)
			output.Tasks(tasks, jsonFlag, showDesc)
			return nil
		}
		if projectEpicID == "" {
			return fmt.Errorf("no feature-id given and no .task-manager file found in directory tree")
		}
		features, err := apiClient.ListFeatures(projectEpicID)
		if err != nil {
			return err
		}
		if jsonFlag {
			type featureWithTasks struct {
				Feature map[string]any   `json:"feature"`
				Tasks   []map[string]any `json:"tasks"`
			}
			result := make([]featureWithTasks, 0, len(features))
			for _, f := range features {
				tasks, err := apiClient.ListTasks(strField(f, "id"))
				if err != nil {
					return err
				}
				result = append(result, featureWithTasks{Feature: f, Tasks: filterAndSort(tasks)})
			}
			output.JSON(result)
			return nil
		}
		for _, f := range features {
			tasks, err := apiClient.ListTasks(strField(f, "id"))
			if err != nil {
				return err
			}
			tasks = filterAndSort(tasks)
			if statusFilter != "" && len(tasks) == 0 {
				continue
			}
			fmt.Printf("\n── %s (%s) ──\n", strField(f, "name"), strField(f, "status"))
			if len(tasks) == 0 {
				fmt.Println("  (no tasks)")
				continue
			}
			output.Tasks(tasks, false, showDesc)
		}
		return nil
	},
}

var tasksGetCmd = &cobra.Command{
	Use:   "get <id>",
	Short: "Show a single task",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		task, err := apiClient.GetTask(args[0])
		if err != nil {
			return err
		}
		output.Item(task, jsonFlag)
		return nil
	},
}

var tasksUpdateCmd = &cobra.Command{
	Use:   "update <id>",
	Short: "Update a task",
	Example: `  tm tasks update abc123 --title "New title"
  tm tasks update abc123 --priority 2 --execution-order 1
  tm tasks update abc123 --environment Production`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "title", "description", "status", "environment", "ai_mode")
		if cmd.Flags().Changed("priority") {
			val, _ := cmd.Flags().GetInt("priority")
			fields["priority"] = val
		}
		if cmd.Flags().Changed("execution-order") {
			val, _ := cmd.Flags().GetInt("execution-order")
			fields["execution_order"] = val
		}
		if cmd.Flags().Changed("tdd") {
			val, _ := cmd.Flags().GetBool("tdd")
			fields["tdd"] = val
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		task, err := apiClient.UpdateTask(args[0], fields)
		if err != nil {
			return err
		}
		if jsonFlag {
			output.JSON(task)
		} else {
			fmt.Println("Task updated.")
		}
		return nil
	},
}

var tasksStatusCmd = &cobra.Command{
	Use:     "status <task-id> <status>",
	Short:   "Update a task's status",
	Example: "  tm tasks status abc123 done",
	Args:    cobra.ExactArgs(2),
	RunE: func(cmd *cobra.Command, args []string) error {
		task, err := apiClient.UpdateTaskStatus(args[0], args[1])
		if err != nil {
			return err
		}
		if jsonFlag {
			output.JSON(task)
		} else {
			fmt.Printf("Status updated to %q.\n", args[1])
		}
		return nil
	},
}

var tasksHistoryCmd = &cobra.Command{
	Use:   "history <task-id>",
	Short: "Show history for a task",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		entries, err := apiClient.GetTaskHistory(args[0])
		if err != nil {
			return err
		}
		output.History(entries, jsonFlag)
		return nil
	},
}

var tasksNoteCmd = &cobra.Command{
	Use:   "note <task-id>",
	Short: "Add a note to a task's history",
	Example: `  tm tasks note abc123 --message "Implemented login flow"
  tm tasks note abc123 --metadata '{"message":"done","model":"claude-sonnet-4-6","duration_ms":4500}'`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		body, meta, err := buildNote(cmd)
		if err != nil {
			return err
		}
		entry, err := apiClient.AddTaskNote(args[0], body, meta)
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

func init() {
	tasksListCmd.Flags().String("status", "", "Filter by status: todo|in_progress|blocked|building_automated_tests|running_automated_tests|done|merged_to_staging|deployed_to_staging|merged_to_master|deployed_to_master")
	tasksListCmd.Flags().BoolP("description", "d", false, "Show task descriptions")

	tasksUpdateCmd.Flags().String("title", "", "Task title")
	tasksUpdateCmd.Flags().String("description", "", "Task description")
	tasksUpdateCmd.Flags().String("status", "", "Task status")
	tasksUpdateCmd.Flags().String("environment", "", "Environment (Development, Production, Staging, Other)")
	tasksUpdateCmd.Flags().String("ai_mode", "", "AI mode")
	tasksUpdateCmd.Flags().Int("priority", 0, "Priority (integer)")
	tasksUpdateCmd.Flags().Int("execution-order", 0, "Execution order")
	tasksUpdateCmd.Flags().Bool("tdd", false, "Enable TDD mode")

	addNoteFlags(tasksNoteCmd)

	tasksCmd.AddCommand(tasksListCmd, tasksGetCmd, tasksUpdateCmd, tasksStatusCmd, tasksHistoryCmd, tasksNoteCmd)
	rootCmd.AddCommand(tasksCmd)
}
