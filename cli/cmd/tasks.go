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
	Use:   "list <feature-id>",
	Short: "List tasks for a feature",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		tasks, err := apiClient.ListTasks(args[0])
		if err != nil {
			return err
		}
		output.Tasks(tasks, jsonFlag)
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
		metadata, err := buildMetadata(cmd)
		if err != nil {
			return err
		}
		entry, err := apiClient.AddTaskNote(args[0], metadata)
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
