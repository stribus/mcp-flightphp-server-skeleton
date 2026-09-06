# Configuração do Servidor MCP para VS Code

## Arquivos relevantes

- **`mcp-server.php`** — servidor MCP via stdio (JSON-RPC 2.0)
- **`.vscode/mcp.json`** — configuração pronta para o VS Code, versionada no repositório
- **`mcp-config.example.json`** — modelo para clientes que não expandem variáveis
- **`scripts/generate-mcp-config.php`** — gera o `mcp-config.json` com o caminho real
- **`start-mcp-server.ps1`** / **`start-mcp-server.bat`** — atalhos para iniciar o servidor
- **`test-mcp-server.ps1`** — teste do transporte stdio
- **`test-http-server.ps1`** — teste do transporte HTTP
- **`.env`** — configuração do ambiente (copie de `.env.example`)

## Como usar com o VS Code

O VS Code lê a configuração de servidores MCP de `.vscode/mcp.json`, e a chave de topo é
`servers` — não `mcpServers`, que é o formato do Claude Desktop.

Este repositório já traz o arquivo pronto. **Não é preciso editar nada**: o VS Code expande
`${workspaceFolder}` para o diretório onde você clonou o projeto, então funciona em qualquer
máquina.

```json
{
  "servers": {
    "flightphp-mcp-skeleton": {
      "type": "stdio",
      "command": "php",
      "args": ["${workspaceFolder}/mcp-server.php"]
    }
  }
}
```

## Outros clientes (Claude Desktop e similares)

Clientes que não expandem variáveis precisam do caminho absoluto. O
`composer create-project` já gera o `mcp-config.json` correto para a sua instalação. Para
regerá-lo a qualquer momento:

```bash
php scripts/generate-mcp-config.php
```

O `mcp-config.json` está no `.gitignore`, porque seu conteúdo é específico de uma máquina.
O modelo versionado é o `mcp-config.example.json`.

## Ferramentas incluídas

- **hello-world-tool** — ferramenta de exemplo que devolve uma saudação
- **generate_sql** — prompt de exemplo para gerar consultas SQL

Ambas são apenas referências: o produto do esqueleto são os geradores.

## Testar o servidor

```powershell
.\test-mcp-server.ps1     # transporte stdio
.\test-http-server.ps1    # transporte HTTP
```

Ou manualmente:

```powershell
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' | php mcp-server.php
```

A resposta deve trazer `protocolVersion`, `capabilities` e `serverInfo`.

## Logs e depuração

O servidor stdio grava cada requisição e cada resposta em `mcp-server.log`. O arquivo não é
versionado. Note que, em modo stdio, **nada além de mensagens MCP válidas pode ir para o
stdout** — é por isso que a barra de depuração do Tracy fica desativada fora do SAPI web.

## Adicionar novas ferramentas

```bash
vendor/bin/runway make:tool MinhaNovaFerramenta
vendor/bin/runway make:prompt MeuNovoPrompt
```

Os comandos precisam ser executados a partir da raiz do projeto. As classes criadas são
descobertas automaticamente — não existe nenhum registro manual a editar.
