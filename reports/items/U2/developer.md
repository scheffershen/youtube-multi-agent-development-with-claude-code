SPEC: specs/U2-agent-active-flag.md
FILES CHANGED: src/Controller/AgentController.php (new action changeActive, PATCH /api/agents/{id}, route agent_active), src/Dto/UpdateAgentActiveRequest.php (new)
SCHEMA CHANGED: no
TESTS: OK (65 tests, 529 assertions) at hand-back (tester file not yet present)
NOTES: 404 JSON for unknown id, 422 {"errors":{"active":...}} for missing/non-bool, info log 'Agent activation changed' with id and active on every 200.
