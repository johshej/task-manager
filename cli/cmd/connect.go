package cmd

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/spf13/cobra"
)

var connectCmd = &cobra.Command{
	Use:   "connect",
	Short: "Connect this project to an epic, creating one if none matches the local git repo",
	Example: `  tm connect
  tm connect --repo git@github.com:user/repo.git
  tm connect --name "My Project"
  tm connect --epic-id 019db598-cc26-733d-a328-e7bd39b52707`,
	PersistentPreRunE: requireClient,
	RunE:              runConnect,
}

func init() {
	connectCmd.Flags().String("repo", "", "Repository URL to match/create against (default: detected from git remote origin)")
	connectCmd.Flags().String("name", "", "Epic name to use when creating a new epic (default: current directory name)")
	connectCmd.Flags().String("epic-id", "", "Connect directly to this epic ID, skipping repo lookup")
	rootCmd.AddCommand(connectCmd)
}

func runConnect(cmd *cobra.Command, args []string) error {
	epicIDFlag, _ := cmd.Flags().GetString("epic-id")

	var epic map[string]any
	var err error

	if epicIDFlag != "" {
		epic, err = apiClient.GetEpic(epicIDFlag)
		if err != nil {
			return fmt.Errorf("epic %s not found: %w", epicIDFlag, err)
		}
	} else {
		repo, _ := cmd.Flags().GetString("repo")
		if repo == "" {
			repo, err = detectGitRemoteURL()
			if err != nil {
				return fmt.Errorf("could not detect git remote URL: %w (pass --repo explicitly)", err)
			}
		}

		matches, err := apiClient.ListEpics(repo)
		if err != nil {
			return err
		}

		switch len(matches) {
		case 0:
			name, _ := cmd.Flags().GetString("name")
			if name == "" {
				name, err = defaultEpicName()
				if err != nil {
					return err
				}
			}
			epic, err = apiClient.CreateEpic(name, repo)
			if err != nil {
				return err
			}
			fmt.Printf("Created new epic %q for %s\n", strField(epic, "name"), repo)
		case 1:
			epic = matches[0]
		default:
			fmt.Fprintf(os.Stderr, "Multiple epics match repository %s:\n", repo)
			for _, m := range matches {
				fmt.Fprintf(os.Stderr, "  %s  %s\n", strField(m, "id"), strField(m, "name"))
			}
			return fmt.Errorf("ambiguous match — pass --epic-id to choose one")
		}
	}

	epicID := strField(epic, "id")

	path, err := ensureProjectFile()
	if err != nil {
		return err
	}
	if err := updateFileKey(path, "EPIC_ID", epicID); err != nil {
		return err
	}

	fmt.Printf("Connected to epic %q (%s)\n", strField(epic, "name"), epicID)
	return nil
}

func detectGitRemoteURL() (string, error) {
	out, err := exec.Command("git", "config", "--get", "remote.origin.url").Output()
	if err != nil {
		return "", err
	}
	return strings.TrimSpace(string(out)), nil
}

func defaultEpicName() (string, error) {
	cwd, err := os.Getwd()
	if err != nil {
		return "", err
	}
	return filepath.Base(cwd), nil
}

// ensureProjectFile returns the path to the nearest .task-manager file,
// creating one in the current directory (with resolved URL/TOKEN) if none exists.
func ensureProjectFile() (string, error) {
	_, path, err := findProjectFileWithPath()
	if err != nil {
		return "", err
	}
	if path != "" {
		return path, nil
	}

	cwd, err := os.Getwd()
	if err != nil {
		return "", err
	}
	path = filepath.Join(cwd, ".task-manager")
	content := fmt.Sprintf("URL=%s\nTOKEN=%s\n", resolvedURL, resolvedToken)
	if err := os.WriteFile(path, []byte(content), 0o600); err != nil {
		return "", err
	}
	return path, nil
}
