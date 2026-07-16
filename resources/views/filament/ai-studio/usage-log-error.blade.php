<div style="font-size:.88rem;line-height:1.9">
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;color:#555;margin-bottom:1rem">
        <span><strong>Provider:</strong> {{ $log->provider_slug }}</span>
        <span><strong>Model:</strong> {{ $log->model ?? '—' }}</span>
        <span><strong>Action:</strong> {{ $log->action_key ?? '—' }}</span>
        <span><strong>Response time:</strong> {{ $log->response_time_ms !== null ? $log->response_time_ms.' ms' : '—' }}</span>
    </div>

    <pre style="white-space:pre-wrap;word-break:break-word;border:1px solid #f5c2c7;background:#fdf2f2;border-radius:8px;padding:.7rem 1rem;color:#b91c1c">{{ $log->error_message }}</pre>
</div>
