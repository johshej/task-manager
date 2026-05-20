package cmd

import (
	"fmt"

	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

var configCmd = &cobra.Command{
	Use:   "config",
	Short: "Manage configuration profiles",
}

var configSetCmd = &cobra.Command{
	Use:   "set",
	Short: "Set URL and token for a profile",
	Example: `  tm config set --url https://tm.example.com --token abc123
  tm config set --profile work --url https://work.example.com --token xyz`,
	RunE: func(cmd *cobra.Command, args []string) error {
		setURL, _ := cmd.Flags().GetString("url")
		setToken, _ := cmd.Flags().GetString("token")

		if setURL == "" || setToken == "" {
			return fmt.Errorf("--url and --token are required")
		}

		cfg, err := loadConfig()
		if err != nil {
			return err
		}

		name := profileFlag
		if name == "" {
			name = "default"
		}

		cfg.Profiles[name] = Profile{URL: setURL, Token: setToken}

		if cfg.DefaultProfile == "" {
			cfg.DefaultProfile = name
		}

		if err := saveConfig(cfg); err != nil {
			return err
		}

		output.Success(fmt.Sprintf("Profile %q saved.", name))
		return nil
	},
}

var configShowCmd = &cobra.Command{
	Use:   "show",
	Short: "Show the active configuration",
	RunE: func(cmd *cobra.Command, args []string) error {
		cfg, err := loadConfig()
		if err != nil {
			return err
		}

		showAll, _ := cmd.Flags().GetBool("all")

		if showAll {
			if jsonFlag {
				output.JSON(cfg)
				return nil
			}
			fmt.Printf("Default profile: %s\n\n", cfg.DefaultProfile)
			for name, p := range cfg.Profiles {
				marker := ""
				if name == cfg.DefaultProfile {
					marker = " (default)"
				}
				fmt.Printf("Profile: %s%s\n", name, marker)
				fmt.Printf("  URL:   %s\n", p.URL)
				fmt.Printf("  Token: %s\n\n", maskToken(p.Token))
			}
			return nil
		}

		profile, name, err := resolveProfile(cfg, profileFlag)
		if err != nil {
			return err
		}

		if jsonFlag {
			output.JSON(map[string]any{
				"profile": name,
				"url":     profile.URL,
				"token":   maskToken(profile.Token),
			})
			return nil
		}

		fmt.Printf("Profile: %s\n", name)
		fmt.Printf("URL:     %s\n", profile.URL)
		fmt.Printf("Token:   %s\n", maskToken(profile.Token))
		return nil
	},
}

var configListCmd = &cobra.Command{
	Use:   "list",
	Short: "List all profiles",
	RunE: func(cmd *cobra.Command, args []string) error {
		cfg, err := loadConfig()
		if err != nil {
			return err
		}

		if len(cfg.Profiles) == 0 {
			fmt.Println("No profiles configured. Run: tm config set --url <url> --token <token>")
			return nil
		}

		type row struct {
			Name    string `json:"name"`
			URL     string `json:"url"`
			Default bool   `json:"default"`
		}
		rows := make([]row, 0, len(cfg.Profiles))
		for name, p := range cfg.Profiles {
			rows = append(rows, row{Name: name, URL: p.URL, Default: name == cfg.DefaultProfile})
		}

		if jsonFlag {
			output.JSON(rows)
			return nil
		}

		for _, r := range rows {
			def := ""
			if r.Default {
				def = " *"
			}
			fmt.Printf("%-20s  %s%s\n", r.Name, r.URL, def)
		}
		return nil
	},
}

func maskToken(token string) string {
	if len(token) <= 8 {
		return "****"
	}
	return token[:4] + "****" + token[len(token)-4:]
}

func init() {
	configSetCmd.Flags().String("url", "", "Task Manager URL")
	configSetCmd.Flags().String("token", "", "API token")

	configShowCmd.Flags().Bool("all", false, "Show all profiles")

	configCmd.AddCommand(configSetCmd, configShowCmd, configListCmd)
	rootCmd.AddCommand(configCmd)
}
