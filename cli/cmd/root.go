package cmd

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"
	"task-manager-cli/api"
)

var (
	profileFlag string
	jsonFlag    bool
	urlFlag     string
	tokenFlag   string

	apiClient *api.Client
)

var rootCmd = &cobra.Command{
	Use:   "tm",
	Short: "Task Manager CLI",
	Long:  "CLI client for the Task Manager API.\nManage epics, features, tasks, and history from the terminal or an AI agent.",
}

func Execute() {
	if err := rootCmd.Execute(); err != nil {
		os.Exit(1)
	}
}

func init() {
	rootCmd.PersistentFlags().StringVar(&profileFlag, "profile", "", "config profile (env: TM_PROFILE)")
	rootCmd.PersistentFlags().BoolVar(&jsonFlag, "json", false, "output as JSON")
	rootCmd.PersistentFlags().StringVar(&urlFlag, "url", "", "task manager URL (env: TM_URL)")
	rootCmd.PersistentFlags().StringVar(&tokenFlag, "token", "", "API token (env: TM_TOKEN)")
}

// requireClient is used as PersistentPreRunE on non-config commands.
func requireClient(cmd *cobra.Command, args []string) error {
	// Inline flags take priority
	if urlFlag != "" && tokenFlag != "" {
		apiClient = api.New(urlFlag, tokenFlag)
		return nil
	}

	cfg, err := loadConfig()
	if err != nil {
		return fmt.Errorf("failed to load config: %w", err)
	}

	profile, _, err := resolveProfile(cfg, profileFlag)
	if err != nil {
		return err
	}

	// Allow partial env override
	tmURL := urlFlag
	if tmURL == "" {
		tmURL = profile.URL
	}
	tmToken := tokenFlag
	if tmToken == "" {
		tmToken = profile.Token
	}

	apiClient = api.New(tmURL, tmToken)
	return nil
}
