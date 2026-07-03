# Epic G1 — Inteligência sobre o Manual

> **Grau:** G1 · **Sprint:** 2 · **Estado:** ⬜ Não iniciada
> **Refs da visão:** §2 (tese), §4 (camadas 5–8), §5.2 (Signal/Notification), §6.2 (descritivo),
> §8 (anti-fadiga), §10.1 (briefing), §10.3 (âncoras), §10.5 (notificações), §11.1.

## Objetivo

Provar a promessa central — *"ele me avisa a hora de comprar"* — **100% sobre dado manual**,
sem uma linha de scraper. Transformar histórico em inteligência descritiva.

## Valor entregue

Âncoras que fazem o histórico "parecer inteligente", uma home que é um briefing de 0–3 itens, e
notificação em 2 eventos fortes por 1 canal. Se isso não faz o usuário dizer "que legal",
nenhum scraper salva (§11.1).

## Pré-requisitos

**G0 concluída** — âncoras e eventos leem o Ledger; ele precisa ter proveniência, validade,
moeda e o preço canônico já definidos (G0-F1).

## Definition of Done da Epic

- [ ] Âncoras são **derivadas** do Ledger (recomputáveis), nunca armazenadas como verdade.
- [ ] A home mostra um briefing e um **estado vazio confiante** ("nada a fazer hoje").
- [ ] Existe **1 canal** de notificação ligado a exatamente **2 eventos** (meta · novo menor histórico).
- [ ] Anti-fadiga aplicado: ≤ ~1 alerta por produto por janela, só cruzando limiar (§8).
- [ ] Só insights de **evento** viram Signal; insights de **estado** decoram o dashboard (§5.2).
- [ ] Nada preditivo (§6.2). Tudo descritivo e defensável.

---

## Feature G1-F1 — Âncoras de preço (Analytics descritivo)

> "Só matemática — barato, faz o histórico parecer inteligente" (§11.1). Camada 5 da §4.

- [ ] **G1-F1-T1 — Cálculo de âncoras a partir do Ledger.**
  Objetivo: derivar mínimo histórico (+ data), mínimo 90 dias, típico (mediana/moda), e
  referência de lançamento. Serviço puro que lê registros válidos.
  _Aceite:_ dado um histórico, retorna as âncoras corretas; ignora registros inválidos;
  recomputável a qualquer momento (nada persistido como verdade); teste com série conhecida.

- [ ] **G1-F1-T2 — Cards de âncora na tela de produto.**
  Objetivo: bloco "ÂNCORAS" do §10.3 (mínimo histórico, mínimo 90d, típico, lançamento).
  _Aceite:_ valores e datas relativas exibidos; some graciosamente quando há pouco dado
  ("ainda não sei" — Princípio 8).

- [ ] **G1-F1-T3 — Honestidade sobre incerteza.**
  Objetivo: quando o histórico é curto, o sistema declara baixa confiança em vez de fingir
  âncora firme (Princípio 8, §6.1).
  _Aceite:_ abaixo de um limiar de pontos/tempo, âncoras aparecem rotuladas como provisórias.

---

## Feature G1-F2 — Signals (fatos notáveis)

> Camada 7. Signal = o acontecimento digno de nota; separado da entrega (§5.2). Só **evento**.

- [ ] **G1-F2-T1 — Entidade/registro de Signal.**
  Objetivo: gravar o histórico do que foi relevante ("novo menor histórico", "bateu meta",
  "queda forte"). Deriva de âncoras + Ledger.
  _Aceite:_ um evento que cruza limiar gera um Signal datado com motivo; insight de **estado**
  (ex.: "está na meta agora") **não** vira Signal; teste separa evento de estado.

- [ ] **G1-F2-T2 — Detecção dos 2 eventos do MVP.**
  Objetivo: (a) preço ≤ meta; (b) novo menor preço histórico. São os 2 eventos do §11.1.
  _Aceite:_ registrar um preço que cruza qualquer um dispara o Signal correspondente uma única
  vez por cruzamento; não redispara sem novo cruzamento.

---

## Feature G1-F3 — Notificações (entrega + anti-fadiga)

> Camada 7/8. Notification = a mensagem entregue. Anti-fadiga vive na política entre Signal e
> Notification (§5.2, §8). Cópia real em §10.5.

- [ ] **G1-F3-T1 — Um canal de entrega.**
  Objetivo: ligar **1** canal (ex.: e-mail via config já existente, ou in-app). Jobs na queue
  `database` já configurada (§13). Manual é o gatilho: o evento nasce do que *você* registrou.
  _Aceite:_ um Signal elegível gera uma Notification entregue pelo canal; falha de entrega não
  quebra o registro do Signal.

- [ ] **G1-F3-T2 — Política anti-fadiga.**
  Objetivo: cooldown por produto/janela; no máximo ~1 alerta por produto por janela; só cruzando
  limiar (§8). Copiar as regras de "NÃO envia" do §10.5.
  _Aceite:_ segundo evento do mesmo produto dentro do cooldown **não** notifica; mexida de R$ 5
  não notifica; teste cobre o cooldown.

- [ ] **G1-F3-T3 — Cópia honesta das notificações.**
  Objetivo: usar a cópia real do §10.5, incluindo o caso honesto ("bom, mas ainda acima do
  mínimo histórico — pode cair mais"). Princípios 2 e 8.
  _Aceite:_ mensagens seguem a cópia do §10.5; toda notificação carrega o **motivo**; nenhuma
  afirmação preditiva.

---

## Feature G1-F4 — Briefing (home do módulo)

> Camada 8. A superfície diária defensável: 0–3 itens, ou "nada a fazer" com orgulho (§2, §10.1).

- [ ] **G1-F4-T1 — Briefing de 0–3 itens no topo da home.**
  Objetivo: bloco "HOJE" do §10.1 alimentado por Signals recentes; ordenação por atenção.
  _Aceite:_ mostra no máximo 3 itens acionáveis; deriva de Signals; nada inventado.

- [ ] **G1-F4-T2 — Estado vazio confiante.**
  Objetivo: quando nada mudou, "✓ Tudo tranquilo — nada exige ação hoje" (§10.1). A ausência de
  alerta é informação (§10.6). Princípio 1 e 12.
  _Aceite:_ sem Signals relevantes, a home exibe o estado calmo, não um vazio triste; sem
  fabricar engajamento.

- [ ] **G1-F4-T3 — Cartão de Preços no painel principal do Vault (5 segundos diários).**
  Objetivo: o mini-cartão do §10.6 ("🎯 1 bateu a meta" ou "✓ Tudo estável · 12 vigiados").
  _Aceite:_ o dashboard geral do Vault ganha o cartão resumido; reflete o estado real em 1 olhada.

---

## Notas de implementação

- **Âncoras e briefing são derivados** — recomputáveis do Ledger, nunca fonte de verdade
  paralela (Princípio 3, armadilha #9/§5.4).
- **Descritivo apenas.** Nada de "vai subir/cair" (§6.2). Isso é G5.
- O badge de meta atual (`PriceGoalBadge.vue` + `GoalStatus`) é a semente; ele **amadurece na
  mesma superfície**, sem tela nova (§6).
- Reaproveitar a queue `database` e os stubs de canal já presentes na infra (§1, §13) — não
  introduzir infraestrutura nova.
