@if (session('status')) <div class="card" style="border-color:#2E7D4F;background:#f1f8f2;max-width:none">{{ session('status') }}</div> @endif
@if (session('error')) <div class="card" style="border-color:#C0392B;background:#fdecea;max-width:none">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="card" style="border-color:#C0392B;background:#fdecea;max-width:none">
        <ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
    </div>
@endif
