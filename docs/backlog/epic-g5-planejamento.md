# Epic G5 — Planejamento & Wishlists

> **Grau:** G5 · **Estado:** ⬜ Não iniciada · **Nível:** Feature (refinar em Tasks na véspera)
> **Refs da visão:** §5.2 (Wishlist), §5.3-D, §7.2 (wishlist com estratégia), §7.3 (features
> futuras), §6.2 (preditivo por último).

## Objetivo

Elevar o engine do nível do produto para o nível do **conjunto**: sequenciar compras de uma
wishlist e introduzir preditivo — sempre rotulado como baixa confiança, só com dado denso.

## Valor entregue

*"GPU: compre agora (mínimo raro). CPU: espere (Prime Day em ~3 semanas). Fonte: quando quiser
(estável). Sequência ótima economiza ~R$ 520."* (§7.2). O briefing passa a dizer **o que fazer**.

## Pré-requisitos

**G4 concluída** (recomendação com confiança) e **meses de dado denso** — preditivo sem base é
o caminho mais rápido para destruir confiança (§6.2).

## Princípios que esta Epic não pode violar

- **Não promete previsão** como certeza; preditivo é sinal fraco, rotulado (§0.3-4, §6.2).
- **Wishlist deriva totais**, não os armazena como verdade (armadilha #9). O histórico do total
  pode ser snapshotado.
- **Não fabrica desejo nem descobre produtos** — age só sobre o que o usuário já quer (§0.3-3).

## Definition of Done da Epic

- [ ] Wishlist com orçamento; Product↔Wishlist muitos-para-muitos (Decisão D, §5.3).
- [ ] Sequenciamento de compras no nível do conjunto, explicável.
- [ ] Preditivo presente **apenas** rotulado como baixa confiança, e só onde há dado denso.
- [ ] Totais derivados, não armazenados; série histórica do total opcional via snapshot.

---

## Features (a expandir em Tasks quando a sprint começar)

- [ ] **G5-F1 — Wishlist (plano com orçamento).** Nome, orçamento, produtos (M:N). Derivados:
  total hoje/semana passada/economia/gap — calculados, não armazenados (§5.2, armadilha #9).

- [ ] **G5-F2 — Sequenciamento no nível do conjunto.** O engine do G4 opera sobre a wishlist:
  o que comprar agora vs. esperar, com a economia da sequência ótima estimada (§7.2).

- [ ] **G5-F3 — Série temporal do total da wishlist.** O total do conjunto vira gráfico (§7.2);
  snapshot do total ao longo do tempo (a única coisa que vale persistir).

- [ ] **G5-F4 — Preditivo rotulado (baixa confiança).** Velocidade de queda, volatilidade como
  traço do produto, memória de sazonalidade (BF/Prime Day/nova geração) — tudo sinal fraco,
  rotulado (§6.2, §7.3).

- [ ] **G5-F5 — Camada anti-impulso (opcional).** *"Isso não está em nenhuma wishlist e você
  definiu foco em economizar — tem certeza?"* Opinativo, faz parecer consultor, não cupom (§7.3).

## Notas

- Refinamento futuro nomeado: **"slots"** de wishlist ("a vaga da GPU, satisfeita por A, B ou C",
  com quantidade e obrigatório/opcional) — §5.2 Wishlist.
- Features de horizonte além do G5 (multi-moeda/importação, engine que presta contas a longo
  prazo) vivem em §12 Fase 6+ da visão, não neste backlog ainda.
