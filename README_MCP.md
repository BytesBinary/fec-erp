# FEC ERP — MCP server

The ERP speaks the [Model Context Protocol](https://modelcontextprotocol.io) so an AI client can do anything the
signed-in user can do, **limited to that user's role and scope**. Every tool call runs as a real ERP user
(never as a superuser), is checked by the same authorization layer as the web UI, and is written to the audit log
with `channel = mcp`.

* Transport: Streamable HTTP at `POST /mcp` (and stdio for local development).
* Auth: per-user **integration token** sent as `Authorization: Bearer <token>`.
* Built on `laravel/mcp`. The tool catalog is declared once in `app/Mcp/Registry` + `app/Mcp/Domains`
  and shared with the in-app assistant.

## 1. Get a token

Tokens are created **only in the web app** (they are never available through MCP or the assistant):

1. Sign in and turn on two-factor authentication (**Settings → Two-factor authentication**). MCP is impossible without it.
2. Open **Settings → AI Integrations** and run the wizard: choose your client, name it, choose
   *Full access as my role* or *Read-only*, choose the expiry (30 / 90 / 180 / 365 days), confirm with a fresh
   authenticator code.
3. Copy the token (shown once) and the ready-to-paste configuration snippet for your client.

Rules: max 5 active integrations per user; tokens are stored hashed; **Stop** revokes a token immediately;
disabling or resetting 2FA stops all of your integrations; super admin can see and revoke every integration.

## 2. Connect a client

Replace `https://erp.example.edu` with your server URL (locally `http://localhost:8000`) and `TOKEN` with your token.

**Claude Code**

```bash
claude mcp add --transport http fec-erp https://erp.example.edu/mcp --header "Authorization: Bearer TOKEN"
```

**Claude Desktop** (`claude_desktop_config.json`, via the `mcp-remote` bridge)

```json
{
  "mcpServers": {
    "fec-erp": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://erp.example.edu/mcp", "--header", "Authorization: Bearer TOKEN"]
    }
  }
}
```

**Cursor** (`.cursor/mcp.json`) / **VS Code** (`.vscode/mcp.json`)

```json
{ "mcpServers": { "fec-erp": { "url": "https://erp.example.edu/mcp", "headers": { "Authorization": "Bearer TOKEN" } } } }
```

(VS Code uses the key `servers` instead of `mcpServers`.)

**MCP Inspector**

```bash
php artisan mcp:inspector mcp          # opens the inspector against POST /mcp
# or:  npx @modelcontextprotocol/inspector  → transport "Streamable HTTP", URL http://localhost:8000/mcp,
#      header  Authorization: Bearer TOKEN
```

**stdio (local development)**

```bash
ERP_MCP_TOKEN=TOKEN php artisan mcp:start erp
```

The same token checks (revoked, expired, 2FA, role switch) run once when the stdio server starts.

**curl**

```bash
curl -s http://localhost:8000/mcp -H 'Authorization: Bearer TOKEN' -H 'Accept: application/json, text/event-stream' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"me_get_profile","arguments":{}}}'
```

## 3. How tools behave

* **Names** are `domain_verb_object` (`course_create`, `clearance_approve`). `tools/list` returns only the tools your
  role (and integration access level) may call; `tools/call` re-checks permission **and scope** in the service layer,
  so guessing a hidden tool name returns `FORBIDDEN`.
* **Read-only integrations** only see and may only call tools with `readOnlyHint: true`.
* **Destructive or bulk tools** (`result_publish`, `enrollment_bulk_create`, `clearance_approve`, `clearance_reject`,
  `user_deactivate`, …) take `confirm: boolean`. Without `confirm: true` they return a **dry-run preview**
  (`dry_run: true`) and change nothing.
* **Create tools** accept an optional `idempotencyKey`; repeating a call with the same key returns the first result
  (`replayed: true`) instead of creating twice.
* **Output** is structured JSON: `{ "summary": "...", "data": ... }`. List tools return
  `data: { items: [...], next_cursor }` — pass `cursor` and `limit` (≤ 100) to page.
* **Errors** (`isError: true`) carry `{ "error": { "code", "message", "context" } }` with stable codes:
  `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_ERROR`, `CONFLICT`, `INVALID_STATE`, `RATE_LIMITED`, `PROFILE_INCOMPLETE`.
* **Authentication failures** are HTTP 401 with `error.code`: `TOKEN_MISSING`, `TOKEN_INVALID`, `TOKEN_REVOKED`,
  `TOKEN_EXPIRED`, `MFA_REQUIRED`, `MCP_DISABLED`, `ACCOUNT_INACTIVE`. HTTP 429 `RATE_LIMITED` when a token exceeds
  its per-minute limit (`MCP_RATE_LIMIT`, default 120).
* A student with an incomplete profile gets `PROFILE_INCOMPLETE` from every tool except `me_*`.
* Students never see unpublished results through any tool.

## 4. Resources and prompts

| Resource | Content |
|---|---|
| `erp://me` | the current user, roles and the scopes of each role |
| `erp://academic-calendar` | all semesters (active one flagged) |
| `erp://grading-scale` | marks → letter → grade point, CGPA rules |

| Prompt | Workflow |
|---|---|
| `create_course_wizard` | create a course and offer it in a semester |
| `publish_semester_results` | preview, confirm and publish a semester |
| `review_pending_clearances` | walk through requests waiting for the approver |

## 5. Example calls per role

| Role | Try |
|---|---|
| Student | `me_get_profile`, `result_get_cgpa`, `student_list_my_courses`, `clearance_check_eligibility`, `clearance_apply`, `clearance_get_my_status` |
| Teacher | `result_roster {course_offering_id}`, `result_enter_marks {enrollment_id, marks}`, `result_submit {course_offering_id}` |
| Department head | `course_create {...}`, `result_approve {course_offering_id}` (preview, then `confirm: true`), `clearance_list_pending_for_me`, `clearance_approve` |
| Hall provost | `hall_residents_list`, `hall_dues_get {student_id}`, `hall_due_record`, `clearance_approve` for residents of their hall |
| Librarian | `library_dues_get {student_id}`, `library_loan_return`, `clearance_approve` / `clearance_reject {reason}` |
| Head of institution | `clearance_list_pending_for_me`, `clearance_approve` (final digital stage) |
| Admin office | `clearance_search {q: "CSE-21-003"}`, `clearance_print`, `clearance_mark_collected {id_verified: true}`, `enrollment_bulk_create` |
| Super admin | `user_create`, `role_assign`, `permission_matrix_update`, `result_publish {semester_id}`, `audit_log_search {channel: "mcp"}` |

## 6. What is not available through MCP

Enabling/disabling 2FA, recovery codes, logging devices out, and creating, renaming or stopping integrations are
**web-only** (they need an interactive step-up). The full list, with reasons, is in [`mcp/EXCLUDED.md`](mcp/EXCLUDED.md);
`tests/Mcp/ParityTest.php` fails when a service method is neither a tool nor listed there.

## 7. Development

```bash
php artisan test --testsuite=Mcp          # contract, role × tool matrix, parity, auth, scenarios
UPDATE_MCP_FIXTURE=1 php artisan test tests/Mcp/RoleToolMatrixTest.php   # regenerate the matrix fixture, then review the diff
```

To add a tool: declare it in `app/Mcp/Domains/*Tools.php` with a `ToolDefinition` (permission, params, annotations,
`->covers('Service::method')`, handler calling the **service**), then regenerate and review the matrix fixture.
Never put SQL or business rules in a handler.
