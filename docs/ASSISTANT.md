# Assistente de rotina

O módulo `/assistant` mantém conversas com GPT e propõe criar, editar ou excluir tarefas,
hábitos e eventos. Uma proposta só grava nesses módulos após **Aprovar e aplicar**.
Uma mensagem como “sim” não confirma ações por si só.

## Ativação

Configure exclusivamente no ambiente do backend:

```dotenv
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-mini
ASSISTANT_DAILY_REQUESTS=50
ASSISTANT_MAX_OUTPUT_TOKENS=8000
```

Use uma chave da sua organização OpenAI, nunca no frontend ou Git. A cobrança da API é separada
do ChatGPT. O modelo deve suportar Responses API e Structured Outputs. O cliente HTTP do Laravel
chama `https://api.openai.com/v1/responses`, sem dependência de SDK adicional.

Limpe o cache de configuração local após configurar (`php artisan config:clear`) ou recrie-o no deploy.
O PHP portátil precisa de CA bundle válido em `curl.cainfo`/`openssl.cafile`; nesta máquina,
`.runtime/php84/cacert.pem` contém os certificados públicos confiáveis do Windows. Reinicie o servidor
PHP após mudar o `php.ini`. O Docker instala `ca-certificates`. Nunca desabilite a validação TLS.
Sem chave, o chat informa “aguardando ativação”; históricos e propostas salvos continuam acessíveis.
Aplique a migration `2026_11_17_000001_create_assistant_tables` no banco local:

```powershell
../.runtime/php84/php.exe artisan migrate --env=local
```

Produção exige o procedimento existente de backup completo e restauração verificada antes do deploy.
A migration consta na lista de release; adicionar o arquivo não executa a migration nem publica código.

## Dados e limites

- Histórico e propostas ficam em `assistant_conversations` e `assistant_messages`, no MySQL.
- A OpenAI recebe a mensagem, até 20 mensagens recentes (reduzidas acima de 60 KB), tarefas
  pendentes/atrasadas/sem data, hábitos ativos e eventos dos 14 dias a partir da data selecionada.
- Até 100 itens por módulo, com textos truncados e indicação de listas incompletas no contexto.
  O modelo deve avisar quando um pedido exigir dados fora da janela.
- A requisição usa `store: false`; isso não promete retenção zero pelo provedor.
- Esta versão não acessa finanças, notas, arquivos, web ou notificações.
- Até 30 ações por proposta, 100 turnos/conversa, 10 envios/minuto e 50 tentativas/dia/usuário por padrão.
  Falhas do provedor também consomem tentativas. O limite diário não é um teto monetário.
- Timeout da OpenAI: 90 segundos; frontend: 120 segundos.
- Tarefas usam datas/horas locais; eventos são convertidos do fuso salvo na proposta para UTC ao gravar.
- Tarefas admitem recorrência diária, dias úteis, semanal, mensal e anual; hábitos admitem dias personalizados.
- Na edição, `null` mantém o valor anterior; na criação usa o padrão. Não é possível remover uma
  data/horário pelo chat nesta versão. Descrição vazia pode limpar uma descrição.

## Aprovação e consistência

O modelo retorna texto e ações estruturadas. `AssistantActions` valida entidades, operações, campos,
datas e propriedade. IDs de usuário não são aceitos da resposta. O preview mostra os dados concretos,
valores anteriores e exclusões. O endpoint de aprovação recebe o ID da mensagem e `confirmed: true`;
executa a proposta persistida, nunca ações enviadas pelo cliente.

Os serviços existentes de Tasks, Habits e Calendar gravam dentro de uma única transação.
Propostas vencem em 24 horas. Nova resposta substitui a proposta pendente anterior da mesma conversa.
Registros alterados externamente bloqueiam o lote inteiro (409): peça uma revisão.
Lock por usuário serializa geração/aprovação/descarte. Repetir aprovação não duplica itens;
`request_id` torna o reenvio da mesma mensagem idempotente.

Em múltiplas instâncias, use cache compartilhado com locks (database ou Redis).
O lock de linha da proposta também protege a aprovação. Conversas diferentes são independentes.

## Testes

```powershell
../.runtime/php84/php.exe vendor/bin/phpunit -c phpunit.recovery.xml tests/Feature/Assistant/AssistantTest.php
```

Usam MySQL local isolado (`vaultos_recovery_*`) e `Http::fake`. Uma chamada real exige chave e créditos;
testes simulados não validam disponibilidade do modelo na conta.

Referências: [Responses API](https://developers.openai.com/api/docs/quickstart),
[Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs).
