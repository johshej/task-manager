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
		statusFilter, _ := cmd.Flags().GetString("status")
		epicID, err := resolveEpicID(args)
		if err != nil {
			return err
		}
		items, err := apiClient.GetEpicQueue(epicID)
		if err != nil {
			return err
		}
		if statusFilter != "" {
			filtered := items[:0]
			for _, t := range items {
				if strField(t, "status") == statusFilter {
					filtered = append(filtered, t)
				}
			}
			items = filtered
		}
		output.AiQueue(items, jsonFlag)
		return nil
	},
}

func init() {
	aiQueueCmd.Flags().String("status", "", "Filter by status: todo|in_progress|blocked|building_automated_tests|running_automated_tests|done|merged_to_staging|deployed_to_staging|merged_to_master|deployed_to_master")
	rootCmd.AddCommand(aiQueueCmd)
}
