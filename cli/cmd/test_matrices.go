package cmd

import (
	"fmt"
	"strings"

	"github.com/spf13/cobra"
	"task-manager-cli/output"
)

// ── Test matrices: scenarios as rows, contexts as columns, a result in every cell ─────────────

var testMatricesCmd = &cobra.Command{
	Use:               "test-matrices",
	Short:             "Manage test matrices, their contexts, scenarios, results, comments and automated tests",
	PersistentPreRunE: requireClient,
}

// show prints a created or updated item, or just a confirmation.
func show(item map[string]any, message string) {
	if jsonFlag {
		output.JSON(item)
		return
	}
	fmt.Println(message)
}

func requireFields(fields map[string]any, names ...string) error {
	for _, name := range names {
		if _, ok := fields[name]; !ok {
			return fmt.Errorf("--%s is required", strings.ReplaceAll(name, "_", "-"))
		}
	}
	return nil
}

var testMatricesListCmd = &cobra.Command{
	Use:   "list [epic-id]",
	Short: "List the test matrices of an epic, or of the project epic",
	Args:  cobra.RangeArgs(0, 1),
	RunE: func(cmd *cobra.Command, args []string) error {
		epicID := projectEpicID
		if len(args) == 1 {
			epicID = args[0]
		}
		if epicID == "" {
			return fmt.Errorf("no epic-id given and no .task-manager file found in directory tree")
		}
		matrices, err := apiClient.ListTestMatrices(epicID)
		if err != nil {
			return err
		}
		output.TestMatrices(matrices, jsonFlag)
		return nil
	},
}

var testMatricesGetCmd = &cobra.Command{
	Use:   "get <matrix-id>",
	Short: "Show a test matrix as a grid (context codes as columns), with the contexts listed below",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		matrix, err := apiClient.GetTestMatrix(args[0])
		if err != nil {
			return err
		}
		output.TestMatrix(matrix, jsonFlag)
		return nil
	},
}

var testMatricesCreateCmd = &cobra.Command{
	Use:     "create",
	Short:   "Create a test matrix in the project epic (or --epic-id)",
	Example: `  tm test-matrices create --name "Roles and devices"`,
	Args:    cobra.NoArgs,
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "description")
		epicID, _ := cmd.Flags().GetString("epic-id")
		if epicID == "" {
			epicID = projectEpicID
		}
		if epicID == "" {
			return fmt.Errorf("no --epic-id given and no .task-manager file found in directory tree")
		}
		fields["epic_id"] = epicID
		if err := requireFields(fields, "name"); err != nil {
			return err
		}
		matrix, err := apiClient.CreateTestMatrix(fields)
		if err != nil {
			return err
		}
		show(matrix, "Test matrix created: "+strField(matrix, "id"))
		return nil
	},
}

var testMatricesUpdateCmd = &cobra.Command{
	Use:   "update <matrix-id>",
	Short: "Update a test matrix",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "description", "feature_id")
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		matrix, err := apiClient.UpdateTestMatrix(args[0], fields)
		if err != nil {
			return err
		}
		show(matrix, "Test matrix updated.")
		return nil
	},
}

var testMatricesDeleteCmd = &cobra.Command{
	Use:   "delete <matrix-id>",
	Short: "Delete a test matrix with its contexts, scenarios and results",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		if err := apiClient.DeleteTestMatrix(args[0]); err != nil {
			return err
		}
		fmt.Println("Test matrix deleted.")
		return nil
	},
}

// ── Contexts (columns) ────────────────────────────────────────────────────────

var testContextsCmd = &cobra.Command{Use: "contexts", Short: "Manage the contexts (columns) of a test matrix"}

var testContextsCreateCmd = &cobra.Command{
	Use:   "create <matrix-id>",
	Short: "Add a context: one named combination such as \"Log in / Desktop / Offline\"",
	Example: `  tm test-matrices contexts create abc123 --code LD --name "Log in / Desktop"
  tm test-matrices contexts create abc123 --code LDO --name "Log in / Desktop / Offline" --scenarios id1,id2`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "code", "name", "description")
		if err := requireFields(fields, "code", "name"); err != nil {
			return err
		}
		fields["test_matrix_id"] = args[0]
		if ids, _ := cmd.Flags().GetStringSlice("scenarios"); cmd.Flags().Changed("scenarios") {
			fields["test_scenario_ids"] = ids
		}
		context, err := apiClient.CreateTestContext(fields)
		if err != nil {
			return err
		}
		show(context, "Context created: "+strField(context, "id"))
		return nil
	},
}

var testContextsUpdateCmd = &cobra.Command{
	Use:   "update <context-id>",
	Short: "Update a context",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "code", "name", "description")
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		context, err := apiClient.UpdateTestContext(args[0], fields)
		if err != nil {
			return err
		}
		show(context, "Context updated.")
		return nil
	},
}

var testContextsDeleteCmd = &cobra.Command{
	Use:   "delete <context-id>",
	Short: "Delete a context with its cells",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		if err := apiClient.DeleteTestContext(args[0]); err != nil {
			return err
		}
		fmt.Println("Context deleted.")
		return nil
	},
}

// ── Scenarios (rows) ──────────────────────────────────────────────────────────

var testScenariosCmd = &cobra.Command{Use: "scenarios", Short: "Manage the scenarios (rows) of a test matrix"}

var testScenariosCreateCmd = &cobra.Command{
	Use:   "create <matrix-id>",
	Short: "Add a scenario",
	Example: `  tm test-matrices scenarios create abc123 --title "Log in"
  tm test-matrices scenarios create abc123 --title "Log in" --contexts id1,id2`,
	Args: cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "title", "description")
		if err := requireFields(fields, "title"); err != nil {
			return err
		}
		fields["test_matrix_id"] = args[0]
		if ids, _ := cmd.Flags().GetStringSlice("contexts"); cmd.Flags().Changed("contexts") {
			fields["test_context_ids"] = ids
		}
		scenario, err := apiClient.CreateTestScenario(fields)
		if err != nil {
			return err
		}
		show(scenario, "Scenario created: "+strField(scenario, "id"))
		return nil
	},
}

var testScenariosUpdateCmd = &cobra.Command{
	Use:   "update <scenario-id>",
	Short: "Update a scenario",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "title", "description")
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		scenario, err := apiClient.UpdateTestScenario(args[0], fields)
		if err != nil {
			return err
		}
		show(scenario, "Scenario updated.")
		return nil
	},
}

var testScenariosRelevanceCmd = &cobra.Command{
	Use:     "relevance <scenario-id> <context-id>...",
	Short:   "Make a scenario relevant in exactly the listed contexts, and not relevant in the others",
	Example: "  tm test-matrices scenarios relevance abc123 ctx1 ctx2",
	Args:    cobra.MinimumNArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		scenario, err := apiClient.SetTestScenarioRelevance(args[0], args[1:])
		if err != nil {
			return err
		}
		show(scenario, "Relevance updated.")
		return nil
	},
}

var testScenariosDeleteCmd = &cobra.Command{
	Use:   "delete <scenario-id>",
	Short: "Delete a scenario with its cells and automated tests",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		if err := apiClient.DeleteTestScenario(args[0]); err != nil {
			return err
		}
		fmt.Println("Scenario deleted.")
		return nil
	},
}

// ── Results (cells) ───────────────────────────────────────────────────────────

var testResultsCmd = &cobra.Command{Use: "results", Short: "Mark the cells of a test matrix"}

var testResultsSetCmd = &cobra.Command{
	Use:   "set <matrix-id> <scenario-id> <context-id>",
	Short: "Set a cell: status, whether it is relevant, and what is expected there",
	Example: `  tm test-matrices results set abc123 scenario1 context1 --status passed
  tm test-matrices results set abc123 scenario1 context2 --status failed
  tm test-matrices results set abc123 scenario1 context3 --relevant=false --expectation Refused`,
	Args: cobra.ExactArgs(3),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "status", "expectation")
		if cmd.Flags().Changed("relevant") {
			relevant, _ := cmd.Flags().GetBool("relevant")
			fields["relevant"] = relevant
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one of --status, --relevant and --expectation is required")
		}
		results, err := apiClient.ListTestResults(args[0])
		if err != nil {
			return err
		}
		for _, result := range results {
			if strField(result, "test_scenario_id") == args[1] && strField(result, "test_context_id") == args[2] {
				updated, err := apiClient.UpdateTestResult(strField(result, "id"), fields)
				if err != nil {
					return err
				}
				show(updated, "Cell updated.")
				return nil
			}
		}
		return fmt.Errorf("no cell for that scenario and context in the matrix")
	},
}

// ── Comments (tasks given to one or more contexts) ────────────────────────────

var testCommentsCmd = &cobra.Command{Use: "comments", Short: "Comments on scenarios: tasks given to one or more contexts"}

var testCommentsListCmd = &cobra.Command{
	Use:   "list <matrix-id>",
	Short: "List the comments of a test matrix",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		comments, err := apiClient.ListTestComments(args[0])
		if err != nil {
			return err
		}
		output.TestComments(comments, jsonFlag)
		return nil
	},
}

var testCommentsCreateCmd = &cobra.Command{
	Use:     "create <scenario-id>",
	Short:   "Comment on a scenario for one or more contexts; this makes a task",
	Example: `  tm test-matrices comments create scenario1 --contexts ctx1,ctx2 --title "Log in fails"`,
	Args:    cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "title", "description", "status")
		if err := requireFields(fields, "title"); err != nil {
			return err
		}
		ids, _ := cmd.Flags().GetStringSlice("contexts")
		if len(ids) == 0 {
			return fmt.Errorf("--contexts is required: at least one context")
		}
		fields["test_scenario_id"] = args[0]
		fields["test_context_ids"] = ids
		comment, err := apiClient.CreateTestComment(fields)
		if err != nil {
			return err
		}
		show(comment, "Comment created: "+strField(comment, "id"))
		return nil
	},
}

var testCommentsUpdateCmd = &cobra.Command{
	Use:   "update <comment-id>",
	Short: "Update a comment: title, description, status, or the contexts it is given to",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "title", "description", "status")
		if ids, _ := cmd.Flags().GetStringSlice("contexts"); cmd.Flags().Changed("contexts") {
			fields["test_context_ids"] = ids
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		comment, err := apiClient.UpdateTestComment(args[0], fields)
		if err != nil {
			return err
		}
		show(comment, "Comment updated.")
		return nil
	},
}

var testCommentsDeleteCmd = &cobra.Command{
	Use:   "delete <comment-id>",
	Short: "Delete a comment together with its task",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		if err := apiClient.DeleteTestComment(args[0]); err != nil {
			return err
		}
		fmt.Println("Comment deleted.")
		return nil
	},
}

// ── Automated tests that cover a scenario ─────────────────────────────────────

var automatedTestsCmd = &cobra.Command{Use: "automated-tests", Short: "The automated tests that cover a scenario"}

var automatedTestsListCmd = &cobra.Command{
	Use:   "list <scenario-id>",
	Short: "List the automated tests of a scenario",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		tests, err := apiClient.ListAutomatedTests(args[0])
		if err != nil {
			return err
		}
		output.AutomatedTests(tests, jsonFlag)
		return nil
	},
}

var automatedTestsCreateCmd = &cobra.Command{
	Use:     "create <scenario-id>",
	Short:   "List an automated test as covering a scenario",
	Example: `  tm test-matrices automated-tests create scenario1 --name "logs in with e-mail" --file tests/Browser/LoginTest.php --kind browser`,
	Args:    cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "kind", "description")
		if file, _ := cmd.Flags().GetString("file"); cmd.Flags().Changed("file") {
			fields["file_path"] = file
		}
		if err := requireFields(fields, "name"); err != nil {
			return err
		}
		fields["test_scenario_id"] = args[0]
		test, err := apiClient.CreateAutomatedTest(fields)
		if err != nil {
			return err
		}
		show(test, "Automated test added: "+strField(test, "id"))
		return nil
	},
}

var automatedTestsUpdateCmd = &cobra.Command{
	Use:   "update <automated-test-id>",
	Short: "Update an automated test",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		fields := collectFlags(cmd, "name", "kind", "description")
		if file, _ := cmd.Flags().GetString("file"); cmd.Flags().Changed("file") {
			fields["file_path"] = file
		}
		if len(fields) == 0 {
			return fmt.Errorf("at least one field flag is required")
		}
		test, err := apiClient.UpdateAutomatedTest(args[0], fields)
		if err != nil {
			return err
		}
		show(test, "Automated test updated.")
		return nil
	},
}

var automatedTestsDeleteCmd = &cobra.Command{
	Use:   "delete <automated-test-id>",
	Short: "Remove an automated test from a scenario",
	Args:  cobra.ExactArgs(1),
	RunE: func(cmd *cobra.Command, args []string) error {
		if err := apiClient.DeleteAutomatedTest(args[0]); err != nil {
			return err
		}
		fmt.Println("Automated test removed.")
		return nil
	},
}

func init() {
	testMatricesCreateCmd.Flags().String("name", "", "Matrix name")
	testMatricesCreateCmd.Flags().String("description", "", "Matrix description")
	testMatricesCreateCmd.Flags().String("epic-id", "", "Epic (default: the project epic)")
	testMatricesUpdateCmd.Flags().String("name", "", "Matrix name")
	testMatricesUpdateCmd.Flags().String("description", "", "Matrix description")
	testMatricesUpdateCmd.Flags().String("feature_id", "", "Feature that collects the tasks made from comments")

	testContextsCreateCmd.Flags().String("code", "", "Short code shown as the column header (unique in the matrix)")
	testContextsCreateCmd.Flags().String("name", "", "Full name, such as \"Log in / Desktop / Offline\"")
	testContextsCreateCmd.Flags().String("description", "", "Description")
	testContextsCreateCmd.Flags().StringSlice("scenarios", nil, "Only these scenarios (ids) are relevant in the context (default: all)")
	testContextsUpdateCmd.Flags().String("code", "", "Short code")
	testContextsUpdateCmd.Flags().String("name", "", "Full name")
	testContextsUpdateCmd.Flags().String("description", "", "Description")

	testScenariosCreateCmd.Flags().String("title", "", "Scenario title")
	testScenariosCreateCmd.Flags().String("description", "", "Description")
	testScenariosCreateCmd.Flags().StringSlice("contexts", nil, "Only these contexts (ids) are relevant for the scenario (default: all)")
	testScenariosUpdateCmd.Flags().String("title", "", "Scenario title")
	testScenariosUpdateCmd.Flags().String("description", "", "Description")

	testResultsSetCmd.Flags().String("status", "", "untested|passed|failed")
	testResultsSetCmd.Flags().Bool("relevant", true, "Whether the scenario applies in the context")
	testResultsSetCmd.Flags().String("expectation", "", "What is expected there when not the plain \"works\", such as Refused")

	testCommentsCreateCmd.Flags().String("title", "", "Comment (task) title")
	testCommentsCreateCmd.Flags().String("description", "", "Description")
	testCommentsCreateCmd.Flags().String("status", "", "Task status (default: todo)")
	testCommentsCreateCmd.Flags().StringSlice("contexts", nil, "The contexts (ids) the comment is given to")
	testCommentsUpdateCmd.Flags().String("title", "", "Comment (task) title")
	testCommentsUpdateCmd.Flags().String("description", "", "Description")
	testCommentsUpdateCmd.Flags().String("status", "", "Task status")
	testCommentsUpdateCmd.Flags().StringSlice("contexts", nil, "The contexts (ids) the comment is given to")

	automatedTestsCreateCmd.Flags().String("name", "", "Test name")
	automatedTestsCreateCmd.Flags().String("file", "", "Where the test is, such as a file path")
	automatedTestsCreateCmd.Flags().String("kind", "", "feature, browser, unit …")
	automatedTestsCreateCmd.Flags().String("description", "", "Description")
	automatedTestsUpdateCmd.Flags().String("name", "", "Test name")
	automatedTestsUpdateCmd.Flags().String("file", "", "Where the test is, such as a file path")
	automatedTestsUpdateCmd.Flags().String("kind", "", "feature, browser, unit …")
	automatedTestsUpdateCmd.Flags().String("description", "", "Description")

	testContextsCmd.AddCommand(testContextsCreateCmd, testContextsUpdateCmd, testContextsDeleteCmd)
	testScenariosCmd.AddCommand(testScenariosCreateCmd, testScenariosUpdateCmd, testScenariosRelevanceCmd, testScenariosDeleteCmd)
	testResultsCmd.AddCommand(testResultsSetCmd)
	testCommentsCmd.AddCommand(testCommentsListCmd, testCommentsCreateCmd, testCommentsUpdateCmd, testCommentsDeleteCmd)
	automatedTestsCmd.AddCommand(automatedTestsListCmd, automatedTestsCreateCmd, automatedTestsUpdateCmd, automatedTestsDeleteCmd)

	testMatricesCmd.AddCommand(testMatricesListCmd, testMatricesGetCmd, testMatricesCreateCmd, testMatricesUpdateCmd, testMatricesDeleteCmd,
		testContextsCmd, testScenariosCmd, testResultsCmd, testCommentsCmd, automatedTestsCmd)
	rootCmd.AddCommand(testMatricesCmd)
}
