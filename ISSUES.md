# migears-ai-proxy — Known Issues / 已知问题

> Generated from the miGears Full-Module Code Review Report (4th round, 2026-09-27).
> This file has two regions. Everything above **Owner feedback** is generated from the report — do
> not edit it there. The **Owner feedback** region belongs to the module maintainer: write into it,
> and it is preserved verbatim when the file is regenerated.
> A `fixed` reply is verified against the code by the reviewer before the finding is closed; a
> `rejected` reply is either accepted as a false positive or answered with counter-evidence.
>
> 本文件分两个区域。**「负责人反馈」之前的全部内容**由评审报告生成，请勿在该区修改；
> **「负责人反馈」区**归模块负责人所有，重新生成时会原样保留。
> 标注 `fixed`（已修复）的回复会被评审对照代码核实后才关闭；标注 `rejected`（不认同）的，
> 评审要么采纳为误报，要么给出反驳证据。
>
> 摘自 miGears 全模块代码评审报告（第四轮，2026-09-27）。

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Findings / 问题 | P0 0 · P1 0 · P2 1 · P3 3 |
| Size / 体量 | src 478 lines (305 net) · 22 tests · 6 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## Verdict / 结论

Every previous item is fixed and the module finally has real integration tests. What remains is one production-relevant default and three documentation/cleanup points. The headline risk: nothing bounds the upstream read timeout by default.

上一轮问题全部修复，模块首次有了真正的集成测试。剩余是一条与生产相关的默认值问题加三条文档与清理项。最值得注意的是：上游读超时默认无任何上界。

## Fixed since the last round / 本轮已修复确认

上一轮 6 项全部修复：cURL 选项合并改 array_replace（caller wins）、失败判定改 non-2xx、Emitter 改 php://output、心跳经 CURLOPT_PROGRESSFUNCTION 打通、curl_exec 返回值与 errorBody 上限、bootstrap 自包含、版本对齐。ISSUES.md 里「自审计以来无提交」的说法已过期。 

## Open findings / 未修问题


### P2

**P2-1** — `src/Stream/Upstream.php:79`

- EN: The upstream read timeout defaults to no limit: CURLOPT_TIMEOUT => 0 with only CONNECTTIMEOUT => 10, and neither OpenAiClient nor Proxy sets one. A half-open upstream can hold a PHP worker forever; the heartbeat only keeps the browser side alive. Callers can now override it, but the default is unbounded and the README never says so.
- 中文: 上游读超时默认无上界：CURLOPT_TIMEOUT => 0，只有 CONNECTTIMEOUT => 10，OpenClient 与 Proxy 都不设。半开的上游可以永久占用一个 PHP worker；心跳只保证浏览器这一侧。调用方现在可以覆盖，但默认无界且 README 未提示。
- Verification / 验证: static / 仅静态推断


### P3

**P3-1** — `src/AiProxyException.php, src/Stream/Emitter.php:42`

- EN: `AiProxyException::from()` has no caller anywhere in src, tests or tools; and the default Emitter opens `php://output` without ever closing it.
- 中文: AiProxyException::from() 在 src、tests、tools 中零调用；默认 Emitter 打开 php://output 后从不 fclose。
- Verification / 验证: static / 仅静态推断

**P3-2** — `src/Proxy.php:46`

- EN: Client-abort detection sits only in the chunk branch, so `connection_aborted()` is never checked while idle. The README says forwarding "stops the moment the browser disconnects", but in practice it waits for the next delta.
- 中文: 断连检测只在 chunk 分支，idle 期间不检查 connection_aborted()。README 称「浏览器断开即停止转发」，实际要等下一个 delta 才发现。
- Verification / 验证: static / 仅静态推断

**P3-3** — `src/Stream/Upstream.php:24-27`

- EN: The `post()` docblock types `$headers` as `array<string,string>` while the implementation and callers pass a list of raw header lines ("Content-Type: application/json"). Reading the signature gives the wrong mental model.
- 中文: post() 的 docblock 把 $headers 标为 array<string,string>，而实现与调用方传的是原始头行列表（"Content-Type: application/json"）。读签名会得到错误的心智模型。
- Verification / 验证: static / 仅静态推断

## Test gaps / 测试盲区

The 499 client-abort branch has no test; the idle-heartbeat test only asserts "at least one idle callback in 2s", not steady keep-alive during a long silence; there is no test for a hung upstream (the default-timeout risk).

499 断连分支无用例；心跳用例只断言「2 秒内至少一次 idle 回调」，未证明长静默期间持续保活；无「上游挂起」用例（即默认无超时的风险点）。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: none on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。

## Owner feedback / 负责人反馈

<!-- OWNER-FEEDBACK:BEGIN -->
<!-- 渠道说明 / channel notice — 跨模块协调人发布，长期有效 / issued by the cross-module coordinator, standing
     ISSUES.md 是本模块「完整」的问题讨论与修复渠道，不只是评审结论的存放处。
     ISSUES.md is this module's COMPLETE issue-discussion-and-fix channel, not merely where review verdicts land.

     1. 每位负责人只对自己模块负责。对别的模块有意见、疑问、反证或改动建议，写入「对方模块」的 ISSUES.md，
        不要写在自己模块里。
        Each owner is responsible for their own module only. Opinions, questions, counter-evidence and
        change requests about ANOTHER module go into THAT module's ISSUES.md, never into your own.
     2. 在对方模块的文件里注明你是谁：模块名 + 身份。署名是硬要求，不署名则无法追溯来源。
        Sign it in the other module's file: your module name and your role. Signing is mandatory; an
        unsigned entry cannot be traced back to its author.
     3. 署名格式 / signature forms, so the source is distinguishable:
          reviewer — migears-full-review   评审方
          coordinator — cross-module       跨模块协调人
          owner — migears-<module>         其他模块负责人
     4. 结论文本一律带状态词：accepted / fixed / rejected / deferred / question / new-evidence。
        无署名条目下一轮可能被按新发现重新评级。
        Sign conclusions with one status word: accepted / fixed / rejected / deferred / question /
        new-evidence. An unsigned entry may be re-graded as a new finding in the next round.
     5. 开工之前先通读本文件：把每条开启条目按证据评估（签名条目也算），再把你接受的条目与自己的工作一并执行，
        不要拆成两轮。每条都要有状态词。
        Read this file before starting work: evaluate every open item on its evidence, signed entries
        included, then execute the ones you accept together with your own work in one pass. Every item
        gets a status word. -->

<!-- Maintainers: reply under each finding's `### <id>` heading and keep the headings, so the
     reviewer can map your reply to the finding. Status vocabulary, one word followed by your
     reasoning and any evidence:
       accepted      you agree; it will be fixed
       fixed         you believe it is already fixed in the code (the reviewer verifies this)
       rejected      you disagree — give the reason; the reviewer either accepts it as a false
                     positive or answers with counter-evidence
       deferred      deliberate, out of scope for now — give the reason
       question      you need a decision or clarification first
       new-evidence  you have additional facts bearing on the finding
     You may also add findings of your own under `### New — <short title>`.

     负责人：请在对应 `### <编号>` 标题下逐条回复，并保留标题以便评审对应。
     状态词（一个词 + 理由与证据）：
       accepted      认同，将会修复
       fixed         认为代码里已经修好（评审会对照代码核实）
       rejected      不认同——请给理由；评审要么采纳为误报，要么给出反驳证据
       deferred      有意暂缓或超出范围——请给理由
       question      需要先明确或决策
       new-evidence  补充与本次结论相关的新事实
     也欢迎在 `### New — <简短标题>` 下补充你发现的问题。 -->

### P2-1
<!-- 负责人反馈 / owner response here -->

### P3-1
<!-- 负责人反馈 / owner response here -->

### P3-2
<!-- 负责人反馈 / owner response here -->

### P3-3
<!-- 负责人反馈 / owner response here -->
<!-- 跨模块条目 / cross-module items — 由跨模块协调人提出，非本轮评审 finding。口径见工作区根目录 `migears-engineering-gates.md`。
      Filed by the cross-module coordinator, not by the round's review. Standard: `migears-engineering-gates.md` at the workspace root. -->

### G2

- EN: Strict flags: `phpunit.xml.dist` currently sets none of the five. The standard is all five — `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests` — which 11 of 27 modules set. Missing here: `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests`. Turn them on and make the suite green; run `./vendor/bin/phpunit` and `composer analyse` before and after, and expect the first run to surface real warnings. If a flag genuinely cannot be turned on, reply `deferred` with the failing test and the reason instead of leaving the suite red.
- 中文: 严格开关：`phpunit.xml.dist` 目前五个开关一个都没开。标准是五个全开——`failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`——27 个模块中 11 个如此。本模块缺 `failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`。请打开并让套件保持全绿；改动前后各跑一次 `./vendor/bin/phpunit` 与 `composer analyse`，第一次跑出真警告是预期内的。若某个开关确实无法打开，请回复 `deferred` 并给出失败的用例与原因，而不是把套件留在红灯状态。
- Reply with one status word (`accepted` / `fixed` / `rejected` / `deferred` / `question`). / 请回复一个状态词（`accepted` / `fixed` / `rejected` / `deferred` / `question`）。
coordinator — cross-module

**fixed** — All five strict flags are now on in `phpunit.xml.dist`, plus the three `displayDetailsOn*` attributes, matching the `migears-data-structure` reference shape. Every pre-existing attribute (`bootstrap="tests/bootstrap.php"`, `colors`, `cacheDirectory`, the `<testsuites>`/`<source>` structure) is preserved.

`phpunit.xml.dist` (phpunit element) now carries:
`failOnWarning="true" failOnNotice="true" failOnDeprecation="true" failOnRisky="true" beStrictAboutOutputDuringTests="true" displayDetailsOnTestsThatTriggerWarnings="true" displayDetailsOnTestsThatTriggerNotices="true" displayDetailsOnTestsThatTriggerDeprecations="true"`.

Evidence — before the change (flags off):
`./vendor/bin/phpunit` → `OK (29 tests, 49 assertions)`, exit 0.
`./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`, exit 0.

After turning the flags on:
`./vendor/bin/phpunit` → `OK (29 tests, 49 assertions)`, exit 0. The first strict run surfaced no warnings/notices/deprecations/risky tests, so no underlying fix was needed and the cost was zero.
`./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`, exit 0.

Commit: see the module commit on `main`. No behaviour or compatibility risk: the change only tightens PHPUnit's local failure criteria; runtime code is untouched.

owner — migears-ai-proxy

<!-- OWNER-FEEDBACK:END -->
