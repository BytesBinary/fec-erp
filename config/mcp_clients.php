<?php

/*
|--------------------------------------------------------------------------
| MCP client connection snippets (spec §4.5, step 4 of the setup wizard)
|--------------------------------------------------------------------------
|
| One place to update when a client changes its configuration format.
| Placeholders: {server_url}, {token}, {server_name}. Formats checked against
| each client's public MCP documentation on 2026-10-10 (docs/DECISIONS.md D-021).
|
*/

return [

    'server_name' => 'fec-erp',

    'clients' => [

        'claude_desktop' => [
            'label' => 'Claude Desktop',
            'description' => 'Anthropic\'s desktop app. Connects through the mcp-remote bridge.',
            'file' => 'claude_desktop_config.json (Settings → Developer → Edit Config)',
            'language' => 'json',
            'snippet' => <<<'TEXT'
{
  "mcpServers": {
    "{server_name}": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "{server_url}", "--header", "Authorization: Bearer {token}"]
    }
  }
}
TEXT,
            'steps' => [
                'Install Node.js (it provides npx) if you do not have it.',
                'In Claude Desktop open Settings → Developer → Edit Config.',
                'Paste the configuration below into claude_desktop_config.json (merge it with any existing "mcpServers").',
                'Save the file and restart Claude Desktop.',
                'Ask Claude: "Who am I in the ERP?" — it should call the me_get_profile tool.',
            ],
        ],

        'claude_code' => [
            'label' => 'Claude Code',
            'description' => 'Anthropic\'s coding agent for the terminal.',
            'file' => 'Run in a terminal',
            'language' => 'bash',
            'snippet' => 'claude mcp add --transport http {server_name} {server_url} --header "Authorization: Bearer {token}"',
            'steps' => [
                'Open a terminal.',
                'Run the command below.',
                'Start Claude Code and type /mcp to see the connection.',
                'Ask: "Who am I in the ERP?" — it should call me_get_profile.',
            ],
        ],

        'cursor' => [
            'label' => 'Cursor',
            'description' => 'The Cursor code editor.',
            'file' => '.cursor/mcp.json (or ~/.cursor/mcp.json for all projects)',
            'language' => 'json',
            'snippet' => <<<'TEXT'
{
  "mcpServers": {
    "{server_name}": {
      "url": "{server_url}",
      "headers": { "Authorization": "Bearer {token}" }
    }
  }
}
TEXT,
            'steps' => [
                'Open Cursor Settings → MCP (or create .cursor/mcp.json).',
                'Paste the configuration below.',
                'Enable the "fec-erp" server in the MCP settings; a green dot means it is connected.',
                'Ask the agent: "Who am I in the ERP?"',
            ],
        ],

        'vscode' => [
            'label' => 'VS Code',
            'description' => 'Visual Studio Code with GitHub Copilot agent mode.',
            'file' => '.vscode/mcp.json',
            'language' => 'json',
            'snippet' => <<<'TEXT'
{
  "servers": {
    "{server_name}": {
      "type": "http",
      "url": "{server_url}",
      "headers": { "Authorization": "Bearer {token}" }
    }
  }
}
TEXT,
            'steps' => [
                'Create .vscode/mcp.json in your workspace (or run "MCP: Add Server" from the command palette).',
                'Paste the configuration below and save.',
                'Click "Start" above the server entry, then open Copilot Chat in agent mode.',
                'Ask: "Who am I in the ERP?"',
            ],
        ],

        'other' => [
            'label' => 'Other MCP client',
            'description' => 'Any client that supports the Streamable HTTP transport.',
            'file' => 'Your client\'s MCP server settings',
            'language' => 'bash',
            'snippet' => <<<'TEXT'
URL:     {server_url}
Header:  Authorization: Bearer {token}

# quick test with curl
curl -s {server_url} -H "Authorization: Bearer {token}" -H "Accept: application/json, text/event-stream" -H "Content-Type: application/json" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
TEXT,
            'steps' => [
                'Add a new MCP server of type "Streamable HTTP" (sometimes called "HTTP" or "remote").',
                'Enter the URL and add the Authorization header shown below.',
                'Save and connect; the client should list the available tools.',
            ],
        ],

    ],

];
