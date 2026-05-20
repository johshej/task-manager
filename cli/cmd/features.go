package cmd

import (
	"fmt"

	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

var featuresCmd = &cobra.Command{
	Use:               "features",
	Short:             "Manage features",
	PersistentPreRunE: requireClient,
}

var featuresListCmd = &cobra.Command{
	Use:   "list <epic-id>",
	Short: "List features for an epic",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		features, err := apiClient.ListFeatures(args[0])
		if err != nil {
			return err
		}
		output.Features(features, jsonFlag)
		return nil
	},
}

var featuresGetCmd = &cobra.Command{
	Use:   "get <id>",
	Short: "Show a single feature",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		feature, err := apiClient.GetFeature(args[0])
		if err != nil {
			return err
		}
		output.Item(feature, jsonFlag)
		return nil
	},
}

var featuresUpdateCmd = &cobra.Command{
	Use:   "update <id>",
	Short: "Update a feature",
	Example: `  tm features update abc123 --name "New name"
  tm features update abc123 --status done
  tm features update abc123 --environment Production`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "description", "status", "environment", "ai_mode")
		if cmd.Flags().Changed("tdd") {
			val, _ := cmd.Flags().GetBool("tdd")
			fields["tdd"] = val
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		feature, err := apiClient.UpdateFeature(args[0], fields)
		if err != nil {
			return err
		}
		if jsonFlag {
			output.JSON(feature)
		} else {
			fmt.Println("Feature updated.")
		}
		return nil
	},
}

var featuresHistoryCmd = &cobra.Command{
	Use:   "history <feature-id>",
	Short: "Show history for a feature",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		entries, err := apiClient.GetFeatureHistory(args[0])
		if err != nil {
			return err
		}
		output.History(entries, jsonFlag)
		return nil
	},
}

var featuresNoteCmd = &cobra.Command{
	Use:   "note <feature-id>",
	Short: "Add a note to a feature's history",
	Example: `  tm features note f1 --message "Reviewed scope"
  tm features note f1 --metadata '{"message":"done","tags":["review"]}'`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		metadata, err := buildMetadata(cmd)
		if err != nil {
			return err
		}
		entry, err := apiClient.AddFeatureNote(args[0], metadata)
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
	featuresUpdateCmd.Flags().String("name", "", "Feature name")
	featuresUpdateCmd.Flags().String("description", "", "Feature description")
	featuresUpdateCmd.Flags().String("status", "", "Feature status")
	featuresUpdateCmd.Flags().String("environment", "", "Environment (Development, Production, Staging, Other)")
	featuresUpdateCmd.Flags().String("ai_mode", "", "AI mode")
	featuresUpdateCmd.Flags().Bool("tdd", false, "Enable TDD mode")

	addNoteFlags(featuresNoteCmd)

	featuresCmd.AddCommand(featuresListCmd, featuresGetCmd, featuresUpdateCmd, featuresHistoryCmd, featuresNoteCmd)
	rootCmd.AddCommand(featuresCmd)
}
