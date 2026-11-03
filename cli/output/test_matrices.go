package output

import (
	"fmt"
	"strings"
)

func TestMatrices(matrices []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(matrices)
		return
	}
	rows := make([][]string, 0, len(matrices))
	for _, m := range matrices {
		rows = append(rows, []string{str(m, "id"), trunc(str(m, "name"), 40), summaryText(m)})
	}
	table([]string{"ID", "NAME", "RELEVANT CELLS"}, rows)
}

func summaryText(matrix map[string]any) string {
	summary, ok := matrix["summary"].(map[string]any)
	if !ok {
		return ""
	}
	return fmt.Sprintf("%s passed, %s failed, %s untested", str(summary, "passed"), str(summary, "failed"), str(summary, "untested"))
}

// TestMatrix prints the grid: scenarios as rows and context codes as columns. A cell shows
// "." untested, "ok" passed, "FAIL" failed and "-" when the scenario is not relevant there.
func TestMatrix(matrix map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(matrix)
		return
	}
	fmt.Printf("%s (%s)\n%s\n\n", str(matrix, "name"), str(matrix, "id"), summaryText(matrix))

	contexts, _ := matrix["contexts"].([]any)
	scenarios, _ := matrix["scenarios"].([]any)
	results, _ := matrix["results"].([]any)

	cells := map[string]string{}
	for _, r := range results {
		result, ok := r.(map[string]any)
		if !ok {
			continue
		}
		cells[str(result, "test_scenario_id")+"/"+str(result, "test_context_id")] = cellText(result)
	}

	headers := []string{"SCENARIO"}
	for _, c := range contexts {
		if context, ok := c.(map[string]any); ok {
			headers = append(headers, str(context, "code"))
		}
	}
	rows := make([][]string, 0, len(scenarios))
	for _, s := range scenarios {
		scenario, ok := s.(map[string]any)
		if !ok {
			continue
		}
		row := []string{trunc(str(scenario, "title"), 50)}
		for _, c := range contexts {
			if context, ok := c.(map[string]any); ok {
				row = append(row, cells[str(scenario, "id")+"/"+str(context, "id")])
			}
		}
		rows = append(rows, row)
	}
	table(headers, rows)

	fmt.Println()
	for _, c := range contexts {
		if context, ok := c.(map[string]any); ok {
			fmt.Printf("%-6s %s (%s)\n", str(context, "code"), str(context, "name"), str(context, "id"))
		}
	}
}

func cellText(result map[string]any) string {
	if relevant, _ := result["relevant"].(bool); !relevant {
		return "-"
	}
	switch str(result, "status") {
	case "passed":
		return "ok"
	case "failed":
		return "FAIL"
	default:
		return "."
	}
}

func TestComments(comments []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(comments)
		return
	}
	rows := make([][]string, 0, len(comments))
	for _, c := range comments {
		contextCount := 0
		if ids, ok := c["test_context_ids"].([]any); ok {
			contextCount = len(ids)
		}
		rows = append(rows, []string{str(c, "id"), trunc(str(c, "title"), 50), str(c, "status"), fmt.Sprintf("%d", contextCount), str(c, "test_scenario_id")})
	}
	table([]string{"ID", "TITLE", "STATUS", "CONTEXTS", "SCENARIO"}, rows)
}

func AutomatedTests(tests []map[string]any, jsonMode bool) {
	if jsonMode {
		JSON(tests)
		return
	}
	rows := make([][]string, 0, len(tests))
	for _, t := range tests {
		rows = append(rows, []string{str(t, "id"), trunc(str(t, "name"), 50), str(t, "kind"), trunc(strings.TrimSpace(str(t, "file_path")), 60)})
	}
	table([]string{"ID", "NAME", "KIND", "FILE"}, rows)
}
