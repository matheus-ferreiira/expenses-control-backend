# Roadmap & Ordem das Sprints — Monitor de Preços

> Define **em que ordem** as Epics são executadas e **por quê essa ordem**. Mapeia 1:1 nos
> Graus de automação (§11) e no Roadmap plurianual (§12) da [visão](../price-monitor-vision.md).

---

## 1. Princípio de sequenciamento

> **Cada Epic entrega valor sozinha e não invalida a UX da anterior.** Nunca começamos uma
> camada de cima sem a de baixo estar sólida (as 8 camadas da §4). Descritivo antes de
> preditivo. Modelo certo antes de inteligência em cima dele.

A promessa central — *"ele me avisa a hora de comprar"* — **se valida 100% com dado manual**
(§11.1). Por isso o esforço inicial é todo em G0–G1, **zero scraper**.

---

## 2. Grafo de dependências

```
G0 Fundação & modo manual   ← consolida o Ledger e alinha o modelo de domínio
      │  (Ledger com shape correto: proveniência, validade, moeda, sem URL no record)
      ▼
G1 Inteligência              ← âncoras + briefing + notificação em 2 eventos
      │  (só lê o Ledger; prova a promessa sem automação)
      ▼
G2 Coleta piloto (1 loja)    ← Watch Target c/ saúde + 1 provider + scheduler + gate de qualidade
      │  (aprende o custo real de manutenção antes de multiplicar)
      ▼
G3 Multi-provider & URL      ← mais providers + cadastro por URL + resolução de identidade
      │
      ▼
G4 Decisão & backtesting     ← Recommendation gravada + confiança separada de força
      │
      ▼
G5 Planejamento              ← wishlists com sequenciamento + preditivo (baixa confiança)
```

Setas = "não faz sentido começar antes de". Dentro de uma Epic, as Features podem ter ordem
própria (documentada em cada arquivo).

---

## 3. Estado das Epics

| Epic | Sprint | Estado | Entrega o valor de... |
|------|--------|--------|-----------------------|
| **G0** — Fundação & modo manual | Sprint 1 | ⬜ Não iniciada | Ledger sólido + registro manual sem fricção |
| **G1** — Inteligência | Sprint 2 | ⬜ Não iniciada | "ele me avisa a hora de comprar" (sobre dado manual) |
| **G2** — Coleta piloto | Sprint 3+ | ⬜ Não iniciada | 1 loja se atualiza sozinha; falha visível |
| **G3** — Multi-provider & URL | — | ⬜ Não iniciada | comparar entre lojas automático; cadastro por URL |
| **G4** — Decisão & backtesting | — | ⬜ Não iniciada | conselho datado que presta contas |
| **G5** — Planejamento | — | ⬜ Não iniciada | sequenciar compras de uma wishlist |

Legenda: ⬜ não iniciada · 🟡 em andamento · ✅ concluída.
**Atualizar esta tabela sempre que uma Epic mudar de estado.**

---

## 4. Recomendação: por que G0 inclui "Fundação do Modelo"

O código atual (`app/Domains/Prices/`) já roda o **modo manual** — mas o *shape* do modelo
ainda não é o da visão:

- a `url` mora no `PriceRecord`, não no alvo de observação (armadilha #2 do §5.4);
- não há separação **Observation** (bruto) ↔ **Price Record** (canônico);
- não há **proveniência** (de onde veio o dado) nem flag de **validade**;
- não há **moeda** nem **facetas de preço** (à vista/Pix/cartão/frete);
- `ProductStatus` é enum plano (`tracking/purchased/discarded`), não o lifecycle com re-track
  da **Decisão C** (§5.3).

Como G1 (Âncoras, briefing) e tudo acima **leem o Ledger**, alinhar o modelo agora custa horas;
descobrir depois que a série está torta custa dias de recomputo e migração. Por isso **G0
carrega o alinhamento mínimo do modelo** — não um big-bang, só o suficiente para o Ledger nascer
com o shape que dura (Princípios 3, 4, 7 da §0.2).

> **Decisão do dono:** aprovar o alinhamento no G0, ou tratar como Epic separada "G0.5"?
> _Default recomendado: dentro do G0._ (Registrar aqui a decisão quando tomada.)

---

## 5. Convenção de sprint

- **Uma Epic ativa por vez.** Não abrir G1 antes de G0 estar ✅.
- No início de cada sprint: **refinar as Tasks** da Epic (Definition of Ready do
  [README](README.md#4-definition-of-ready-antes-de-começar-uma-task)).
- No fim: revisar o **Definition of Done** da Epic e atualizar a tabela da §3.
- Commits referenciam a Task: `feat(prices): ... [G0-F1-T2]`.

---

## 6. Fora de escopo (limites deliberados — §0.3 da visão)

Nenhuma Epic deste roadmap deve introduzir: comparador de mercado global, checkout/compra
automática, descoberta de produtos, previsão vestida de certeza, coleta em tempo real, crawler
de propósito geral ou features sociais. Se uma Task começar a puxar para lá, **a Task está
errada**, não o limite.
