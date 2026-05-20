package api

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

type Client struct {
	baseURL    string
	token      string
	httpClient *http.Client
}

func New(baseURL, token string) *Client {
	return &Client{
		baseURL:    strings.TrimRight(baseURL, "/") + "/api/v1",
		token:      token,
		httpClient: &http.Client{Timeout: 30 * time.Second},
	}
}

type APIError struct {
	Status  int
	Message string
}

func (e *APIError) Error() string {
	return fmt.Sprintf("HTTP %d: %s", e.Status, e.Message)
}

func (c *Client) do(method, path string, body map[string]any, params url.Values) (any, error) {
	var bodyReader io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		bodyReader = bytes.NewReader(b)
	}

	fullURL := c.baseURL + "/" + strings.TrimLeft(path, "/")
	if len(params) > 0 {
		fullURL += "?" + params.Encode()
	}

	req, err := http.NewRequest(method, fullURL, bodyReader)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == 204 {
		return nil, nil
	}

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	var result map[string]any
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, fmt.Errorf("failed to parse response: %w", err)
	}

	if resp.StatusCode >= 400 {
		msg := "request failed"
		if m, ok := result["message"].(string); ok {
			msg = m
		}
		return nil, &APIError{Status: resp.StatusCode, Message: msg}
	}

	if data, ok := result["data"]; ok {
		return data, nil
	}
	return result, nil
}

func toSlice(data any) ([]map[string]any, error) {
	if data == nil {
		return []map[string]any{}, nil
	}
	raw, ok := data.([]any)
	if !ok {
		return nil, fmt.Errorf("expected array response")
	}
	result := make([]map[string]any, 0, len(raw))
	for _, item := range raw {
		if m, ok := item.(map[string]any); ok {
			result = append(result, m)
		}
	}
	return result, nil
}

func toMap(data any) (map[string]any, error) {
	if data == nil {
		return nil, nil
	}
	m, ok := data.(map[string]any)
	if !ok {
		return nil, fmt.Errorf("expected object response")
	}
	return m, nil
}

// ── Epics ─────────────────────────────────────────────────────────────────────

func (c *Client) ListEpics(repoURL string) ([]map[string]any, error) {
	params := url.Values{}
	if repoURL != "" {
		params.Set("repository_url", repoURL)
	}
	data, err := c.do("GET", "epics", nil, params)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) GetEpic(id string) (map[string]any, error) {
	data, err := c.do("GET", "epics/"+id, nil, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) UpdateEpic(id string, fields map[string]any) (map[string]any, error) {
	data, err := c.do("PATCH", "epics/"+id, fields, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) GetEpicQueue(id string) ([]map[string]any, error) {
	data, err := c.do("GET", "epics/"+id+"/queue", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) GetEpicHistory(id string) ([]map[string]any, error) {
	data, err := c.do("GET", "epics/"+id+"/history", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) AddEpicNote(id string, metadata map[string]any) (map[string]any, error) {
	data, err := c.do("POST", "epics/"+id+"/history", map[string]any{"action": "note", "metadata": metadata}, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

// ── Features ──────────────────────────────────────────────────────────────────

func (c *Client) ListFeatures(epicID string) ([]map[string]any, error) {
	data, err := c.do("GET", "epics/"+epicID+"/features", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) GetFeature(id string) (map[string]any, error) {
	data, err := c.do("GET", "features/"+id, nil, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) UpdateFeature(id string, fields map[string]any) (map[string]any, error) {
	data, err := c.do("PATCH", "features/"+id, fields, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) GetFeatureHistory(id string) ([]map[string]any, error) {
	data, err := c.do("GET", "features/"+id+"/history", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) AddFeatureNote(id string, metadata map[string]any) (map[string]any, error) {
	data, err := c.do("POST", "features/"+id+"/history", map[string]any{"action": "note", "metadata": metadata}, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

// ── Tasks ─────────────────────────────────────────────────────────────────────

func (c *Client) ListTasks(featureID string) ([]map[string]any, error) {
	data, err := c.do("GET", "features/"+featureID+"/tasks", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) GetTask(id string) (map[string]any, error) {
	data, err := c.do("GET", "tasks/"+id, nil, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) UpdateTask(id string, fields map[string]any) (map[string]any, error) {
	data, err := c.do("PATCH", "tasks/"+id, fields, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) UpdateTaskStatus(id, status string) (map[string]any, error) {
	data, err := c.do("PATCH", "tasks/"+id+"/status", map[string]any{"status": status}, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}

func (c *Client) GetTaskHistory(id string) ([]map[string]any, error) {
	data, err := c.do("GET", "tasks/"+id+"/history", nil, nil)
	if err != nil {
		return nil, err
	}
	return toSlice(data)
}

func (c *Client) AddTaskNote(id string, metadata map[string]any) (map[string]any, error) {
	data, err := c.do("POST", "tasks/"+id+"/history", map[string]any{"action": "note", "metadata": metadata}, nil)
	if err != nil {
		return nil, err
	}
	return toMap(data)
}
