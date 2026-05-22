package cmd

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/spf13/cobra"
	"task-manager-cli/api"
)

var daemonAddrFlag string

var daemonCmd = &cobra.Command{
	Use:   "daemon",
	Short: "Start the Claude session daemon",
	Long: `Registers this project session with the task manager server and
listens for push messages. When a message arrives it is injected into
the active Claude Code pane in tmux, or a new Claude window is opened.`,
	RunE: runDaemon,
}

func init() {
	daemonCmd.Flags().StringVar(&daemonAddrFlag, "addr", "", "listen address host:port (default: <tailscale-ip>:7373)")
	rootCmd.AddCommand(daemonCmd)
}

func runDaemon(_ *cobra.Command, _ []string) error {
	pf, pfPath, err := findProjectFileWithPath()
	if err != nil {
		return fmt.Errorf("finding .task-manager: %w", err)
	}
	if pf == nil {
		return fmt.Errorf("no .task-manager file found in directory tree")
	}
	projectPath := filepath.Dir(pfPath)

	if pf.URL == "" || pf.Token == "" {
		return fmt.Errorf("missing URL or TOKEN in .task-manager")
	}
	client := api.New(pf.URL, pf.Token)

	addr := daemonAddrFlag
	if addr == "" {
		ip, err := tailscaleIP()
		if err != nil {
			return fmt.Errorf("getting tailscale IP (use --addr to override): %w", err)
		}
		addr = ip + ":7373"
	}
	daemonURL := "http://" + addr

	sessionID, err := client.RegisterSession(api.SessionInput{
		DaemonURL:   daemonURL,
		ProjectPath: projectPath,
		EpicID:      pf.EpicID,
		FeatureID:   pf.ActiveFeatureID,
		TaskID:      pf.ActiveTaskID,
	})
	if err != nil {
		return fmt.Errorf("registering session: %w", err)
	}
	log.Printf("Session registered: %s", sessionID)

	_ = updateFileKey(pfPath, "SESSION_ID", sessionID)
	_ = updateFileKey(pfPath, "DAEMON_URL", daemonURL)

	ctx, cancel := context.WithCancel(context.Background())

	cleanup := func() {
		cancel()
		if derr := client.DeregisterSession(sessionID); derr != nil {
			log.Printf("Warning: deregister failed: %v", derr)
		}
		_ = updateFileKey(pfPath, "SESSION_ID", "")
		_ = updateFileKey(pfPath, "DAEMON_URL", "")
		log.Println("Session deregistered.")
	}

	go func() {
		ticker := time.NewTicker(5 * time.Minute)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				if herr := client.HeartbeatSession(sessionID); herr != nil {
					log.Printf("Heartbeat failed: %v", herr)
				}
			}
		}
	}()

	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = fmt.Fprint(w, `{"ok":true}`)
	})
	mux.HandleFunc("POST /push", func(w http.ResponseWriter, r *http.Request) {
		handlePush(w, r, projectPath)
	})

	server := &http.Server{Addr: addr, Handler: mux}

	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
	go func() {
		<-sigCh
		cleanup()
		_ = server.Shutdown(context.Background())
	}()

	log.Printf("Daemon listening on %s  project: %s", addr, projectPath)
	if serr := server.ListenAndServe(); serr != nil && serr != http.ErrServerClosed {
		return serr
	}
	return nil
}

type pushPayload struct {
	Message     string `json:"message"`
	ProjectPath string `json:"project_path"`
}

func handlePush(w http.ResponseWriter, r *http.Request, defaultProject string) {
	body, err := io.ReadAll(io.LimitReader(r.Body, 1<<20))
	if err != nil {
		http.Error(w, "read error", http.StatusBadRequest)
		return
	}
	var p pushPayload
	if err := json.Unmarshal(body, &p); err != nil {
		http.Error(w, "invalid json", http.StatusBadRequest)
		return
	}
	if p.ProjectPath == "" {
		p.ProjectPath = defaultProject
	}

	paneID, _ := findClaudePaneInProject(p.ProjectPath)
	if paneID == "" {
		log.Printf("No Claude pane found for %s — launching new window", p.ProjectPath)
		if lerr := launchClaudeInProject(p.ProjectPath); lerr != nil {
			log.Printf("Failed to launch Claude: %v", lerr)
			http.Error(w, "no claude pane and launch failed", http.StatusServiceUnavailable)
			return
		}
		time.Sleep(3 * time.Second)
		paneID, _ = findClaudePaneInProject(p.ProjectPath)
		if paneID == "" {
			http.Error(w, "claude did not start in time", http.StatusServiceUnavailable)
			return
		}
	}

	if ierr := injectMessage(paneID, p.Message); ierr != nil {
		log.Printf("Failed to inject into pane %s: %v", paneID, ierr)
		http.Error(w, "inject failed", http.StatusInternalServerError)
		return
	}

	log.Printf("Injected message into pane %s", paneID)
	w.Header().Set("Content-Type", "application/json")
	_, _ = fmt.Fprint(w, `{"ok":true}`)
}

func findClaudePaneInProject(projectPath string) (string, error) {
	out, err := exec.Command(
		"tmux", "list-panes", "-a",
		"-F", "#{pane_id}|||#{pane_current_path}|||#{pane_current_command}",
	).Output()
	if err != nil {
		return "", fmt.Errorf("tmux list-panes: %w", err)
	}
	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		parts := strings.Split(line, "|||")
		if len(parts) != 3 {
			continue
		}
		paneID, panePath, paneCmd := parts[0], parts[1], parts[2]
		inProject := panePath == projectPath ||
			strings.HasPrefix(panePath, projectPath+string(os.PathSeparator))
		if inProject && strings.Contains(paneCmd, "claude") {
			return paneID, nil
		}
	}
	return "", nil
}

func launchClaudeInProject(projectPath string) error {
	return exec.Command("tmux", "new-window", "-c", projectPath, "claude").Run()
}

func injectMessage(paneID, message string) error {
	if err := exec.Command("tmux", "send-keys", "-t", paneID, "-l", message).Run(); err != nil {
		return err
	}
	return exec.Command("tmux", "send-keys", "-t", paneID, "Enter").Run()
}

func tailscaleIP() (string, error) {
	out, err := exec.Command("tailscale", "ip", "-4").Output()
	if err != nil {
		return "", fmt.Errorf("tailscale ip: %w", err)
	}
	ip := strings.TrimSpace(string(out))
	if ip == "" {
		return "", fmt.Errorf("tailscale returned empty IP")
	}
	return ip, nil
}
