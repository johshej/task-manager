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

	apiClient     *api.Client
	projectEpicID string
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
// Priority: flags > env vars > .task-manager file > profile config
func requireClient(cmd *cobra.Command, args []string) error {
	tmURL := firstNonEmpty(urlFlag, os.Getenv("TM_URL"))
	tmToken := firstNonEmpty(tokenFlag, os.Getenv("TM_TOKEN"))

	pf, _ := findProjectFile()
	if pf != nil {
		if pf.EpicID != "" {
			projectEpicID = pf.EpicID
		}
		if tmURL == "" {
			tmURL = pf.URL
		}
		if tmToken == "" {
			tmToken = pf.Token
		}
	}

	if tmURL == "" || tmToken == "" {
		cfg, err := loadConfig()
		if err != nil {
			return fmt.Errorf("failed to load config: %w", err)
		}
		profile, _, err := resolveProfile(cfg, profileFlag)
		if err != nil {
			return err
		}
		if tmURL == "" {
			tmURL = profile.URL
		}
		if tmToken == "" {
			tmToken = profile.Token
		}
	}

	apiClient = api.New(tmURL, tmToken)
	return nil
}

func firstNonEmpty(vals ...string) string {
	for _, v := range vals {
		if v != "" {
			return v
		}
	}
	return ""
}

func strField(m map[string]any, key string) string {
	if v, ok := m[key]; ok && v != nil {
		return fmt.Sprintf("%v", v)
	}
	return ""
}
