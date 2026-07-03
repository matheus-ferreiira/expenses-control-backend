# Implementation Backlog — Monitor de Preços (Vault)

> Este é o **plano de execução** do módulo de monitoramento de preços. Ele traduz o
> [documento de visão](../price-monitor-vision.md) em Epics, Features e Tasks implementáveis.
> A visão diz **o quê e por quê**; este backlog diz **em que ordem e como**.
>
> **Regra de ouro:** implementamos **uma Epic por vez**, e dentro dela **uma Task por vez**.
> Cada Task é uma spec pequena, bem delimitada e com objetivo claro — é assim que o Claude
> produz as melhores implementações e é assim que evitamos retrabalho.

---

## 1. Como o backlog está organizado

| Nível | O que é | Mapeamento |
|-------|---------|-----------|
| **Epic** | Um marco que entrega valor sozinho e não invalida a UX anterior | 1:1 com os **Graus de automação G0–G5** (§11 da visão) |
| **Feature** | Um entregável coeso dentro de uma Epic (uma capacidade nova) | agrupa Tasks relacionadas |
| **Task** | Uma unidade de trabalho implementável em uma sessão | back + front + testes |

Cada arquivo `epic-*.md` é uma Epic. O [00-roadmap.md](00-roadmap.md) define a ordem das sprints
e o estado de cada Epic.

---

## 2. Esquema de IDs

```
G1-F2-T3
│  │  └── Task 3
│  └───── Feature 2
└──────── Epic / Grau G1
```

IDs são **estáveis**: uma vez criados, não se renumeram (mesmo que a Task seja cancelada — vira
`~~G1-F2-T3~~ (cancelada)`). Isso permite referenciar uma Task em commits, PRs e conversas.

**Referência em commits:** `feat(prices): âncoras de preço [G1-F1-T2]`

---

## 3. Estados de uma Task

| Estado | Marca | Significado |
|--------|-------|-------------|
| A fazer | `[ ]` | ainda não começou |
| Em andamento | `[~]` | sendo implementada agora (só **uma** por vez) |
| Feita | `[x]` | implementada **e** com testes passando |
| Bloqueada | `[!]` | depende de algo não resolvido (anotar do quê) |
| Cancelada | `~~texto~~` | decidimos não fazer (manter o registro do porquê) |

Uma Task só vira `[x]` quando cumpre o **Definition of Done** (§5).

---

## 4. Definition of Ready (antes de começar uma Task)

Uma Task está pronta para implementar quando:

- [ ] O objetivo cabe em **uma frase**.
- [ ] Os **critérios de aceite** estão escritos (como sei que terminou?).
- [ ] As entidades/arquivos afetados estão nomeados.
- [ ] As dependências (outras Tasks) estão `[x]`.
- [ ] Não viola nenhum **Princípio Fundamental** (§0.2 da visão).

Se algo acima falta, a Task é **refinada** antes de codar — não se começa no escuro.

---

## 5. Definition of Done (para marcar `[x]`)

Alinhado às regras de qualidade do projeto ([back/CLAUDE.md](../../CLAUDE.md) ·
[front/CLAUDE.md](../../../front/CLAUDE.md)):

**Backend**
- [ ] Migration com UUID PK + índices + `decimal(15,2)` para dinheiro.
- [ ] Model com `HasUuids`, `SoftDeletes`, casts tipados, scopes, factory + `newFactory()`.
- [ ] Fluxo Controller → Service → Action respeitado (controller thin).
- [ ] Policy de ownership registrada em `AppServiceProvider`.
- [ ] Resource no envelope padrão.
- [ ] **Teste de feature** cobrindo: happy path, isolamento de usuário, 403 alheio, 422 inválido,
      lógica crítica. `php artisan test --filter=...` verde.
- [ ] `./vendor/bin/pint` sem alterações.

**Frontend**
- [ ] Tipos em `@types` espelhando o backend, sem `any`.
- [ ] Service com `unwrap()`; Store Pinia (Setup API); componente `<script setup lang="ts">`.
- [ ] Barrel exports atualizados.
- [ ] `npm run type-check` + `npm run build` sem erros.

**Ambos**
- [ ] Respeita os Princípios Fundamentais e os limites do §0.3 (o sistema aconselha, nunca age; etc.).
- [ ] O documento de visão foi atualizado se alguma decisão de design mudou.

---

## 6. Fluxo de trabalho com o Claude

```
1. Escolher a Epic ativa (ver 00-roadmap.md).
2. Expandir/refinar as Tasks da Epic (Definition of Ready).
3. Implementar UMA Task por vez:
     Claude implementa → testes no mesmo commit → marcar [x].
4. Ao fechar todas as Tasks da Epic → revisar Definition of Done da Epic → próxima Epic.
```

**Por que uma Task por vez:** o Claude entrega implementações muito melhores com uma spec
pequena e um objetivo claro do que com "refaça o módulo". Cada `[x]` é um ponto de checkpoint
seguro para commit.

---

## 7. Índice das Epics

| Epic | Grau | Arquivo | Detalhe |
|------|------|---------|---------|
| **G0 — Fundação & modo manual** | G0 | [epic-g0-fundacao.md](epic-g0-fundacao.md) | Task-level |
| **G1 — Inteligência sobre o manual** | G1 | [epic-g1-inteligencia.md](epic-g1-inteligencia.md) | Task-level |
| **G2 — Coleta piloto (1 loja)** | G2 | [epic-g2-coleta.md](epic-g2-coleta.md) | Feature-level |
| **G3 — Multi-provider & URL** | G3 | [epic-g3-multi-provider.md](epic-g3-multi-provider.md) | Feature-level |
| **G4 — Decisão & backtesting** | G4 | [epic-g4-decisao.md](epic-g4-decisao.md) | Feature-level |
| **G5 — Planejamento & wishlists** | G5 | [epic-g5-planejamento.md](epic-g5-planejamento.md) | Feature-level |

> As Epics G2–G5 estão em nível de Feature de propósito: são "futuro" na visão. Cada uma é
> **refinada em Tasks na véspera de sua sprint**, quando o aprendizado das anteriores já existe.
