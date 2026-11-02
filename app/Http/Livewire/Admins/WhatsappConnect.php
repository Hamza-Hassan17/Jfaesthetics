<?php

namespace App\Http\Livewire\Admins;

use App\Support\PaymentReminder;
use App\Support\WhatsAppGateway;
use App\Traits\LogsActivity;
use Livewire\Component;

class WhatsappConnect extends Component
{
    use LogsActivity;

    public $test_number = '';

    public function send_test()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $number = PaymentReminder::whatsappNumber($this->test_number);
        if (!$number) {
            $this->addError('test_number', 'Enter a valid mobile number, e.g. 03001234567.');
            return;
        }

        $result = WhatsAppGateway::send($number, 'Test message from ' . config('app.name') . '. WhatsApp is connected.');
        isset($result['error'])
            ? session()->flash('error', $result['error'])
            : session()->flash('message', 'Test message sent.');
    }

    public function disconnect()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        WhatsAppGateway::logout();
        $this->logActivity('updated', 'settings', null, 'Disconnected WhatsApp.');
        session()->flash('message', 'WhatsApp disconnected. Scan the new code to connect again.');
    }

    public function render()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        return view('livewire.admins.whatsapp-connect', [
            'configured' => WhatsAppGateway::configured(),
            'whatsapp' => WhatsAppGateway::status(),
        ])->layout('admins.layouts.app');
    }
}
