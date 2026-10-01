# migears-ai-proxy — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **P2 open** |
| Size | src 323 lines (net) · 29 tests · 4 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 1 · P3 2 · other 0 |
| Settled | 3 of 6 |
| Waiting on the owner | `P2-1`, `P3-4` |
| Waiting on the coordinator | _nothing_ |
| Waiting on the reviewer | `P3-1` |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **accepted** | A slow upstream that sends one byte every idle-timeout seconds never … |
| [`P3-1`](issues/P3-1.md) | P3 | **rejected** | `AiProxyException::from()` has no caller anywhere in src, tests or … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | Client-abort detection sits only in the chunk branch, so … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | The `post()` docblock types `$headers` as `array<string,string>` while … |
| [`P3-4`](issues/P3-4.md) | P3 | **open** | A non-chunked upstream with no `Content-Length` that closes mid-body is … |
| [`G2`](issues/G2.md) | - | **verified** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **3** of 6 |
| By status | `open` 1 · `accepted` 1 · `rejected` 1 |
| Waiting on | owner 2 · reviewer 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `accepted` | owner | A slow upstream that sends one byte every idle-timeout seconds never … |
| **P3** | [`P3-1`](issues/P3-1.md) | `rejected` | reviewer | `AiProxyException::from()` has no caller anywhere in src, tests or … |
| **P3** | [`P3-4`](issues/P3-4.md) | `open` | owner | A non-chunked upstream with no `Content-Length` that closes mid-body is … |

## Verdict

A compact streaming AI-proxy handler with proper SSE parsing and idle-timeout watchdog; a slow-trickle attack can hold a PHP worker indefinitely by sending one byte per timeout window.

## Fixed since the last round

All three prior P3 items confirmed fixed; G2 strict flags complete; all 5 flags load-bearing.

## Test gaps

No test for the slow-trickle scenario (one byte per timeout window); no test for upstream connection failure mid-stream; no test for response body exceeding memory limit.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-ai-proxy — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **P2 待修** |
| 体量 | src 323 行（净）· 29 个用例 · 4 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 1 · P3 2 · 其他 0 |
| 已了结 | 3 / 6 |
| 等模块主 | `P2-1`, `P3-4` |
| 等协调人 | _无_ |
| 等评审方 | `P3-1` |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **accepted** | 慢速上游每空闲超时秒发一字节，永远不会触发看门狗——流始终「活跃」，无限占用 PHP worker。看门狗只对完全静默生效，不对涓流生效。 |
| [`P3-1`](issues/P3-1.md) | P3 | **rejected** | AiProxyException::from() 在 src、tests、tools 中零调用；默认 Emitter 打开 … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | 断连检测只在 chunk 分支，idle 期间不检查 connection_aborted()。README … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | post() 的 docblock 把 $headers 标为 … |
| [`P3-4`](issues/P3-4.md) | P3 | **open** | 没有 `Content-Length` 的非分块上游若在正文中途关闭，会被报成成功。真实 SSE … |
| [`G2`](issues/G2.md) | - | **verified** | 严格开关：`phpunit.xml.dist` … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **3** / 6 |
| 按状态 | `open` 1 · `accepted` 1 · `rejected` 1 |
| 等在谁 | 模块主 2 · 评审方 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P2** | [`P2-1`](issues/P2-1.md) | `accepted` | 模块主 | 慢速上游每空闲超时秒发一字节，永远不会触发看门狗——流始终「活跃」，无限占用 PHP worker。看门狗只对完全静默生效，不对涓流生效。 |
| **P3** | [`P3-1`](issues/P3-1.md) | `rejected` | 评审方 | AiProxyException::from() 在 src、tests、tools 中零调用；默认 Emitter 打开 … |
| **P3** | [`P3-4`](issues/P3-4.md) | `open` | 模块主 | 没有 `Content-Length` 的非分块上游若在正文中途关闭，会被报成成功。真实 SSE … |

## 结论

一个精简的流式 AI 代理处理器，SSE 解析与空闲超时看门狗齐全；慢速涓流攻击可在每个超时窗口发一字节，从而无限占用 PHP worker。

## 本轮已修复确认

All three prior P3 items confirmed fixed; G2 strict flags complete; all 5 flags load-bearing.

## 测试盲区

无慢速涓流场景测试（每超时窗口一字节）；无上流连接中途失败测试；无响应体超出内存限制测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
