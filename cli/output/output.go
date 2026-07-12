package output

import (
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"text/tabwriter"
)

func JSON(data any) {
	enc := json.NewEncoder(os.Stdout)
	enc.SetIndent("", "  ")
	_ = enc.Encode(data)
}

func table(headers []string, rows [][]string) {
	w := tabwriter.NewWriter(os.Stdout, 0, 0, 2, ' ', 0)
	fmt.Fprintln(w, strings.Join(headers, "\t"))
	sep := make([]string, len(headers))
	for i, h := range headers {
		sep[i] = strings.Repeat("-", len(h))
	}
	fmt.Fprintln(w, strings.Join(sep, "\t"))
	for _, row := range rows {
		fmt.Fprintln(w, strings.Join(row, "\t"))
	}
	w.Flush()
}

func str(m map[string]any, key string) string {
	if v, ok := m[key]; ok && v != nil {
		return fmt.Sprintf("%v", v)
	}
	return ""
}

func trunc(s string, n int) string {
	s = strings.ReplaceAll(s, "\n", " ")
	if len([]rune(s)) > n {
		return string([]rune(s)[:n-1]) + "…"
	}
	return s
}

func Epics(epics []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(epics)
		return
	}
	rows := make([][]string, 0, len(epics))
	for _, e := range epics {
		rows = append(rows, []string{
			str(e, "id"),
			trunc(str(e, "name"), 40),
			str(e, "status"),
			trunc(str(e, "repository_url"), 55),
		})
	}
	table([]string{"ID", "NAME", "STATUS", "REPO"}, rows)
}

func Features(features []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(features)
		return
	}
	rows := make([][]string, 0, len(features))
	for _, f := range features {
		rows = append(rows, []string{
			str(f, "id"),
			trunc(str(f, "name"), 40),
			str(f, "status"),
			str(f, "resolved_tdd"),
			str(f, "resolved_environment"),
		})
	}
	table([]string{"ID", "NAME", "STATUS", "TDD", "ENV"}, rows)
}

func Tasks(tasks []map[string]any, jsonMode bool, showDescription bool) {
	if jsonMode {
		JSON(tasks)
		return
	}
	if showDescription {
		w := tabwriter.NewWriter(os.Stdout, 0, 0, 2, ' ', 0)
		for _, t := range tasks {
			fmt.Fprintf(w, "%s\t%s\t%s\t%s\n",
				str(t, "id"),
				trunc(str(t, "title"), 50),
				str(t, "status"),
				str(t, "priority"),
			)
			if desc := str(t, "description"); desc != "" {
				fmt.Fprintf(w, "\t%s\t\t\n", trunc(desc, 100))
			}
		}
		w.Flush()
		return
	}
	rows := make([][]string, 0, len(tasks))
	for _, t := range tasks {
		rows = append(rows, []string{
			str(t, "id"),
			trunc(str(t, "title"), 50),
			str(t, "status"),
			str(t, "priority"),
		})
	}
	table([]string{"ID", "TITLE", "STATUS", "PRI"}, rows)
}

func History(entries []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(entries)
		return
	}
	rows := make([][]string, 0, len(entries))
	for _, h := range entries {
		rows = append(rows, []string{
			trunc(str(h, "created_at"), 19),
			trunc(str(h, "actor_name"), 20),
			str(h, "actor_type"),
			str(h, "action"),
			trunc(historyDetail(h), 60),
		})
	}
	table([]string{"DATE", "ACTOR", "TYPE", "ACTION", "DETAIL"}, rows)
}

func historyDetail(h map[string]any) string {
	if old, ok := h["old_values"].(map[string]any); ok {
		if newV, ok := h["new_values"].(map[string]any); ok {
			if s, ok := old["status"]; ok {
				return fmt.Sprintf("%v → %v", s, newV["status"])
			}
		}
	}
	if meta, ok := h["metadata"].(map[string]any); ok {
		if msg, ok := meta["message"].(string); ok {
			return msg
		}
	}
	return ""
}

func Item(item map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(item)
		return
	}
	for k, v := range item {
		fmt.Printf("%-24s %v\n", k+":", v)
	}
}

func Success(msg string) {
	fmt.Println(msg)
}

func Err(msg string) {
	fmt.Fprintln(os.Stderr, "Error:", msg)
}
