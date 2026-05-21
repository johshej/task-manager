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
	URL    string
	Token  string
	EpicID string
}

// findProjectFile walks up from cwd looking for .task-manager and parses it.
func findProjectFile() (*ProjectFile, error) {
	dir, err := os.Getwd()
	if err != nil {
		return nil, err
	}
	for {
		data, err := os.ReadFile(filepath.Join(dir, ".task-manager"))
		if err == nil {
			return parseProjectFile(data)
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			break
		}
		dir = parent
	}
	return nil, nil
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
		}
	}
	return pf, nil
}
