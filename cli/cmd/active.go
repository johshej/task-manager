package cmd

import (
	"fmt"

	"github.com/spf13/cobra"
)

var activeCmd = &cobra.Command{
	Use:   "active",
	Short: "Manage the active task or feature for the current project",
}

var activeShowCmd = &cobra.Command{
	Use:   "show",
	Short: "Show the active task or feature",
	RunE:  runActiveShow,
}

var activeSetTaskCmd = &cobra.Command{
	Use:     "task <id>",
	Short:   "Set the active task (clears active feature)",
	Args:    cobra.ExactArgs(1),
	RunE:    runActiveSetTask,
	Example: "  tm active set task 019e1234-abcd-7000-0000-000000000001",
}

var activeSetFeatureCmd = &cobra.Command{
	Use:     "feature <id>",
	Short:   "Set the active feature (clears active task)",
	Args:    cobra.ExactArgs(1),
	RunE:    runActiveSetFeature,
	Example: "  tm active set feature 019e1234-abcd-7000-0000-000000000002",
}

var activeClearCmd = &cobra.Command{
	Use:   "clear",
	Short: "Clear the active task and feature",
	RunE:  runActiveClear,
}

func init() {
	activeSetCmd := &cobra.Command{
		Use:   "set",
		Short: "Set the active task or feature",
	}
	activeSetCmd.AddCommand(activeSetTaskCmd, activeSetFeatureCmd)
	activeCmd.AddCommand(activeShowCmd, activeSetCmd, activeClearCmd)
	rootCmd.AddCommand(activeCmd)
}

func runActiveShow(_ *cobra.Command, _ []string) error {
	pf, err := findProjectFile()
	if err != nil {
		return err
	}
	if pf == nil {
		return fmt.Errorf("no .task-manager file found in directory tree")
	}
	if pf.ActiveTaskID != "" {
		fmt.Printf("Active task:    %s\n", pf.ActiveTaskID)
	} else {
		fmt.Println("Active task:    (none)")
	}
	if pf.ActiveFeatureID != "" {
		fmt.Printf("Active feature: %s\n", pf.ActiveFeatureID)
	} else {
		fmt.Println("Active feature: (none)")
	}
	if pf.SessionID != "" {
		fmt.Printf("Session:        %s\n", pf.SessionID)
	}
	if pf.DaemonURL != "" {
		fmt.Printf("Daemon:         %s\n", pf.DaemonURL)
	}
	return nil
}

func runActiveSetTask(_ *cobra.Command, args []string) error {
	if err := writeProjectFileKey("ACTIVE_TASK_ID", args[0]); err != nil {
		return err
	}
	if err := writeProjectFileKey("ACTIVE_FEATURE_ID", ""); err != nil {
		return err
	}
	fmt.Printf("Active task set to %s\n", args[0])
	return nil
}

func runActiveSetFeature(_ *cobra.Command, args []string) error {
	if err := writeProjectFileKey("ACTIVE_FEATURE_ID", args[0]); err != nil {
		return err
	}
	if err := writeProjectFileKey("ACTIVE_TASK_ID", ""); err != nil {
		return err
	}
	fmt.Printf("Active feature set to %s\n", args[0])
	return nil
}

func runActiveClear(_ *cobra.Command, _ []string) error {
	if err := writeProjectFileKey("ACTIVE_TASK_ID", ""); err != nil {
		return err
	}
	if err := writeProjectFileKey("ACTIVE_FEATURE_ID", ""); err != nil {
		return err
	}
	fmt.Println("Active task and feature cleared.")
	return nil
}
