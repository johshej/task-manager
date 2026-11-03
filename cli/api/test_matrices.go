package api

// ── Test matrices ─────────────────────────────────────────────────────────────
// A test matrix has scenarios as rows and contexts as columns, with a result in every cell.

func (c *Client) ListTestMatrices(epicID string) ([]map[string]any, error) {
	data, err := c.do("GET", "epics/"+epicID+"/test-matrices", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) GetTestMatrix(id string) (map[string]any, error) {
	return c.object("GET", "test-matrices/"+id, nil)
}

func (c *Client) CreateTestMatrix(fields map[string]any) (map[string]any, error) {
	return c.object("POST", "test-matrices", fields)
}

func (c *Client) UpdateTestMatrix(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PUT", "test-matrices/"+id, fields)
}

func (c *Client) DeleteTestMatrix(id string) error {
	_, err := c.do("DELETE", "test-matrices/"+id, nil, nil)
	return err
}

func (c *Client) CreateTestContext(fields map[string]any) (map[string]any, error) {
	return c.object("POST", "test-contexts", fields)
}

func (c *Client) UpdateTestContext(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PUT", "test-contexts/"+id, fields)
}

func (c *Client) DeleteTestContext(id string) error {
	_, err := c.do("DELETE", "test-contexts/"+id, nil, nil)
	return err
}

func (c *Client) CreateTestScenario(fields map[string]any) (map[string]any, error) {
	return c.object("POST", "test-scenarios", fields)
}

func (c *Client) UpdateTestScenario(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PUT", "test-scenarios/"+id, fields)
}

// SetTestScenarioRelevance makes the scenario relevant in exactly the given contexts.
func (c *Client) SetTestScenarioRelevance(id string, contextIDs []string) (map[string]any, error) {
	return c.object("PUT", "test-scenarios/"+id+"/relevance", map[string]any{"test_context_ids": contextIDs})
}

func (c *Client) DeleteTestScenario(id string) error {
	_, err := c.do("DELETE", "test-scenarios/"+id, nil, nil)
	return err
}

func (c *Client) ListTestResults(matrixID string) ([]map[string]any, error) {
	data, err := c.do("GET", "test-matrices/"+matrixID+"/results", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) UpdateTestResult(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PATCH", "test-results/"+id, fields)
}

func (c *Client) ListTestComments(matrixID string) ([]map[string]any, error) {
	data, err := c.do("GET", "test-matrices/"+matrixID+"/comments", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) CreateTestComment(fields map[string]any) (map[string]any, error) {
	return c.object("POST", "test-comments", fields)
}

func (c *Client) UpdateTestComment(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PUT", "test-comments/"+id, fields)
}

func (c *Client) DeleteTestComment(id string) error {
	_, err := c.do("DELETE", "test-comments/"+id, nil, nil)
	return err
}

func (c *Client) ListAutomatedTests(scenarioID string) ([]map[string]any, error) {
	data, err := c.do("GET", "test-scenarios/"+scenarioID+"/automated-tests", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) CreateAutomatedTest(fields map[string]any) (map[string]any, error) {
	return c.object("POST", "automated-tests", fields)
}

func (c *Client) UpdateAutomatedTest(id string, fields map[string]any) (map[string]any, error) {
	return c.object("PUT", "automated-tests/"+id, fields)
}

func (c *Client) DeleteAutomatedTest(id string) error {
	_, err := c.do("DELETE", "automated-tests/"+id, nil, nil)
	return err
}

// object runs a request that returns one object.
func (c *Client) object(method, path string, body map[string]any) (map[string]any, error) {
	data, err := c.do(method, path, body, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}
