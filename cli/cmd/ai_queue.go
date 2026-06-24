package cmd

import (
	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

var aiQueueCmd = &cobra.Command{
	Use:               "ai-queue [epic-id]",
	Short:             "Show the AI Queue for the project epic",
	Args:              cobra.RangeArgs(0, 1),
	PersistentPreRunE: requireClient,
	RunE: func(cmd *cobra.Command, args []string) error {
		epicID, err := resolveEpicID(args)
		if err != nil {
			return err
		}
		tasks, err := apiClient.GetEpicQueue(epicID)
		if err != nil {
			return err
		}
		output.AiQueue(tasks, jsonFlag)
		return nil
	},
}

func init() {
	rootCmd.AddCommand(aiQueueCmd)
}
