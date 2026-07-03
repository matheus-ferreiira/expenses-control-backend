# Epic G4 — Decisão & Backtesting

> **Grau:** G4 · **Estado:** ⬜ Não iniciada · **Nível:** Feature (refinar em Tasks na véspera)
> **Refs da visão:** §6 (Decision Engine), §6.1 (força ≠ confiança), §6.3 (auto-avaliação),
> §6.4 (promoção falsa), §5.2 (Recommendation), §10.3 (cartão de recomendação).

## Objetivo

Transformar o badge de meta em um **conselho datado, gravado e explicável** — com força e
confiança como eixos separados — e fechar o loop de accountability via backtesting.

## Valor entregue

*"Bom momento ★★★★☆ — menor preço em 8 meses, 3% abaixo do típico, em estoque (confiança:
média)."* E, semanas depois, o engine se cobra: *"eu disse COMPRE a R$ 3.200; o mínimo dos 60
dias seguintes foi R$ 2.950 — errei o timing em 8%."* É o que separa brinquedo de produto sério.

## Pré-requisitos

**G1 concluída** (descritivo maduro) e idealmente **G2/G3** (dado denso). `PricePurchase` já
existe — é a semente do backtesting (§6.3).

## Princípios que esta Epic não pode violar

- **Recomendação forte ≠ confiança alta** — dois eixos independentes (§6.1). Sem separar, o
  sistema mente com cara de autoridade.
- **Descritivo primeiro, preditivo muito depois** (§6.2). G4 é descritivo/prescritivo, **não** preditivo.
- **Toda recomendação é explicável** (Princípio 2) e **gravada** (senão não há backtesting, armadilha #11).
- **O sistema aconselha, nunca age** (Princípio 6).
- **Decision Engine só lê o Ledger** — nunca acoplado a providers/lojas (armadilha #12).

## Definition of Done da Epic

- [ ] Cada recomendação grava ação + **força** + **confiança** + **motivos**, datada.
- [ ] O cartão do §10.3 substitui/eleva o badge de meta, na mesma superfície.
- [ ] Backtesting compara recomendação gravada contra o que aconteceu depois (via Purchase e Ledger).
- [ ] Detecção de promoção falsa contra a própria história (§6.4).
- [ ] Zero previsão vestida de certeza.

---

## Features (a expandir em Tasks quando a sprint começar)

- [ ] **G4-F1 — Recommendation (entidade gravada).** O conselho datado: ação, força, confiança,
  motivos, snapshot do estado que o gerou. Gravar é o que permite o backtesting (§5.2, armadilha #11).

- [ ] **G4-F2 — Decision Engine descritivo.** Lógica que pesa histórico + âncoras + meta e emite
  força **e** confiança separadas (§6.1). Só lê o Ledger. Semente = `GoalStatus` + âncoras do G1.

- [ ] **G4-F3 — Cartão de recomendação na tela de produto.** O bloco "💡 Bom momento ★★★★☆" do
  §10.3, com força, confiança e motivos em uma frase. Amadurece o badge, sem tela nova.

- [ ] **G4-F4 — Backtesting / auto-avaliação.** O engine grava o que recomendou → usuário compra
  (`PricePurchase`) → semanas depois o engine se cobra contra o mínimo do período seguinte (§6.3).
  Vale mesmo que o usuário **não** tenha seguido o conselho.

- [ ] **G4-F5 — Detecção de promoção falsa.** O "de/por" inflado exposto contra a própria
  história: *"esse '40% off' é mentira: estava mais barato mês passado"* (§6.4). Killer feature BR.

## Notas

- Confiança é **sempre declarada** junto com a força (§6.1, Princípio 8).
- O conselho gravado permanece como "o que o sistema pensava naquele momento", independente da
  decisão humana (§0.1 passo 5).
