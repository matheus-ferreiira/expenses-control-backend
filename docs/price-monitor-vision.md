# Vault — Monitor de Preços · Visão de Produto & Arquitetura Conceitual

> **Documento vivo.** Registra a visão completa do módulo de monitoramento de preços do Vault:
> produto, arquitetura conceitual, modelo de domínio, UX real e roadmap. Atualizado a cada
> rodada de discussão. **Não contém código, migrations ou implementação** — apenas conceito,
> produto, experiência e trade-offs.
>
> **Princípio editorial deste documento (leia antes de tudo):** existe **um só sistema**. O
> "modo manual de hoje" e o "modo automático do futuro" **não são dois produtos** — são o
> mesmo sistema rodando com mais ou menos automação ligada. Por isso, todo fluxo é descrito
> com a dualidade **🖐 Manual (hoje)** vs **🤖 Automático (futuro)** lado a lado, e a UX é
> desenhada para **nunca precisar ser reescrita** quando a automação entrar.

| Versão | Data | O que mudou |
|--------|------|-------------|
| v0.1 | 2026-07-03 | Documento inicial: diagnóstico, arquitetura em camadas, crítica à visão, riscos, roadmap. |
| v0.2 | 2026-07-03 | §13 Modelo de Domínio Conceitual. |
| v0.3 | 2026-07-03 | Fechadas 4 decisões de fundação do modelo. |
| v0.4 | 2026-07-03 | §14 Fluxos Reais de Uso + §15 definição de MVP. |
| v0.5 | 2026-07-03 | §16 UX real / telas concretas. |
| **v1.0** | 2026-07-03 | **Reescrita completa e reorganização.** Reenquadramento central: um só sistema, automação como grau de maturidade (não MVP à parte). Dualidade Manual/Automático lado a lado em todos os fluxos e telas. |
| v1.1 | 2026-07-03 | Adicionada **PARTE 0 — O produto, em essência**: Jornada Completa do Produto (§0.1), Princípios Fundamentais (§0.2) e O que o sistema NÃO faz (§0.3). Narrativa que amarra a visão antes da arquitetura. |

---

# PARTE 0 — O PRODUTO, EM ESSÊNCIA

> Se você só vai ler uma parte deste documento, leia esta. Aqui está o que o módulo *é*, qual
> problema resolve, quais regras ele nunca quebra e onde ele deliberadamente para de crescer —
> tudo antes de qualquer detalhe de arquitetura ou implementação.

**O módulo em uma frase:** *um assistente pessoal que vigia o preço das coisas que você quer
comprar e te diz — com honestidade e no momento certo — quando vale a pena comprar, quando
esperar e onde está mais barato.*

**O problema que resolve:** comprar tecnologia bem é um jogo de *timing* e *memória*. Preços
sobem e descem, promoções mentem ("de/por" inflado), e ninguém consegue lembrar quanto uma GPU
custava há 4 meses. Sem ajuda, você compra por impulso num pico, ou perde a promoção real
porque não estava olhando na hora. O Vault carrega essa memória e essa vigilância por você.

## 0.1 A Jornada Completa do Produto (o ciclo de vida dentro do Vault)

Não são telas — é a *história* de um produto, do desejo à revenda. Segue um exemplo real: uma
placa de vídeo. O ciclo é um **loop**, não uma linha: cada compra ensina o sistema, cada
revenda informa a próxima decisão.

```
  INTENÇÃO ─► VIGILÂNCIA ─► SINAL ─► RECOMENDAÇÃO ─►[decisão humana]─► COMPRA
                  ▲            (gravada, datada)                          │
                  │                                                        ▼
            RE-TRACK ◄── POSSE ◄── VEREDITO (o sistema se avalia) ◄────────┘
             (upgrade)     │
                           ▼
                        REVENDA ─► CUSTO REAL DE POSSE ─┐
                           │                             │
                        DESCARTE                         └─► alimenta futuras decisões ↺
                     (arquivado, história preservada)
```

1. **Intenção.** Você decide: "meu PC vai precisar de uma GPU nova." Cria o produto no Vault com
   uma *intenção* e uma *meta de preço* — ainda não é uma compra, é um desejo declarado. O
   produto nasce no estado **acompanhando**.
2. **Vigilância.** O histórico começa a crescer — você registrando (hoje) ou o coletor (amanhã).
   Semana após semana, as **âncoras** se formam (mínimo histórico, típico) e o produto revela
   sua "personalidade": oscila muito? cai devagar? tem sazonalidade? O sistema observa em
   silêncio, sem te incomodar.
3. **Sinal.** Algo cruza um limiar: "novo menor preço em 8 meses". Nasce um Signal — que sobe no
   briefing ou dispara uma notificação. O sistema quebra o silêncio *porque valeu a pena*.
4. **Recomendação.** Você abre o produto. O Decision Engine pesa histórico + âncoras + sua meta
   e diz: *"Bom momento ★★★★☆ — menor preço em 8 meses, 3% abaixo do típico, em estoque
   (confiança: média)."* **Este conselho é gravado, datado, com seus motivos** — semente do
   passo 7.
5. **Decisão humana.** Você decide — comprar, esperar ou ignorar. **O sistema aconselha, nunca
   age.** Ele ilumina; quem dirige é você. Faça o que fizer, o conselho gravado permanece como
   "o que o sistema pensava naquele momento".
6. **Compra.** Você compra e registra: preço pago, loja, data, garantia. O produto transiciona
   **acompanhando → comprado**. O monitoramento pode pausar (agora é seu), mas a história é
   preservada inteira.
7. **Veredito (auto-avaliação).** Semanas depois, o sistema olha pra trás com honestidade:
   *"você pagou R$ 3.150; o mínimo dos 60 dias seguintes foi R$ 2.950; eu recomendei COMPRE
   ★★★★ — antecipei um pouco."* Isso vale mesmo que você **não** tenha seguido o conselho. É
   assim que o engine **presta contas e ganha confiança ao longo dos anos**.
8. **Posse.** O produto é seu. Pode continuar sendo acompanhado de leve (valor de revenda atual,
   garantia) ou ficar dormente.
9. **Re-track / Upgrade.** O lifecycle permite voltar a **acompanhando** para o próximo ciclo. O
   sistema pode até provocar: *"você comprou esta GPU há 18 meses por R$ 3.150; a sucessora já
   está mais barata que isso — hora do upgrade?"* A compra antiga permanece como história.
10. **Revenda.** Você vende a antiga e registra a venda. Agora o sistema fecha o loop maior e
    calcula o **custo real de posse**: pagou R$ 3.150, vendeu por R$ 1.800 após 18 meses =
    R$ 1.350 líquido, ~R$ 75/mês. Esse número informa a próxima decisão de compra.
11. **Descarte.** Quando o produto deixa de importar, vai para **descartado** — arquivado da
    lista ativa, mas **nunca apagado**. História preservada para sempre.

**A ideia central:** o valor não está em nenhum passo isolado, mas no *loop se fechando*. A
compra valida a recomendação; a recomendação avaliada melhora a próxima; a revenda revela o
custo real; o custo real afina o próximo desejo. Um sistema que você usa por anos vira um
assistente que conhece o seu padrão de compra.

## 0.2 Princípios Fundamentais (as regras que nunca se quebram)

Regras de **produto**, não decisões técnicas. Toda evolução futura deve respeitá-las; se uma
feature nova violar um princípio, o errado é a feature.

1. **Silêncio é melhor que ruído.** O sistema prefere não avisar a avisar mal. Notificação que
   não se prova útil é notificação que você desliga — e aí perde todas as outras.
2. **Toda recomendação é explicável.** Nunca um "compre" sem motivos. Sem caixa-preta: se o
   sistema não consegue explicar em uma frase por que, ele não recomenda.
3. **O Ledger é a única fonte da verdade.** Todo gráfico, âncora, insight, recomendação e alerta
   deriva dele. Nenhuma camada acima inventa ou guarda dado paralelo.
4. **Manual e automático produzem exatamente o mesmo resultado.** A origem do dado é invisível
   para todas as camadas de valor. Um preço que você digitou e um que o scraper leu são
   cidadãos iguais no histórico.
5. **O usuário sempre pode substituir a automação.** Scraper quebrado, loja sem provider, dado
   errado — sempre existe o fallback manual. A máquina nunca bloqueia o humano.
6. **O sistema aconselha, nunca age.** Ele nunca compra, nunca decide, nunca clica por você. A
   decisão é sempre humana; o papel do sistema é iluminar, não dirigir.
7. **Fato bruto é imutável; história nunca se apaga.** Observações não são editadas — se erradas,
   são anotadas ou invalidadas. Dado ruim é *invalidado*, nunca deletado. Tudo que é derivado
   pode ser recalculado a qualquer momento.
8. **Honestidade sobre incerteza.** O sistema diz "ainda não sei" quando tem pouco dado.
   Confiança é sempre declarada junto com a força da recomendação. Um "talvez honesto" vale mais
   que uma "certeza" falsa.
9. **Confiança se conquista prestando contas.** O sistema avalia as próprias recomendações contra
   o que aconteceu depois. Ele admite quando errou. Autoridade sem accountability é achismo.
10. **Comparar contra a própria história, não contra a vitrine.** "Barato" é relativo ao
    histórico real do produto, jamais ao "de/por" que a loja anuncia.
11. **Baixa fricção no que o humano faz.** Registrar um preço é questão de segundos. Fricção mata
    o hábito; sem hábito não há dado; sem dado não há sistema.
12. **Não fabricamos engajamento.** Sem streaks, sem gamificação, sem truques para te trazer de
    volta. Sucesso é você **economizar**, não você **abrir o app**. O melhor dia do produto é um
    em que ele te deixou em paz.
13. **Foco pessoal, não escala corporativa.** Cada decisão prioriza a simplicidade e o uso real
    de uma pessoa por muitos anos — não generalidade teórica nem robustez de sistema de milhões.

## 0.3 O que o sistema NÃO faz (limites deliberados)

Dizer não é o que mantém o produto focado e sustentável ao longo dos anos. Cada limite abaixo é
uma escolha consciente, com o porquê.

1. **Não é um comparador do mercado inteiro** (estilo Buscapé/Zoom). Só acompanha o que *você*
   escolheu vigiar. *Por quê:* ser user-scoped elimina o problema insolúvel de identidade global
   e mantém o foco em *decisão pessoal*, não em busca genérica.
2. **Não compra nem executa transações.** Sem checkout, carrinho ou compra automatizada.
   *Por quê:* aconselhar ≠ agir (Princípio 6). Transacionar traz pagamento, responsabilidade e
   risco que não pertencem a um assistente pessoal.
3. **Não descobre produtos nem gera desejo.** Não sugere "coisas novas pra você comprar" como um
   recomendador de e-commerce. Age só sobre o que você já quer. *Por quê:* é assistente de
   *timing*, não motor de consumo — alinhá-lo a "te fazer comprar mais" corromperia todo o resto.
4. **Não promete previsão.** Quando existir, previsão é sinal fraco e rotulado, nunca garantia de
   "vai custar X em Y dias". *Por quê:* previsão vestida de certeza destrói a confiança, que é o
   ativo central do produto.
5. **Não persegue tempo real.** Não é bot de sniping de promoção-relâmpago com atualização por
   segundo; a coleta é de baixa frequência. *Por quê:* custo e complexidade sem retorno para
   compra ponderada, e evita a guerra de anti-bot agressivo.
6. **Não cobre 100% das lojas.** Poucas fontes sólidas valem mais que muitas quebradas.
   *Por quê:* manutenção solo é finita; cobertura total é insustentável e mina a confiança no dado.
7. **Não é gestor financeiro nem controla seu orçamento.** Ele decide *quando e onde comprar um
   item*; não cuida de contas, faturas e fluxo de caixa — isso é o módulo Finance do Vault.
   *Por quê:* separação de responsabilidades mantém cada módulo afiado.
8. **Não é inventário pessoal completo.** Toca "posse" só o suficiente para fechar o loop de
   decisão (custo de posse, revenda) — não cataloga tudo que você tem, garantias de tudo, etc.
   *Por quê:* o foco é a *decisão de compra*, não a gestão patrimonial.
9. **Não é social nem colaborativo.** Sem compartilhar listas, comparar com amigos ou comunidade
   de deals. *Por quê:* é uma ferramenta íntima e pessoal; social exige moderação, privacidade e
   escala — é outro produto.
10. **Não é um crawler de propósito geral.** Não indexa a web nem varre lojas atrás de produtos;
    lê apenas as URLs que você apontou. *Por quê:* foco, sustentabilidade e postura legal defensável.
11. **Não substitui o seu julgamento.** É, por definição, ferramenta de apoio. A responsabilidade
    da compra é sempre sua. *Por quê:* coerência com o Princípio 6 — e humildade honesta.

---

# PARTE I — VISÃO & PRODUTO

## 1. Onde estamos hoje (fato, não aspiração)

Stack real (confirmado no código):
- **Back:** Laravel 12 / PHP 8.3 / MySQL, DDD por domínios. Domínio `Prices` já maduro:
  `PriceCategory`, `PriceStore`, `PriceProduct` (com `target_price`, `launch_price`,
  `status: tracking/purchased/discarded`), `PriceRecord` (preço + data + loja + url opcional),
  `PricePurchase` e `PriceSale`.
- **Front:** Vue 3 + TS + Pinia + Tailwind, gráfico em Chart.js (`PriceHistoryChart.vue`).
- **Infra latente, ainda não usada:** fila `database` configurada; Scheduler disponível; stubs
  de config para Resend/Postmark/SES/Slack. **Zero** scraping, **zero** job agendado,
  **zero** notificação implementada.

Tradução: **a fundação de dados é sólida.** O sistema hoje roda em "modo manual" — mas é o
mesmo sistema que um dia rodará com automação. Nada precisa ser reescrito; o trabalho é
*ligar camadas* progressivamente. `GoalStatus::fromPrice()` já é a "single source of truth"
da regra de meta — exatamente o padrão que a camada de Inteligência vai reaproveitar.

## 2. A tese de produto

O valor hoje é "memória de preços". O valor que buscamos é **assistente de decisão de compra**.

**Discordância registrada:** a meta *não* é "abrir todos os dias". Monitor de preço é de baixa
frequência — ninguém compra GPU toda semana. Otimizar engajamento diário leva a manufaturar
ruído. A meta certa:

> **O sistema ganha o direito de te interromper no momento exato, e quando você abre, te
> respeita com uma resposta única e clara.** Sucesso não é "abri todo dia" — é "ele pegou a
> promoção que eu teria perdido" e "ele me impediu de comprar num pico".

O sistema passa **95% do tempo em silêncio** e 5% sendo extremamente útil. A superfície diária
defensável é um **briefing de 0 a 3 itens**; e quando nada mudou, ele diz isso com orgulho:
*"nada a fazer hoje, tudo estável"*. Um sistema com coragem de recomendar **não fazer nada** é
mais premium do que um que inventa urgência.

## 3. O princípio da dualidade — um só sistema, automação como grau

Este é o eixo que mantém tudo coerente ao longo dos anos.

> O **modo manual** é o sistema completo com as camadas de automação **dormentes**. Nele, **o
> ser humano é o Provider**: você lê o preço e digita. O **modo automático** é o *mesmo*
> sistema com essas camadas **acordadas**: um scraper é o Provider. **As telas são idênticas
> nos dois modos.** O que muda é invisível para a UX — apenas *quem preenche os campos*.

Consequências que valem ouro:
- **A UX nunca é reescrita.** A tela de produto, o gráfico, a tabela "por loja", as âncoras, as
  notificações — tudo funciona igual seja você ou um robô alimentando o dado.
- **O indicador de frescor** ("atualizado hoje" / "⚠ há 4 dias") funciona idêntico nos dois
  modos: no manual ele diz "faz X dias que *você* não atualiza"; no automático, "faz X dias
  que o *scraper* não consegue ler". Mesmo componente, mesma semântica.
- **A automação é um dial, não um botão.** Pode estar ligada para uma loja e desligada para
  outra, no mesmo produto, ao mesmo tempo. Kabum automática, Amazon manual — convivendo.
- **Todo fluxo neste documento é descrito nos dois modos**, lado a lado, justamente para provar
  que a evolução é contínua e sem rupturas.

---

# PARTE II — ARQUITETURA CONCEITUAL

## 4. Arquitetura em camadas

A separação de responsabilidades é o maior acerto da visão. Oito camadas; cada uma só conhece
a de baixo.

```
┌─────────────────────────────────────────────────────────────┐
│ 8. APRESENTAÇÃO   dashboard · briefing diário · wishlists      │
├─────────────────────────────────────────────────────────────┤
│ 7. SINAIS         eventos → notificações triadas · anti-fadiga │
├─────────────────────────────────────────────────────────────┤
│ 6. DECISÃO        recomendação + confiança + backtesting       │  prescritivo
├─────────────────────────────────────────────────────────────┤
│ 5. ANALYTICS      âncoras de preço · estatísticas · tendência  │  descritivo
├─────────────────────────────────────────────────────────────┤
│ 4. LEDGER         histórico canônico · proveniência · validade │ ← gate de qualidade
├─────────────────────────────────────────────────────────────┤
│ 3. COLETA         providers → observações (burro, isolado)     │
├─────────────────────────────────────────────────────────────┤
│ 2. MONITORAMENTO  watch targets (produto↔loja↔URL↔saúde)       │
├─────────────────────────────────────────────────────────────┤
│ 1. CATÁLOGO       produtos · identidade · lojas · categorias   │
└─────────────────────────────────────────────────────────────┘
```

**Onde a dualidade Manual/Automático vive:** só nas camadas 2 e 3. No manual, a Coleta é você
digitando (Provider = Manual) e o Monitoramento é um atalho de URL. No automático, a Coleta é
um scraper agendado e o Monitoramento ganha saúde/ciclo de vida. **Camadas 4 a 8 não sabem nem
se importam com qual modo produziu o dado** — é isso que torna o sistema evolutivo.

## 5. Modelo de domínio conceitual

Vocabulário desenhado para durar. Princípios que regem tudo:

1. **Separe o que é do mundo do que é seu.** Realidade externa (um produto existe, um preço foi
   observado) vs. sua intenção (quero comprar isso, essa é minha meta). Misturar os dois é a
   raiz de quase todo erro duradouro.
2. **Fato bruto é imutável; interpretação é derivada.** Se algo pode ser recomputado, não é
   fato — é derivação.
3. **Identidade é separada de localização.** "O produto" é uma coisa; "onde é vendido" é outra.
4. **Tudo é pessoal (user-scoped).** Você não constrói um catálogo global do mundo, mas *a sua*
   lista. Isso mata boa parte do problema de identidade (§7.2).

### 5.1 Mapa de dependências (quem conhece quem)

Seta = "depende de / referencia". Embaixo, o mais estável; em cima, o mais derivado.

```
                    Notification          (entrega ao usuário)
                         │ deriva de
                      Signal              (fato notável: "algo mudou")
                         │ deriva de
   Recommendation ───────┤               (conselho datado: força + confiança + motivos)
        │ lê             │ deriva de
        │             Price Anchor        (níveis de referência nomeados)
        │                │ deriva de
        └──────────► Price Record          ← LEDGER: verdade canônica e limpa
                         │ é projeção de
                    Observation            ← COLETA: fato bruto, imutável, rico
                         │ produzida por        (passa pelo gate de Qualidade)
                    Watch Target           ← MONITORAMENTO: "onde observar" + saúde
                       │      │
                  Product    Store         ← CATÁLOGO: identidade estável
                     │
                  Category

   Wishlist ──► Product        (agrupamento/plano; opcional sobre o catálogo)
   Purchase ──► Product, Store  (evento de aquisição; fecha o loop com Recommendation)
   Sale ──► Purchase            (revenda; 1:1)
```

### 5.2 As entidades, uma a uma

**Product — a identidade do que você quer possuir.** Âncora de identidade, independente de
onde é vendido. Guarda: identidade (nome/marca/modelo/specs/categoria), sua intenção (meta,
lifecycle), referências (lançamento). *Armadilha central:* confundir "produto" com "oferta em
uma loja" — a RTX 4070 *na Kabum* é uma oferta, não o produto; se amarrar produto a loja/URL,
quebra a comparação entre lojas na raiz.

**Store — um lugar que vende coisas.** Estável, independente de produtos. *Armadilha:* acoplar
Store a Provider — "Kabum, o varejista" ≠ "o mecanismo de ler a Kabum" (o mecanismo quebra e
muda; a loja não).

**Watch Target — a instrução permanente de "observe isto aqui".** Liga Product↔Store↔endereço.
É o *sujeito da coleta*. Guarda o vínculo, a URL/SKU, a estratégia (qual provider) e — o mais
importante — **saúde/ciclo de vida** (`ativo`, `quebrado`, `sem estoque`, `descontinuado`,
`pausado`; último check, último sucesso, falhas consecutivas).
- *Dualidade:* **🖐 manual** → é essencialmente um atalho de URL que você abre para conferir; a
  "saúde" é "faz quanto tempo *você* não atualizou". **🤖 automático** → é o que o scraper lê; a
  saúde é a do scraper.
- *Armadilha #1:* hoje a `url` mora no registro de preço — conflaciona "onde observo" (estável)
  com "o que observei" (evento). A URL sobe para o Watch Target.
- *Armadilha #2:* achar que saúde é detalhe operacional. "Essa fonte está defasada há 9 dias" é
  **estado de domínio de primeira classe e visível** — scraper quebrado é a realidade nº 1 de
  manutenção.
- *Decisão deliberada:* **não** criamos uma entidade "Offer/Listing" separada — Watch Target +
  última observação já representam "a oferta que me interessa" e seu estado atual.

**Observation — um fato bruto, imutável e rico, coletado de uma fonte.** Guarda (não só preço):
variantes de preço (à vista/cartão/**Pix**), **estoque**, preço-cheio vs. promocional,
**frete**, vendedor (1P/3P), *snapshot* da identidade (nome/SKU/imagem → alimenta cadastro
automático), **sinal de sucesso da coleta**, timestamp, **moeda**, qual provider.
- *Dualidade:* **🖐 manual** → uma observação com provider = Manual, sem Watch Target
  obrigatório; você preenche o que quiser (no mínimo o preço). **🤖 automático** → o scraper
  preenche tudo que a página expõe.
- *Armadilhas:* nunca **editar** uma observação (é fato histórico — anote/invalide); nunca
  fazer Observation = Price Record (bruto rico e possivelmente errado ≠ canônico limpo).

**Data Quality / Validation — o portão entre o bruto e o canônico.** Mais *política + veredito*
do que entidade: toda Observation recebe `aceita` / `rejeitada` / `em quarentena – revisar`,
com motivo. A UX concreta é a **fila de quarentena**.
- *Por que existe:* um `R$ 49` no lugar de `R$ 4.900` dispararia "novo menor preço histórico!!",
  envenenaria a média para sempre e mataria a confiança num único evento.
- *Dualidade:* **🖐 manual** → o portão é quase trivial (você é acurado por construção; pega
  só typo grosseiro). **🤖 automático** → o portão é essencial: sanity-check, outlier, quarentena.

**Price Record — a verdade canônica: o Ledger.** O preço limpo, confiável e **comparável** de
um produto, numa loja, num momento. É o que Analytics/Decisão/gráficos leem. Guarda o preço
"manchete" + facetas, a *proveniência* (de qual Observation veio, ou manual) e um flag de
**validade**. *Armadilhas:* apagar registro ruim em vez de **invalidar** (perde auditoria e
recomputo); tratar preço como escalar único (é feixe — ver decisão B em §5.3).

**Purchase & Sale — o evento que fecha o loop.** Purchase = você adquiriu de fato (preço pago,
quando, onde, garantia); é *compromisso/evento*, não observação de mercado. Sale = revenda
(1:1). Purchase **valida a Recommendation**: "o engine disse COMPRE a R$ 3.200; você pagou
R$ 3.150; o mínimo dos 60 dias seguintes foi R$ 2.950".

**Wishlist — um plano com orçamento e estratégia, não uma soma.** Palco para o Decision Engine
operar no nível do *conjunto* (sequenciar compras). Guarda nome, orçamento, os produtos e
derivados (total hoje/semana passada/economia/gap) — *derivados, não armazenados como verdade*
(o histórico do total, sim, vale snapshotar). Product↔Wishlist é muitos-para-muitos.
*Refinamento futuro nomeado:* "slots" ("a vaga da GPU, satisfeita por A, B ou C", com
quantidade e obrigatório/opcional).

**Provider — a capacidade de ler uma fonte** (mais infra que domínio). Estratégia/adaptador
("como ler a Kabum"); **Manual é um provider como outro qualquer** (elegante — unifica os dois
modos sob o mesmo conceito). O que é *do domínio* é o vínculo "este Watch Target usa este
provider". *Armadilha:* promover Provider a entidade central — o domínio acima da Coleta não
raciocina sobre providers.

**Decision Engine & Recommendation — o conselho, e o registro do conselho.** O *Engine* é a
lógica (prescritiva); a **Recommendation é a entidade** — o conselho *datado e gravado* (ação +
força + **confiança** + motivos). Gravar é o que permite o backtesting que transforma o engine
de achismo em algo que **presta contas**. Detalhes em §6.

**Signal & Notification — o fato notável, e a entrega.** *Signal* = o acontecimento digno de
nota ("novo menor histórico"), a *história do que foi relevante*. *Notification* = a mensagem
entregue por um canal. O **anti-fadiga vive na política entre os dois** (limiar, cooldown,
digest). *Armadilha:* notificar direto de um insight — perde o histórico de relevância e o
ponto único de controle de spam. Signals nascem de insights de **evento** (algo mudou);
insights de **estado** (é verdade agora) decoram o dashboard e não viram Signal.

### 5.3 Decisões de fundação fechadas (2026-07-03)

- **A. Observação manual NÃO exige Watch Target.** Observation referencia Product+Store sempre,
  e Watch Target *opcionalmente* (presente quando a origem foi automação). Dois caminhos de
  entrada convivendo — aceito conscientemente pela baixa fricção. É a materialização da
  dualidade no modelo.
- **B. Preço comparável canônico = melhor preço efetivo à vista/Pix.** É o "manchete" para
  gráficos e tendência. Cartão e frete são facetas guardadas à parte, não dirigem a série.
- **C. Ciclo de vida do produto é um lifecycle simples com re-track.** acompanhar → comprar →
  (voltar a acompanhar, ex.: upgrade) → descartar. Substitui o enum plano.
- **D. Product ↔ Wishlist é muitos-para-muitos.**

### 5.4 Erros de modelagem a evitar desde o dia zero

1. Confundir **Product (identidade)** com **Watch Target (onde é vendido)**.
2. Pôr **URL/origem no Price Record** em vez de no Watch Target.
3. Fazer **Observation = Price Record** (bruto vs. canônico).
4. **Mutar Observation** — anote/invalide, não edite.
5. **Apagar** dado ruim em vez de **invalidar**.
6. Assumir **BRL/Pix** no núcleo; não modelar **moeda** e facetas de preço.
7. Tratar **preço como escalar** em vez de feixe com um comparável escolhido.
8. **Status do produto** como enum plano em vez de lifecycle.
9. **Armazenar totais de wishlist** como verdade em vez de derivar.
10. **Notificar direto de insight**, sem a camada de Signal.
11. **Não gravar Recommendations** → sem backtesting, engine fica achismo.
12. **Acoplar Decision Engine a providers/lojas** — ele só lê o Ledger.
13. Construir **catálogo global** em vez de user-scoped → inferno de identidade.

---

# PARTE III — INTELIGÊNCIA

## 6. Decision Engine — a joia da coroa, e onde é mais fácil se queimar

O badge que já existe (`na meta / perto / acima`) é a **semente** do engine. Ele amadurece na
mesma superfície, sem tela nova.

### 6.1 Recomendação forte ≠ confiança alta
Dois eixos independentes. "★★★★★ Compre — **confiança alta**, 2 anos, mínimo em 8 meses" → aja.
"★★★★★ Compre — **confiança baixa**, 3 semanas de dados" → sinal fraco disfarçado de forte.
Sem separar, o sistema mente com cara de autoridade. Um assistente premium diz *"ainda não sei"*.

### 6.2 Descritivo constrói confiança; preditivo destrói (se apressado)
- **Descritivo** ("menor preço em 8 meses", "12% abaixo do típico") é **fato defensável**.
- **Preditivo** ("vai subir semana que vem") é **onde a credibilidade morre** se errar 2 vezes.

Regra: **descritivo primeiro, preditivo muito depois** e sempre rotulado como baixa confiança.

### 6.3 O recurso mais premium: o engine se auto-avalia
Você já tem `PricePurchase`. Feche o loop: o engine grava o que recomendou e por quê → você
compra → semanas depois ele se cobra: *"eu disse COMPRE a R$ 3.200; o mínimo dos 60 dias
seguintes foi R$ 2.950 — errei o timing em 8%."* Isso separa brinquedo de produto profissional.

### 6.4 Detecção de promoção falsa (killer feature para o Brasil)
O varejo BR infla "de/por". O insight matador não é "40% off" — é: *"esse '40% off' é mentira:
estava mais barato mês passado; o preço cheio é fictício."* Só possível porque você tem o
histórico próprio. Reagir ao que o produto **realmente vale contra a própria história**, não ao
que a loja *diz*.

## 7. Cadastro por URL, Wishlists e o que ainda não pensamos

### 7.1 Cadastro por URL — fácil vs. difícil
Extrair nome/marca/imagem/SKU/preço *de uma página* é fácil (mesma engine de coleta). **O
difícil é resolução de identidade:** linkar que a RTX 4070 da Kabum é a *mesma* da Pichau —
exatamente o que "comparar entre lojas" exige. Caminho honesto: auto-preenche os campos, mas
**humano confirma o match** com um clique. 100% automático aqui gera comparações erradas
silenciosas — pior que pedir um clique.

### 7.2 Wishlists — o plano com estratégia
A versão premium é o engine no nível do conjunto: *"GPU: compre agora (mínimo raro). CPU:
espere (Prime Day em ~3 semanas). Fonte: quando quiser (estável). Sequência ótima economiza
~R$ 520."* O total do conjunto também é uma série temporal digna de gráfico.

### 7.3 Features que ainda não pensamos
- **Preço verdadeiro = preço + frete + método de pagamento** (Pix vs. cartão vs. boleto muda
  tudo no BR).
- **Volatilidade como traço do produto** (uns oscilam → esperar compensa; outros decaem
  monotonicamente → esperar rende cada vez menos).
- **Velocidade de queda** ("caiu 5% e continua caindo" ≠ "caiu 5% e estabilizou").
- **Memória de sazonalidade** (Black Friday, Prime Day, nova geração derrubando a anterior).
- **Camada anti-impulso** ("isso não está em nenhuma wishlist e você definiu foco em economizar
  — tem certeza?"). Opinativo, faz parecer consultor, não cupom.
- **Multi-moeda / importação** (AliExpress, Amazon US) como eixo futuro.

## 8. Sinais & Notificações — anti-fadiga

Só insights de **evento** viram Signal; **estados** decoram o dashboard. Um Signal vira zero ou
uma Notification conforme relevância + cooldown, ou entra num digest. Regra: no máximo ~1
alerta por produto por janela, só cruzando limiar. Notificação que não se prova útil é
notificação que você desliga. Cópia real em §10.4.

---

# PARTE IV — EXPERIÊNCIA (UX REAL)

> Zero teoria. O produto pronto sendo usado. Cópia em PT-BR real, layouts concretos, tempos
> reais. **Cada tela mostra os dois modos lado a lado** — provando que a UX não muda, só quem
> alimenta o dado. Mocks em texto servem só para dar forma, não são especificação visual.

**Princípio que rege todos os fluxos:** num projeto pessoal o inimigo nº 1 é o **abandono**. Se
registrar um preço dói, você para. Se a notificação não se prova útil cedo, você desiste. Tudo
abaixo é desenhado para **sustentar o hábito**, não para exibir features.

## 9. Fluxos narrados (a experiência do início ao fim)

### 9.1 Primeiro uso — "decidi que quero um monitor"
1. **Criar produto (≈15s):** nome ("LG 27GP850") + meta opcional (R$ 1.800). Não pede specs —
   isso mata adoção.
2. **Dizer onde ficar de olho:** escolhe a loja (Kabum) e cola a URL.
   - **🖐 Manual:** a URL é um atalho clicável — você abre e confere.
   - **🤖 Automático:** a URL é o que o scraper lerá sozinho. *Mesmo campo, mesma tela.*
3. **Primeiro preço:**
   - **🖐 Manual:** você viu R$ 2.100, registra (3 toques).
   - **🤖 Automático:** o sistema já buscou e plotou o primeiro ponto.
4. Sensação: "agora eu esqueço e ele me chama."

### 9.2 Rotina — como o histórico cresce
- **🖐 Manual (hoje):** de vez em quando você abre a URL e registra o preço. O histórico cresce
  no seu ritmo. Um lembrete gentil ("faz 8 dias que você não atualiza o LG") ajuda o hábito.
- **🤖 Automático (futuro):** 1x/dia o agendador percorre as fontes ativas → Observação →
  portão de qualidade → Price Record. Você não faz nada e a linha se forma sozinha.
- **O que é idêntico:** em ambos, o resultado é um Price Record no mesmo gráfico. A tela não
  distingue quem escreveu.

### 9.3 Dia a dia — o que você vê ao abrir
Silêncio 95% do tempo. Ao abrir, um **briefing** de 0–3 itens; se nada mudou, um estado vazio
*confiante*. Notificação só em eventos fortes. **Idêntico nos dois modos** — no manual, os
eventos disparam a partir do que *você* registrou; no automático, do que o *scraper* trouxe.

### 9.4 Manutenção — quando a fonte "quebra"
- **🖐 Manual:** "quebra" = você parou de atualizar. O sistema mostra o preço como **defasado**
  ("último preço há 12 dias") e pode te cutucar de leve.
- **🤖 Automático:** o scraper falha (404, layout mudou). A saúde da fonte vira `quebrado`; o
  produto mostra o último preço marcado como defasado; um aviso calmo aparece.
- **A correção é a mesma porta nos dois modos:** abrir a URL, colar a nova se mudou, **ou
  registrar o preço na mão**. O sistema **nunca bloqueia** — scraper quebrado *degrada para o
  modo manual*, que é o estado natural de hoje. É a prova viva de que os dois modos são um só.

### 9.5 Decisão futura — como a experiência evolui
Mesma superfície ficando mais esperta: badge de meta → "menor preço em 6 meses, 14% abaixo do
típico" → cartão com força + confiança + motivos → sequenciamento no nível da wishlist.

## 10. Telas concretas — Manual vs Automático lado a lado

### 10.1 Home do módulo (= um briefing)

**Estado vazio (idêntico nos dois modos):**
```
┌───────────────────────────────────────────────┐
│   👀  Comece a vigiar um preço                  │
│   Cole o link de um produto e o Vault passa a   │
│   acompanhar o preço pra você.                  │
│   ┌─────────────────────────────────────────┐  │
│   │  Cole o link do produto…            [ + ]│  │
│   └─────────────────────────────────────────┘  │
│   Ex.: Kabum, Pichau, Terabyte, Mercado Livre   │
└───────────────────────────────────────────────┘
```

**Estado com dados (a mesma tela nos dois modos):**
```
┌───────────────────────────────────────────────┐
│  Preços                              [ + Novo ] │
├───────────────────────────────────────────────┤
│  HOJE                                           │
│  🎯 LG 27GP850 bateu sua meta — R$ 1.780 (Kabum)│
│  📉 RTX 4070: menor preço em 8 meses — R$ 2.950 │
├───────────────────────────────────────────────┤
│  ACOMPANHANDO (12)          [Comprados] [Todos] │
│  LG 27GP850        R$ 1.780  ▁▂▃▂▁  🟢 na meta   │
│  Kabum · em estoque          ▼ 4% (7d)          │
│  RTX 4070 Ventus   R$ 2.950  ▅▄▃▂▁  🟡 perto     │
│  Pichau · em estoque         ▼ 6% (7d)          │
└───────────────────────────────────────────────┘
```
- **🖐 Manual:** os preços da lista são os **últimos que você registrou**; o frescor reflete
  quando *você* atualizou. Itens muito defasados ganham um ⏳ discreto.
- **🤖 Automático:** os preços são os da **última coleta**; o frescor reflete o scraper.
- **O que NÃO muda:** layout, briefing no topo, ordenação por atenção, badges de meta,
  sparklines, o estado vazio confiante (`✓ Tudo tranquilo — nada exige ação hoje`).

### 10.2 Adicionar produto — colar 1 URL

```
┌───────────────────────────────────────────────┐
│  Cole o link do produto                         │
│  ┌─────────────────────────────────────────┐   │
│  │ https://kabum.com.br/produto/...          │   │
│  └─────────────────────────────────────────┘   │
└───────────────────────────────────────────────┘
```
- **🤖 Automático (futuro):** ~2s "buscando…" → preview preenchido: imagem, nome, categoria,
  loja (pelo domínio), **preço atual detectado**. A **única** pergunta é a meta ("Qual preço te
  faria comprar?"). `Salvar` → tela do produto com o 1º ponto já plotado.
- **🖐 Manual (hoje):** a mesma tela; o sistema registra a URL como atalho e **pede que você
  digite o preço atual você mesmo** (nome pode vir da própria URL ou você ajusta). `Salvar` →
  tela do produto com o 1º ponto (o que você digitou).
- **O que NÃO muda:** um único campo para começar, o preview *é* a confirmação (sem wizard), a
  meta é a única coisa que o sistema nunca sabe, ~15–20s de fluxo, loja não reconhecida cai
  para entrada manual (nunca beco sem saída).
- **Adicionar 2ª loja:** `+ Adicionar loja` → cola URL da Pichau → *"Isso parece ser o mesmo LG
  27GP850? Confirmar?"* (auto no futuro; no manual você confirma na hora ao cadastrar).

### 10.3 Tela de produto (o coração)

```
┌───────────────────────────────────────────────┐
│  ← LG UltraGear 27GP850-B          Acompanhando │
│  [img]   R$ 1.780  ↓  · Kabum · em estoque      │
│          🟢 Na meta (você queria R$ 1.800)      │
│  ─────────────────────────────────────────────  │
│  💡 Bom momento ★★★★☆                            │
│     menor preço em 8 meses · 3% abaixo do típico │
│  ─────────────────────────────────────────────  │
│   R$                            ·· meta 1.800    │
│   2100 ┤ ╲___     Kabum ──   Pichau ──          │
│   1900 ┤     ╲__ __/                             │
│   1780 ┤        ╲╱          ● hoje              │
│        └──────────────────────────────────────  │
│        [ 30d ]  90d   Tudo                       │
│  ─────────────────────────────────────────────  │
│  POR LOJA                                       │
│  Kabum      R$ 1.780  ↓   em estoque   hoje  🟢  │
│  Pichau     R$ 1.899      em estoque   hoje      │
│  Terabyte   R$ 1.950      sem estoque  ⚠ 4 dias  │
│  ─────────────────────────────────────────────  │
│  ÂNCORAS                                        │
│  Mínimo histórico R$ 1.750 (há 4 meses)         │
│  Mínimo 90 dias   R$ 1.890 · Típico  R$ 2.050    │
│  Lançamento       R$ 2.400                       │
│  ─────────────────────────────────────────────  │
│  O QUE MUDOU                                     │
│  • Novo menor preço em 8 meses      1 semana atrás│
│  • Caiu 5% na Kabum                    2 dias atrás│
│  • Você registrou manual              3 sem. atrás│
└───────────────────────────────────────────────┘
```
- **🖐 Manual:** a coluna "por loja" mostra o frescor do seu registro; o feed "O que mudou"
  inclui entradas "Você registrou manual"; um botão `Registrar preço` fica sempre à mão.
- **🤖 Automático:** o mesmo, mas o frescor é do scraper e o feed mostra "Atualizado
  automaticamente"; a coluna ganha o status de saúde da fonte (`⚠ 4 dias` = scraper defasado).
- **O que NÃO muda:** o gráfico multi-loja com meta e âncoras, a tabela por loja com frescor e
  estoque, os cards de âncora, o feed "O que mudou", o cartão de recomendação. **A tela é a
  mesma; a origem do dado é invisível.**

### 10.4 Registrar / atualizar um preço

- **🖐 Manual (o fluxo que faz ou quebra a adoção):** a partir do produto, `Registrar preço` →
  folha mínima: **produto pré-selecionado, loja lembrada da última vez, um campo de número**.
  Data = hoje por padrão. Confirmar em **poucos segundos**. Se levar mais que isso, você para
  de usar — este é o UX mais crítico do modo manual.
```
┌───────────────────────────────┐
│  Registrar preço · LG 27GP850  │
│  Loja:  [ Kabum ▾ ]            │
│  Preço: [ R$ 1.899        ]    │
│  Data:  [ hoje ▾ ]            │
│              [ Salvar ]        │
└───────────────────────────────┘
```
- **🤖 Automático:** este fluxo **desaparece do seu dia** — o scraper registra sozinho. Mas o
  botão continua existindo como **fallback** (fonte quebrada, loja sem provider, correção
  pontual). Nunca some do produto.
- **O que NÃO muda:** o resultado é um Price Record idêntico no mesmo histórico.

### 10.5 Notificações reais (cópia de verdade)

Idênticas nos dois modos — mudam só na origem do dado que as dispara.

**Envia:**
> 🎯 **LG 27GP850 bateu sua meta!** R$ 1.780 na Kabum (sua meta era R$ 1.800). Em estoque.

> 📉 **Novo menor preço em 8 meses:** RTX 4070 por R$ 2.950 na Pichau — R$ 340 abaixo do típico.

> 🔥 **Queda forte:** SSD 2TB caiu 22% hoje na Terabyte, R$ 640. *(Ainda acima do mínimo
> histórico de R$ 590 — pode cair mais.)*  ← honesto: é bom, mas não é o melhor de todos os tempos

**Digest semanal (opcional, discreto):**
> 📊 **Sua semana em preços:** 2 quedas relevantes · 1 produto perto da meta · 9 estáveis.
> Nada urgente.

**Lembrete só no modo manual (gentil, opcional):**
> ⏳ Faz 10 dias que você não atualiza o preço do LG 27GP850. Ainda de olho nele?

**NÃO envia:** preço mexeu R$ 5 · preço subiu (a não ser que você peça) · voltou a ter estoque
pelo mesmo preço · nag diário de "você acompanha 12 produtos" · segundo alerta do mesmo produto
dentro do cooldown.

### 10.6 Os 5 segundos diários (sem intenção de ver preços)
No painel principal do Vault, o cartão de Preços mostra sozinho:
```
┌───────────────────────┐        ┌───────────────────────┐
│  Preços                │        │  Preços                │
│  🎯 1 bateu a meta      │   ou   │  ✓ Tudo estável         │
│  R$ 1.780 · LG 27GP850 │        │  12 vigiados            │
└───────────────────────┘        └───────────────────────┘
```
Em 5s você lê: tem 1 coisa que vale olhar, ou está tudo em paz. **A ausência de alerta é
informação** — "nada que mereça seu dinheiro se mexeu". Idêntico nos dois modos; a diferença é
só se foi você ou o robô que manteve o dado atual.

---

# PARTE V — EXECUÇÃO & EVOLUÇÃO

## 11. Graus de automação — como o mesmo sistema evolui sem reescrever UX

Não há "MVP" e "produto final" como coisas diferentes. Há **um sistema** que liga automação
progressivamente. Cada grau reusa 100% das telas do anterior.

| Grau | O que está ligado | O que o usuário faz | O que muda na UX |
|------|-------------------|---------------------|------------------|
| **G0 — Manual** (hoje) | Catálogo, Ledger, Analytics, Sinais, Notificações | registra preços na mão | **nada a construir de tela nova depois** — já é o produto |
| **G1 — Inteligência sobre manual** | + Âncoras, briefing, 2 eventos de notificação | igual G0 | briefing no topo, âncoras no produto, alerta de meta/mínimo |
| **G2 — Coleta piloto (1 loja)** | + Watch Target c/ saúde, 1 provider, scheduler, portão de qualidade | quase nada p/ essa loja | surge indicador de saúde da fonte; botão manual vira fallback |
| **G3 — Multi-provider** | + mais providers, cadastro por URL, resolução de identidade | cola URLs | preview auto-preenchido; comparação entre lojas fica automática |
| **G4 — Decisão** | + Recommendation gravada, backtesting | consome recomendações | badge de meta vira cartão de recomendação c/ força+confiança |
| **G5 — Planejamento** | + Wishlist com sequenciamento, preditivo | planeja compras | wishlist ganha estratégia; briefing diz "o que fazer" |

**A regra de ouro:** cada grau **entrega valor sozinho** e **não invalida** a UX do anterior. É
por isso que descrevemos tudo com a dualidade — G0 e G5 são a mesma tela com mais camadas
acordadas por baixo.

### 11.1 O que faz o sistema já parecer "mágico" no G0/G1 (sobre dado manual)
Todo mundo acha que "o produto" é o scraping. **É a parte menos urgente.** A promessa central —
*"ele me avisa a hora de comprar"* — se valida **100% com dado manual**:
1. Produto com meta (existe). 2. Registro manual sem fricção (existe, revisar velocidade).
3. Histórico + loja mais barata (existe). 4. **Âncoras** (só matemática — barato, faz o
histórico *parecer* inteligente). 5. **Notificação em 2 eventos** (bateu meta · novo menor
histórico) por 1 canal. 6. **Briefing / estado vazio confiante.**

Se isso não te faz dizer "que legal" no manual, nenhum scraper salva. E note: quase tudo já
existe no domínio `Prices` — falta **âncoras**, **um canal de notificação ligado a 2 eventos** e
transformar a home num **briefing**.

## 12. Roadmap plurianual

Mapeia 1:1 nos graus de automação da §11.
- **Fase 0/1 (G0–G1):** inteligência sobre dado manual — âncoras, briefing, notificação nos 2
  eventos. Entrega a promessa sem uma linha de scraper.
- **Fase 2 (G2):** Watch Target com saúde + 1 provider piloto (a loja que você mais usa),
  scheduler + queue, falha visível. Aprender o custo real de manutenção antes de multiplicar.
- **Fase 3 (G3):** multi-provider, comparação entre lojas, cadastro semi-automático por URL com
  confirmação de identidade.
- **Fase 4 (G4):** Analytics maduro + Decision Engine descritivo (força + confiança), sem
  previsão. Backtesting começa a acumular.
- **Fase 5 (G5):** wishlists com sequenciamento; sazonalidade e preditivo — só com meses de dado
  denso, rotulado como baixa confiança.
- **Fase 6+:** anti-impulso comportamental, multi-moeda/importação, o engine que presta contas.

## 13. Escala sem complicar (projeto pessoal)

**Não construa escala que você não tem.** O volume (produtos × lojas × diário) é minúsculo —
anos cabem em MySQL comum. Nada de microserviço, fila distribuída ou data warehouse. O
orçamento de complexidade vai **inteiro** para (1) a abstração provider→observação ser limpa e
(2) as regras de insight serem data-driven, não `if` espalhado. Laravel Scheduler + queue
`database` aguenta por anos. E: **não invista em scraper auto-reparável** — torne a quebra
visível + fallback manual fácil (o 80/20 honesto).

## 14. Riscos & armadilhas

| Risco | Gravidade | Mitigação |
|-------|-----------|-----------|
| **Dado ruim envenena insights** | 🔴 existencial | portão de qualidade + outlier + proveniência + validade |
| Scraper quebra silenciosamente | 🔴 alto | saúde do target como estado visível; degradar p/ manual |
| Recomendação errada destrói confiança | 🔴 alto | confiança separada de força + descritivo-first + backtesting |
| **Abandono (projeto pessoal)** | 🔴 alto | registro manual em segundos + notificação que se prova útil cedo |
| Fadiga de notificação | 🟠 médio | só eventos acima de limiar + cooldown + digest |
| Manutenção de scraper insustentável | 🟠 médio | poucas lojas sólidas + fallback manual sempre |
| Over-engineering | 🟠 médio | não construir escala inexistente |
| Match errado entre lojas | 🟠 médio | humano confirma identidade |
| Amazon anti-bot | 🟡 baixo | tratar como manual/API, não brigar com scraper |

### 14.1 Viabilidade por provider (realidade que importa pro roadmap)
| Provider | Viabilidade | Observação |
|----------|-------------|-----------|
| Manual | ✅ existe | fonte de verdade, sempre confiável; é o modo base |
| Kabum / Pichau / Terabyte | scraping HTML | sem API pública; risco de anti-bot em algumas |
| Mercado Livre | API pública decente | melhor caso automatizado do BR |
| Amazon | caso especial | PA-API exige afiliado c/ vendas; anti-bot forte → manual/API |
| APIs futuras | plugável | a abstração já suporta |

**Verdade incômoda:** 100% de cobertura não é realista para manutenção solo. Melhor 3–4 lojas
sólidas que 10 pela metade. Cada provider automático **falha de volta pro manual** — nunca
tudo-ou-nada.

## 15. Respostas diretas às perguntas do dono

- **O que falta na visão?** O gate de qualidade de dado e a separação confiança-vs-força.
- **O que eu faria diferente?** Não mirar "uso diário"; coletar Observação rica, não só preço;
  descritivo antes de preditivo; e tratar manual/automático como **um sistema só**.
- **Arquitetura conceitual melhor?** As 8 camadas (§4), com Ledger e descritivo/prescritivo
  separados, e a dualidade como princípio (§3).
- **Features valiosas não pensadas?** Promoção falsa, engine que se auto-avalia,
  preço-verdadeiro-com-frete/Pix, wishlist com sequenciamento.
- **O que parece profissional?** Honestidade sobre incerteza + backtesting da própria decisão.
- **O que faz consultar sempre?** Não é abrir todo dia — é confiar que ele te chama na hora
  certa e te respeita quando abre.
- **Como virar assistente de verdade?** Descritivo confiável → recomendação com confiança →
  sequenciamento de wishlist. Nessa ordem.
- **Escalável sem complicar?** §13 — complexidade só em provider e regras; infra simples.
- **Roadmap?** §12, mapeado nos graus de automação (§11).

---

## Glossário de conceitos (vocabulário do projeto)

- **Modo Manual / Automático:** o *mesmo* sistema com automação dormente ou acordada. No manual,
  você é o Provider; no automático, um scraper. As telas são idênticas.
- **Product:** identidade do que você quer possuir, independente de onde é vendido.
- **Store:** um varejista. Estável, independente de produtos e do mecanismo de leitura.
- **Watch Target:** o "onde observar" — produto↔loja↔URL↔saúde. Distinto do histórico.
- **Observação:** fato bruto, imutável e rico coletado de uma fonte (preço, estoque, frete...).
- **Data Quality / Validation:** política + veredito que barra dado ruim antes do Ledger.
- **Price Record / Ledger:** histórico canônico e limpo de preços, com proveniência e validade.
- **Âncora de preço:** nível de referência nomeado (mínimo histórico, preço BF, mediana...).
- **Purchase / Sale:** evento de aquisição / revenda. Fecha o loop e valida o Decision Engine.
- **Wishlist:** plano com orçamento e estratégia de timing sobre um conjunto de produtos.
- **Provider:** fonte de observação (Manual, Kabum, Mercado Livre...). Manual é um provider.
- **Decision Engine:** camada prescritiva — recomenda ação com força + confiança + motivos.
- **Recommendation:** o conselho *datado e gravado* — permite backtesting do engine.
- **Signal/Event:** fato notável ("novo menor preço"). Separado da entrega da notificação.
- **Notification:** a mensagem entregue por um canal. Anti-fadiga vive na política entre ambos.
- **Briefing:** resumo diário de 0–3 itens acionáveis. Pode dizer "nada a fazer".
- **Grau de automação (G0–G5):** o quanto de automação está ligado. Cada grau reusa a UX do
  anterior; não há "MVP" separado do "produto final".
