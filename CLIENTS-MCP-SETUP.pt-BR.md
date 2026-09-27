# Conectando clientes MCP

Este esqueleto fala dois transportes — **stdio** e **Streamable HTTP** — e já vem com a
configuração pronta para os três clientes mais usados. Nenhum dos arquivos versionados contém
caminho da máquina de ninguém: cada cliente tem sua própria forma de nomear a raiz do projeto, e é
ela que os arquivos abaixo usam.

> Versão em inglês desta página: [`CLIENTS-MCP-SETUP.md`](CLIENTS-MCP-SETUP.md).

## Panorama

Os três clientes divergem em quase todos os detalhes, e é daí que vem a maior parte dos erros de
configuração:

| Cliente | Arquivo | Chave de topo | Variável da raiz do projeto |
|---|---|---|---|
| Claude Code | `.mcp.json` (raiz) | `mcpServers` | `${CLAUDE_PROJECT_DIR}` |
| VS Code | `.vscode/mcp.json` | `servers` | `${workspaceFolder}` |
| Claude Desktop | `mcp-config.json` | `mcpServers` | nenhuma — exige caminho absoluto |

Os dois primeiros estão versionados neste repositório e não precisam de edição. O terceiro é
gerado no momento da instalação.

## Claude Code

### stdio — o caminho padrão

O `.mcp.json` já está na raiz do repositório e não precisa de nenhuma alteração:

```json
{
  "mcpServers": {
    "flightphp-mcp-skeleton": {
      "type": "stdio",
      "command": "php",
      "args": ["${CLAUDE_PROJECT_DIR:-.}/mcp-server.php"]
    }
  }
}
```

A forma `${VAR:-default}` é importante aqui. O `CLAUDE_PROJECT_DIR` é a maneira documentada de
nomear a raiz do projeto, e é usado quando o Claude Code o fornece. Quando não fornece — como
ocorre no Claude Code 2.1.263 — entra o fallback `.`, e como o Claude Code inicia servidores de
escopo *project* a partir do diretório do projeto, o caminho relativo resolve para o mesmo arquivo.

Sem o fallback o Claude Code não falha de imediato: ele registra um aviso de variável ausente e
repassa o texto `${CLAUDE_PROJECT_DIR}` sem expandir, como se fosse um caminho literal — algo fácil
de não perceber. O fallback elimina esse cenário.

Abra o projeto no Claude Code. Por ser um servidor de escopo *project*, o Claude Code pede sua
aprovação na primeira vez. Depois disso, confira com o comando `/mcp` ou pelo terminal:

```bash
claude mcp list
```

O servidor deve aparecer como conectado, sem nenhum aviso abaixo. Se não conectar, veja a saída de
emergência com caminho absoluto na seção de resolução de problemas.

### Configurando na mão, em outro escopo

O arquivo versionado cobre o escopo *project*. Para uma entrada pessoal, que não é compartilhada,
use a CLI. Repare no separador `--`: ele é obrigatório, e tudo que vem depois dele é repassado ao
servidor sem alteração.

```bash
# Só para você, apenas neste projeto (escopo padrão)
claude mcp add flightphp-mcp-skeleton -- php "$PWD/mcp-server.php"

# Só para você, em todos os projetos
claude mcp add --scope user flightphp-mcp-skeleton -- php /caminho/absoluto/para/mcp-server.php
```

Os escopos gravam em lugares diferentes: `local` e `user` vivem em `~/.claude.json`, enquanto o
`project` é justamente o `.mcp.json` que você já tem.

### HTTP — a alternativa

O esqueleto também serve MCP por HTTP, e o Claude Code suporta isso diretamente:

```bash
composer start   # num terminal
claude mcp add --transport http flightphp-mcp-http http://localhost:8000/mcp
```

A contrapartida é que **você precisa subir o servidor**. Com stdio, o Claude Code inicia o
processo e o encerra sozinho. Use HTTP quando o servidor já estiver rodando em algum lugar, ou
quando quiser vários clientes compartilhando a mesma instância.

Não use `--transport sse`: o transporte SSE está deprecado no Claude Code, e a documentação
orienta a usar servidores HTTP no lugar. Este esqueleto vem com SSE desligado por padrão por um
motivo relacionado — veja a nota no [`README.md`](README.md).

Para remover um servidor:

```bash
claude mcp remove flightphp-mcp-skeleton
```

## VS Code

O `.vscode/mcp.json` está versionado e não precisa de edição:

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

Dois detalhes diferem do Claude Code e são fáceis de errar: a chave de topo é `servers`, não
`mcpServers`, e a variável da raiz do projeto é `${workspaceFolder}`.

## Claude Desktop

O Claude Desktop não expande variável alguma, então precisa de um caminho absoluto de verdade. Em
vez de pedir que você edite um placeholder, o `composer create-project` escreve o `mcp-config.json`
com o caminho correto da sua instalação. Regere sempre que o projeto mudar de lugar:

```bash
php scripts/generate-mcp-config.php --force
```

O resultado fica assim, com o seu caminho no lugar:

```json
{
  "mcpServers": {
    "flightphp-mcp-skeleton": {
      "command": "php",
      "args": ["/caminho/absoluto/do/seu-projeto/mcp-server.php"],
      "env": {}
    }
  }
}
```

Copie esse bloco para o arquivo de configuração do próprio Claude Desktop. O `mcp-config.json` está
no `.gitignore`, porque seu conteúdo é específico de uma máquina; o modelo versionado é o
`mcp-config.example.json`.

## Verificando a conexão

Independentemente de qualquer cliente, dá para falar com o servidor diretamente:

```bash
# stdio
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' | php mcp-server.php

# Suítes completas de teste
.\test-mcp-server.ps1     # stdio, 25 verificações
.\test-http-server.ps1    # transporte HTTP, 18 verificações
```

Uma resposta saudável de `initialize` traz `protocolVersion`, `capabilities` e `serverInfo`.

## Resolução de problemas

**O servidor não aparece.** Confirme que o `php` está no seu `PATH` — todas as configurações acima
o invocam pelo nome. O comando `php --version` precisa funcionar no mesmo terminal que abre o
cliente.

**O Claude Code não encontra o `mcp-server.php`.** O `.mcp.json` versionado resolve o caminho por
`${CLAUDE_PROJECT_DIR:-.}`, o que depende de o Claude Code definir essa variável ou de ele iniciar
o servidor a partir do diretório do projeto. Se nenhum dos dois valer no seu ambiente, registre o
servidor com caminho absoluto:

```bash
claude mcp add flightphp-mcp-skeleton -- php "$PWD/mcp-server.php"
```

Isso grava uma entrada de escopo *local*, que passa a valer só para você sem alterar o arquivo
compartilhado. Como alternativa, exporte o `CLAUDE_PROJECT_DIR` antes de abrir o Claude Code.

**Conecta, mas não expõe nenhuma ferramenta.** As ferramentas são descobertas varrendo `app/tools`,
e uma classe só é encontrada se implementar `MCPToolInterface` e tiver construtor sem argumentos.
O `ClassAutoLoader` instancia sem parâmetros, então um argumento obrigatório no construtor torna a
classe silenciosamente invisível.

**Nada funciona no stdio, e a saída parece HTML.** Alguma coisa escreveu no stdout algo que não era
uma mensagem MCP. A especificação proíbe isso, e corrompe o fluxo. Procure por `echo`, `var_dump`
ou `print_r` esquecidos nas suas ferramentas; escreva no `mcp-server.log` em vez disso.

**O transporte HTTP devolve 403.** O endpoint valida o cabeçalho `Origin` contra uma allowlist para
prevenir ataques de DNS rebinding. Clientes nativos não enviam `Origin` e não são afetados; os
baseados em navegador precisam do `MCP_ALLOWED_ORIGINS` configurado no `.env`.

**Logs.** O servidor stdio grava cada requisição e cada resposta no `mcp-server.log`. Esse arquivo
não é versionado e cresce a cada execução.

## Criando suas próprias ferramentas

```bash
vendor/bin/runway make:tool MinhaFerramenta
vendor/bin/runway make:prompt MeuPrompt
```

Execute a partir da raiz do projeto — o Runway resolve `commands/*.php` e `.runway-config.json`
relativos ao diretório atual. As classes geradas são descobertas automaticamente; não há registro
algum a editar.
