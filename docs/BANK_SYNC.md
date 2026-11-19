# Conexão bancária (Pluggy / MeuPluggy)

A tela **Bancos conectados** (`/finance/connections`) lê saldos e transações direto do banco pelo
Open Finance, usando a [Pluggy](https://docs.pluggy.ai). O VaultOS guarda uma **cópia do que o banco
informa** (`bank_connection_*`) e a confere com o livro-caixa:

- cada conta do banco é **ligada** a uma conta do app (cartão → cartão);
- o que já está no app é ligado (`matched`); no cartão, cada compra fica na fatura que o banco informa;
- o que falta vai para **"A lançar"** (`/finance/connections/review`) com uma sugestão, e **só entra no
  livro-caixa quando o usuário aprova**, pelas mesmas actions do app (criar, confirmar previsão, pagar
  fatura).

## Como o usuário conecta (MeuPluggy, uso pessoal)

A conta Pluggy do projeto é de uso pessoal: o único conector real é o **MeuPluggy** (id 200, OAuth),
gratuito, até 5 conexões e sem uso comercial. Os conectores sandbox servem para testes.

1. O usuário conecta seus bancos em <https://meu.pluggy.ai> (Open Finance). Cada banco é autorizado lá.
2. No VaultOS, **Conectar banco** pede um connect token ao backend e abre o widget Pluggy Connect.
3. No widget, ele escolhe **MeuPluggy**, entra com a conta do Meu Pluggy e autoriza **um banco por vez**.
   Cada banco vira um item da Pluggy e uma linha em `bank_connections`.
4. O widget devolve o `item.id`; o frontend envia para `POST /finance/bank-connections`, que confere na
   Pluggy se o item foi criado para este usuário (`clientUserId`) antes de registrar e ler os dados.
5. Em cada conta (toque → "Ligar ao app"), escolhe a conta ou o cartão do app. O início é detectado
   (ver "Conferência") e pode ser trocado.
6. Em **A lançar**, confere e aprova.

A senha do banco é digitada no widget (iframe da Pluggy) e nunca passa pelo VaultOS.

## Ativação

Somente no ambiente do backend (`.env.local`, `.env.production` ou variáveis do Railway):

```dotenv
PLUGGY_CLIENT_ID=
PLUGGY_CLIENT_SECRET=
PLUGGY_INCLUDE_SANDBOX=false   # true mostra os bancos de teste no widget (desenvolvimento)
```

As credenciais ficam em `config/services.php` (`services.pluggy`). O backend troca o client id/secret
por uma API key na `POST /auth` da Pluggy (vale 2 h; fica em cache por 100 min) e entrega ao navegador
só um connect token de 30 min (`POST /connect_token`, com `clientUserId` = id do usuário). **Não usar
`avoidDuplicates`:** os itens do MeuPluggy não têm credenciais (OAuth), então a Pluggy trata todo banco
novo como duplicata do primeiro e recusa (`400 ITEM_USER_ALREADY_EXISTS`, "Um erro inesperado" no
widget). Sem credenciais, `GET /finance/bank-connections/status` responde `configured: false`
e a tela mostra "Conexão bancária desativada". Depois de mudar o `.env`, rode `artisan config:clear`.

Produção: a migration `2026_11_17_000002_create_bank_connection_tables` rodou em 2026-11-17 (procedimento
de release com backup verificado); as duas variáveis ficam no Railway.

## Dados

| Tabela | Conteúdo |
|---|---|
| `bank_connections` | Um item da Pluggy: conector, `status`/`execution_status`, `consent_expires_at`, `provider_updated_at` (última leitura do banco pela Pluggy), `last_synced_at` (última leitura pelo VaultOS), `last_sync_error`, `name` (nome dado pelo usuário) |
| `bank_connection_accounts` | Conta (`type` BANK) ou cartão (`type` CREDIT): saldo em centavos, limite, vencimento/fechamento da fatura, `bank_code` (COMPE); ligação: `bank_account_id` ou `credit_card_id` e `import_from` (início de "A lançar") |
| `bank_connection_transactions` | Transação: `date`, `description`, `amount_cents` (sempre positivo, em reais), `direction` (`in`/`out`), `status` (`posted`/`pending`), parcela, categoria da Pluggy, `payload` (registro original); conferência: `review_status` e `transaction_id` (lançamento do app ligado) |
| `bank_review_rules` | Escolha do usuário por contraparte e sentido (`match_key` "SAFE PAY INTERMEDIAC", `out`): ação, categoria, outra conta (transferência), descrição, reembolso, proporção da parte própria (despesa dividida) |

**Nome do banco.** O MeuPluggy chama toda conexão de "MeuPluggy". O nome mostrado é: o nome dado pelo
usuário, senão o banco deduzido do código COMPE da conta (`bankData.transferNumber`, ex.: 260 = Nubank,
`BankInstitutions`), senão o nome do conector.

**Valores.** Usa `amountInAccountCurrency` quando existe (compra internacional: `amount` vem em
dólar). O sinal decide a direção, como a Pluggy documenta (e como o Actual Budget usa com dados reais
do MeuPluggy): na conta, negativo saiu; no cartão, positivo é compra e negativo é pagamento/estorno. O
campo `type` não é usado. O sandbox da Pluggy manda o cartão invertido; só para conectores `isSandbox`
o sinal do cartão é corrigido. No cartão, `balance_cents` positivo é a fatura em aberto.

**Datas.** A Pluggy manda datas em UTC. Meia-noite UTC é tratada como o próprio dia (bancos que só
informam o dia); qualquer outro horário é convertido para o fuso do usuário (`UserClock`).

## Leitura (sync)

`BankSyncService::sync()` lê o item, as contas e as transações, grava com upsert e, em seguida, roda a
conferência das contas ligadas. Um lock por conexão evita duas leituras simultâneas (409). Regras:

- Só lê contas/transações com o item `UPDATED` ou `OUTDATED`. Em `LOGIN_ERROR`/`WAITING_USER_INPUT` a
  Pluggy pode responder listas vazias: a cópia não é apagada e a conexão fica com `needs_action`.
- Primeira leitura de uma conta: todas as transações (`GET /v2/transactions`, cursor de 500).
  Depois: só os últimos 60 dias (`dateFrom`). Dentro dessa janela, o que a Pluggy deixou de informar é
  apagado (ela troca o id quando não reconhece uma transação pendente que foi liquidada).
- Uma resposta vazia nunca apaga contas; uma conta que some de uma resposta não vazia é apagada.
- Item que a Pluggy não conhece mais vira `NOT_FOUND` (pede nova conexão), sem apagar a cópia.
- Falha na Pluggy: 502, a mensagem fica em `last_sync_error` e os dados anteriores continuam.

O MeuPluggy atualiza cada banco **uma vez por dia**, cerca de 24 h depois da leitura anterior (o horário
vai se deslocando), e o item do VaultOS não pode forçar atualização. O comando `bank-sync:pull` roda **de
hora em hora** (`BankSyncService::pullIfUpdated`): pede só o item à Pluggy (uma chamada) e relê contas e
transações apenas se `lastUpdatedAt` mudou desde a última leitura. Relê também se a última leitura falhou,
se o status mudou ou se passaram 24 h (`FULL_READ_HOURS`). Assim, o que o banco informou aparece até uma
hora depois da leitura do MeuPluggy. "Buscar dados agora" na tela sempre relê a conexão inteira. O que for novo e não estiver no app aparece em "A lançar"
(contador na Visão Geral, card "Do banco").

**Autorização.** No MeuPluggy ela vale 1 ano (`consent_expires_at`; a tela avisa 30 dias antes). Para
renovar, "Autorizar de novo" abre o widget em modo de atualização (`connect-token` com
`connection_id` devolve `update_item_id`). Após reautorizar, o item pode mudar de id; a conexão antiga
pode ser desconectada.

**Desconectar** apaga o item na Pluggy (revoga o acesso do VaultOS) e a cópia local (conexão, contas,
transações). Lançamentos já criados no app continuam.

Webhooks da Pluggy não são usados: a leitura agendada cobre o ritmo diário do MeuPluggy.

## Conferência com o livro-caixa (`BankReconciler`)

`review_status` de cada transação do banco:

| Status | Significado |
|---|---|
| `null` | Conta não ligada, ou transação com data futura (parcela/agendamento): espera a data |
| `before_start` | Antes de `import_from`: histórico, não é oferecida |
| `new` | Em "A lançar", esperando decisão |
| `matched` | Já estava no app (digitada, importada ou previsão confirmada); `transaction_id` aponta para ela |
| `imported` | Criada no app a partir daqui; fica travada como os importados de extrato |
| `ignored` | O usuário decidiu deixar fora do app |

**Comparação (match).** Mesmo lado (conta: receitas/despesas sem cartão e transferências de/para a
conta; cartão: compras, estornos e pagamentos da fatura), mesmo valor, data a até 3 dias (parcela: mesmo
número/total a até 45 dias, porque o banco data pela fatura). Na conta, linha `pending` é previsão:
nunca casa sozinha. No cartão, linhas `pending` são cobranças agendadas e casam. Nomes desempatam:
1. lançamentos que citam a pessoa do Pix ("Reembolso Peterson" para PETERSON), sozinhos e depois em
   **par** (dois almoços pagos num Pix só, 41,75 = 21,00 + 20,75);
2. depois os demais, sem nunca dar a alguém um lançamento que cita outra pessoa do mesmo extrato
   (regra só da conta; no cartão os nomes são de lojas, escritos diferente).

Lançamento apagado no app libera a transação do banco de volta para "A lançar".

**Fatura do cartão.** O banco diz em que fatura cada cobrança caiu (`payload.creditCardMetadata.billForecastDate`,
"2026-12" = fatura que vence em dezembro; vem também nas já fechadas). O lançamento do cartão ligado ao banco
a partir do início, ou criado em "A lançar", grava essa fatura em `statement_month`
(`BankReconciler::billMonth`). O dia de fechamento do cartão no app só decide quando o banco não diz
(previsões, compras digitadas). No app, `closing_day` é o **último dia que entra** na fatura: o Nubank
que "fecha dia 12" (compra do dia 12 já vai para a próxima) fica com `closing_day` 11. Pagamentos da
fatura (transferências) mantêm a fatura que pagam e o histórico importado (`import_batch_id`) não muda.

**Início (`import_from`).** Detectado na ligação: depois do último trecho de 5 dias com movimento em
que tudo bate, o primeiro dia com algo faltando; tudo batendo, o dia seguinte ao último movimento; sem
trecho que bata, o primeiro dia do mês. O usuário pode escolher outra data.

**Sugestões** (calculadas na leitura de "A lançar", em ordem):
1. **Previsão**: lançamento da mesma conta/cartão, mesmo tipo, a até 10 dias, valor a até 15% (40% para
   boleto "Pagamento efetuado", contas de consumo). Pendente → `confirm` (confirma com valor e data do
   banco); série já confirmada ou assinatura do cartão já realizada e não ligada → `match` (liga e corrige). Uma
   contraparte já conhecida por outra categoria não recebe previsão. Pares mais próximos primeiro.
2. **Fatura**: "Pagamento de fatura" (conta) / "Pagamento recebido" (cartão) → `pay_card`, com a
   fatura cujo vencimento é o mais próximo; o outro lado é reconhecido sem pagar duas vezes.
3. **Regra** (`bank_review_rules`): a última escolha do usuário para a contraparte.
4. **Histórico**: o que foi feito com a mesma contraparte em transações já ligadas. Importados antigos
   sem categoria não ensinam; entre as 12 últimas, vence a classificação mais frequente; a descrição vem
   da ocorrência mais recente que cita a mesma pessoa. Despesa dividida traz a proporção da parte própria.
5. **Padrão**: Pix de/para o próprio nome do usuário → transferência entre contas.
6. Senão: despesa/receita com o nome da contraparte.

**Aprovar** (`POST review`, item a item, cada um numa transação; o que falha volta em `skipped`):
`expense`/`income` criam pelo `TransactionService` (conta ou cartão; parcela vira "Nome (n/N)"; receita
pode ser reembolso; despesa pode ser **dividida** com `own_share_cents`, gravando `economic_kind`
`shared_expense` e só a parte própria como gasto); `transfer` cria transferência com a outra conta;
`pay_card` usa `PayCardStatementAction`; `confirm` confirma a previsão; `match` liga a um lançamento que
já existe e dá a ele o valor e a data do banco (pendente é confirmado; confirmado é corrigido e a conta recebe
a diferença; transferência e histórico importado não mudam); `ignore`. No cartão, `expense`/`income` e
`match` usam a fatura que o banco informa. Ex.: a previsão "OpenAI (ChatGPT) + IOF" de 107,57 no dia 14,
ligada à cobrança de 103,90 do dia 6, vira 103,90 no dia 6, na fatura do banco (o IOF vem à parte).
Transferência e pagamento de fatura primeiro tentam casar com o que o outro lado já criou. A escolha vira
regra (`remember`, padrão sim). No fim, a conferência roda em todas as contas ligadas do usuário.

**Trava.** Lançamento criado a partir do banco (`imported`) fica com valor, data, conta e descrição
travados (`RecoveryRecordGuard`, `recovery_locked` no `TransactionResource`); categoria, tags e notas
mudam. Para desfazer: na lista da conta (Bancos conectados), "Desfazer" apaga o lançamento e devolve a
transação para "A lançar"; ignorada volta para "A lançar".

## Testar com os dados reais sem tocar produção

Cópia de produção num banco local separado (`vaultos_prodcopy`), com `.env.prodcopy` (ignorado pelo Git,
`APP_ENV=prodcopy` para o `artisan serve` não cair no `.env.local`):

1. Dump só de leitura de produção (`reconciliacao-2026-10-15/scripts/backup.php <pasta>`, TLS).
2. Restaurar no MySQL local em `vaultos_prodcopy`; `artisan migrate --env=prodcopy`.
3. `artisan serve --env=prodcopy --port=8001`; entrar com o usuário real (mesma senha da produção).

Em 2026-11-17, aprovando todas as sugestões da conta Nubank na cópia, o saldo do app ficou igual ao do
banco (R$ 447,96).

## Testes

`tests/Feature/Finance/BankConnectionTest.php` simula a Pluggy (`Http::fake`, nada sai da máquina):
connect token, registro com checagem de `clientUserId`, sinais, moeda e datas, paginação, janela de
releitura, autorização expirada, falha da Pluggy, renomear/desconectar, isolamento e o comando agendado.
`tests/Feature/Finance/BankReconcileTest.php` cobre ligação e início, histórico e regras, previsões
(pendente e já confirmada, com valor e data do banco), transferência entre contas, cartão (parcelas,
assinatura, fatura informada pelo banco, pagamento dos dois lados), despesa dividida, mesma quantia de duas pessoas e Pix com dois almoços, trava e desfazer,
ignorar, validação e isolamento. As rotas que recebem um registro estão no `FinanceRoutesAccessTest`.
