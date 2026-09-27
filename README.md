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
- **Stall-aware** — aborts the upstream after `idleTimeoutSeconds` (default 300s) with no body byte, so a half-open upstream cannot pin a worker
- **Client-abort aware** — `connection_aborted()` is checked on every delta *and* every idle tick, so forwarding stops as soon as the disconnect is seen
- **Testable** — the upstream transport is injectable; full PHPUnit suite

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
tolerated before the call is aborted; `0` turns the watchdog off.

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
- **停滞感知** — 上游超过 `idleTimeoutSeconds`（默认 300 秒）没有任何数据即中止，半开连接无法长期占用 worker
- **断连感知** — 每个 delta 与每个空闲 tick 都检查 `connection_aborted()`，一旦发现断开立即停止转发
- **可测** — 上游传输可注入，内置完整 PHPUnit 测试套件

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