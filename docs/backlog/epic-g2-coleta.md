# Epic G2 — Coleta Piloto (1 loja)

> **Grau:** G2 · **Sprint:** 3+ · **Estado:** ⬜ Não iniciada · **Nível:** Feature (refinar em Tasks na véspera)
> **Refs da visão:** §4 (camadas 2–3), §5.2 (Watch Target, Observation, Data Quality, Provider),
> §5.3-A, §9.2/§9.4 (rotina/manutenção automática), §13 (escala), §14 (riscos), §14.1 (viabilidade).

## Objetivo

Ligar automação para **uma única loja** (a mais usada). Aprender o custo real de manutenção de
scraper **antes de multiplicar** — poucas fontes sólidas valem mais que muitas quebradas (§0.3-6).

## Valor entregue

Essa loja se atualiza sozinha 1x/dia; quando o scraper quebra, a falha é **visível** e degrada
para o modo manual sem bloquear ninguém (§9.4). O botão manual vira fallback.

## Pré-requisitos

**G1 concluída.** O Ledger já tem proveniência/validade/moeda (G0-F1), então a coleta só precisa
alimentar Observations → gate → Price Record.

## Princípios que esta Epic não pode violar

- Manual e automático produzem **o mesmo resultado**; origem é invisível às camadas de valor (Princípio 4).
- O usuário **sempre** pode substituir a automação (Princípio 5); scraper quebrado degrada para manual.
- **Não** investir em scraper auto-reparável — tornar a quebra visível + fallback fácil (§13).
- **Não** perseguir tempo real; coleta de baixa frequência (§0.3-5).

## Definition of Done da Epic

- [ ] 1 provider real coletando 1 loja via scheduler + queue `database`.
- [ ] Toda coleta gera **Observation** imutável → passa pelo **gate de qualidade** → vira Price Record.
- [ ] Watch Target tem **saúde/ciclo de vida** visível (`ativo/quebrado/sem estoque/pausado`).
- [ ] Scraper quebrado é estado de domínio visível e degrada para manual sem bloquear.
- [ ] Camadas 4–8 (Ledger, âncoras, briefing) funcionam idênticas com dado automático.

---

## Features (a expandir em Tasks quando a sprint começar)

- [ ] **G2-F1 — Watch Target com saúde.** A "instrução permanente de observe isto aqui":
  liga Product↔Store↔URL + estratégia (provider) + estado de saúde (último check, último
  sucesso, falhas consecutivas). Absorve a URL que G0-F1-T1 moveu do record. (§5.2 Watch Target)

- [ ] **G2-F2 — Observation (fato bruto imutável).** Entidade rica: variantes de preço
  (à vista/Pix/cartão), estoque, cheio vs. promocional, frete, vendedor, snapshot de identidade,
  sinal de sucesso da coleta, timestamp, moeda, provider. **Nunca editada** (anota/invalida).
  Referencia Product+Store sempre e Watch Target opcionalmente (Decisão A, §5.3). (§5.2 Observation)

- [ ] **G2-F3 — Gate de qualidade / quarentena.** Política que dá veredito
  `aceita/rejeitada/quarentena` a cada Observation (sanity-check, outlier). Materializa a **fila
  de quarentena**. É o que impede um `R$ 49` de virar "novo menor histórico!!". Risco existencial
  do §14. (§5.2 Data Quality)

- [ ] **G2-F4 — Provider piloto + scheduler.** 1 adaptador real (a loja mais usada — ver
  viabilidade §14.1), rodando via Laravel Scheduler + queue `database`. Provider é infra; o
  domínio só sabe "este Watch Target usa este provider". Manual continua sendo um provider. (§5.2 Provider, §13)

- [ ] **G2-F5 — Saúde da fonte na UX.** Indicador de saúde do scraper na tabela "por loja"
  (`⚠ 4 dias` = defasado); o botão "Registrar preço" vira **fallback** explícito; aviso calmo
  quando a fonte quebra. Mesmo componente de frescor do G0-F2-T3. (§9.4, §10.3)

## Notas

- Projetar Observation → Price Record como **projeção** (Observation é bruto rico; Record é
  canônico limpo). Nunca fazer os dois serem a mesma coisa (armadilha #3, §5.4).
- Volume é minúsculo; nada de fila distribuída ou data warehouse (§13).
