# Epic G0 — Fundação & Modo Manual

> **Grau:** G0 (Manual, hoje) · **Sprint:** 1 · **Estado:** ⬜ Não iniciada
> **Refs da visão:** §3 (dualidade), §4 (camadas 1–4), §5 (modelo de domínio),
> §5.3 (decisões de fundação), §5.4 (erros a evitar), §10.4 (registrar preço), §11.1.

## Objetivo

Consolidar o modo manual como **o produto** e alinhar o modelo de domínio ao shape que dura —
para que toda a inteligência de G1+ seja construída sobre um Ledger correto, e não refeita.

## Valor entregue

Um monitor de preços manual completo: cadastro de produto com meta, registro de preço em poucos
segundos, histórico por loja, badge de meta — com o modelo de dados pronto para receber
automação **sem reescrita**.

## Pré-requisitos

Nenhum. É a base. (O domínio `Prices` já existe parcialmente — ver "O que já existe".)

## O que já existe (não refazer)

- `PriceProduct` (com `target_price`, `launch_price`, `status`), `PriceCategory`, `PriceStore`,
  `PriceRecord` (com `url`), `PricePurchase`, `PriceSale` — models, controllers, services,
  requests, resources, policies.
- `GoalStatus::fromPrice()` — **single source of truth** da regra de meta (§1). Reaproveitar, não duplicar.
- Front: `PriceHistoryChart.vue`, `PriceGoalBadge.vue`, `PriceRecordFormSheet.vue`,
  `PriceProductsPage.vue`, `PriceProductDetailPage.vue`, store `prices.ts`, service `prices.ts`.

## Definition of Done da Epic

- [ ] O Ledger tem proveniência, validade, moeda e o preço comparável canônico definido (Decisão B).
- [ ] `url` não mora mais no evento de preço (armadilha #2 resolvida).
- [ ] Lifecycle do produto suporta re-track (Decisão C).
- [ ] Registrar um preço leva **poucos segundos** (§10.4) — medido no fluxo real.
- [ ] Nenhum Princípio Fundamental (§0.2) violado; nenhuma migração destrutiva de histórico.
- [ ] Testes de feature verdes para cada Task; `pint` limpo; front com `type-check` + `build` ok.

---

## Feature G0-F1 — Alinhamento do modelo de domínio (Ledger)

> O passo que evita retrabalho. Alinhar o shape antes de construir inteligência em cima.
> **Nunca apagar histórico** — migrações preservam dado existente (Princípio 7).

- [ ] **G0-F1-T1 — Mover `url` do `PriceRecord` para o vínculo produto↔loja.**
  Objetivo: separar "onde observo" (estável) de "o que observei" (evento) — armadilha #2.
  Como o Watch Target completo só nasce no G2, criar aqui a forma mínima: a URL passa a ser
  atributo do par (produto, loja). Opções a decidir na Task: coluna em tabela pivô
  `price_product_store` **ou** campo no primeiro registro migrado para um lugar estável.
  _Aceite:_ registrar preço não pede mais URL; a URL de referência é editável por (produto, loja);
  migration move as URLs existentes sem perda; `PriceHistoryChart` e detalhe do produto seguem
  funcionando.

- [ ] **G0-F1-T2 — Adicionar proveniência e validade ao `PriceRecord`.**
  Objetivo: preparar o gate de qualidade e o backtesting (Princípios 3, 7).
  Campos: `provider` (enum, default `manual`), `is_valid` (bool, default `true`),
  `invalidated_reason` (nullable), `source_observation_id` (nullable — apontará para Observation no G2).
  _Aceite:_ leituras do Ledger passam a filtrar `is_valid = true` por padrão; invalidar um
  registro **não o apaga** (soft-invalidate); teste cobre "registro inválido some da série mas
  continua no banco".

- [ ] **G0-F1-T3 — Modelar moeda e facetas de preço.**
  Objetivo: não assumir BRL/Pix no núcleo (armadilha #6); preço é feixe, não escalar (Decisão B).
  Campos no `PriceRecord`: `currency` (default `BRL`), `price` (o comparável canônico =
  melhor efetivo à vista/Pix), e facetas opcionais `price_card`, `shipping`, `in_stock`.
  _Aceite:_ o gráfico e as âncoras (G1) leem sempre `price` como manchete; facetas ficam
  guardadas mas não dirigem a série; teste cobre default de moeda e persistência de facetas.

- [ ] **G0-F1-T4 — Lifecycle do produto com re-track (Decisão C).**
  Objetivo: substituir o enum plano por um ciclo `acompanhando → comprado → (re-acompanhar) → descartado`.
  Reusar/estender `ProductStatus`; garantir transições válidas em um único lugar (Action ou método
  do enum), não `if` espalhado.
  _Aceite:_ é possível voltar um produto `purchased` para `tracking`; transições inválidas
  rejeitadas; histórico do produto preservado; teste cobre cada transição legal e uma ilegal.

---

## Feature G0-F2 — Registro manual sem fricção

> O UX que **faz ou quebra a adoção** (§10.4). Se registrar um preço dói, o usuário para.

- [ ] **G0-F2-T1 — Folha de registro mínima.**
  Objetivo: produto pré-selecionado, **loja lembrada da última vez**, um campo de número, data =
  hoje por padrão. Confirmar em poucos toques. Reusar/afinar `PriceRecordFormSheet.vue`.
  _Aceite:_ do detalhe do produto ao "salvo" em ≤ 3 interações; loja default = última usada
  naquele produto; medir e anotar o tempo real do fluxo.

- [ ] **G0-F2-T2 — Provider = Manual como cidadão de primeira classe.**
  Objetivo: todo registro manual grava `provider = manual` (usa G0-F1-T2). Materializa a
  dualidade: manual é um provider como outro (§5.2, Provider).
  _Aceite:_ registro manual persiste proveniência; nenhuma camada acima distingue manual de
  automático ao ler o Ledger (Princípio 4).

- [ ] **G0-F2-T3 — Indicador de frescor do preço.**
  Objetivo: "atualizado hoje" / "⚠ há X dias" — mesmo componente que servirá ao scraper (§3).
  No manual significa "faz X dias que *você* não atualizou".
  _Aceite:_ cada linha "por loja" e a home mostram frescor derivado de `recorded_at`; item muito
  defasado ganha marca discreta; sem tela nova, só componente reutilizável.

---

## Feature G0-F3 — Tela de produto & histórico (consolidação)

> A tela é a mesma nos dois modos; aqui garantimos a base descritiva (§10.3).

- [ ] **G0-F3-T1 — Gráfico multi-loja com linha de meta.**
  Objetivo: `PriceHistoryChart.vue` plota séries por loja + linha da meta + marcação "hoje".
  Lê só `price` válido (G0-F1-T2/T3).
  _Aceite:_ múltiplas lojas no mesmo eixo; meta visível; janelas 30d/90d/Tudo; ignora registros
  inválidos.

- [ ] **G0-F3-T2 — Tabela "Por loja" com preço, estoque e frescor.**
  Objetivo: última leitura por loja com preço, `in_stock`, frescor (G0-F2-T3) e badge de meta.
  _Aceite:_ ordena por atenção (mais barato/na meta primeiro); reflete o último registro válido
  de cada loja.

- [ ] **G0-F3-T3 — Feed "O que mudou" (base manual).**
  Objetivo: linha do tempo simples de eventos derivados do Ledger; no manual inclui "Você
  registrou manual". É a semente dos Signals do G1/§5.2.
  _Aceite:_ lista os últimos eventos (novo registro, mudança relevante de preço) com data
  relativa; deriva do Ledger, não guarda verdade paralela.

---

## Notas de implementação

- **Sem big-bang de migração.** Cada Task de F1 é uma migration pequena e reversível que
  preserva o histórico existente. Rodar `migrate` em ambiente limpo e sobre o banco atual.
- **Reusar `GoalStatus::fromPrice()`** em todo lugar que precise do estado de meta — nunca
  reescrever a regra.
- Ao fechar F1, **atualizar a seção 5.3 da visão** se alguma decisão for refinada na prática.
