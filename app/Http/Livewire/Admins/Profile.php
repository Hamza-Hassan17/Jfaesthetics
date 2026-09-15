<?php

namespace App\Http\Livewire\Admins;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Profile extends Component
{
    public $editing = false;
    public $name;
    public $email;

    public $changing_password = false;
    public $current_password;
    public $new_password;
    public $new_password_confirmation;

    public function mount()
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function edit()
    {
        $this->editing = true;
    }

    public function cancelEdit()
    {
        $this->editing = false;
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
        $this->resetErrorBag();
    }

    public function save()
    {
        $user = auth()->user();

        $this->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'module' => 'users',
            'record_id' => $user->id,
            'description' => "Updated own profile.",
            'ip_address' => request()->ip(),
        ]);

        $this->editing = false;
        session()->flash('message', 'Profile updated successfully.');
    }

    public function showChangePassword()
    {
        $this->changing_password = true;
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
    }

    public function cancelChangePassword()
    {
        $this->changing_password = false;
        $this->resetErrorBag();
    }

    public function changePassword()
    {
        $user = auth()->user();

        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        $user->update(['password' => bcrypt($this->new_password)]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'module' => 'users',
            'record_id' => $user->id,
            'description' => 'Changed own password.',
            'ip_address' => request()->ip(),
        ]);

        $this->changing_password = false;
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
        session()->flash('message', 'Password changed successfully.');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.admins.profile', [
            'user' => $user,
            'recentActivity' => ActivityLog::where('user_id', $user->id)->latest()->take(10)->get(),
        ])->layout('admins.layouts.app');
    }
}
