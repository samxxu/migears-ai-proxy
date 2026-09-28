# migears-ai-proxy — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Size / 体量 | src 478 lines (305 net) · 22 tests · 6 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 0 · P2 1 · P3 3 · other 1 |
| Answered / 已回复 | 2 of 5 |
| Waiting / 等待回复 | `P2-1`, `P3-2`, `P3-3` |

| id | level | status | title |
|---|---|---|---|
| [`P2-1`](issues/P2-1.md) | P2 | **open** | The upstream read timeout defaults to no limit: CURLOPT_TIMEOUT => 0 … |
| [`P3-1`](issues/P3-1.md) | P3 | **rejected** | `AiProxyException::from()` has no caller anywhere in src, tests or … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | Client-abort detection sits only in the chunk branch, so … |
| [`P3-3`](issues/P3-3.md) | P3 | **open** | The `post()` docblock types `$headers` as `array<string,string>` while … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Verdict / 结论

Every previous item is fixed and the module finally has real integration tests. What remains is one production-relevant default and three documentation/cleanup points. The headline risk: nothing bounds the upstream read timeout by default.

上一轮问题全部修复，模块首次有了真正的集成测试。剩余是一条与生产相关的默认值问题加三条文档与清理项。最值得注意的是：上游读超时默认无任何上界。

## Fixed since the last round / 本轮已修复确认

上一轮 6 项全部修复：cURL 选项合并改 array_replace（caller wins）、失败判定改 non-2xx、Emitter 改 php://output、心跳经 CURLOPT_PROGRESSFUNCTION 打通、curl_exec 返回值与 errorBody 上限、bootstrap 自包含、版本对齐。ISSUES.md 里「自审计以来无提交」的说法已过期。 

## Test gaps / 测试盲区

The 499 client-abort branch has no test; the idle-heartbeat test only asserts "at least one idle callback in 2s", not steady keep-alive during a long silence; there is no test for a hung upstream (the default-timeout risk).

499 断连分支无用例；心跳用例只断言「2 秒内至少一次 idle 回调」，未证明长静默期间持续保活；无「上游挂起」用例（即默认无超时的风险点）。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
