# Epic G3 — Multi-provider & Cadastro por URL

> **Grau:** G3 · **Estado:** ⬜ Não iniciada · **Nível:** Feature (refinar em Tasks na véspera)
> **Refs da visão:** §7.1 (cadastro por URL), §10.2 (colar 1 URL), §14.1 (viabilidade por provider).

## Objetivo

Adicionar mais providers e o cadastro semi-automático por URL, tornando a **comparação entre
lojas** automática — com o humano confirmando a resolução de identidade.

## Valor entregue

Colar uma URL preenche imagem/nome/categoria/loja/preço detectado; a única pergunta é a meta.
Uma 2ª loja para o mesmo produto é sugerida e **confirmada com um clique**.

## Pré-requisitos

**G2 concluída** — a abstração provider→observation e o gate de qualidade já provados em 1 loja.

## Princípio crítico desta Epic

**Resolução de identidade nunca é 100% automática** (§7.1). Auto-preenche os campos, mas o
**humano confirma o match** — 100% automático gera comparações erradas silenciosas, pior que
pedir um clique. Loja não reconhecida **cai para entrada manual**, nunca beco sem saída (§10.2).

## Definition of Done da Epic

- [ ] ≥ 2 providers automáticos funcionando + Manual como fallback universal.
- [ ] Cadastro por URL preenche preview; meta é a única coisa que o sistema pergunta.
- [ ] Match entre lojas exige confirmação humana; nenhum link silencioso.
- [ ] Loja sem provider degrada para manual sem beco sem saída.

---

## Features (a expandir em Tasks quando a sprint começar)

- [ ] **G3-F1 — Providers adicionais.** Mais 1–2 adaptadores sólidos (priorizar por viabilidade
  §14.1: Mercado Livre = melhor caso; Amazon = manual/API). Cada provider falha de volta pro manual.

- [ ] **G3-F2 — Extração por URL.** Mesma engine de coleta lê nome/marca/imagem/SKU/preço de uma
  página (a parte fácil, §7.1) e monta o preview do §10.2.

- [ ] **G3-F3 — Resolução de identidade assistida.** Ao adicionar 2ª loja, o sistema sugere
  "isso parece ser o mesmo produto?" e **pede confirmação** antes de linkar (a parte difícil,
  §7.1). Sem match silencioso.

- [ ] **G3-F4 — Fluxo "colar 1 URL" (§10.2).** Um único campo → preview preenchido em ~2s → só
  pergunta a meta → salva com o 1º ponto plotado. ~15–20s de fluxo. Loja não reconhecida → manual.

- [ ] **G3-F5 — Comparação entre lojas automática.** Com identidade resolvida, a tabela "por
  loja" e o gráfico multi-loja passam a ser alimentados por múltiplos providers.

## Notas

- Reusar tudo do G2 (Observation, gate, saúde). G3 é "mais fontes + identidade", não nova arquitetura.
- Não construir catálogo global — segue user-scoped (Princípio de modelo 4, §5).
