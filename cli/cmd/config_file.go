package cmd

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"gopkg.in/yaml.v3"
)

type Profile struct {
	URL   string `yaml:"url"`
	Token string `yaml:"token"`
}

type Config struct {
	DefaultProfile string             `yaml:"default_profile"`
	Profiles       map[string]Profile `yaml:"profiles"`
}

func configPath() string {
	home, _ := os.UserHomeDir()
	return filepath.Join(home, ".config", "tm", "config.yaml")
}

func loadConfig() (*Config, error) {
	path := configPath()
	data, err := os.ReadFile(path)
	if os.IsNotExist(err) {
		return &Config{Profiles: map[string]Profile{}}, nil
	}
	if err != nil {
		return nil, err
	}
	var cfg Config
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, err
	}
	if cfg.Profiles == nil {
		cfg.Profiles = map[string]Profile{}
	}
	return &cfg, nil
}

func saveConfig(cfg *Config) error {
	path := configPath()
	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return err
	}
	data, err := yaml.Marshal(cfg)
	if err != nil {
		return err
	}
	return os.WriteFile(path, data, 0o600)
}

func resolveProfile(cfg *Config, profileFlag string) (Profile, string, error) {
	name := profileFlag
	if name == "" {
		name = os.Getenv("TM_PROFILE")
	}
	if name == "" {
		name = cfg.DefaultProfile
	}
	if name == "" {
		name = "default"
	}

	// Env vars override everything
	envURL := os.Getenv("TM_URL")
	envToken := os.Getenv("TM_TOKEN")
	if envURL != "" && envToken != "" {
		return Profile{URL: envURL, Token: envToken}, name, nil
	}

	p, ok := cfg.Profiles[name]
	if !ok {
		return Profile{}, name, fmt.Errorf("profile %q not found. Run: tm config set --profile %s --url <url> --token <token>", name, name)
	}

	if envURL != "" {
		p.URL = envURL
	}
	if envToken != "" {
		p.Token = envToken
	}

	if p.URL == "" || p.Token == "" {
		return Profile{}, name, fmt.Errorf("profile %q is missing url or token. Run: tm config set --profile %s --url <url> --token <token>", name, name)
	}

	return p, name, nil
}

// ProjectFile holds values from a .task-manager file in the project directory.
type ProjectFile struct {
	URL             string
	Token           string
	EpicID          string
	ActiveTaskID    string
	ActiveFeatureID string
	DaemonURL       string
	SessionID       string
}

// findProjectFile walks up from cwd looking for .task-manager and parses it.
func findProjectFile() (*ProjectFile, error) {
	pf, _, err := findProjectFileWithPath()
	return pf, err
}

func findProjectFileWithPath() (*ProjectFile, string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return nil, "", err
	}
	for {
		path := filepath.Join(dir, ".task-manager")
		data, err := os.ReadFile(path)
		if err == nil {
			pf, err := parseProjectFile(data)
			return pf, path, err
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			break
		}
		dir = parent
	}
	return nil, "", nil
}

func parseProjectFile(data []byte) (*ProjectFile, error) {
	pf := &ProjectFile{}
	for _, line := range strings.Split(string(data), "\n") {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		parts := strings.SplitN(line, "=", 2)
		if len(parts) != 2 {
			continue
		}
		switch strings.TrimSpace(parts[0]) {
		case "URL":
			pf.URL = strings.TrimSpace(parts[1])
		case "TOKEN":
			pf.Token = strings.TrimSpace(parts[1])
		case "EPIC_ID":
			pf.EpicID = strings.TrimSpace(parts[1])
		case "ACTIVE_TASK_ID":
			pf.ActiveTaskID = strings.TrimSpace(parts[1])
		case "ACTIVE_FEATURE_ID":
			pf.ActiveFeatureID = strings.TrimSpace(parts[1])
		case "DAEMON_URL":
			pf.DaemonURL = strings.TrimSpace(parts[1])
		case "SESSION_ID":
			pf.SessionID = strings.TrimSpace(parts[1])
		}
	}
	return pf, nil
}

// writeProjectFileKey sets or removes a key in the nearest .task-manager file.
// Pass an empty value to delete the key.
func writeProjectFileKey(key, value string) error {
	_, path, err := findProjectFileWithPath()
	if err != nil {
		return err
	}
	if path == "" {
		return fmt.Errorf("no .task-manager file found in directory tree")
	}
	return updateFileKey(path, key, value)
}

func updateFileKey(path, key, value string) error {
	data, _ := os.ReadFile(path)
	lines := strings.Split(strings.TrimRight(string(data), "\n"), "\n")

	found := false
	result := make([]string, 0, len(lines)+1)
	for _, line := range lines {
		if strings.HasPrefix(line, key+"=") {
			if value != "" {
				result = append(result, key+"="+value)
				found = true
			}
			// empty value = delete (skip line)
		} else if line != "" {
			result = append(result, line)
		}
	}
	if !found && value != "" {
		result = append(result, key+"="+value)
	}
	return os.WriteFile(path, []byte(strings.Join(result, "\n")+"\n"), 0o600)
}
