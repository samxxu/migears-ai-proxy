# migears-ai-proxy — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 328 lines (net) · 29 tests · 6 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 3 · other 0 |
| Settled | 4 of 7 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | `P3-4` |
| Waiting on the reviewer | `P3-1`, `P3-5` |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | A slow upstream that sends one byte every idle-timeout seconds never … |
| [`P3-1`](issues/P3-1.md) | P3 | **rejected** | `AiProxyException::from()` has no caller anywhere in src, tests or … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | Client-abort detection sits only in the chunk branch, so … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | The `post()` docblock types `$headers` as `array<string,string>` while … |
| [`P3-4`](issues/P3-4.md) | P3 | **question** | A non-chunked upstream with no `Content-Length` that closes mid-body is … |
| [`P3-5`](issues/P3-5.md) | P3 | **fixed** | The README says the model client parses upstream data: lines and … |
| [`G2`](issues/G2.md) | - | **verified** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **3** of 7 |
| By status | `question` 1 · `rejected` 1 · `fixed` 1 |
| Waiting on | coordinator 1 · reviewer 2 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P3** | [`P3-1`](issues/P3-1.md) | `rejected` | reviewer | `AiProxyException::from()` has no caller anywhere in src, tests or … |
| **P3** | [`P3-4`](issues/P3-4.md) | `question` | coordinator | A non-chunked upstream with no `Content-Length` that closes mid-body is … |
| **P3** | [`P3-5`](issues/P3-5.md) | `fixed` | reviewer | The README says the model client parses upstream data: lines and … |

## Verdict

Every item state matches the code and the timeout ruling is documented in both halves; the model client’s deliberate drop of whitespace-only deltas is correct but still undocumented.

## Fixed since the last round

P2-1's ruling is landed: the total timeout stays unbounded and both README halves now say only silence is bounded, with the cost written down and a finite-timeout curl option shown. The other four items are consistent with the code.

## Test gaps

The Emitter has no test for a failed fopen of php://output (which would silently discard all output); Upstream::post has no direct test for the 64KB error-body cap; extraHeaders values are never checked for a colon or a newline.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-ai-proxy — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 328 行（净）· 29 个用例 · 6 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 3 · 其他 0 |
| 已了结 | 4 / 7 |
| 等模块主 | _无_ |
| 等协调人 | `P3-4` |
| 等评审方 | `P3-1`, `P3-5` |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | 慢速上游每空闲超时秒发一字节，永远不会触发看门狗——流始终「活跃」，无限占用 PHP worker。看门狗只对完全静默生效，不对涓流生效。 |
| [`P3-1`](issues/P3-1.md) | P3 | **rejected** | AiProxyException::from() 在 src、tests、tools 中零调用；默认 Emitter 打开 … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | 断连检测只在 chunk 分支，idle 期间不检查 connection_aborted()。README … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | post() 的 docblock 把 $headers 标为 … |
| [`P3-4`](issues/P3-4.md) | P3 | **question** | 没有 `Content-Length` 的非分块上游若在正文中途关闭，会被报成成功。真实 SSE … |
| [`P3-5`](issues/P3-5.md) | P3 | **fixed** | README 称模型客户端解析上游 data: 行并「转发文本增量」，但 trim 后为空的增量（纯 "\n\n"）被静默丢弃，转发 … |
| [`G2`](issues/G2.md) | - | **verified** | 严格开关：`phpunit.xml.dist` … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **3** / 7 |
| 按状态 | `question` 1 · `rejected` 1 · `fixed` 1 |
| 等在谁 | 协调人 1 · 评审方 2 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P3** | [`P3-1`](issues/P3-1.md) | `rejected` | 评审方 | AiProxyException::from() 在 src、tests、tools 中零调用；默认 Emitter 打开 … |
| **P3** | [`P3-4`](issues/P3-4.md) | `question` | 协调人 | 没有 `Content-Length` 的非分块上游若在正文中途关闭，会被报成成功。真实 SSE … |
| **P3** | [`P3-5`](issues/P3-5.md) | `fixed` | 评审方 | README 称模型客户端解析上游 data: 行并「转发文本增量」，但 trim 后为空的增量（纯 "\n\n"）被静默丢弃，转发 … |

## 结论

每条条目状态都与代码一致，超时裁定已在两半文档写明；模型客户端刻意丢弃空白增量是对的，但尚未写进文档。

## 本轮已修复确认

P2-1's ruling is landed: the total timeout stays unbounded and both README halves now say only silence is bounded, with the cost written down and a finite-timeout curl option shown. The other four items are consistent with the code.

## 测试盲区

Emitter 无「php://output 打开失败」用例（那会让输出被静默丢弃）；Upstream::post 的 64KB 错误正文封顶无直接用例；extraHeaders 的取值从不校验冒号或换行。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
