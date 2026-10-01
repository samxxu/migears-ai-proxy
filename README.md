# migears/ai-proxy

A minimal AI streaming proxy for PHP 8.1+ — relays OpenAI-compatible streamed
chat responses to the browser over Server-Sent Events (SSE).

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **Zero framework dependencies** — requires only PHP `^8.1`, `ext-curl`, `ext-json`
- **Real-time relay** — cURL `WRITEFUNCTION` streams chunk-by-chunk; never buffers the full payload
- **OpenAI-compatible** — works with DeepSeek, Qwen, Ollama, Together, ... via the `/chat/completions` SSE contract
- **Heartbeat** — emits `: ping` comments to keep proxies/gateways from idle-timeout
- **Stall-aware** — aborts the upstream after `idleTimeoutSeconds` (default 300s) with no body byte, so a silent upstream cannot pin a worker (only silence is bounded — see the note under Quick start)
- **Client-abort aware** — `connection_aborted()` is checked on every delta *and* every idle tick, so forwarding stops as soon as the disconnect is seen
- **Testable** — the upstream transport is injectable; full PHPUnit suite

## Boundaries

**In scope**

- Relay orchestration (`Proxy::stream()`): pull text deltas from a model client, push them to the SSE sink, inject `: ping` heartbeats, and abort on client disconnect — PSR-4 root `MiGears\AiProxy`.
- The SSE output sink (`Stream\Emitter`) and the cURL streamed upstream reader (`Stream\Upstream`): `data:` / `event:` / `id:` framing with default `text/event-stream` headers, under an idle watchdog.
- An OpenAI-compatible model client (`Model\OpenAiClient` behind `Model\ClientInterface`): parse the upstream `data:` SSE lines and forward text deltas. Zero framework dependencies — PHP `^8.1`, `ext-curl`, `ext-json` only.

**Not in scope (by design)**

- Authentication, authorization and request validation — the caller authenticates and validates before calling; that responsibility belongs to `migears/security`.
- Routing and framework integration — no router, controller or request object; the caller wires the proxy into its own controller.
- Rendering, retrying or logging upstream failures — transport, idle-timeout and non-2xx errors surface as `AiProxyException` for the caller.

## Installation

```bash
composer require migears/ai-proxy
```

## Quick start

```php
use MiGears\AiProxy\Proxy;
use MiGears\AiProxy\Stream\Emitter;
use MiGears\AiProxy\Model\OpenAiClient;

$emitter = new Emitter(); // writes to php://output
$client = new OpenAiClient(
    endpoint: 'https://api.deepseek.com/chat/completions',
    token:    getenv('DEEPSEEK_API_KEY'),
    model:    'deepseek-chat',
);

$proxy = new Proxy($emitter, heartbeatSeconds: 20);
$proxy->stream($client, [
    ['role' => 'user', 'content' => '讲个笑话'],
]);
```

Call this from your controller after validating/authenticating the request.

`idleTimeoutSeconds:` on `OpenAiClient` tunes how long a silent upstream is
tolerated before the call is aborted; `0` turns the watchdog off. Only silence
is bounded — total transfer time is deliberately unbounded, so a long
generation is never cut off mid-stream. The cost of that choice: an upstream
that sends one byte inside each idle window keeps the call alive and holds its
PHP worker. Pass `curlOpts: [CURLOPT_TIMEOUT => 600]` to `OpenAiClient` when
you want a total cap instead.

## Architecture

![migears/ai-proxy data flow](docs/architecture-en.svg)

Directory layout:

```
src/
├── AiProxyException.php
├── Proxy.php                      # orchestration
├── Stream/
│   ├── Emitter.php                # SSE output sink
│   └── Upstream.php               # cURL streamed reader
└── Model/
    ├── ClientInterface.php
    └── OpenAiClient.php
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT

---

# migears/ai-proxy

适用于 PHP 8.1+ 的极简 AI 流式代理。它把 OpenAI 兼容的**流式**对话响应，通过 Server-Sent Events（SSE）实时转发给浏览器。

## 特性

- **零框架依赖** — 仅要求 PHP `^8.1`、`ext-curl`、`ext-json`
- **实时转发** — 用 cURL `WRITEFUNCTION` 逐块读取，绝不整包缓冲
- **OpenAI 兼容** — 兼容 DeepSeek、通义千问、Ollama、Together 等 `/chat/completions` SSE 协议
- **心跳保活** — 周期性输出 `: ping` 注释，避免网关空闲超时断连
- **停滞感知** — 上游超过 `idleTimeoutSeconds`（默认 300 秒）没有任何数据即中止，静默的上游无法长期占用 worker（被约束的只有静默——见「快速上手」下的说明）
- **断连感知** — 每个 delta 与每个空闲 tick 都检查 `connection_aborted()`，一旦发现断开立即停止转发
- **可测** — 上游传输可注入，内置完整 PHPUnit 测试套件

## 边界

**范围内**

- 编排层（`Proxy::stream()`）：从模型客户端拉取文本增量、推送到 SSE 输出端、注入 `: ping` 心跳，并在客户端断连时中止 —— PSR-4 根为 `MiGears\AiProxy`。
- SSE 输出端（`Stream\Emitter`）与 cURL 流式上游读取（`Stream\Upstream`）：`data:` / `event:` / `id:` 分帧并默认发送 `text/event-stream` 响应头，由停滞看门狗兜底。
- OpenAI 兼容的模型客户端（`Model\OpenAiClient`，实现 `Model\ClientInterface`）：解析上游 `data:` SSE 行并转发文本增量。零框架依赖 —— 仅需 PHP `^8.1`、`ext-curl`、`ext-json`。

**范围外（刻意不做）**

- 认证、鉴权与入参校验 —— 调用方需自行完成鉴权与校验；该职责属于 `migears/security`。
- 路由与框架集成 —— 不提供路由、controller 或 request 对象；由调用方把代理接入自己的 controller。
- 上游失败的渲染、重试与日志 —— 传输错误、停滞超时与非 2xx 均以 `AiProxyException` 抛给调用方。

## 安装

```bash
composer require migears/ai-proxy
```

## 快速上手

```php
use MiGears\AiProxy\Proxy;
use MiGears\AiProxy\Stream\Emitter;
use MiGears\AiProxy\Model\OpenAiClient;

$emitter = new Emitter(); // 默认输出到 php://output
$client = new OpenAiClient(
    endpoint: 'https://api.deepseek.com/chat/completions',
    token:    getenv('DEEPSEEK_API_KEY'),
    model:    'deepseek-chat',
);

$proxy = new Proxy($emitter, heartbeatSeconds: 20);
$proxy->stream($client, [
    ['role' => 'user', 'content' => '讲个笑话'],
]);
```

在 controller 里完成入参校验与鉴权后再调用本模块。

`OpenAiClient` 的 `idleTimeoutSeconds:` 决定上游静默多久后中止调用，传 `0` 表示关闭该看门狗。
被约束的只有静默——总时长刻意不限，一次长生成不会因总上限被中途掐断。这个选择的代价是：
上游只要在每个空闲窗口内发一个字节，调用就一直活着，并占住它的 PHP worker。
需要总时长上限时，改用 `OpenAiClient` 的 `curlOpts: [CURLOPT_TIMEOUT => 600]`。

## 架构

![migears/ai-proxy 数据流](docs/architecture-zh.svg)

目录结构：

```
src/
├── AiProxyException.php
├── Proxy.php                      # 编排
├── Stream/
│   ├── Emitter.php                # SSE 输出端
│   └── Upstream.php               # cURL 流式读取
└── Model/
    ├── ClientInterface.php
    └── OpenAiClient.php
```

## 测试

```bash
composer install
vendor/bin/phpunit
```

## 许可证

MIT