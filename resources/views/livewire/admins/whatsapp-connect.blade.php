<div class="container-fluid p-4" @if ($configured && $whatsapp['state'] !== 'connected') wire:poll.5s @endif>
    <h3 class="mb-3"><i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp</h3>

    @if (session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="bg-white rounded shadow-sm p-4 text-center" style="max-width:520px">
        @if (!$configured)
            <p class="mb-0">WhatsApp isn't set up on this site yet. Add <code>WHATSAPP_SERVICE_URL</code> and <code>WHATSAPP_TOKEN</code> to <code>.env</code>.</p>
        @elseif ($whatsapp['state'] === 'connected')
            <h5 class="text-success">Connected</h5>
            <p>Sending from <strong>{{ $whatsapp['number'] }}</strong></p>

            <form wire:submit.prevent="send_test" class="form-inline justify-content-center mb-3">
                <input type="text" class="form-control mr-2" wire:model.defer="test_number" placeholder="03001234567">
                <button class="btn btn-primary">Send test</button>
            </form>
            @error('test_number') <p class="text-danger small">{{ $message }}</p> @enderror

            @if (auth()->user()->isSuperAdmin())
            <button class="btn btn-outline-danger" wire:click="disconnect" onclick="return confirm('Disconnect this WhatsApp number?')">Disconnect</button>
            @endif
        @elseif (!auth()->user()->isSuperAdmin())
            <h5>WhatsApp isn't connected</h5>
            <p class="text-muted mb-0">Ask a Super Admin to connect it.</p>
        @elseif ($whatsapp['qr'])
            <h5>Connect WhatsApp</h5>
            <img src="{{ $whatsapp['qr'] }}" alt="WhatsApp QR code" width="320" height="320" style="max-width:100%">
            <p class="text-muted mt-3 mb-0">On the phone: WhatsApp &rarr; Settings &rarr; Linked Devices &rarr; Link a Device, then scan this code.</p>
        @else
            <h5>{{ $whatsapp['state'] === 'offline' ? 'The WhatsApp service is offline' : 'Connecting...' }}</h5>
            <p class="text-muted mb-0">{{ $whatsapp['error'] ?? 'The code will appear here shortly.' }}</p>
        @endif
    </div>
</div>
